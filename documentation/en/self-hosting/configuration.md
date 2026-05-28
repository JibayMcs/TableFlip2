---
title: Configuration
order: 3
---

# Configuration

Most TableFlip-specific settings live in `config/tableflip.php` and
read their default value from an environment variable. The file can be
overridden in a custom build, but in a Docker deployment the
environment is the canonical source.

## Authentication

```php
'breeze_enabled' => env('AUTH_BREEZE_ENABLED', true),
'direct_db_enabled' => env('AUTH_DIRECT_DB_ENABLED', true),
'registration_enabled' => env('AUTH_REGISTRATION_ENABLED', false),
'require_db_name' => env('TABLEFLIP_REQUIRE_DB_NAME', false),
```

When both `breeze_enabled` and `direct_db_enabled` are enabled, the
login page displays two tabs. Disabling one hides its tab. Disabling
both makes the login form unreachable through a browser ; this is only
useful when access is granted through an API.

## Restricting the direct-database scope

```php
'hosts' => explode(',', env('TABLEFLIP_ALLOWED_DB_HOSTS', '')),
'drivers' => explode(',', env('TABLEFLIP_ALLOWED_DB_DRIVERS', '')),
'databases' => explode(',', env('TABLEFLIP_ALLOWED_DB_NAMES', '')),
```

Each list contains case-insensitive patterns (wildcards supported).
A list that contains **exactly one** value pre-fills and disables the
corresponding form field. This is the mechanism used to turn TableFlip
into a single-server alternative to phpMyAdmin : every field except
username and password is locked.

## Audit log

```php
'enabled' => env('TABLEFLIP_AUDIT_LOG_ENABLED', true),
```

When enabled, every write performed through the Explorer (insert,
update, delete, truncate, drop) is recorded in the audit log.
Disabling this option skips the recording on very write-heavy
deployments. The administrator browser at `/admin/audit` then has no
entries to display.

## Editing

```php
'bulk_confirm_threshold' => env('TABLEFLIP_BULK_OP_CONFIRM_THRESHOLD', 10),
```

A bulk delete affecting more rows than the threshold requires a typed
confirmation. A value of `0` requires confirmation on every bulk
delete, regardless of size.

## Exports

```php
'disk' => env('TABLEFLIP_EXPORTS_DISK', 'local'),
'retention_days' => env('TABLEFLIP_EXPORTS_RETENTION_DAYS', 7),
'download_url_ttl_minutes' => env('TABLEFLIP_EXPORTS_DOWNLOAD_TTL', 30),
```

The selected disk must exist in `config/filesystems.php`. The default
`local` disk writes to `storage/app/exports/` inside the container.
With the bundled Compose stack, that directory lives on the storage
volume and persists across restarts.

## Settings that do not live in `config/tableflip.php`

- The application key (`APP_KEY`) is part of `config/app.php`.
- Database connection details are in `config/database.php`.
- Cache, queue and session drivers are in `config/cache.php`,
  `config/queue.php`, and `config/session.php`.

All of these follow standard Laravel conventions.
