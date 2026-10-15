#!/bin/sh
# Entrypoint admin-backend (dipakai juga oleh admin-worker & admin-scheduler).
# File ini milik INFRA (jangan dihapus saat scaffolding ulang backend).
# Urutan: tunggu Postgres -> siapkan storage -> package:discover ->
# migrate --force -> cache config (prod) -> publish aset publik ke volume
# bersama -> exec command (php-fpm / queue:work / scheduler loop).
set -eu

cd /var/www/html

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-absensi}"

echo "[entrypoint] menunggu postgres ${DB_HOST}:${DB_PORT} ..."
for i in $(seq 1 60); do
  if pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME"

mkdir -p storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# composer install dijalankan dengan --no-scripts, jadi discover di sini.
su-exec www-data php artisan package:discover --ansi >/dev/null 2>&1 || true

echo "[entrypoint] menjalankan migrasi ..."
su-exec www-data php artisan migrate --force

if [ "${APP_ENV:-production}" = "production" ]; then
  echo "[entrypoint] caching config/route/view ..."
  su-exec www-data php artisan config:cache
  su-exec www-data php artisan route:cache
  su-exec www-data php artisan view:cache
fi

# Salin public/ (build Vite + index.php) ke volume bersama agar Caddy bisa
# menyajikan file statis langsung tanpa lewat FPM.
if [ -d /srv/public ]; then
  echo "[entrypoint] mempublish aset publik ke /srv/public ..."
  cp -a /var/www/html/public/. /srv/public/
fi

case "${1:-}" in
  php-fpm)
    # php-fpm menurunkan privilege ke www-data sendiri (www.conf).
    exec "$@"
    ;;
  *)
    # queue:work / schedule:run loop -> jalan sebagai www-data.
    exec su-exec www-data "$@"
    ;;
esac
