from concurrent.futures import ThreadPoolExecutor

import pytest
from fastapi.testclient import TestClient

from herd_api.main import create_app
from herd_api.models import TagChangeEvent
from herd_api.repository import InMemoryRepository
from herd_api.seed import demo_repository
from herd_api.service import HerdService

ANIMAL = "animal-020"
EVENTS = "/api/v1/events"


def note(**changes):
    return {
        "type": "note",
        "animal_id": ANIMAL,
        "occurred_on": "2026-11-20",
        "notes": "Observed in pasture",
        **changes,
    }


def tag_change(**changes):
    return {
        "type": "tag_change",
        "animal_id": ANIMAL,
        "occurred_on": "2026-11-20",
        "new_tag": "#200 Yellow",
        **changes,
    }


def test_roster_search_current_former_and_description(client):
    for query in ("#20 WHITE", " #8 white ", "former tag"):
        page = client.get("/api/v1/animals", params={"q": query}).json()
        assert page["total"] == 1
        assert page["items"][0]["id"] == ANIMAL
    assert client.get("/api/v1/animals?q=no-such-tag").json()["total"] == 0


def test_pagination_is_stable_and_bounded(client):
    first = client.get("/api/v1/animals?limit=1").json()
    second = client.get("/api/v1/animals?limit=1&offset=1").json()
    assert first["total"] == second["total"] == 3
    assert first["items"][0]["id"] != second["items"][0]["id"]
    assert client.get("/api/v1/animals?offset=100").json()["items"] == []
    for suffix in ("limit=0", "limit=101", "offset=-1"):
        assert client.get(f"/api/v1/animals?{suffix}").status_code == 422


def test_create_untagged_animal_and_retrieve(client):
    response = client.post(
        "/api/v1/animals",
        json={
            "kind": "calf",
            "description": "  Brown calf  ",
            "group_id": "group-a",
        },
    )
    assert response.status_code == 201
    animal = response.json()
    assert animal["tags"] == []
    assert animal["description"] == "Brown calf"
    assert animal["review_status"] == "unverified"
    assert client.get(response.headers["Location"]).json() == animal


def test_tags_are_not_primary_keys_or_globally_unique(client):
    data = {"kind": "cow", "description": "Sample", "current_tag": "#20 White"}
    first = client.post("/api/v1/animals", json=data).json()
    second = client.post("/api/v1/animals", json=data).json()
    assert first["id"] != second["id"] != ANIMAL
    assert client.get("/api/v1/animals?q=%2320%20White").json()["total"] == 3


def test_animal_creation_rejects_unknown_group_and_user_assigned_id(client):
    data = {"kind": "calf", "description": "Sample", "group_id": "missing"}
    assert client.post("/api/v1/animals", json=data).status_code == 404
    data.pop("group_id")
    assert client.post("/api/v1/animals", json={**data, "id": "chosen"}).status_code == 422
    assert client.get("/api/v1/animals").json()["total"] == 3


def test_note_preserves_approximation_and_source(client):
    source = {
        "document": "Sample.xlsx",
        "sheet": "Roster",
        "row": 8,
        "original_text": "Observed around November 20",
    }
    response = client.post(EVENTS, json=note(approximate=True, source=source))
    assert response.status_code == 201
    event = response.json()
    assert event["record"]["approximate"] is True
    assert event["record"]["source"] == source
    assert event["recorded_at"].endswith("Z")
    assert client.get(response.headers["Location"]).json() == event


@pytest.mark.parametrize("date", ["2026-02-30", "11/20/2026", "2026-11-20T00:00:00Z", 0, None])
def test_rejects_invalid_or_ambiguous_calendar_dates(client, date):
    response = client.post(EVENTS, json=note(occurred_on=date))
    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"
    assert client.get(EVENTS).json()["total"] == 0


@pytest.mark.parametrize(
    "change",
    [
        {"notes": "   "},
        {"approximate": "false"},
        {"unexpected": True},
        {"type": "invented_event"},
        {"animal_id": " "},
    ],
)
def test_rejects_invalid_event_fields(client, change):
    assert client.post(EVENTS, json=note(**change)).status_code == 422


def test_validation_error_does_not_echo_submitted_payload(client):
    response = client.post(EVENTS, json=note(occurred_on="invalid", notes="Private note"))
    assert "Private note" not in response.text
    assert response.json()["error"]["fields"]


def test_weight_requires_positive_number_and_explicit_unit(client):
    data = {"type": "weight", "animal_id": ANIMAL, "occurred_on": "2026-11-20"}
    for extra in (
        {"value": -1, "unit": "lb"},
        {"value": 0, "unit": "kg"},
        {"value": True, "unit": "lb"},
        {"value": "500", "unit": "lb"},
        {"value": 500},
        {"value": 500, "unit": "buckets"},
    ):
        assert client.post(EVENTS, json={**data, **extra}).status_code == 422
    response = client.post(EVENTS, json={**data, "value": 500.5, "unit": "lb"})
    assert response.status_code == 201
    assert response.json()["record"]["value"] == 500.5


def test_missing_animal_never_creates_orphan_event(client):
    assert client.post(EVENTS, json=note(animal_id="missing")).status_code == 404
    assert client.get(EVENTS).json()["total"] == 0


def test_history_sorts_by_occurrence_then_creation_and_filters(client):
    for day in ("2026-11-20", "2026-01-01", "2026-11-21"):
        assert client.post(EVENTS, json=note(occurred_on=day)).status_code == 201
    client.post(EVENTS, json=note(animal_id="animal-calf-01"))
    page = client.get(EVENTS, params={"animal_id": ANIMAL, "limit": 2}).json()
    assert page["total"] == 3
    assert [item["record"]["occurred_on"] for item in page["items"]] == [
        "2026-11-21",
        "2026-11-20",
    ]
    assert client.get(EVENTS, params={"animal_id": "missing"}).status_code == 404
    assert (
        client.get(EVENTS, params={"animal_id": ANIMAL, "group_id": "group-a"}).status_code == 422
    )


def test_tag_change_preserves_identity_and_searchable_history(client):
    response = client.post(EVENTS, json=tag_change())
    assert response.status_code == 201
    animal = client.get(f"/api/v1/animals/{ANIMAL}").json()
    assert animal["id"] == ANIMAL
    assert [tag["label"] for tag in animal["tags"] if tag["current"]] == ["#200 Yellow"]
    old_tag = next(tag for tag in animal["tags"] if tag["label"] == "#20 White")
    assert old_tag["valid_to"] == "2026-11-20"
    for label in ("#8 White", "#20 White", "#200 Yellow"):
        assert client.get("/api/v1/animals", params={"q": label}).json()["items"][0]["id"] == ANIMAL


def test_rejected_tag_changes_leave_history_unchanged(client):
    assert client.post(EVENTS, json=tag_change(approximate=True)).status_code == 422
    assert client.post(EVENTS, json=tag_change()).status_code == 201
    assert client.post(EVENTS, json=tag_change()).status_code == 409
    assert (
        client.post(
            EVENTS, json=tag_change(occurred_on="2026-11-19", new_tag="#201 Yellow")
        ).status_code
        == 409
    )
    assert client.get(EVENTS).json()["total"] == 1


def test_birth_links_existing_calf_and_is_visible_in_both_histories(client):
    data = {
        "type": "birth",
        "animal_id": ANIMAL,
        "occurred_on": "2026-11-20",
        "calf_id": "animal-calf-01",
        "calf_description": "Brown calf",
        "approximate": True,
    }
    assert client.post(EVENTS, json={**data, "calf_id": "missing"}).status_code == 404
    assert client.post(EVENTS, json={**data, "calf_id": ANIMAL}).status_code == 422
    assert client.post(EVENTS, json=data).status_code == 201
    for animal_id in (ANIMAL, "animal-calf-01"):
        assert client.get(EVENTS, params={"animal_id": animal_id}).json()["total"] == 1
    assert client.get("/api/v1/animals").json()["total"] == 3


def test_birth_observation_can_have_unknown_calf_id(client):
    response = client.post(
        EVENTS,
        json={
            "type": "birth",
            "animal_id": ANIMAL,
            "occurred_on": "2026-11-20",
            "calf_description": "Untagged calf seen nearby",
        },
    )
    assert response.status_code == 201
    assert response.json()["record"]["calf_id"] is None


def test_treatment_records_entered_details_without_generating_schedule(client):
    response = client.post(
        EVENTS,
        json={
            "type": "treatment",
            "animal_id": ANIMAL,
            "occurred_on": "2026-11-20",
            "product": "Illustrative product",
            "dose_as_entered": "As recorded on source sheet",
        },
    )
    assert response.status_code == 201
    record = response.json()["record"]
    assert record["dose_as_entered"] == "As recorded on source sheet"
    assert "next_due" not in record


def test_paddock_move_requires_known_group_and_paddocks(client):
    data = {
        "type": "paddock_move",
        "group_id": "group-a",
        "occurred_on": "2026-11-20",
        "from_paddock_id": "paddock-1",
        "to_paddock_id": "paddock-2",
    }
    for changes in (
        {"group_id": "missing"},
        {"to_paddock_id": "missing"},
        {"from_paddock_id": "missing"},
    ):
        assert client.post(EVENTS, json={**data, **changes}).status_code == 404
    assert client.post(EVENTS, json={**data, "to_paddock_id": "paddock-1"}).status_code == 422
    assert client.post(EVENTS, json=data).status_code == 201
    assert client.get(EVENTS, params={"group_id": "group-a"}).json()["total"] == 1
    assert client.get(EVENTS, params={"animal_id": ANIMAL}).json()["total"] == 0
    assert len(client.get("/api/v1/paddocks").json()) == 8
    assert len(client.get("/api/v1/groups").json()) == 1


def test_unknown_resources_and_routes_return_consistent_errors(client):
    for path in ("/missing", "/api/v1/animals/missing", f"{EVENTS}/missing"):
        response = client.get(path)
        assert response.status_code == 404
        assert set(response.json()["error"]) == {"code", "message", "fields"}
    assert client.delete(f"/api/v1/animals/{ANIMAL}").status_code == 405


def test_cors_allows_only_configured_frontend_origins(client):
    headers = {
        "Origin": "http://localhost:4173",
        "Access-Control-Request-Method": "POST",
        "Access-Control-Request-Headers": "content-type",
    }
    response = client.options(EVENTS, headers=headers)
    assert response.status_code == 200
    assert response.headers["access-control-allow-origin"] == "http://localhost:4173"
    response = client.options(EVENTS, headers={**headers, "Origin": "https://unrelated.example"})
    assert response.status_code == 400
    assert "access-control-allow-origin" not in response.headers


def test_openapi_exposes_event_variants_and_error_schema(client):
    schema = client.get("/openapi.json").json()
    event_body = schema["paths"][EVENTS]["post"]["requestBody"]["content"]["application/json"][
        "schema"
    ]
    assert len(event_body["oneOf"]) == 6
    assert event_body["discriminator"]["propertyName"] == "type"
    assert "ErrorResponse" in schema["components"]["schemas"]
    assert client.get("/docs").status_code == 200
    assert client.get("/health").json()["status"] == "ok"


def test_app_instances_and_injected_storage_are_independent(client):
    client.post(EVENTS, json=note())
    with TestClient(create_app()) as other:
        assert other.get(EVENTS).json()["total"] == 0
    with TestClient(create_app(InMemoryRepository())) as empty:
        assert empty.get("/api/v1/animals").json()["total"] == 0


def test_tag_transaction_rolls_back_if_event_write_fails():
    class FailingRepository(InMemoryRepository):
        def add_event(self, event):
            super().add_event(event)
            raise RuntimeError("Simulated storage failure")

    repo = FailingRepository(animals=demo_repository().list_animals())
    before = repo.get_animal(ANIMAL)
    with pytest.raises(RuntimeError, match="Simulated"):
        HerdService(repo).create_event(TagChangeEvent(**tag_change()))
    assert repo.get_animal(ANIMAL) == before
    assert repo.list_events() == []


def test_concurrent_tag_changes_preserve_every_event_and_one_current_tag():
    repo = demo_repository()
    service = HerdService(repo)
    with ThreadPoolExecutor(max_workers=4) as pool:
        events = list(
            pool.map(
                lambda n: service.create_event(TagChangeEvent(**tag_change(new_tag=f"Tag {n}"))),
                range(12),
            )
        )
    assert len({event.id for event in events}) == len(repo.list_events()) == 12
    tags = repo.get_animal(ANIMAL).tags
    assert len(tags) == 14
    assert sum(tag.current for tag in tags) == 1


def test_returned_models_cannot_mutate_repository():
    repo = demo_repository()
    repo.get_animal(ANIMAL).tags.clear()
    assert len(repo.get_animal(ANIMAL).tags) == 2
