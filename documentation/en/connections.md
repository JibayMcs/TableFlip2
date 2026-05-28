---
title: Connections
order: 3
---

# Connections

A *connection* is a set of credentials TableFlip uses to reach a
database server : driver, host, port, username, password, and
optionally a database name. Account users save and reuse them ;
direct-database users work with a single connection valid for the
session.

## Adding a connection (account mode)

Open the connection switcher in the top bar and select **New
connection**. Fill the form :

- **Driver** : MySQL, MariaDB, PostgreSQL, SQL Server or SQLite
- **Host, port, username, password**
- **Database** (optional) : leave blank to list every database on the
  server at login time ; required for SQLite (file path)
- **Label** : a friendly name shown in the switcher

The **Test connection** button runs a single `SELECT version()` query
and displays the real database error message when it fails (DNS, auth,
connection refused, SSL, host-based access rules, and so on). Use it
before saving.

Passwords are encrypted at rest using the application key (`APP_KEY`).
Losing or rotating that key makes every saved password unreadable and
requires re-entering them.

## Switching between connections

The switcher in the top bar lists every saved connection, with the
active one highlighted. Switching is instant : no re-login, no page
reload.

## SSL and advanced options

The current release supports `sslmode=require` for PostgreSQL through
the connection URL. Full CA certificate, client certificate and key,
SSH tunneling, and connection-level pooling are planned for a later
release.

## Deletion

Deleting a connection removes it permanently from the storage
database, including the encrypted password. There is no recycle bin
and no audit trail for deletions.
