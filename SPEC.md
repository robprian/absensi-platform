# SPEC — Absensi & Payroll Platform (Field Workers, Indonesia)

## Apps (exactly 3 user-facing)
1. **Employee PWA** (`apps/employee-pwa`): SvelteKit + TypeScript + Tailwind + Lucide, installable PWA (id-ID).
2. **Admin Portal + Backend API** (`apps/admin-backend`): Laravel 12, PHP 8.3, PostgreSQL, Redis. Blade+Tailwind admin UI served by same service. Sanctum token auth for PWA.
3. **tDocs Portal** (`services/tdocs`): real cloned repo github.com/robprian/tDocs (Go). Private files; backend talks to it via authenticated adapter.

## tDocs API (verified from repo)
- Base: `http://tdocs:8080` (internal docker network)
- Auth: `Authorization: Bearer <TDOCS_ADMIN_PASSWORD>` (or `?api_key=`)
- Upload resumable: `POST /api/upload/init {name,size,mime_type,folder_id}` → `{session_id, chunk_size(5MB)}`; `POST /api/upload/chunk` multipart `{session_id, chunk_index, chunk}`; `POST /api/upload/complete {session_id}` → 201 file record
- Files: `GET /api/files/{id}/meta`, `GET /api/files/{id}/download` (auth), `GET /api/files/{id}/stream`
- Folders: `POST /api/folders {name, parent_id?}`
- Health: `GET /api/health`, `GET /api/status`
- Set `TDOCS_CDN_PUBLIC=0` → CDN private. Never expose admin password to employees; Laravel proxies downloads with signed short-lived URLs.

## Currency & time
- All money: integer IDR ( rupiah ), never float. Format `Rp 80.000`.
- Store timestamps UTC; display Asia/Jakarta, Indonesian dates.

## Core domain rules
- Attendance = `attendance_sessions` (one per employee per workday/shift) + `attendance_events` (MASUK/KELUAR, immutable, server timestamp authoritative).
- Idempotency: client sends `Idempotency-Key` header; `idempotency_records` table + DB unique constraint prevents duplicate clock-in/out.
- Never trust client timestamp/GPS; record as reported evidence + server receipt time + GPS accuracy; validate: coordinate ranges, accuracy threshold, stale fix rejection, geofence policy per work site.
- Working hours computed server-side: masuk→keluar minus configurable break; overnight shifts; late/early/overtime flags; missing clock-out → exception for review.
- Payroll: `payroll_periods` (weekly/biweekly/monthly/custom), `payroll_runs` (draft → finalized → locked), `payroll_items`, `payslips`. Rate snapshots stored at finalization. Draft ≠ finalized; employees see only finalized payslips. Integer math only.
- Reimbursement: global setting `reimbursement_enabled` ON/OFF. When OFF: hide UI + disable endpoints (403), keep history. Statuses: menunggu/disetujui/ditolak/dibayar, audit trail per transition.
- RBAC roles: super_admin, hr_admin, payroll_officer, supervisor, employee. Enforce in Laravel policies.

## PWA essentials
- Home: header (logo, "Halo, {nama}", date, notif icon); one big attendance card (Belum Absen → [Mulai Kerja] green; Sedang Bekerja → live counter + [Selesai Kerja] red; Selesai Bekerja → summary); shortcuts Riwayat/Slip Gaji/Reimburse (conditional); bottom nav Beranda/Riwayat/Slip Gaji/Profil.
- Capture flow: camera permission → GPS permission → front camera → live watermark preview → capture → confirm → Kirim Absen. Watermark BURNED into image via canvas: name, employee ID, MASUK/KELUAR, date/time WIB, lat/lng, address (reverse geocode best-effort), accuracy. Bottom overlay, semi-transparent dark, never cover face.
- Offline: app shell cached, drafts queued with pending-sync status + idempotency key; never mark verified until backend confirms.
- All employee text in Bahasa Indonesia.

## DB entities (minimum)
users, employees, roles, permissions, employee_role_assignments, departments, work_sites, work_assignments, work_schedules, attendance_sessions, attendance_events, attendance_evidence, attendance_corrections, break_records, compensation_policies, employee_compensation_rules, payroll_periods, payroll_runs, payroll_items, payslips, reimbursements, reimbursement_attachments, stored_files, system_settings, notifications, audit_logs, idempotency_records.

## Infra
compose.yml services: employee-pwa, admin-backend, admin-worker, admin-scheduler, tdocs, postgres, redis, reverse-proxy (Caddy). Dev ports: 3000 PWA, 8000 admin/API, 8080 tdocs. Prod domains configurable: app./admin./docs. Private network; postgres/redis not publicly exposed. One-command startup, healthchecks, volumes, backups documented.

## CI/CD (.github/workflows)
lint+typecheck, unit tests, API integration tests, frontend build, docker builds, vuln scan, smoke tests. No deploy on test failure.
