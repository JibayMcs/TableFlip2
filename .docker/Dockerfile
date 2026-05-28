# syntax=docker/dockerfile:1.7
#
# TableFlip — production image.
#
# Modeled on the Cerise prod stack (which runs reliably on the same Dokploy
# server) : Apache + mod_php on `php:8.3-apache` (Debian Bookworm). No
# FrankenPHP / Caddy : the simpler stack means Traefik routing "just works"
# and the image is on a base widely battle-tested in this infra.
#
# Multi-stage build :
#   1. composer : PHP deps (no dev, optimised autoloader)
#   2. node     : npm + Vite bundle
#   3. runtime  : Apache + mod_php with the 4 PDO drivers we ship
#                 (mysql / pgsql / sqlsrv / sqlite) + phpredis.
#
# Listens on port 80 behind Traefik (TLS terminated upstream).

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
# Vendor files referenced by app.ts (livewire.esm).
COPY --from=composer /app/vendor/livewire/livewire/dist /app/vendor/livewire/livewire/dist
RUN npm run build

# ---------- 3. runtime (Apache + mod_php) ----------
FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# 1) Apache : point DocumentRoot at public/, enable mod_rewrite (Laravel).
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}/!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

# 2) System libs for the PHP extensions we compile + the 4 DB clients.
#  - libicu : intl + mbstring
#  - libpq  : PostgreSQL
#  - unixodbc + freetds : SQL Server (via pdo_dblib here ; we add sqlsrv via PECL too)
#  - libsqlite3-dev : for pdo_sqlite (storage DB)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        gnupg ca-certificates apt-transport-https \
        curl unzip git \
        netcat-openbsd \
        libicu-dev libzip-dev libxml2-dev libonig-dev \
        libpq-dev libsqlite3-dev \
        unixodbc-dev freetds-dev \
    && rm -rf /var/lib/apt/lists/*

# 3) Microsoft repo + msodbcsql18 (runtime driver for sqlsrv/pdo_sqlsrv).
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

# 4) Core PHP extensions via docker-php-ext-install.
RUN docker-php-ext-install -j"$(nproc)" \
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
        zip

# 5) PECL : phpredis (Laravel default Redis client) + sqlsrv/pdo_sqlsrv (MSSQL).
RUN pecl install redis sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable redis sqlsrv pdo_sqlsrv \
    && php -m | grep -iE 'redis|sqlsrv|pdo_sqlsrv' || (php -m && false)

# 6) PHP production config + TableFlip overrides (memory, opcache, JIT…).
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
COPY .docker/php.ini /usr/local/etc/php/conf.d/zz-tableflip.ini

# 7) Code + built artefacts (ownership = www-data, Apache's user).
WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=composer /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data --from=node /app/public/build /var/www/html/public/build

# 8) Bootstrap cache only — the rest of storage/ lives on the named volume
# and is created at boot by the entrypoint (avoids Docker pre-populating
# the volume from image content, which conflicts with Dokploy).
RUN mkdir -p bootstrap/cache \
    && chown -R www-data:www-data bootstrap/cache

# 9) Entrypoint : SQLite touch + redis wait + cache + migrate, then exec CMD.
COPY .docker/entrypoint.sh /usr/local/bin/tableflip-entrypoint
RUN chmod +x /usr/local/bin/tableflip-entrypoint

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/tableflip-entrypoint"]
CMD ["apache2-foreground"]
