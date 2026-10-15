# Absensi Kerja — Employee PWA

Aplikasi absensi & penggajian untuk karyawan lapangan (Bahasa Indonesia).
SvelteKit 2 + TypeScript + Tailwind CSS 4 + lucide-svelte + vite-plugin-pwa.

## Rute

| Rute | Fungsi |
|---|---|
| `/login` | Masuk (No. HP / ID karyawan + kata sandi) |
| `/` | Beranda: kartu absen (Belum Absen / Sedang Bekerja / Selesai Bekerja), pintasan |
| `/absen?type=MASUK\|KELUAR` | Alur kamera + GPS + watermark → kirim absen |
| `/riwayat` | Riwayat absensi per bulan |
| `/slip-gaji`, `/slip-gaji/[id]` | Daftar & detail slip gaji final + unduh PDF |
| `/reimburse` | Pengajuan reimburse (hanya bila diaktifkan admin) |
| `/profil` | Data karyawan, panduan instalasi, keluar |
| `/notifikasi` | Notifikasi |
| `/install` | Panduan instalasi PWA (Android/iOS) |

## API

Base URL via `VITE_API_BASE_URL` (default `/api`, di-proxy ke backend).
Kontrak endpoint: `POST /api/v1/auth/login`, `GET /api/v1/settings/public`,
`GET /api/v1/attendance/today`, `POST /api/v1/attendance/clock-in|clock-out`
(FormData + header `Idempotency-Key`), `GET /api/v1/attendance/history`,
`GET /api/v1/payslips[/{id}[/download-url]]`, `GET|POST /api/v1/reimbursements`,
`GET /api/v1/notifications`, `POST /api/v1/attendance/geocode`.

## Offline

Pengiriman absen yang gagal/offline diantrekan di IndexedDB (`absensi-pwa`)
dengan kunci idempotensi yang **sama**; disinkronkan saat online.
Tidak pernah ditampilkan sebagai terverifikasi sebelum backend 200.

## Perintah

```bash
npm install
npm run dev      # :3000 (proxy /api → :8000 bila VITE_API_BASE_URL=/api)
npm run check    # svelte-check
npm run lint     # prettier + eslint
npm run build    # output ke build/ (adapter-static, fallback index.html)
```

Catatan: `Dockerfile`, `.dockerignore`, `docker/` milik workstream INFRA —
jangan dihapus.
