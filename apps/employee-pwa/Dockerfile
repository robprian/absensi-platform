# syntax=docker/dockerfile:1
# ---------------------------------------------------------------------------
# Employee PWA — SvelteKit (adapter-static) served by nginx.
# Build context : apps/employee-pwa
# Prasyarat     : SvelteKit dikonfigurasi dengan adapter-static dan
#                 output dir "build/" (lihat svelte.config.js).
# File ini milik INFRA (jangan dihapus saat scaffolding ulang frontend).
# ---------------------------------------------------------------------------

ARG VITE_API_BASE_URL=/api

# ---- build stage -----------------------------------------------------------
FROM node:24-alpine AS build
WORKDIR /app

# Install dependencies dulu agar layer ini ter-cache bila hanya kode berubah.
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then \
      npm ci --no-audit --no-fund; \
    else \
      npm install --no-audit --no-fund; \
    fi

COPY . .

# Base URL API publik, di-bake saat build (Vite).
# Default "/api" -> same-origin, di-proxy Caddy ke backend (tanpa CORS).
ARG VITE_API_BASE_URL
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}

RUN npm run build

# Gagalkan build bila output adapter-static tidak ada.
RUN test -d build || (echo "ERROR: direktori 'build/' tidak ditemukan. Pastikan adapter-static terpasang dan output dir = build/." >&2; exit 1)

# ---- runtime stage ---------------------------------------------------------
FROM nginx:alpine AS runtime

COPY --from=build /app/build /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1/healthz || exit 1
