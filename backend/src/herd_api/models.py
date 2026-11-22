"""API contracts, independent of any database table or ORM."""

import re
from datetime import date, datetime
from typing import Annotated, Literal, Self

from pydantic import BaseModel, BeforeValidator, ConfigDict, Field, model_validator


def date_only(value: object) -> object:
    # Reject timestamps and epoch numbers instead of silently changing their meaning.
    if isinstance(value, datetime):
        raise ValueError("Use a calendar date in YYYY-MM-DD format, without a time")
    if isinstance(value, date):
        return value
    if not isinstance(value, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", value):
        raise ValueError("Use a calendar date in YYYY-MM-DD format")
    return value


CalendarDate = Annotated[date, BeforeValidator(date_only)]
Identifier = Annotated[str, Field(min_length=1, max_length=100)]
Label = Annotated[str, Field(min_length=1, max_length=120)]
Notes = Annotated[str, Field(max_length=4000)]


class Contract(BaseModel):
    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)


class Source(Contract):
    """Optional provenance for a manually transcribed observation."""

    document: Label
    sheet: Label | None = None
    row: Annotated[int, Field(gt=0, strict=True)] | None = None
    original_text: Notes


class Tag(Contract):
    label: Label
    valid_from: CalendarDate | None = None
    valid_to: CalendarDate | None = None
    current: bool = True


class AnimalCreate(Contract):
    kind: Literal["cow", "bull", "heifer", "steer", "calf"]
    description: Annotated[str, Field(min_length=1, max_length=1000)]
    current_tag: Label | None = None
    status: Literal["active", "sold", "deceased", "unknown"] = "unknown"
    group_id: Identifier | None = None
    notes: Notes = ""
    source: Source | None = None


class Animal(Contract):
    id: Identifier
    kind: Literal["cow", "bull", "heifer", "steer", "calf"]
    description: str
    status: Literal["active", "sold", "deceased", "unknown"]
    group_id: Identifier | None = None
    tags: list[Tag] = Field(default_factory=list)
    notes: str = ""
    source: Source | None = None
    review_status: Literal["unverified"] = "unverified"


class Group(Contract):
    id: Identifier
    name: Label


class Paddock(Contract):
    id: Identifier
    name: Label


class EventBase(Contract):
    occurred_on: CalendarDate
    approximate: bool = Field(default=False, strict=True)
    notes: Notes = ""
    source: Source | None = None


class AnimalEvent(EventBase):
    animal_id: Identifier


class NoteEvent(AnimalEvent):
    type: Literal["note"]
    notes: Annotated[str, Field(min_length=1, max_length=4000)]


class WeightEvent(AnimalEvent):
    type: Literal["weight"]
    value: Annotated[float, Field(gt=0, allow_inf_nan=False, strict=True)]
    unit: Literal["lb", "kg"]


class BirthEvent(AnimalEvent):
    """An observation on the mother's record, not automatic calf registration."""

    type: Literal["birth"]
    calf_id: Identifier | None = None
    calf_description: Annotated[str, Field(min_length=1, max_length=1000)]


class TreatmentEvent(AnimalEvent):
    """Record an actual treatment; no dosage or schedule recommendations."""

    type: Literal["treatment"]
    product: Label
    dose_as_entered: Annotated[str, Field(min_length=1, max_length=200)]
    administered_by: Label | None = None


class TagChangeEvent(AnimalEvent):
    type: Literal["tag_change"]
    new_tag: Label

    @model_validator(mode="after")
    def exact_change_date(self) -> Self:
        if self.approximate:
            raise ValueError("Tag changes require an exact date; use a note for uncertain history")
        return self


class MoveEvent(EventBase):
    type: Literal["paddock_move"]
    group_id: Identifier
    from_paddock_id: Identifier | None = None
    to_paddock_id: Identifier

    @model_validator(mode="after")
    def different_paddocks(self) -> Self:
        if self.from_paddock_id == self.to_paddock_id:
            raise ValueError("The origin and destination paddocks must differ")
        return self


EventInput = Annotated[
    NoteEvent | WeightEvent | BirthEvent | TreatmentEvent | TagChangeEvent | MoveEvent,
    Field(discriminator="type"),
]


class Event(Contract):
    id: Identifier
    recorded_at: datetime
    record: EventInput


class AnimalPage(Contract):
    items: list[Animal]
    total: int
    limit: int
    offset: int


class EventPage(Contract):
    items: list[Event]
    total: int
    limit: int
    offset: int


class ErrorDetail(Contract):
    code: str
    message: str
    fields: list[dict[str, str]] = Field(default_factory=list)


class ErrorResponse(Contract):
    error: ErrorDetail
