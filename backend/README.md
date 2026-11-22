# Magwood backend

This FastAPI service provides animal records and field-event history without a database.
It is the first backend implementation, with a replaceable storage interface for the
database teammate. All seed records are illustrative and unverified.

## Local setup

Use Python 3.12 or newer in a dedicated virtual environment. Run these commands from
the repository root in PowerShell. They do not require activating the environment.

```powershell
python -m venv .venv-backend
.\.venv-backend\Scripts\python.exe -m pip install -c backend/constraints.txt -e './backend[dev]'
.\.venv-backend\Scripts\python.exe -m uvicorn herd_api.main:app --reload --host 127.0.0.1 --port 8000
```

For macOS/Linux, use `python3 -m venv .venv-backend` and replace the interpreter path
in subsequent commands with `.venv-backend/bin/python`.

The initial build was tested with Python 3.12.14. `constraints.txt` records the
tested dependency versions; update it intentionally when upgrading dependencies.

Open http://127.0.0.1:8000/docs for interactive API documentation. The machine-readable
contract is http://127.0.0.1:8000/openapi.json. The API creates fresh sample storage
when it starts; restarting or development auto-reload discards all writes.

This build has **no authentication and no durable storage**. Bind it to loopback for
development. Do not expose it publicly or use it for real ranch records. Run one
worker: each process has its own independent in-memory store. CORS is a browser
integration setting, not access control.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/health` | Process health |
| GET | `/api/v1/animals?q=...` | Search current/former tags, descriptions, status and notes |
| POST | `/api/v1/animals` | Register an unverified animal, with or without a tag |
| GET | `/api/v1/animals/{id}` | Animal details and tag history |
| GET | `/api/v1/events` | All events or history filtered by `animal_id` or `group_id` |
| POST | `/api/v1/events` | Record a note, weight, birth, treatment, tag change or group move |
| GET | `/api/v1/events/{id}` | Retrieve an event |
| GET | `/api/v1/groups` | Available sample groups |
| GET | `/api/v1/paddocks` | Available placeholder paddocks |

Animal and event lists return `{items, total, limit, offset}`. `limit` defaults to 50
and must be 1–100; `offset` is nonnegative. Animals sort by internal ID. Events sort
by `occurred_on`, then UTC `recorded_at`, then ID, all descending. Animal history
also includes birth observations explicitly linking that animal as the calf.
Group history contains group events, not an inferred history of individual members.
Unknown animal/group filters return 404; conflicting filters return 422.

Successful creation returns 201 and a `Location` header. Expected errors have the
shape `{error: {code, message, fields}}`. Validation returns 422, missing references
404, and conflicting tag changes 409. The API rejects unknown input fields.

## Try a complete workflow

The sample cow `animal-020` has a current `#20 White` tag and former `#8 White` tag.
In PowerShell, search for it and record a weight:

```powershell
Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/animals?q=%238%20White'

$weightRecord = @{
    type = 'weight'
    animal_id = 'animal-020'
    occurred_on = '2026-11-20'
    approximate = $false
    value = 520.5
    unit = 'lb'
    notes = 'Illustrative weigh-in'
} | ConvertTo-Json

Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/events' -Method Post -ContentType 'application/json' -Body $weightRecord
Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/events?animal_id=animal-020'
```

An event response contains `id`, `recorded_at`, and `record`. The `record` holds the
structured input fields. Calendar dates stay `YYYY-MM-DD`, separate from server UTC
timestamps. Numeric timestamps and datetime strings are not accepted as calendar dates.

Other event examples for `POST /api/v1/events`:

```json
{"type":"note","animal_id":"animal-020","occurred_on":"2026-11-20","approximate":true,"notes":"Sample observation"}
```

```json
{"type":"birth","animal_id":"animal-020","occurred_on":"2026-11-20","calf_description":"Untagged brown calf","approximate":true}
```

```json
{"type":"treatment","animal_id":"animal-020","occurred_on":"2026-11-20","product":"Sample product","dose_as_entered":"As transcribed from source","administered_by":"Demo operator"}
```

```json
{"type":"tag_change","animal_id":"animal-020","occurred_on":"2026-11-20","new_tag":"#200 Yellow"}
```

```json
{"type":"paddock_move","group_id":"group-a","occurred_on":"2026-11-20","from_paddock_id":"paddock-1","to_paddock_id":"paddock-2"}
```

## Record semantics

- Internal IDs remain stable. Tags are descriptive aliases, not unique animal IDs.
  A search may legitimately return several animals with the same tag. The caller
  must select an internal ID before writing an event.
- New animals default to unknown status and remain unverified. No bulk import or
  client-data confirmation is performed by this service.
- Births are observations on the mother's record. An optional `calf_id` must refer
  to another existing animal. Recording a birth does not automatically register a
  calf, establish verified parentage, or calculate a next-calving date.
- Weights require a positive numeric value and an explicit `lb` or `kg` unit.
  No implicit unit conversions occur.
- Treatments store entered product/dose information. The service does not supply
  care advice, withdrawal periods, or future treatment schedules.
- Tag changes close the current assignment and append a new assignment, together
  with their event, in one transaction. The date must be exact and cannot precede
  already dated tag history. Assignment boundaries are start-inclusive and
  end-exclusive; same-day changes retain their event order. Unknown historical
  assignment dates remain null. Use notes for uncertain history; historical
  corrections require a later review workflow.
- Paddock moves record observations for a group, with an optional unknown origin.
  They do not infer current occupancy, enforce a rotation schedule, or change
  individual animal membership. These require confirmed client rules.
- Optional `source` preserves a document name, sheet, row, and original text.
  The service does not read the referenced file or treat its text as instructions.
- Dates may be approximate. This first contract uses a representative date plus
  an approximation flag and notes. Unknown dates and date ranges need a later
  import contract; do not invent exact dates to import source material.

## Frontend handoff

The original prototype continues to run independently with its browser fixtures.
It is not automatically connected to this API. The frontend teammate can replace
its arrays and form submission handler with `fetch` calls. The API does not return
the prototype's display-only `text`, `meta`, or `time` strings; format these in the UI.

```javascript
const response = await fetch('http://127.0.0.1:8000/api/v1/animals?q=%238%20White');
if (!response.ok) throw new Error('Unable to load the herd');
const { items } = await response.json();
```

Browser origins `http://localhost:4173` and `http://127.0.0.1:4173` are allowed by
default. To change them, set `HERD_CORS_ORIGINS` to a comma-separated list before
starting the service. Serve the prototype over HTTP; opening it with `file://`
does not match these origins. Field names use snake_case; event types use lowercase
names such as `weight` and `paddock_move`. See `/docs` for required fields by type.

## Database teammate handoff

`src/herd_api/repository.py` defines `HerdRepository`. Implement that protocol with
the selected database and inject the implementation through `create_app(repository)`.
The service does not import a database driver or ORM. Database engine, tables,
schema migrations, indexes, credentials, and deployment remain unselected.

The implementation must:

1. Preserve stable IDs, structured values, original source text, and tag history.
2. Return independent model objects and `None` for missing IDs.
3. Append events without overwriting existing event IDs.
4. Commit animal changes and their events together, roll back on failure, and
   serialize concurrent changes to the same animal to prevent lost tag history.
5. Maintain the same HTTP behavior when the existing API tests run against it.

The current protocol returns collections for filtering in the service. This is
appropriate for the demo herd. Move filtering/pagination into repository queries
if scale requires it while preserving the public API contract.

Agree explicitly who owns the adapter/queries as well as tables/migrations. The
database teammate may own both, or the backend developer may implement the adapter
against their schema. Authentication/user persistence also needs an owner.

## Tests and checks

From the repository root:

```powershell
.\.venv-backend\Scripts\python.exe -m pytest backend/tests
.\.venv-backend\Scripts\python.exe -m ruff check backend
.\.venv-backend\Scripts\python.exe -m ruff format --check backend
npm test
```

Tests exercise HTTP workflows, date/unit validation, reference checks, event
history, CORS, OpenAPI, isolated repositories, and atomic tag changes under failure
and concurrent writes. They require no database or running external service.
The installed Starlette version currently emits a deprecation notice for its
HTTPX test transport; the tests pass. Switching to its HTTPX2 transport can be
handled with a later dependency update.

## Deployment remains a separate milestone

FastAPI is the chosen development stack. The likely IONOS Cloud Server plan has
not been confirmed. Before a real deployment, add durable storage and authentication/
authorization, and assign ownership for server and dependency updates, HTTPS,
process startup/restarts, backup/restore, and application support. A Cloud Server
does not by itself remove those responsibilities.

Future work includes record editing/corrections, reviewed workbook imports, real
group/paddock administration, confirmed projection rules, reminders, offline sync,
and the later operations features. This backend does not claim those are complete.
