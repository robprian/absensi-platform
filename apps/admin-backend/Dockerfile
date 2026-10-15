# syntax=docker/dockerfile:1
# ---------------------------------------------------------------------------
# Admin Portal + Backend API — Laravel 12 di PHP 8.3-FPM (Alpine).
# Image yang SAMA dipakai ulang untuk tiga service:
#   admin-backend   -> command ["php-fpm"]            (FastCGI :9000)
#   admin-worker    -> command ["php", "artisan", "queue:work", ...]
#   admin-scheduler -> command [loop "php artisan schedule:run"]
# Caddy (reverse-proxy) berbicara FastCGI ke :9000 dan menyajikan file statis
# dari volume bersama "backend-public" (diisi entrypoint saat boot).
# Build context: apps/admin-backend
# File ini milik INFRA (jangan dihapus saat scaffolding ulang backend).
# ---------------------------------------------------------------------------

FROM php:8.3-fpm-alpine AS app

# Ekstensi yang dibutuhkan Laravel + Postgres + Redis + Queue:
# pdo_pgsql (DB), pcntl (queue worker), bcmath (kalkulasi rupiah presisi),
# gd + xml (dompdf & maatwebsite/excel), opcache, zip. redis via PECL.
# DL3018 diabaikan: versi paket apk sengaja tidak di-pin; pin versi dilakukan
# di base image (php:8.3-fpm-alpine) agar build tidak rapuh terhadap rotasi
# minor di registry Alpine. SC2086 diabaikan: $PHPIZE_DEPS memang daftar flag
# pisah-spasi (pola resmi dari image php).
# hadolint ignore=DL3018,SC2086
RUN apk add --no-cache \
        su-exec \
        postgresql-client \
        libpng \
        libjpeg-turbo \
        freetype \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pcntl \
        bcmath \
        gd \
        opcache \
        zip \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis opcache \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Composer dari image resmi (binary saja, tanpa stage terpisah).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# Kode aplikasi milik www-data (user yang menjalankan php-fpm).
COPY --chown=www-data:www-data . /var/www/html

# Install dependency PHP (prod only). Ekstensi sudah ter-install di atas,
# sehingga composer dapat memvalidasi platform requirements tanpa ignore.
RUN composer install \
      --no-dev \
      --no-interaction \
      --no-progress \
      --no-scripts \
      --prefer-dist \
      --optimize-autoloader \
    && composer clear-cache \
    && rm -rf /tmp/*

RUN mkdir -p storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/html/vendor

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'
