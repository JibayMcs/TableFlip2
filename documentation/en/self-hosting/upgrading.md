---
title: Upgrading
order: 4
---

# Upgrading

## Upgrading the application

```bash
git pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker build --pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d
```

Database migrations run automatically on container start. Set
`MIGRATE_ON_BOOT=0` in the environment if migrations are orchestrated
by a separate one-shot job.

> The application key (`APP_KEY`) must remain the same across upgrades.
> Rotating it invalidates every stored connection password, and users
> have to re-enter them.

## Switching from SQLite to MariaDB or PostgreSQL

SQLite is suitable for a small deployment (a few simultaneous users,
a moderate number of stored connections). For larger setups, the
storage database can be switched to MariaDB or PostgreSQL.

```env
DB_CONNECTION=mysql
DB_HOST=database.example.com
DB_PORT=3306
DB_DATABASE=tableflip
DB_USERNAME=tableflip
DB_PASSWORD=…
```

1. Create an empty database on the target server (`CREATE DATABASE tableflip`).
2. Update the environment and redeploy.
3. The container runs the migrations on first start.

The application key can stay the same. Stored connections continue to
work ; only the storage location changes.

> Existing data is **not** migrated automatically. To preserve the
> audit log and stored connections from a previous SQLite deployment,
> export the SQLite file with `php artisan db:dump` first, then import
> the result into the new database.

## Volume safety

The storage volume holds the SQLite file, or the upload directory when
the storage database has been switched to a remote engine. Treat it
like any critical data : back up, snapshot or replicate it before any
risky operation.

The Redis volume holds the append-only file used by sessions, the
queue and the cache. Losing it logs every user out and drops any
in-flight job, but does not affect persistent data. It can be removed
without further consequences.

## When an upgrade fails

The [Troubleshooting](/docs/self-hosting/troubleshooting) page
documents the common signatures observed on first boot or after an
upgrade, and how to recover.
