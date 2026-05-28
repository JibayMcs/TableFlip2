---
title: Self-hosting
order: 10
---

# Self-hosting

TableFlip is distributed as a small Laravel application packaged with
a Docker Compose stack of four services. The application listens on
port 80 inside the container, behind a reverse proxy (such as Traefik,
nginx or Caddy) that terminates TLS in front.

## Stack overview

| Service | Role |
|---|---|
| Application | Serves the web interface on port 80 |
| Worker | Processes asynchronous jobs (exports) |
| Scheduler | Runs daily maintenance commands |
| Redis | Backs the queue, cache and sessions |

The storage database is **SQLite** by default : a single file kept on
a Docker volume, with no external database to provision. The queue,
cache and session backends use Redis, so the SQLite file stays
contention-free even under regular use.

For larger deployments, the storage database can be switched to
MariaDB or PostgreSQL — see [Upgrading](/docs/self-hosting/upgrading).

## What this stack does not include

- **No bundled reverse proxy.** TLS termination and routing are
  expected upstream. The application container simply serves HTTP on
  port 80.
- **No bundled MariaDB or PostgreSQL.** TableFlip stores its own
  data ; the databases that users browse are added at runtime through
  the Connections form or the direct login.
- **No bundled mail server.** The default configuration writes mail
  to the container logs. SMTP credentials can be provided through
  environment variables when needed.

## Where to deploy

The Compose stack runs on any Docker host that has a reverse proxy in
front. The [Quickstart](/docs/self-hosting/quickstart) covers a local
test run and a deployment through Dokploy.
