#!/bin/sh
# TableFlip — container entrypoint.
#
# Boot order :
#   1. Fail fast if APP_KEY is missing (we never auto-generate one in
#      production — losing it would invalidate every encrypted DB password).
#   2. Wait for the configured database to accept connections (best-effort,
#      30s timeout — if it never comes up we let Laravel surface the error).
#   3. Cache config / routes / events so the first request is fast.
#   4. Run pending migrations.
#   5. Hand over to whatever CMD was passed (web server / queue worker /
#      scheduler — we don't make assumptions).

set -e

# 0. Create the storage tree on the mounted volume. We don't ship these
# dirs in the image (would conflict with Dokploy's volume init), so the
# first boot has to bootstrap them.
mkdir -p /app/storage/app/exports \
         /app/storage/app/public \
         /app/storage/app/private \
         /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set."
    echo "       Generate one once with `php artisan key:generate --show`"
    echo "       and inject it as an environment variable (don't lose it !)."
    exit 1
fi

# 2a. SQLite : make sure the file exists before migrations run. Laravel
# refuses to open a missing SQLite file even in "create on connect" mode.
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

# 2b. Remote DB wait — only when DB_HOST / DB_PORT are set (mysql / pgsql /
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

# 2c. Redis wait — sessions / queue / cache live there, so it's worth
# making sure it's up before we start serving requests.
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

# 3. Cache. We *clear* first so previous-image caches don't poison the new one.
php artisan config:clear  >/dev/null 2>&1 || true
php artisan route:clear   >/dev/null 2>&1 || true
php artisan view:clear    >/dev/null 2>&1 || true
php artisan event:clear   >/dev/null 2>&1 || true

php artisan config:cache  >/dev/null
php artisan route:cache   >/dev/null
php artisan view:cache    >/dev/null
php artisan event:cache   >/dev/null

# 4. Migrate. Set MIGRATE_ON_BOOT=0 to skip (handy if you orchestrate
# migrations from a separate one-shot job).
if [ "${MIGRATE_ON_BOOT:-1}" = "1" ]; then
    php artisan migrate --force --no-interaction
fi

# Storage symlink — idempotent, so safe to run every boot.
php artisan storage:link >/dev/null 2>&1 || true

# Make sure storage / cache stay writable when Docker mounts a volume over
# them (volume created with root ownership).
chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true

exec "$@"
