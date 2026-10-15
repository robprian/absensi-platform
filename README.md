# Absensi & Payroll Platform — Karyawan Lapangan (Indonesia)

Platform absensi + penggajian untuk karyawan lapangan. Tiga aplikasi
user-facing, satu stack Docker Compose.

```
                        ┌───────────────────────────────┐
                        │        reverse-proxy (Caddy)  │
                        │   :80/:443, HTTPS otomatis    │
                        └──────┬──────────┬─────────┬───┘
                               │          │         │
              app.example.com  │  admin…  │  docs…  │
                               ▼          ▼         ▼
                     ┌──────┐ ┌────────────────┐ ┌────────┐
                     │ PWA  │ │ admin-backend  │ │ tdocs  │
                     │nginx │ │ Laravel (FPM)  │ │  (Go)  │
                     │ :80  │ │     :9000      │ │ :8080  │
                     └──┬───┘ └───────┬────────┘ └───┬──┘
                        │             │              │
                        │   ┌─────────┴─────────┐    │
                        │   │  network "internal" (privat) │
                        │   └─────────┬─────────┘    │
                        │             │              │
                   ┌────┴───┐   ┌─────┴────┐   ┌─────┴────┐
                   │postgres│   │  redis   │   │ (volume) │
                   │  :5432 │   │  :6379   │   │tdocs-data│
                   └────────┘   └──────────┘   └──────────┘
```

- **Employee PWA** (`apps/employee-pwa`) — SvelteKit, mobile-first, Bahasa
  Indonesia. Satu layar satu aksi: Mulai Kerja → selfie + watermark GPS →
  Kirim Absen. `/api/*` di-proxy same-origin oleh Caddy (tanpa CORS).
- **Admin Portal + Backend API** (`apps/admin-backend`) — Laravel 12,
  PHP 8.3-FPM. Blade untuk admin UI, REST API + Sanctum token untuk PWA.
  Queue worker & scheduler jalan sebagai service terpisah dari image yang sama.
- **tDocs Portal** (`services/tdocs`) — repo Go asli (jangan dimodifikasi
  source-nya). Menyimpan selfie absensi, struk reimburse, PDF slip gaji.
  `TDOCS_CDN_PUBLIC=0` = semua akses file privat; Laravel mem-proxy download
  dengan URL bertanda tangan berumur pendek.

Hanya `reverse-proxy` yang membuka port ke publik (80/443). PostgreSQL,
Redis, tDocs, dan backend **tidak** di-publish di produksi.

## Prasyarat

- Docker Engine 24+ dengan plugin `docker compose`
- `openssl`, `python3`, `bash`
- 3 DNS A record → IP VPS: `app.` / `admin.` / `docs.` (contoh:
  `app.example.com`)
- VPS ringan (±2 vCPU / 4 GB RAM) cukup; batas resource per service sudah
  dipasang di `compose.yml`

## Quickstart (produksi)

```bash
cp .env.example .env          # atau langsung:
./scripts/init.sh              # buat secret, build, up, tunggu sehat
```

`init.sh` idempotent: aman dijalankan ulang. Setelah sehat:

```bash
# Buat akun admin pertama (lihat apps/admin-backend/database/seeders):
docker compose exec admin-backend php artisan db:seed --class=AdminSeeder
```

Buka:

| Layanan      | URL produksi                 | URL dev (`compose.dev.yml`) |
|--------------|------------------------------|-----------------------------|
| PWA Karyawan | `https://app.example.com`    | `http://localhost:3000`     |
| Admin + API  | `https://admin.example.com`  | `http://localhost:8000`     |
| tDocs        | `https://docs.example.com`   | `http://localhost:8080`     |
| PostgreSQL   | *(privat)*                   | `localhost:5432`            |
| Redis        | *(privat)*                   | `localhost:6379`            |

Dev:

```bash
docker compose -f compose.yml -f compose.dev.yml up --build
```

> Catatan kamera/GPS di dev: buka PWA via `http://localhost:3000`, **bukan**
> IP LAN — hanya `localhost`/HTTPS yang dianggap *secure context* oleh
> browser sehingga `getUserMedia` & geolocation diizinkan.

## Struktur repo

```
.
├── apps/
│   ├── employee-pwa/          # SvelteKit PWA
│   │   ├── Dockerfile         # node:24-alpine build -> nginx:alpine
│   │   └── docker/nginx.conf
│   └── admin-backend/         # Laravel 12
│       ├── Dockerfile         # php:8.3-fpm-alpine multi-stage
│       └── docker/
│           ├── entrypoint.sh  # tunggu DB -> migrate -> cache -> serve
│           └── php/app.ini
├── services/
│   └── tdocs/                 # repo Go asli (read-only untuk kita)
├── docker/
│   └── proxy/
│       ├── Caddyfile          # produksi (domain + HTTPS otomatis)
│       └── Caddyfile.dev      # dev (port 3000/8000/8080, tanpa TLS)
├── scripts/
│   ├── init.sh                # one-command startup
│   ├── backup.sh              # pg_dump + arsip volume tdocs-data
│   └── restore.sh             # restore interaktif
├── .github/workflows/
│   ├── ci.yml                 # test + build + scan + smoke
│   └── deploy.yml             # stub manual dispatch (butuh CI hijau)
├── compose.yml                # produksi
├── compose.dev.yml            # override dev
├── .env.example               # semua variabel, terdokumentasi
└── SPEC.md                    # spesifikasi produk
```

## Kontrak environment (penting untuk tim backend/frontend)

| Variabel | Dipakai oleh | Keterangan |
|---|---|---|
| `APP_KEY` | backend | dibuat otomatis oleh `init.sh` bila kosong |
| `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` | backend | host `postgres` di dalam network |
| `REDIS_HOST/REDIS_PORT/REDIS_PASSWORD` | backend | host `redis`; password opsional |
| `TDOCS_BASE_URL` | backend | selalu `http://tdocs:8080` (internal) |
| `TDOCS_ADMIN_PASSWORD` | tdocs + backend | **sama** untuk login portal & `Authorization: Bearer` API; jangan expose ke PWA |
| `VITE_API_BASE_URL` | PWA (build arg) | default `/api` (same-origin via Caddy) |
| `APP_DOMAIN/ADMIN_DOMAIN/DOCS_DOMAIN` | Caddy | wajib diisi di produksi |

Aturan uang & waktu (lihat SPEC.md): semua nominal **integer rupiah**
(tanpa float), timestamp disimpan **UTC**, tampil **Asia/Jakarta**.

## Backup & restore

```bash
./scripts/backup.sh     # -> backups/db-<ts>.sql.gz + backups/tdocs-data-<ts>.tar.gz
                        #    retensi default 7 hari (BACKUP_RETENTION_DAYS)
./scripts/restore.sh    # interaktif, dengan konfirmasi "YA"
```

Jadwalkan backup harian via cron di host:

```cron
0 2 * * * cd /opt/absensi-platform && ./scripts/backup.sh >> /var/log/absensi-backup.log 2>&1
```

Simpan salinan backup **di luar VPS** (object storage / NAS). File `.env`
tidak ikut di-backup — simpan di password manager.

## Rollback database (migrasi)

Prosedur aman setiap kali migrasi bermasalah:

```bash
./scripts/backup.sh   # 1. backup dulu
# 2. rollback N batch terakhir:
docker compose exec admin-backend php artisan migrate:rollback --step=1
# 3. verifikasi:
docker compose exec admin-backend php artisan migrate:status
```

Untuk rollback rilis aplikasi penuh: checkout tag rilis sebelumnya, lalu
`docker compose up -d --build`, dan bila skema berubah ikutsertakan
`migrate:rollback` / restore dari backup.

## CI/CD

- `.github/workflows/ci.yml` — setiap push/PR: composer validate, `php -l`,
  migrasi + test (Postgres & Redis service), svelte-check, eslint, build PWA,
  `docker build` ketiga image, Trivy scan (best-effort, non-blocking),
  validasi `compose config`, validasi Caddyfile via `caddy validate`,
  shellcheck untuk scripts.
- `.github/workflows/deploy.yml` — manual dispatch; job pertama memeriksa
  via GitHub API bahwa **CI sukses untuk ref yang sama**, gagal bila tidak
  (`fail-closed`). Environment `production` sebaiknya diproteksi dengan
  *required reviewers*. Langkah deploy ke server masih stub — sesuaikan.

## Troubleshooting

| Gejala | Cek |
|---|---|
| `init.sh` gagal di secret | pastikan `openssl` terinstal |
| Service `unhealthy` | `docker compose ps`, `docker compose logs --tail=100 <service>` |
| Caddy tidak dapat sertifikat | DNS A record menunjuk ke VPS? port 80/443 terbuka? `docker compose logs reverse-proxy` |
| Backend 502 di `/api/*` | `admin-backend` healthy? `docker compose exec admin-backend php-fpm -t`? cek `backend-public` volume terisi |
| tDocs healthcheck gagal | `TDOCS_ADMIN_PASSWORD` di `.env` sama dengan env container? `/api/health` butuh Bearer |
| PWA kamera/GPS ditolak (dev) | buka via `http://localhost:3000`, bukan IP LAN |
| Port 3000/8000/8080 bentrok (dev) | service lain memakai port tsb; ubah mapping di `compose.dev.yml` |
| Disk penuh | `docker system df`; backup lama dibersihkan otomatis per retensi; log dibatasi 10 MB × 3 per container |

## Keamanan

- Secret hanya di `.env` (chmod 600, tidak di-commit). Tidak ada secret
  hardcode di image/compose/Caddyfile.
- `TDOCS_ADMIN_PASSWORD` tidak pernah dibake ke PWA; karyawan mengakses file
  hanya lewat proxy backend dengan URL bertanda tangan berumur pendek.
- Network `internal` privat; satu-satunya ingress = Caddy (80/443).
- Header keamanan (nosniff, DENY frame, Referrer-Policy, HSTS, Permissions-Policy)
  dipasang di Caddy. PWA (`app.`) mengizinkan `camera` + `geolocation` untuk
  *self* — memang dibutuhkan fitur absensi.
- Rate limiting login & API diimplementasikan di Laravel (bukan di proxy).
- Lokasi GPS hanya diambil saat aksi absensi yang sah, tidak ada tracking
  background.

## Yang belum bisa diverifikasi di lingkungan ini

Tidak ada Docker daemon di environment build ini, sehingga yang **belum**
terverifikasi dan wajib dicek saat deploy pertama:

1. `docker build` ketiga image (khususnya `pecl install redis` &
   `docker-php-ext-install` di `php:8.3-fpm-alpine`).
2. `docker compose up` end-to-end + healthcheck + migrasi otomatis.
3. `caddy validate` untuk kedua Caddyfile (disertakan di CI `smoke` job).
4. Penerbitan sertifikat Let's Encrypt untuk 3 domain.
5. Alur FastCGI Caddy → php-fpm (pola `php_fastcgi` + `root` transport
   terpisah sudah mengikuti pola dokumentasi Caddy, tapi butuh uji live).
6. Trivy/shellcheck di CI (best-effort, non-blocking).
