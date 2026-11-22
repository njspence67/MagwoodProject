# Magwood Herd Management

This repository contains a capstone herd-management frontend prototype and a Python/FastAPI backend under development. The backend runs locally without a database. Hosting and database integration remain open team decisions; the likely hosting destination is an IONOS Cloud Server.

## Start here

- Read `docs/PROJECT_START.md` for the recommended scope, team split, and provisional answers to unresolved questions.
- Read `backend/README.md` for FastAPI setup, endpoints, examples, tests, and the database teammate handoff.
- Open `prototype/index.html` for the phone-first clickable demo. All displayed records are illustrative.
- Review the [GitHub repository](https://github.com/njspence67/MagwoodProject) and [CAPSTONE TRELLO board](https://trello.com/b/s1IEApC3/capstone-trello) for the shared team plan.

The prototype uses plain HTML, CSS, and JavaScript. It currently runs independently of the backend. The backend exposes structured animal and event APIs for the frontend teammate to connect.

## Run the backend

From the repository root in PowerShell, using Python 3.12 or newer:

    python -m venv .venv-backend
    .\.venv-backend\Scripts\python.exe -m pip install -c backend/constraints.txt -e './backend[dev]'
    .\.venv-backend\Scripts\python.exe -m uvicorn herd_api.main:app --reload --host 127.0.0.1 --port 8000

Open http://127.0.0.1:8000/docs to try the API. Use a single worker; the development store is in memory and resets on restart. Do not expose this unauthenticated build publicly.

## Run the prototype

    npm start

Then open http://localhost:4173.

## Run the tests

    npm test

The tests cover tag search, approximate event creation, date validation, and local date formatting using Node’s built-in test runner.

Run backend tests separately:

    .\.venv-backend\Scripts\python.exe -m pytest backend/tests

## Current limitations

The backend supports animal creation/search, structured field events, and tag history. There is no durable persistence, authentication, offline synchronization, medical protocol, or client-verified roster. The frontend is not yet wired to the API. Do not use the sample records for ranch decisions.
