#!/usr/bin/env bash
# =====================================================================
# init.sh — one-command startup Absensi & Payroll Platform.
#   ./scripts/init.sh
# Yang dilakukan:
#   1. cek docker + plugin compose + openssl
#   2. buat .env dari .env.example bila belum ada (chmod 600)
#   3. buat secret otomatis: APP_KEY, POSTGRES_PASSWORD,
#      TDOCS_ADMIN_PASSWORD (bila kosong / masih placeholder)
#   4. docker compose up -d --build
#   5. tunggu semua service sehat
#   6. tampilkan URL + langkah seed admin
# Aman dijalankan ulang (idempotent).
# =====================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

need() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "ERROR: '$1' tidak ditemukan. Install dulu lalu ulangi." >&2
    exit 1
  }
}

need docker
docker compose version >/dev/null 2>&1 || {
  echo "ERROR: plugin 'docker compose' tidak tersedia." >&2
  exit 1
}
need openssl
command -v python3 >/dev/null 2>&1 || {
  echo "ERROR: 'python3' dibutuhkan untuk cek kesehatan service." >&2
  exit 1
}

# ---- 1. .env ----------------------------------------------------------
if [ ! -f .env ]; then
  echo "[init] .env tidak ada -> menyalin dari .env.example"
  cp .env.example .env
  chmod 600 .env
fi

# ---- 2. secret otomatis ------------------------------------------------
gen_hex_secret() {
  # $1 = nama var, dibuat bila kosong / masih placeholder
  local var="$1" val
  val="$(grep -E "^${var}=" .env | cut -d= -f2- || true)"
  if [ -z "${val}" ] || [[ "${val}" == *"change-me"* ]]; then
    local secret
    secret="$(openssl rand -hex 32)"
    sed -i "s|^${var}=.*|${var}=${secret}|" .env
    echo "[init] ${var}: secret dibuat otomatis."
  fi
}

gen_hex_secret POSTGRES_PASSWORD
gen_hex_secret TDOCS_ADMIN_PASSWORD

if grep -qE '^APP_KEY=$' .env; then
  # base64 alphabet tidak mengandung '&' -> aman untuk sed.
  key="base64:$(openssl rand -base64 32)"
  sed -i "s|^APP_KEY=.*|APP_KEY=${key}|" .env
  echo "[init] APP_KEY dibuat otomatis."
fi

# ---- 3. up -------------------------------------------------------------
echo "[init] docker compose up -d --build ..."
docker compose up -d --build

# ---- 4. tunggu sehat ---------------------------------------------------
echo "[init] menunggu semua service sehat (maks ~10 menit) ..."
deadline=$((SECONDS + 600))
while [ $SECONDS -lt $deadline ]; do
  status="$(docker compose ps --format json 2>/dev/null | python3 -c "
import json, sys
raw = sys.stdin.read().strip()
if not raw:
    print('WAIT'); sys.exit()
try:
    data = json.loads(raw)
except Exception:
    print('WAIT'); sys.exit()
items = data if isinstance(data, list) else [data]
bad = []
for s in items:
    name = s.get('Service') or s.get('Name') or '?'
    health = (s.get('Health') or '').lower()
    state = (s.get('State') or '').lower()
    if 'unhealthy' in health or state in ('exited', 'dead', 'failed'):
        bad.append(name)
    elif health and health != 'healthy':
        bad.append(name + '(' + health + ')')
print('OK' if not bad else 'WAIT:' + ','.join(bad))
" || echo WAIT)"
  if [ "$status" = "OK" ]; then
    echo "[init] semua service sehat."
    break
  fi
  echo "  ... $status"
  sleep 10
done

if [ "$status" != "OK" ]; then
  echo "ERROR: sebagian service belum sehat: $status" >&2
  echo "Periksa: docker compose ps ; docker compose logs --tail=100 <service>" >&2
  exit 1
fi

# ---- 5. ringkasan ------------------------------------------------------
set -a
# shellcheck disable=SC1091
source .env
set +a

cat <<EOF

=================================================================
 Absensi & Payroll Platform berjalan.
   PWA Karyawan : https://${APP_DOMAIN}
   Admin Portal : https://${APP_DOMAIN}/admin
   tDocs Portal : https://${DOCS_DOMAIN}  (login: password TDOCS_ADMIN_PASSWORD)
=================================================================

Langkah berikutnya — buat akun admin pertama:
  docker compose exec admin-backend php artisan db:seed --class=AdminSeeder
  (lihat apps/admin-backend/database/seeders untuk kredensial default)

Operasional:
  ./scripts/backup.sh    -> backup DB + volume tdocs-data
  ./scripts/restore.sh    -> panduan restore interaktif
  docker compose logs -f  -> lihat log semua service
EOF
