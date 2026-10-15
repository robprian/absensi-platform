#!/usr/bin/env bash
# =====================================================================
# restore.sh — restore interaktif database PostgreSQL dan/atau volume
# tdocs-data dari arsip buatan ./scripts/backup.sh.
#
#   ./scripts/restore.sh
#
# PERINGATAN: restore MENIMPA data yang sedang berjalan. Jalankan
# ./scripts/backup.sh dulu sebelum restore bila data saat ini berharga.
# =====================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

[ -f .env ] || {
  echo "ERROR: .env tidak ditemukan." >&2
  exit 1
}
set -a
# shellcheck disable=SC1091
source .env
set +a

BACKUP_DIR="${BACKUP_DIR:-./backups}"
[ -d "$BACKUP_DIR" ] || {
  echo "ERROR: direktori backup '$BACKUP_DIR' tidak ada." >&2
  exit 1
}

echo "Arsip database yang tersedia:"
ls -1 "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null | sort || {
  echo "  (tidak ada)"
  exit 1
}
echo
echo "Arsip tdocs-data yang tersedia:"
ls -1 "$BACKUP_DIR"/tdocs-data-*.tar.gz 2>/dev/null | sort || echo "  (tidak ada)"
echo

read -rp "Restore DATABASE? (y/N): " do_db
read -rp "Restore VOLUME tdocs-data? (y/N): " do_vol

if [ "$do_db" != "y" ] && [ "$do_vol" != "y" ]; then
  echo "Tidak ada yang di-restore. Batal."
  exit 0
fi

echo
echo "!!! PERINGATAN: data yang sedang berjalan akan DITIMPA. !!!"
read -rp "Ketik YA untuk lanjut: " confirm
[ "$confirm" = "YA" ] || {
  echo "Batal."
  exit 1
}

if [ "$do_db" = "y" ]; then
  read -rp "Nama file arsip DB (contoh: db-20260101-120000.sql.gz): " dbfile
  [ -f "${BACKUP_DIR}/${dbfile}" ] || {
    echo "ERROR: file tidak ditemukan." >&2
    exit 1
  }
  echo "[restore] memastikan postgres berjalan ..."
  docker compose up -d postgres
  echo "[restore] memutus koneksi aktif ke database ${POSTGRES_DB} ..."
  docker compose exec -T postgres psql -U "$POSTGRES_USER" -d postgres \
    -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${POSTGRES_DB}' AND pid <> pg_backend_pid();"
  echo "[restore] me-restore database ..."
  # Arsip pg_dump polos: drop dulu schema agar restore bersih.
  docker compose exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
    -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
  gunzip -c "${BACKUP_DIR}/${dbfile}" |
    docker compose exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
      -v ON_ERROR_STOP=1 -q
  echo "[restore] database selesai."
fi

if [ "$do_vol" = "y" ]; then
  read -rp "Nama file arsip volume (contoh: tdocs-data-20260101-120000.tar.gz): " volfile
  [ -f "${BACKUP_DIR}/${volfile}" ] || {
    echo "ERROR: file tidak ditemukan." >&2
    exit 1
  }
  echo "[restore] menghentikan tdocs ..."
  docker compose stop tdocs
  TDOCS_CID="$(docker compose ps -q tdocs)"
  [ -n "$TDOCS_CID" ] || {
    echo "ERROR: container tdocs tidak ditemukan." >&2
    exit 1
  }
  echo "[restore] me-restore volume tdocs-data ..."
  docker run --rm \
    --volumes-from "$TDOCS_CID" \
    -v "${ROOT}/${BACKUP_DIR}:/backup:ro" \
    alpine:3.21 \
    sh -c "rm -rf /data/* /data/..?* /data/.[!.]* 2>/dev/null; tar xzf /backup/${volfile} -C /data"
  docker compose up -d tdocs
  echo "[restore] volume tdocs-data selesai."
fi

echo
echo "[restore] selesai. Verifikasi:"
echo "  docker compose ps"
echo "  curl -sk https://${APP_DOMAIN}/api/health   (bila backend menyediakan)"
echo "  Buka https://${APP_DOMAIN}/admin dan cek data karyawan/absensi."
