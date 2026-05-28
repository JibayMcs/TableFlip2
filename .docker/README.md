# TableFlip — Docker self-hosting

This folder ships everything you need to run TableFlip in production behind
a reverse proxy (Traefik / nginx / Caddy).

The image is built around [**FrankenPHP**](https://frankenphp.dev) : one
binary that serves HTTP + PHP without a separate nginx / php-fpm pair.
It's designed for Dokploy-style deployments where Traefik already handles
TLS in front.

## Contents

| File | Purpose |
|---|---|
| `Dockerfile` | Multi-stage build (composer → node → FrankenPHP runtime, ~250MB final image) |
| `Caddyfile` | FrankenPHP / Caddy routing config (HTTP on :8080) |
| `php.ini` | Production tuning : opcache + JIT, 512MB memory, 600s timeout |
| `entrypoint.sh` | DB wait → cache config/routes/views → migrate → exec CMD |
| `docker-compose.yml` | App + queue worker + scheduler + Redis (no DB, BYODB) |
| `.env.docker.example` | Environment variables template |

## Quickstart (local test)

```bash
# 1. Generate an APP_KEY locally (you'll keep it forever — losing it
#    invalidates every encrypted DB connection password).
php artisan key:generate --show

# 2. Copy the env template and fill it in.
cp .docker/.env.docker.example .env.docker
$EDITOR .env.docker

# 3. Build + run.
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d --build

# 4. Browse http://localhost:8080
```

The container will :
- wait up to 30 s for `DB_HOST:DB_PORT` to accept connections
- `php artisan migrate --force`
- cache config / routes / views / events
- start FrankenPHP on `:8080`

## Dokploy deployment

1. Create a new **Compose** app in Dokploy pointing at this repo's
   `.docker/docker-compose.yml`.
2. Paste the contents of `.env.docker.example` into the **Environment** tab
   and fill the placeholders (`APP_KEY`, `APP_URL`, `DB_*`).
3. Add Traefik routing in the UI :
   - host : `tableflip.yourdomain.tld`
   - entrypoint : `websecure`
   - cert resolver : whatever your Dokploy is set up to use
   - service port : `8080`
4. Deploy. Dokploy will build the image, pull Redis, start the 3 services
   (app / worker / scheduler).

## What's NOT in this stack

- **No nginx / Caddy in front** — Traefik or whatever your Dokploy uses
  handles TLS + routing.
- **No DB container** — bring your own MariaDB / PostgreSQL / MSSQL. The
  image already has `pdo_mysql + pdo_pgsql + pdo_dblib` so you can point
  `DB_CONNECTION` at any of those.
- **No mail server** — the default `MAIL_MAILER=log` writes mails to the
  container logs. Plug your SMTP via env vars when you need it.

## Sizing

For a typical 1-5 users deployment :
- App : 512 MB RAM, 0.5 CPU
- Worker : 256 MB, 0.25 CPU
- Scheduler : 128 MB, 0.1 CPU
- Redis : 64 MB, 0.1 CPU

Adjust upward when running parallel exports on huge databases.

## Updating

```bash
git pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker build --pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d
```

Migrations run automatically on container boot (set `MIGRATE_ON_BOOT=0`
in the env if you orchestrate them from a separate job).

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `FATAL: APP_KEY is not set.` at boot | `APP_KEY` missing from env. Generate one with `php artisan key:generate --show` |
| 500 + "No application encryption key has been specified" | Same as above — env not picked up by the container |
| Queue jobs stuck in `pending` | `worker` service crashed — check `docker logs <worker>` |
| Exports never complete | Worker not running, OR storage volume not writable. Check `docker exec <app> ls -la /app/storage/app/exports` |
| `connection refused` on the BYODB host | Wrong `DB_HOST` (must be reachable from the Docker network — `localhost` from the host machine becomes `host.docker.internal` from inside) |
