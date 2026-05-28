# TableFlip — Docker self-hosting

This folder ships everything you need to run TableFlip in production
behind a reverse proxy (Traefik / nginx / Caddy).

The image is **Apache + mod_php on `php:8.3-apache` (Debian Bookworm)** —
the same battle-tested stack our sibling Cerise project runs in this
infra. Listens on port 80 behind Traefik (TLS terminated upstream).

## Default stack — zero external services

| Service | Purpose | Why |
|---|---|---|
| `app` | Apache + mod_php serving on `:80` | The actual TableFlip web app |
| `worker` | `php artisan queue:work` | Async export jobs |
| `scheduler` | `php artisan schedule:work` | Daily cleanup-exports |
| `redis` | queue + cache + sessions | Hot paths, avoids disk I/O on SQLite |

**Storage DB = SQLite** by default — one file in the `app-storage`
volume, no external MariaDB/Postgres needed. SQLite holds the cool
state (users, saved connections, audit log, exports metadata) ; Redis
handles every high-frequency write so the SQLite file stays cool.

If you need multi-instance / HA later, swap SQLite for MariaDB or
Postgres — see [Upgrading the storage DB](#upgrading-the-storage-db).

## Contents

| File | Purpose |
|---|---|
| `Dockerfile` | Multi-stage build (composer → node → Apache+mod_php runtime) |
| `php.ini` | Production tuning : opcache + JIT, 512 MB memory, 600 s timeout |
| `entrypoint.sh` | Creates SQLite file → wait Redis → cache config/routes/views → migrate → exec CMD |
| `docker-compose.yml` | The 4-service stack above |
| `.env.docker.example` | Environment variables template |

## Quickstart (local test)

```bash
# 1. Generate an APP_KEY locally (you'll keep it forever — losing it
#    invalidates every encrypted DB connection password).
php artisan key:generate --show

# 2. Copy the env template and fill it in.
cp .docker/.env.docker.example .env.docker
$EDITOR .env.docker      # paste your APP_KEY and APP_URL

# 3. Build + run.
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d --build

# 4. Browse http://localhost:8080
```

The `app` container will :
- create `/var/www/html/storage/app/tableflip.sqlite` if missing (first run)
- wait up to 30 s for Redis
- `php artisan migrate --force`
- cache config / routes / views / events
- start Apache on `:80`

## Dokploy deployment

1. Create a new **Compose** app in Dokploy pointing at this repo's
   `.docker/docker-compose.yml`.
2. Paste the contents of `.env.docker.example` into the **Environment**
   tab and fill the placeholders (`APP_KEY`, `APP_URL`).
3. Add Traefik routing in the UI :
   - host : `tableflip.yourdomain.tld`
   - entrypoint : `websecure`
   - cert resolver : whatever your Dokploy is set up to use
   - service port : `80`
4. Deploy. Dokploy will build the image, pull Redis, start the 4
   services.

## Using TableFlip as a phpMyAdmin replacement

If you only want PMA-style direct-DB logins (no account system), add
these to your `.env.docker` :

```
AUTH_BREEZE_ENABLED=false
AUTH_DIRECT_DB_ENABLED=true
# Optional : lock the form so the user only fills username + password.
# Each list with EXACTLY ONE value pre-fills + disables that field.
TABLEFLIP_ALLOWED_DB_HOSTS=mariadb.example.com
TABLEFLIP_ALLOWED_DB_DRIVERS=mysql
TABLEFLIP_ALLOWED_DB_NAMES=mydb
TABLEFLIP_REQUIRE_DB_NAME=true
```

The login page will hide the account tab and show only the direct-DB
form.

## What's NOT in this stack

- **No nginx / Caddy in front** — Traefik (or whatever Dokploy uses)
  handles TLS + routing ; Apache serves :80 inside the container.
- **No bundled MariaDB/Postgres** — TableFlip stores its own data in
  SQLite. Browse any MariaDB/Postgres/MSSQL/SQLite by adding it at
  runtime via the Connections UI or the direct-DB login.
- **No mail server** — the default `MAIL_MAILER=log` writes mails to
  the container logs. Plug your SMTP via env vars when you need it.

## Upgrading the storage DB

SQLite is fine for the typical 1-5 user self-hosted setup. If you
outgrow it (multi-instance behind a load balancer, hundreds of saved
connections, etc.) :

```
DB_CONNECTION=mysql
DB_HOST=mariadb.example.com
DB_PORT=3306
DB_DATABASE=tableflip
DB_USERNAME=tableflip
DB_PASSWORD=…
```

`CREATE DATABASE tableflip` once, redeploy, the entrypoint runs the
migrations on first boot. You can keep using the same APP_KEY — your
saved connections will keep working.

## Sizing

| Service | RAM | CPU |
|---|---|---|
| App | 512 MB | 0.5 |
| Worker | 256 MB | 0.25 |
| Scheduler | 128 MB | 0.1 |
| Redis | 64 MB | 0.1 |

Bump the app + worker when running parallel exports on huge databases.

## Updating

```bash
git pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker build --pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d
```

Migrations run automatically on container boot (set
`MIGRATE_ON_BOOT=0` in the env if you orchestrate them from a separate
job).

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `FATAL: APP_KEY is not set.` at boot | `APP_KEY` missing from env. Generate one with `php artisan key:generate --show` |
| 500 + "No application encryption key has been specified" | Same — env not picked up by the container |
| Queue jobs stuck in `pending` | `worker` service crashed — check `docker logs <worker>` |
| Exports never complete | Worker not running, OR storage volume not writable. Check `docker exec <app> ls -la /var/www/html/storage/app/exports` |
| SQLite file readable but writes fail | Volume created with root ownership — entrypoint should chown to `www-data` on every boot. Check `docker exec <app> ls -la /var/www/html/storage/app/tableflip.sqlite` |
| Redis connection refused | The `redis` service is down or the `REDIS_PASSWORD` env doesn't match |
| `connection refused` from the direct-DB login | Wrong host (must be reachable from the Docker network — `localhost` from the host machine becomes `host.docker.internal` from inside the container) |
