#!/usr/bin/env bash
# =====================================================================
# backup.sh — backup database PostgreSQL + volume tdocs-data.
#   ./scripts/backup.sh
# Hasil: backups/db-YYYYMMDD-HHMMSS.sql.gz
#        backups/tdocs-data-YYYYMMDD-HHMMSS.tar.gz
# Retensi: file lebih tua dari BACKUP_RETENTION_DAYS (default 7) dihapus.
# Catatan: file .env TIDAK ikut di-backup (berisi secret) — simpan manual
# di password manager.
# =====================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

[ -f .env ] || {
  echo "ERROR: .env tidak ditemukan. Jalankan ./scripts/init.sh dulu." >&2
  exit 1
}
set -a
# shellcheck disable=SC1091
source .env
set +a

command -v docker >/dev/null 2>&1 || {
  echo "ERROR: docker tidak ditemukan." >&2
  exit 1
}

TS="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION="${BACKUP_RETENTION_DAYS:-7}"
mkdir -p "$BACKUP_DIR"

echo "[backup] dump PostgreSQL (${POSTGRES_DB}) ..."
docker compose exec -T postgres \
  pg_dump -U "${POSTGRES_USER:?POSTGRES_USER kosong}" "${POSTGRES_DB:?POSTGRES_DB kosong}" \
  | gzip >"${BACKUP_DIR}/db-${TS}.sql.gz"

echo "[backup] arsip volume tdocs-data ..."
TDOCS_CID="$(docker compose ps -q tdocs)"
[ -n "$TDOCS_CID" ] || {
  echo "ERROR: container tdocs tidak ditemukan." >&2
  exit 1
}
docker run --rm \
  --volumes-from "$TDOCS_CID" \
  -v "${ROOT}/${BACKUP_DIR}:/backup" \
  alpine:3.21 \
  tar czf "/backup/tdocs-data-${TS}.tar.gz" -C /data .

echo "[backup] membersihkan arsip > ${RETENTION} hari ..."
find "$BACKUP_DIR" -maxdepth 1 -name 'db-*.sql.gz' -mtime "+${RETENTION}" -delete
find "$BACKUP_DIR" -maxdepth 1 -name 'tdocs-data-*.tar.gz' -mtime "+${RETENTION}" -delete

echo "[backup] selesai:"
ls -lh "${BACKUP_DIR}/db-${TS}.sql.gz" "${BACKUP_DIR}/tdocs-data-${TS}.tar.gz"
echo "Ingat: simpan salinan backup di lokasi OFF-SERVER (bukan hanya di VPS ini)."
