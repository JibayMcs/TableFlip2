# syntax=docker/dockerfile:1.7
#
# TableFlip — production image.
#
# Multi-stage build:
#   1. composer  : install PHP dependencies (no dev, optimised autoloader)
#   2. node      : install JS deps and build the Vite bundle
#   3. runtime   : FrankenPHP + the app, with both PDO drivers we ship
#                  (mysql / pgsql / sqlsrv) pre-installed.
#
# Run behind a reverse proxy (Traefik / Caddy / nginx) that handles TLS.
# The container listens on port 8080 (HTTP).
#

# ---------- 1. composer ----------
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------- 2. node ----------
FROM node:22-alpine AS node
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.* ./
COPY tsconfig*.json ./
COPY public ./public
# Vendor files are referenced by app.ts (livewire.esm), copy what we need.
COPY --from=composer /app/vendor/livewire/livewire/dist /app/vendor/livewire/livewire/dist
RUN npm run build

# ---------- 3. runtime (FrankenPHP) ----------
FROM dunglas/frankenphp:1-php8.3-alpine AS runtime

# System packages we need at runtime :
#  - icu-libs / oniguruma : intl + mbstring
#  - libpq                : PostgreSQL client
#  - unixodbc + freetds   : SQL Server connectivity
#  - git + unzip          : composer scripts may need them
RUN apk add --no-cache \
        icu-libs \
        oniguruma \
        libpq \
        libzip \
        sqlite-libs \
        unixodbc \
        freetds \
        gettext \
        netcat-openbsd \
        && \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        oniguruma-dev \
        libpq-dev \
        libzip-dev \
        sqlite-dev \
        unixodbc-dev \
        freetds-dev \
        && \
    docker-php-ext-configure intl && \
    docker-php-ext-install -j$(nproc) \
        intl \
        mbstring \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_dblib \
        pdo_sqlite \
        opcache \
        bcmath \
        zip \
        && \
    apk del .build-deps && \
    rm -rf /tmp/* /var/cache/apk/*

WORKDIR /app

# Copy app + built artefacts in the right order so layer cache stays warm.
COPY --from=composer /app/vendor /app/vendor
COPY --from=node /app/public/build /app/public/build
COPY . /app

# Production tunings : opcache validate_timestamps=0, JIT on.
COPY .docker/php.ini /usr/local/etc/php/conf.d/zz-tableflip.ini

# FrankenPHP worker config + Caddyfile (HTTP only — TLS handled by Traefik).
COPY .docker/Caddyfile /etc/caddy/Caddyfile

# Storage tree lives entirely on the mounted volume — entrypoint creates
# the subdirs on first boot. We DON'T pre-create them here : if we did,
# Docker would pre-populate the named volume from image content, which
# conflicts with Dokploy's volume management.
RUN mkdir -p bootstrap/cache && \
    chown -R www-data:www-data bootstrap/cache

# Entrypoint runs migrations + caches the config/routes/views then hands
# over to the FrankenPHP server (or queue worker, depending on CMD).
COPY .docker/entrypoint.sh /usr/local/bin/tableflip-entrypoint
RUN chmod +x /usr/local/bin/tableflip-entrypoint

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/tableflip-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
