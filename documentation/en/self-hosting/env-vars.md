---
title: Environment variables
order: 2
---

# Environment variables

The variables below are read at container start. Most map to a key in
`config/tableflip.php` and can also be set there ; in a Docker
deployment, the environment is the canonical source.

## Required variables

| Variable | Purpose |
|---|---|
| `APP_KEY` | Base64 key used to encrypt stored connection passwords. Generated once with `php artisan key:generate --show`. **Losing it invalidates every stored connection.** |
| `APP_URL` | Public URL where TableFlip is served. Used in absolute links and in signed download URLs. |

## Application

| Variable | Default | Purpose |
|---|---|---|
| `APP_ENV` | `production` | Standard Laravel environment name. |
| `APP_DEBUG` | `false` | Must remain disabled in production. Otherwise stack traces and environment values are exposed on error pages. |
| `APP_TIMEZONE` | `UTC` | Time zone used in the audit log and the scheduler. |
| `LOG_CHANNEL` | `stderr` | Containers write logs to standard error so Docker collects them. |

## Storage database

| Variable | Default | Purpose |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | Driver used for TableFlip's own data : `sqlite`, `mysql`, `pgsql` or `sqlsrv`. |
| `DB_DATABASE` | `/var/www/html/storage/app/tableflip.sqlite` | Path to the SQLite file, or database name for the other drivers. |
| `DB_HOST` | — | Used when the driver is not SQLite. |
| `DB_PORT` | — | Used when the driver is not SQLite. |
| `DB_USERNAME` | — | Used when the driver is not SQLite. |
| `DB_PASSWORD` | — | Used when the driver is not SQLite. |

## Cache, queue and sessions

| Variable | Default | Purpose |
|---|---|---|
| `CACHE_STORE` | `redis` | Cache driver. |
| `SESSION_DRIVER` | `redis` | Session driver. Redis persistence is on, so sessions survive a container restart. |
| `QUEUE_CONNECTION` | `redis` | Driver used by asynchronous exports. |
| `REDIS_HOST` | `redis` | Hostname of the Redis service inside the Compose stack. |
| `REDIS_PORT` | `6379` | Redis port. |
| `REDIS_PASSWORD` | — | Empty for an unauthenticated local Redis. |

## Authentication

| Variable | Default | Purpose |
|---|---|---|
| `AUTH_BREEZE_ENABLED` | `true` | Display the **Account** tab on the login page. |
| `AUTH_DIRECT_DB_ENABLED` | `true` | Display the **Direct database** tab on the login page. |
| `AUTH_REGISTRATION_ENABLED` | `false` | Allow self-registration. The current release does not ship a public registration form ; the flag is kept for future use. |
| `TABLEFLIP_REQUIRE_DB_NAME` | `false` | Force the direct-database form to require a database name. |

## Restricting the direct-database form

Each list with **exactly one** value pre-fills and disables the
corresponding field on the login form. Comma-separated values leave
the field editable while restricting accepted values.

| Variable | Purpose |
|---|---|
| `TABLEFLIP_ALLOWED_DB_HOSTS` | Comma-separated allowlist of hostnames (wildcards supported). |
| `TABLEFLIP_ALLOWED_DB_DRIVERS` | Subset of `mysql`, `pgsql`, `sqlsrv`, `sqlite`. |
| `TABLEFLIP_ALLOWED_DB_NAMES` | Comma-separated allowlist of database names. |

## Audit log and editing

| Variable | Default | Purpose |
|---|---|---|
| `TABLEFLIP_AUDIT_LOG_ENABLED` | `true` | When disabled, write operations are not recorded in the audit table. |
| `TABLEFLIP_BULK_OP_CONFIRM_THRESHOLD` | `10` | A bulk delete above this row count requires a typed confirmation. |

## Exports

| Variable | Default | Purpose |
|---|---|---|
| `TABLEFLIP_EXPORTS_DISK` | `local` | Filesystem disk where generated files are stored. |
| `TABLEFLIP_EXPORTS_RETENTION_DAYS` | `7` | Days before expired exports are removed by the cleanup command. |
| `TABLEFLIP_EXPORTS_DOWNLOAD_TTL` | `30` | Validity of the signed download URL, in minutes. |

## Mail (optional)

The default value `MAIL_MAILER=log` writes outbound mail to standard
output. To send real messages, provide the usual Laravel SMTP
variables : `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.

## Container behaviour

| Variable | Default | Purpose |
|---|---|---|
| `MIGRATE_ON_BOOT` | `1` | When set to `0`, the container does not run database migrations at start. Useful when migrations are orchestrated by a separate one-shot job. |
