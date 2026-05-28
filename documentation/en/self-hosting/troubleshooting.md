---
title: Troubleshooting
order: 5
---

# Troubleshooting

## Start-up failures

| Symptom | Likely cause and resolution |
|---|---|
| `FATAL: APP_KEY is not set.` in the log | The application key is missing from the environment. Generate one with `php artisan key:generate --show` and add it to the environment. |
| HTTP 500 with *No application encryption key has been specified* | Same cause : the environment is not being picked up by the container. Re-check that the environment file is loaded. |
| `Class "Redis" not found` from the worker | The phpredis PHP extension is missing from the image. Rebuild the image after pulling the latest changes. |
| The container logs `Waiting for redis…` for thirty seconds, then continues | The Redis service is not reachable on the Docker network. See *Network topology* below. |
| Migration fails with `permissions table already exists` | The storage SQLite file is in an inconsistent state, usually after a previously failed start. Stop the stack, remove the storage volume, then redeploy. |

## Runtime issues

| Symptom | Likely cause and resolution |
|---|---|
| Queue jobs stay in `pending` | The worker container has crashed. Inspect its log to find the underlying error. |
| Exports never complete | Either the worker is not running or the storage volume is not writable. Check the contents of `/var/www/html/storage/app/exports` from inside the application container. |
| SQLite is readable but writes fail | The volume was created with root ownership. The container start-up script fixes ownership on every boot ; check the ownership of `tableflip.sqlite` inside the volume to confirm. |
| Mixed-content warnings in the browser console | The application is generating `http://` asset URLs while served over HTTPS. Make sure `APP_URL` starts with `https://` and that the reverse proxy is trusted by the application. |

## Network topology

On some platforms (Dokploy in particular), the reverse proxy only sees
the containers that carry its routing labels on the shared network.
The Redis service does not carry such labels, so it is not added to
the shared network automatically. The result is that the application
cannot reach Redis.

The shipped Compose file makes the project's default network point to
the external proxy network, so every service is born on that network.
The relevant declaration looks like this :

```yaml
services:
  redis:
    # …
    networks:
      - dokploy-network
      - default

networks:
  default:
    external: true
    name: dokploy-network
  dokploy-network:
    external: true
```

If a customised Compose file dropped this section, recreate it.

## Direct database login fails

| Symptom | Likely cause and resolution |
|---|---|
| `connection refused` | The host is wrong. From inside a Docker container, `localhost` of the host machine is reached through `host.docker.internal`. |
| `getaddrinfo failed` | A DNS issue. Make sure the target database is on a network that the application container can resolve. |
| Authentication error | The credentials are wrong, or the database server's host-based access rules reject the source IP. |

When the test connection fails, the form displays the raw error
returned by the database driver. Reading it carefully usually points
directly at the cause.

## Inspecting logs

The following commands stream the logs of each service. Replace the
container names with those of the actual deployment (Docker tools
often expose them through `docker compose ps`).

```bash
# Application container
docker logs -f <application-container>

# Worker (queue:work)
docker logs -f <worker-container>

# Scheduler (schedule:work)
docker logs -f <scheduler-container>

# Redis
docker logs -f <redis-container>
```
