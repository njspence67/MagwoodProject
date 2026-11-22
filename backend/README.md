# Magwood Laravel backend

PHP/Laravel replacement for the earlier Python/FastAPI API. Endpoint paths, JSON field names, six event types, reference checks, history semantics, and error envelopes are preserved. The frontend does not need a framework change.

The runtime is Laravel 13 on PHP 8.3+. `composer.lock` pins dependencies resolved against PHP 8.3 so installing on a newer development runtime does not silently require a newer production PHP version. The rewrite was tested locally on PHP 8.4.28.

## Setup and run

Install PHP 8.3+ and Composer 2. Enable Laravel's required extensions (including Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML); Composer also benefits from Zip. A future MySQL adapter requires PDO MySQL.

The repository provides separate helpers for Windows PowerShell and Linux Bash. Run these from the repository root.

Windows PowerShell:

```powershell
.\scripts\backend.ps1 setup
.\scripts\backend.ps1 serve
```

Linux Bash:

```bash
bash scripts/backend.sh setup
bash scripts/backend.sh serve
```

The Linux helper requires PHP on PATH and uses `.tools/composer.phar` when available, otherwise Composer on PATH. It does not use the Windows PHP executable. Both helpers install dependencies, create `.env` only if absent, and generate a key only when `APP_KEY` is blank. They also support `test`, `lint`, and `prototype`; omitting the action starts `serve`.

Do not overwrite an existing `.env` or regenerate an existing application key during later deployments. Never commit `.env`, `vendor/`, demo records, or logs.

- Interactive local API reference: http://127.0.0.1:8000/docs
- OpenAPI 3.1 contract: http://127.0.0.1:8000/openapi.json
- Process liveness: http://127.0.0.1:8000/health (does not assert production readiness)

The docs page serves locally without a CDN and can issue GET/POST requests. `resources/openapi.json` is the versioned contract preserved from the original API; update it alongside request validation and response changes. Framework-specific validation messages may differ; status codes and the error shape remain stable.

## Local demo storage

PHP starts application state afresh for ordinary web requests. The old in-memory default would lose a new record between POST and GET, so the default Laravel adapter stores demo records in `storage/app/private/herd-demo.json`.

- An absent file supplies the illustrative seed roster; the first successful write saves it.
- A separate lock file serializes read/write transactions across PHP processes.
- A transaction writes to a temporary file and replaces the data file only after success. A failed event write cannot leave an animal's tag history partially changed.
- Restarting the server **does not reset data**. With the server stopped, back up and remove only `storage/app/private/herd-demo.json` to reset the local demo. Leave the stable lock file in place.
- Invalid/corrupt storage raises an error; it is never silently overwritten with seed data.

This adapter is for a small local demo, not a production database or backup solution. `InMemoryHerdRepository` provides isolated test storage. Neither imports the client's workbook. The default binding permits only `APP_ENV=local` or `testing`; production herd endpoints return 503 until the team replaces it and implements authentication. Do not use `APP_ENV=local` to expose this unauthenticated API publicly.

## API

| Method | Path | Purpose |
|---|---|---|
| GET | `/health` | Process liveness |
| GET | `/api/v1/animals?q=...` | Search current/former tags, descriptions, status, and notes |
| POST | `/api/v1/animals` | Register an unverified animal, with or without a tag |
| GET | `/api/v1/animals/{id}` | Animal details and tag history |
| GET | `/api/v1/events` | All events or history filtered by `animal_id` or `group_id` |
| POST | `/api/v1/events` | Record a note, weight, birth, treatment, tag change, or group move |
| GET | `/api/v1/events/{id}` | Retrieve an event |
| GET | `/api/v1/groups` | Available sample groups |
| GET | `/api/v1/paddocks` | Eight placeholder paddocks |

Lists return `{items, total, limit, offset}`. `limit` defaults to 50 and must be 1–100; `offset` is nonnegative. Animals sort by internal ID. Events sort by `occurred_on`, UTC `recorded_at`, and ID, all descending. A linked birth appears in both mother and calf histories. Group history contains explicit group events, not inferred events of members.

Successful creation returns 201 and a `Location` header. Errors use `{error: {code, message, fields}}`: validation 422, unknown references 404, conflicting tag changes 409, and unsupported methods 405. Each validation field contains `path` and `message`; submitted values are not echoed. Unknown body/source fields are rejected. POST bodies must be JSON objects. JSON booleans, positive numbers, and positive integer source-row numbers are validated without string coercion. Dates must be valid `YYYY-MM-DD` calendar dates, not datetimes or epoch numbers.

## Try a workflow

In PowerShell:

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

Other POST `/api/v1/events` examples:

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

An event response contains `id`, `recorded_at`, and `record`. `record` includes normalized input and defaults. Optional source provenance contains `document`, `sheet`, `row`, and `original_text`.

## Herd rules preserved

- Internal IDs are immutable. Tags are reusable labels, not globally unique identities. Search can return several animals with the same tag; writes use an internal ID.
- New animals default to unknown status and remain unverified. Registration does not confirm a client roster.
- Births are observations on a mother's record. An optional `calf_id` must reference a different existing animal. No calf is automatically registered, no parentage is verified, and no calving projection is calculated.
- Weights need positive finite JSON numbers and an explicit `lb` or `kg` unit. There is no implicit conversion.
- Treatments retain entered product/dose information without generating care advice, withdrawal periods, or schedules.
- Tag changes and their events commit together. Dates must be exact and cannot precede dated tag history. Assignment boundaries are start-inclusive/end-exclusive; same-day changes retain their order in the tag array. Unknown historical dates stay null. Historical corrections need a separate workflow.
- Paddock moves reference a known group and known origin/destination (origin may be null). They do not infer occupancy, enforce rotation, or alter animal membership.
- Approximate observations use a representative date, approximation flag, and notes. Unknown dates/date ranges need a later import contract; do not invent exact dates.
- Source provenance is retained as data; referenced documents are not read or interpreted as instructions.

## Frontend handoff

The original prototype remains independent. Replace fixture reads/form handling with `fetch` calls when connecting it:

```javascript
const response = await fetch('http://127.0.0.1:8000/api/v1/animals?q=%238%20White');
if (!response.ok) throw new Error('Unable to load the herd');
const { items } = await response.json();
```

The API does not supply the prototype's display-only `text`, `meta`, or `time` fields. Format them in the UI. Dates remain local calendar strings; `recorded_at` is UTC. The API uses snake_case field names and event types such as `paddock_move`.

`HERD_CORS_ORIGINS` defaults to `http://localhost:4173,http://127.0.0.1:4173`; serve the prototype over HTTP. Configure explicit origins if needed. CORS does not authenticate users. Credentials/cookie authentication are not configured yet.

## Database teammate handoff

`app/Contracts/HerdRepository.php` is the PHP storage boundary. `app/Services/HerdService.php` contains domain operations; request classes perform input validation; the controller and routes handle HTTP. Implement a database adapter and bind it in `app/Providers/AppServiceProvider.php`. Database engine, tables, migrations, indexes, and ORM choices remain the database teammate's responsibility.

The adapter must return independent arrays, preserve the documented JSON shapes, and return null for unknown IDs. It must append events without overwriting existing IDs. Transactions must commit animal changes and events together, roll back failures, and serialize conflicting changes to an animal (for example, row locks inside a database transaction). Do not merely wrap writes in a transaction while leaving the initial animal read unlocked. Run the HTTP and transaction tests against the adapter.

The current service filters and paginates collections for the small demo. Push these operations into database queries if scale requires it, preserving order and API behavior. Assign ownership for the adapter/queries as well as tables/migrations, and separately for users/authentication. Production must replace the demo-only binding after these pieces are ready.

## Tests

With PHP and Composer on PATH, the following commands work in either PowerShell or Bash from the `backend` directory:

```sh
composer test
composer lint
composer validate --strict
```

From the repository root, use `.\scripts\backend.ps1 test` and `.\scripts\backend.ps1 lint` on Windows, or `bash scripts/backend.sh test` and `bash scripts/backend.sh lint` on Linux. `npm test` runs the unchanged frontend domain tests and requires Node.js/npm.

PHPUnit covers all event types, source preservation, strict validation, reference failures, pagination/order, CORS, documentation routes, repository replacement, production demo refusal, rollback, file persistence, corruption handling, and actual concurrent tag changes from eight independent PHP processes. Tests use isolated fixtures/files and never the normal demo file. Laravel Pint checks PHP formatting. No running database is needed.

## IONOS shared-hosting deployment

The code is prepared for a conventional Laravel/PHP deployment, but no IONOS account or server has been accessed or tested. Confirm the exact Linux shared-hosting package, PHP version/extensions, Composer/SSH access, and database availability. See [IONOS Composer setup](https://www.ionos.co.uk/help/hosting/using-php-for-web-projects/installing-php-composer-in-ionos-web-hosting/) and [Laravel deployment requirements](https://laravel.com/framework/docs/deployment).

After production database integration and authentication are implemented:

1. Install locked dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`. Use the same PHP version/extensions for CLI and web requests; run `composer check-platform-reqs --no-dev` on the server.
2. Point the domain document root at `backend/public`, never at the repository/backend root. The included `.htaccess` routes Apache requests to `index.php`. Keep `.env`, source, logs, vendor files, and storage outside public access.
3. Configure the private production `.env` (`APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL, database settings and explicit CORS origins). Generate an application key once, then retain it securely.
4. Make only `storage/` and `bootstrap/cache/` writable by the PHP account. Do not use world-writable permissions.
5. Run the database teammate's migrations, then `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` as part of deployment. Clear/rebuild caches when configuration changes.
6. Verify authenticated read/write workflows, HTTPS, backup/restore, error logs, and access control on the actual plan.

The current API needs no always-running queue worker, Node backend, or WebSocket server. Do not use `php artisan serve` in production; the host's web server handles PHP requests. Future jobs/reminders/offline sync require separate design and hosting verification.

## Still outside this rewrite

Authentication/roles, production database integration, frontend wiring, record corrections, reviewed workbook imports, real group/paddock administration, confirmed projections, reminders, offline sync, and later operations features. The rewrite preserves the implemented backend scope; it does not claim these future capabilities are complete.
