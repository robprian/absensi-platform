# Absensi & Payroll Platform — Admin Backend

Laravel 12 (PHP 8.3) backend for the Absensi & Payroll Platform: REST API for the
employee PWA (`/api/v1`, Sanctum tokens) + a Blade + Tailwind admin portal
(`/admin`, session auth).

## Requirements

- PHP 8.3 with extensions: pdo_pgsql (prod) / pdo_sqlite (dev/tests), zip, gd, intl
- Composer 2
- PostgreSQL 15+ (production); SQLite works for local dev/tests
- tDocs document service reachable at `TDOCS_BASE_URL` (default `http://tdocs:8080`)

## Setup

```bash
cp .env.example .env
php artisan key:generate
# configure DB_*, TDOCS_*, ADMIN_EMAIL, ADMIN_PASSWORD in .env
php artisan migrate --force
php artisan db:seed --force        # roles, permissions, settings, super admin
php artisan serve                  # dev only; production uses the Docker image
```

### Seed credentials

The seeder creates a super admin from env (defaults shown):

- Email: `ADMIN_EMAIL` (default `admin@example.com`)
- Password: `ADMIN_PASSWORD` (default `changeme123` — **change it in production**)

## API overview (`/api/v1`)

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| POST | `/auth/login` | — (throttle 5/min) | email+password+device_name → Sanctum token |
| POST | `/auth/logout` | token | revokes current token |
| GET | `/auth/me` | token | current user + employee + roles |
| POST | `/attendance/clock-in` | token, employee | multipart: selfie, lat, lng, accuracy, captured_at?, idempotency_key (or `Idempotency-Key` header) |
| POST | `/attendance/clock-out` | token, employee | same as clock-in |
| GET | `/attendance/status` | token, employee | today's state for the PWA home screen |
| GET | `/attendance/history` | token, employee | paginated work-day summaries |
| GET | `/payroll/payslips` | token, employee | **only finalized/published** payslips |
| GET | `/payroll/payslips/{id}/pdf` | token, employee | payslip PDF (ownership-checked) |
| GET/POST | `/reimbursements` | token, employee | 403 for employees when `reimbursement_enabled` is OFF |
| GET | `/reimbursements/{id}` | token, employee | |
| POST | `/files/{storedFile}/download` | signed URL | 10-min signed proxy to tDocs (password never exposed) |
| * | `/admin/*` | token + role | user/role admin, payroll draft→finalize→lock, reimburse review, settings, audit |

**Idempotency:** clock-in/clock-out and reimbursement create accept the key as the
`idempotency_key` field or the `Idempotency-Key` header. Replaying a key returns
the original response (no double-create); reusing a key with different data
returns `409 idempotency_key_mismatch`.

**Photos when tDocs is down:** the request still succeeds; the photo is staged
locally and `upload_pending: true` is returned. `RetryTdocsUpload` (queue)
retries asynchronously, and `storage:reconcile` re-uploads stale staged files.

## Payroll engine

- Money is **always integers (IDR)** — no floats anywhere in payroll math.
- `compensation_policies` + `employee_compensation_rules` → `payroll_periods`
  (weekly/biweekly/monthly/custom) → `payroll_runs` (draft → review → finalized → locked).
- Rates are snapshotted at finalization; employees only ever see finalized payslips.
- Payslip PDF via barryvdh/laravel-dompdf.

## Working hours

`WorkingHoursService` (pure, unit-tested): masuk/keluar from immutable events,
configurable break deduction, overnight shifts, late/early/overtime flags,
missing clock-out → exception (see `attendance:mark-exceptions` scheduler).

## Scheduled tasks

- `attendance:mark-exceptions` — flag missing clock-outs / anomalies
- `storage:reconcile` — retry staged uploads that never reached tDocs

## Tests

```bash
php artisan test
```

42 tests / 165 assertions (idempotency, working-hours, payroll integer math,
geofence, RBAC, reimbursement toggle, payroll flow, signed PDF, admin web).
Runs on `sqlite :memory:` — see `phpunit.xml`.

## Environment variables

| Var | Purpose |
|---|---|
| `TDOCS_BASE_URL` | tDocs base URL (default `http://tdocs:8080`) |
| `TDOCS_ADMIN_PASSWORD` | tDocs admin password — **server-side only** |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | seeded super admin |
| `DB_*` | PostgreSQL connection (production) |

## Not verified in this environment

- PostgreSQL: migrations are written for PG (FKs, unique constraints, indexes)
  but were only executed on SQLite — no local PG server was available.
- Live tDocs integration: `TdocsService` implements the spec'd resumable-upload
  protocol but was tested only against faked HTTP; no live tDocs instance here.
- Docker build/compose: `Dockerfile`, `.dockerignore`, `docker/` are owned by the
  infra workstream ("Milik INFRA") — do not modify them here.
