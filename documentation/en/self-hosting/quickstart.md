---
title: Quickstart
order: 1
---

# Quickstart

## Running a local test

The repository ships a Compose file and an environment template. The
sequence below starts a complete TableFlip stack on the local machine.

```bash
# 1. Generate an application key. Keep it for the lifetime of the
#    deployment ; losing it invalidates every stored connection password.
php artisan key:generate --show

# 2. Copy the environment template and fill in the placeholders.
cp .docker/.env.docker.example .env.docker
$EDITOR .env.docker

# 3. Build and start the stack.
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d --build

# 4. Open the application.
#    The container listens on port 80 inside the Docker network.
```

The application container performs the following steps on first boot :

- creates the SQLite storage file if it does not exist
- waits for Redis to become reachable (up to thirty seconds)
- runs database migrations
- caches configuration, routes, views and event listeners
- starts Apache on port 80

## Deploying with Dokploy

1. Create a new **Compose** application in Dokploy and point it at the
   `.docker/docker-compose.yml` file in this repository.
2. Paste the contents of `.env.docker.example` in the **Environment**
   tab and fill in the placeholders. `APP_KEY` and `APP_URL` are
   required.
3. Add a domain to the application :
   - choose the public hostname
   - set the **Container Port** to **80** (the Dokploy default of 3000
     does not match the TableFlip image)
   - enable HTTPS with Let's Encrypt or any other certificate provider
     supported by the installation
4. Trigger a deployment. Dokploy builds the image, pulls Redis and
   starts the four services.

> **Common first-time issue.** When the Compose stack uses a
> per-project default network, the reverse proxy does not see the
> Redis service and the application fails to connect. The shipped
> Compose file declares the project default as an external
> `dokploy-network` so every service is born on the shared network.
> Keep that section as it is.

## Using TableFlip as a phpMyAdmin replacement

When TableFlip is dedicated to a single database server, the login
form can be reduced to a username and password by locking the other
fields :

```env
AUTH_BREEZE_ENABLED=false
AUTH_DIRECT_DB_ENABLED=true

TABLEFLIP_ALLOWED_DB_HOSTS=database.example.com
TABLEFLIP_ALLOWED_DB_DRIVERS=mysql
TABLEFLIP_ALLOWED_DB_NAMES=production
TABLEFLIP_REQUIRE_DB_NAME=true
```

The login page displays the **username** and **password** fields only.
The host, driver and database fields are pre-filled and disabled.
