#!/bin/bash
# TableFlip — container entrypoint (Apache stack).
#
# Boot order :
#   0. Create the storage tree on the mounted volume (vol starts empty).
#   1. Fail fast if APP_KEY is missing (we never auto-generate one in
#      production — losing it would invalidate every encrypted DB password).
#   2. Touch the SQLite file if missing (Laravel refuses to open a missing
#      SQLite file even in "create on connect" mode).
#   3. Wait for the configured remote DB (skipped for SQLite) + Redis.
#   4. Clear then cache config / routes / events / views.
#   5. Run pending migrations (set MIGRATE_ON_BOOT=0 to skip).
#   6. storage:link + chown.
#   7. exec "$@" (apache2-foreground / queue:work / schedule:work).

set -e

APP_ROOT=/var/www/html

# 0. Storage tree on the volume — entrypoint creates these every boot so the
# named volume starts empty and Dokploy's volume init doesn't conflict with
# image-shipped storage content.
mkdir -p "$APP_ROOT"/storage/app/exports \
         "$APP_ROOT"/storage/app/public \
         "$APP_ROOT"/storage/app/private \
         "$APP_ROOT"/storage/framework/cache/data \
         "$APP_ROOT"/storage/framework/sessions \
         "$APP_ROOT"/storage/framework/views \
         "$APP_ROOT"/storage/logs

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set."
    echo "       Generate one once with 'php artisan key:generate --show'"
    echo "       and inject it as an environment variable (don't lose it !)."
    exit 1
fi

# 2. SQLite : create the file before migrations run.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -n "${DB_DATABASE:-}" ]; then
    sqlite_file="${DB_DATABASE}"
    sqlite_dir="$(dirname "$sqlite_file")"
    mkdir -p "$sqlite_dir"
    if [ ! -f "$sqlite_file" ]; then
        echo "Creating SQLite storage database at ${sqlite_file}"
        touch "$sqlite_file"
    fi
    chown www-data:www-data "$sqlite_file" 2>/dev/null || true
    chmod 0664 "$sqlite_file" 2>/dev/null || true
fi

# 3a. Remote DB wait — only when DB_HOST + DB_PORT are set (mysql / pgsql /
# sqlsrv). SQLite skips this entirely.
if [ "${DB_CONNECTION:-sqlite}" != "sqlite" ] && [ -n "${DB_HOST:-}" ] && [ -n "${DB_PORT:-}" ]; then
    echo "Waiting for ${DB_HOST}:${DB_PORT} (up to 30s)…"
    i=0
    while ! nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "WARN: ${DB_HOST}:${DB_PORT} did not respond in 30s — continuing anyway."
            break
        fi
        sleep 1
    done
fi

# 3b. Redis wait — sessions / queue / cache live there.
if [ -n "${REDIS_HOST:-}" ]; then
    echo "Waiting for ${REDIS_HOST}:${REDIS_PORT:-6379} (up to 30s)…"
    i=0
    while ! nc -z "$REDIS_HOST" "${REDIS_PORT:-6379}" 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "WARN: ${REDIS_HOST}:${REDIS_PORT:-6379} did not respond in 30s — continuing anyway."
            break
        fi
        sleep 1
    done
fi

cd "$APP_ROOT"

# 4. Cache. Clear first so previous-image caches don't poison the new one.
php artisan config:clear  >/dev/null 2>&1 || true
php artisan route:clear   >/dev/null 2>&1 || true
php artisan view:clear    >/dev/null 2>&1 || true
php artisan event:clear   >/dev/null 2>&1 || true

php artisan config:cache  >/dev/null
php artisan route:cache   >/dev/null
php artisan view:cache    >/dev/null
php artisan event:cache   >/dev/null

# 5. Migrate. Set MIGRATE_ON_BOOT=0 to skip (handy if you orchestrate
# migrations from a separate one-shot job).
if [ "${MIGRATE_ON_BOOT:-1}" = "1" ]; then
    php artisan migrate --force --no-interaction
fi

# 6. Storage symlink (idempotent) + ownership on the volume.
php artisan storage:link --force >/dev/null 2>&1 || true
chown -R www-data:www-data "$APP_ROOT"/storage "$APP_ROOT"/bootstrap/cache 2>/dev/null || true

exec "$@"
