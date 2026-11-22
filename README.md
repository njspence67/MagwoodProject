# Magwood Herd Management

This capstone repository contains a phone-first HTML/CSS/JavaScript frontend prototype and a PHP/Laravel herd-records API. The backend has been rewritten from FastAPI for the team's IONOS shared-hosting direction. Laravel 13 requires PHP 8.3 or newer; the exact hosting package still needs deployment verification.

## Start here

- Read `backend/README.md` for setup, API examples, tests, IONOS requirements, and the database teammate handoff.
- Read `docs/PROJECT_START.md` for scope, team context, and unresolved client rules.
- Open `prototype/index.html` for the clickable demo. All records are illustrative.
- Review the [GitHub repository](https://github.com/njspence67/MagwoodProject) and [CAPSTONE TRELLO board](https://trello.com/b/s1IEApC3/capstone-trello) for the shared team plan.

## Run locally on Windows

From the repository root:

```powershell
.\scripts\backend.ps1 setup
.\scripts\backend.ps1 serve
```

Open http://127.0.0.1:8000/docs for the interactive API reference. The helper uses the workspace-local PHP/Composer tools if present, otherwise installed tools from PATH. `.tools/` is ignored and is not included in a fresh clone; teammates need PHP 8.3+ and Composer 2 installed. Standard commands for other systems are in `backend/README.md`.

In another terminal, serve the frontend:

```powershell
.\scripts\backend.ps1 prototype
```

Then open http://127.0.0.1:4173. With PHP on PATH, `npm start` serves the same prototype.

## Run locally on Linux

Use Bash; PowerShell is not required. Install PHP 8.3+ with the required extensions and Composer 2 on PATH. The Windows PHP executable under `.tools/` cannot be used on Linux. The Bash helper can use `.tools/composer.phar` if present, or `composer` from PATH.

From the repository root:

```bash
bash scripts/backend.sh setup
bash scripts/backend.sh serve
```

Open http://127.0.0.1:8000/docs. In another terminal, run `bash scripts/backend.sh prototype` and open http://127.0.0.1:4173. Invoking the script with `bash` does not require changing its executable permissions.

## Verify changes

On Windows, from the repository root:

```powershell
.\scripts\backend.ps1 test
.\scripts\backend.ps1 lint
npm test
```

On Linux, from the repository root:

```bash
bash scripts/backend.sh test
bash scripts/backend.sh lint
npm test
```

Both helpers support `setup`, `serve` (the default), `test`, `lint`, and `prototype`. `npm test` requires Node.js/npm on either system.

## Current scope

The API supports animal creation/search, six structured event types, tag history, pagination, and provenance. A private JSON file keeps local demo writes across requests and restarts. The storage interface remains replaceable for the database teammate; no database schema has been selected or implemented.

Authentication, production database integration, offline synchronization, and client verification remain unfinished. The frontend still runs independently and has not been wired to the API. Keep the unauthenticated demo bound to loopback. The default demo repository refuses production mode; this is a development build, not a live ranch system.
