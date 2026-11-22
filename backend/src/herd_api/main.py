"""Application factory for the local, unauthenticated development API."""

import os
from typing import Annotated

from fastapi import Depends, FastAPI, Query, Request, Response
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException

from .models import (
    Animal,
    AnimalCreate,
    AnimalPage,
    ErrorResponse,
    Event,
    EventInput,
    EventPage,
    Group,
    Paddock,
)
from .repository import HerdRepository
from .seed import demo_repository
from .service import DomainError, HerdService


def get_service(request: Request) -> HerdService:
    return HerdService(request.app.state.repository)


Service = Annotated[HerdService, Depends(get_service)]
Limit = Annotated[int, Query(ge=1, le=100)]
Offset = Annotated[int, Query(ge=0)]


def error_response(status: int, code: str, message: str, fields=None) -> JSONResponse:
    return JSONResponse(
        status_code=status,
        content={"error": {"code": code, "message": message, "fields": fields or []}},
    )


def create_app(repository: HerdRepository | None = None) -> FastAPI:
    app = FastAPI(
        title="Magwood Herd Records API",
        version="0.1.0",
        description=(
            "Local development API with unverified sample data. No authentication or durable "
            "storage. Writes are lost on restart. Do not expose this build publicly."
        ),
        responses={code: {"model": ErrorResponse} for code in (404, 409, 422)},
    )
    app.state.repository = repository if repository is not None else demo_repository()
    origins = [
        origin.strip()
        for origin in os.getenv(
            "HERD_CORS_ORIGINS", "http://localhost:4173,http://127.0.0.1:4173"
        ).split(",")
        if origin.strip()
    ]
    app.add_middleware(
        CORSMiddleware,
        allow_origins=origins,
        allow_credentials=False,
        allow_methods=["GET", "POST"],
        allow_headers=["Content-Type"],
    )

    @app.exception_handler(DomainError)
    async def domain_error(_request: Request, exc: DomainError) -> JSONResponse:
        return error_response(exc.status, exc.code, exc.message)

    @app.exception_handler(RequestValidationError)
    async def validation_error(_request: Request, exc: RequestValidationError) -> JSONResponse:
        # Do not echo submitted payloads (potentially treatment notes or personal data).
        fields = [
            {"path": ".".join(map(str, error["loc"])), "message": error["msg"]}
            for error in exc.errors()
        ]
        return error_response(422, "validation_error", "Request validation failed", fields)

    @app.exception_handler(HTTPException)
    async def http_error(_request: Request, exc: HTTPException) -> JSONResponse:
        response = error_response(exc.status_code, "http_error", str(exc.detail))
        if exc.headers:
            response.headers.update(exc.headers)
        return response

    @app.get("/health", tags=["System"])
    def health() -> dict[str, str]:
        return {"status": "ok", "mode": "development"}

    @app.get("/api/v1/animals", response_model=AnimalPage, tags=["Animals"])
    def animals(
        service: Service,
        q: Annotated[str, Query(max_length=200)] = "",
        limit: Limit = 50,
        offset: Offset = 0,
    ) -> AnimalPage:
        return service.list_animals(q, limit, offset)

    @app.post("/api/v1/animals", response_model=Animal, status_code=201, tags=["Animals"])
    def add_animal(data: AnimalCreate, response: Response, service: Service) -> Animal:
        animal = service.create_animal(data)
        response.headers["Location"] = f"/api/v1/animals/{animal.id}"
        return animal

    @app.get("/api/v1/animals/{animal_id}", response_model=Animal, tags=["Animals"])
    def animal(animal_id: str, service: Service) -> Animal:
        return service.get_animal(animal_id)

    @app.get("/api/v1/events", response_model=EventPage, tags=["Events"])
    def events(
        service: Service,
        animal_id: Annotated[str | None, Query(min_length=1, max_length=100)] = None,
        group_id: Annotated[str | None, Query(min_length=1, max_length=100)] = None,
        limit: Limit = 50,
        offset: Offset = 0,
    ) -> EventPage:
        return service.list_events(animal_id, group_id, limit, offset)

    @app.post("/api/v1/events", response_model=Event, status_code=201, tags=["Events"])
    def add_event(data: EventInput, response: Response, service: Service) -> Event:
        event = service.create_event(data)
        response.headers["Location"] = f"/api/v1/events/{event.id}"
        return event

    @app.get("/api/v1/events/{event_id}", response_model=Event, tags=["Events"])
    def event(event_id: str, service: Service) -> Event:
        return service.get_event(event_id)

    @app.get("/api/v1/groups", response_model=list[Group], tags=["Grazing"])
    def groups(service: Service) -> list[Group]:
        return service.repository.list_groups()

    @app.get("/api/v1/paddocks", response_model=list[Paddock], tags=["Grazing"])
    def paddocks(service: Service) -> list[Paddock]:
        return service.repository.list_paddocks()

    return app


app = create_app()
