"""Herd rules; HTTP and database details belong outside this module."""

from datetime import UTC, datetime
from uuid import uuid4

from .models import (
    Animal,
    AnimalCreate,
    AnimalPage,
    BirthEvent,
    Event,
    EventInput,
    EventPage,
    MoveEvent,
    Tag,
    TagChangeEvent,
)
from .repository import HerdRepository


class DomainError(Exception):
    def __init__(self, status: int, code: str, message: str) -> None:
        super().__init__(message)
        self.status = status
        self.code = code
        self.message = message


class HerdService:
    def __init__(self, repository: HerdRepository) -> None:
        self.repository = repository

    def get_animal(self, animal_id: str) -> Animal:
        animal = self.repository.get_animal(animal_id)
        if animal is None:
            raise DomainError(404, "animal_not_found", "Animal not found")
        return animal

    def list_animals(self, query: str, limit: int, offset: int) -> AnimalPage:
        query = query.strip().casefold()
        animals = [
            animal
            for animal in self.repository.list_animals()
            if query
            in " ".join(
                [
                    animal.id,
                    animal.description,
                    animal.kind,
                    animal.status,
                    animal.notes,
                    *(tag.label for tag in animal.tags),
                ]
            ).casefold()
        ]
        animals.sort(key=lambda animal: animal.id)
        return AnimalPage(
            items=animals[offset : offset + limit], total=len(animals), limit=limit, offset=offset
        )

    def require_group(self, group_id: str) -> None:
        if not any(group.id == group_id for group in self.repository.list_groups()):
            raise DomainError(404, "group_not_found", "Group not found")

    def create_animal(self, data: AnimalCreate) -> Animal:
        with self.repository.transaction():
            if data.group_id:
                self.require_group(data.group_id)
            animal = Animal(
                id=str(uuid4()),
                **data.model_dump(exclude={"current_tag"}),
                tags=[Tag(label=data.current_tag)] if data.current_tag else [],
            )
            self.repository.save_animal(animal)
        return animal

    def get_event(self, event_id: str) -> Event:
        event = self.repository.get_event(event_id)
        if event is None:
            raise DomainError(404, "event_not_found", "Event not found")
        return event

    def list_events(
        self, animal_id: str | None, group_id: str | None, limit: int, offset: int
    ) -> EventPage:
        if animal_id and group_id:
            raise DomainError(422, "invalid_filter", "Filter by animal_id or group_id, not both")
        if animal_id:
            self.get_animal(animal_id)
        if group_id:
            self.require_group(group_id)
        events = [
            event
            for event in self.repository.list_events()
            if (
                animal_id is None
                or getattr(event.record, "animal_id", None) == animal_id
                or getattr(event.record, "calf_id", None) == animal_id
            )
            and (group_id is None or getattr(event.record, "group_id", None) == group_id)
        ]
        events.sort(
            key=lambda event: (event.record.occurred_on, event.recorded_at, event.id), reverse=True
        )
        return EventPage(
            items=events[offset : offset + limit], total=len(events), limit=limit, offset=offset
        )

    def create_event(self, data: EventInput) -> Event:
        with self.repository.transaction():
            if isinstance(data, MoveEvent):
                self.require_group(data.group_id)
                paddock_ids = {paddock.id for paddock in self.repository.list_paddocks()}
                for paddock_id in (data.from_paddock_id, data.to_paddock_id):
                    if paddock_id is not None and paddock_id not in paddock_ids:
                        raise DomainError(404, "paddock_not_found", "Paddock not found")
            else:
                animal = self.get_animal(data.animal_id)
                if isinstance(data, BirthEvent) and data.calf_id is not None:
                    self.get_animal(data.calf_id)
                    if data.calf_id == animal.id:
                        raise DomainError(422, "invalid_parent", "Mother and calf must differ")
                if isinstance(data, TagChangeEvent):
                    self._change_tag(animal, data)
            event = Event(id=str(uuid4()), recorded_at=datetime.now(UTC), record=data)
            self.repository.add_event(event)
        return event

    def _change_tag(self, animal: Animal, data: TagChangeEvent) -> None:
        current = next((tag for tag in animal.tags if tag.current), None)
        if current and current.label.casefold() == data.new_tag.casefold():
            raise DomainError(409, "unchanged_tag", "This is already the animal's current tag")
        # This operation changes the current tag. Historical corrections need a separate flow.
        dated_history = [
            value
            for tag in animal.tags
            for value in (tag.valid_from, tag.valid_to)
            if value is not None
        ]
        if dated_history and data.occurred_on < max(dated_history):
            raise DomainError(409, "backdated_tag", "Tag change precedes existing tag history")
        for tag in animal.tags:
            if tag.current:
                tag.current = False
                tag.valid_to = data.occurred_on
        animal.tags.append(Tag(label=data.new_tag, valid_from=data.occurred_on))
        self.repository.save_animal(animal)
