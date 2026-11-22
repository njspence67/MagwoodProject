"""Illustrative fixtures only. This does not import the client's workbook."""

from .models import Animal, Group, Paddock, Tag
from .repository import InMemoryRepository


def demo_repository() -> InMemoryRepository:
    return InMemoryRepository(
        animals=[
            Animal(
                id="animal-020",
                kind="cow",
                description="Illustrative cow with a former tag",
                status="unknown",
                group_id="group-a",
                tags=[Tag(label="#8 White", current=False), Tag(label="#20 White")],
                notes="Prototype example only. Identity and status need confirmation.",
            ),
            Animal(
                id="animal-calf-01",
                kind="calf",
                description="Illustrative untagged brown calf with a white face",
                status="unknown",
                notes="No mother confirmed. Sample record only.",
            ),
            Animal(
                id="animal-1347",
                kind="bull",
                description="Historical bull example from the frontend prototype",
                status="deceased",
                tags=[Tag(label="#1347")],
                notes="Sample historical record; not a verified roster entry.",
            ),
        ],
        groups=[Group(id="group-a", name="Group A (sample)")],
        paddocks=[Paddock(id=f"paddock-{n}", name=f"Paddock {n}") for n in range(1, 9)],
    )
