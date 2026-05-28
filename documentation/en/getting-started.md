---
title: Getting started
order: 2
---

# Getting started

TableFlip offers two ways to sign in. Choose the one that matches how
the deployment was configured.

## Account mode

The default. An administrator creates an account, the user signs in
with that account and benefits from features that require persistent
storage :

- saved database connections, encrypted at rest and attached to the
  account
- asynchronous exports that keep running after a page refresh
- the same set of connections available from one session to the next

This is the recommended mode for daily use.

## Direct database mode

A login form similar to phpMyAdmin asks for **driver, host, port,
username and password**, and connects directly to the database server
without creating any TableFlip account. The session lasts as long as
the browser tab stays open.

This is the right mode for one-shot access or when no persistence is
needed.

In direct mode, asynchronous exports are not available — the worker
has no credentials to replay the session. Synchronous one-shot
exports, the explorer, the SQL editor and inline editing all work
the same way as in account mode.

## Restricting the login form

An administrator can pre-fill and lock the host, driver and database
fields through environment variables. When configured, the
direct-database form is reduced to **username and password** only.
This is convenient when TableFlip serves as a single-server
phpMyAdmin replacement. See the
[self-hosting quickstart](/docs/self-hosting/quickstart) for the
exact configuration.
