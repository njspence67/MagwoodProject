"""Storage boundary to implement when the team's database is available."""

from collections.abc import Iterator
from contextlib import AbstractContextManager, contextmanager
from threading import RLock
from typing import Protocol

from .models import Animal, Event, Group, Paddock


class HerdRepository(Protocol):
    def transaction(self) -> AbstractContextManager[None]:
        """Commit all writes together; roll back on failure; serialize conflicting writes."""
        ...

    def list_animals(self) -> list[Animal]: ...
    def get_animal(self, animal_id: str) -> Animal | None: ...
    def save_animal(self, animal: Animal) -> None: ...
    def list_events(self) -> list[Event]: ...
    def get_event(self, event_id: str) -> Event | None: ...
    def add_event(self, event: Event) -> None: ...
    def list_groups(self) -> list[Group]: ...
    def list_paddocks(self) -> list[Paddock]: ...


class InMemoryRepository:
    """Single-process development storage; deliberately not durable.

    Copies prevent callers from accidentally mutating stored state. The transaction
    lock and rollback make a tag update plus its history event one atomic operation.
    """

    def __init__(
        self,
        animals: list[Animal] | None = None,
        groups: list[Group] | None = None,
        paddocks: list[Paddock] | None = None,
    ) -> None:
        self._lock = RLock()
        self._animals = {animal.id: animal.model_copy(deep=True) for animal in animals or []}
        self._events: dict[str, Event] = {}
        self._groups = [group.model_copy(deep=True) for group in groups or []]
        self._paddocks = [paddock.model_copy(deep=True) for paddock in paddocks or []]

    @contextmanager
    def transaction(self) -> Iterator[None]:
        with self._lock:
            animals_before = self._animals.copy()
            events_before = self._events.copy()
            try:
                yield
            except Exception:
                self._animals = animals_before
                self._events = events_before
                raise

    def list_animals(self) -> list[Animal]:
        with self._lock:
            return [animal.model_copy(deep=True) for animal in self._animals.values()]

    def get_animal(self, animal_id: str) -> Animal | None:
        with self._lock:
            animal = self._animals.get(animal_id)
            return animal.model_copy(deep=True) if animal else None

    def save_animal(self, animal: Animal) -> None:
        with self._lock:
            self._animals[animal.id] = animal.model_copy(deep=True)

    def list_events(self) -> list[Event]:
        with self._lock:
            return [event.model_copy(deep=True) for event in self._events.values()]

    def get_event(self, event_id: str) -> Event | None:
        with self._lock:
            event = self._events.get(event_id)
            return event.model_copy(deep=True) if event else None

    def add_event(self, event: Event) -> None:
        with self._lock:
            if event.id in self._events:
                raise ValueError("Event ID already exists")
            self._events[event.id] = event.model_copy(deep=True)

    def list_groups(self) -> list[Group]:
        return [group.model_copy(deep=True) for group in self._groups]

    def list_paddocks(self) -> list[Paddock]:
        return [paddock.model_copy(deep=True) for paddock in self._paddocks]
