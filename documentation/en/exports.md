---
title: Exports
order: 6
---

# Exports

TableFlip can produce three kinds of exports :

- **Per-table** : CSV, JSON (array or one-document-per-line), or a
  compact SQL file. Launched from the data view, with the current
  filters and sort applied, or driven by a custom SQL statement.
- **Database dump** : a full SQL file with structure and data,
  multiple tables, similar in spirit to a phpMyAdmin export.
- **From the SQL editor** : run a query and export its result set.

Exports run asynchronously in a background worker. The reader can
leave the page ; the result appears in the **Exports** list, with a
status (pending, running, completed, failed). A signed download link
becomes available once the export completes, and stays valid for
thirty minutes by default.

## Per-table export

Open the data view of a table and select **Export**. Then choose :

- **Format** : CSV (delimiter and header toggle), JSON (array or
  one document per line), or SQL (drop statement, minimal create,
  multi-row inserts)
- **Compression** : none, gzip, or zip
- **Filename template** : placeholders like `@DATABASE@`, `@TABLE@`,
  `@DATETIME@` and `@USER@` are substituted at the moment the export
  is generated

The export carries the current filters and sort. If the data view is
running on a custom SQL statement, that statement is used instead.

## Database dump

The database dump page exposes two modes :

- **Quick** : one click with sensible defaults. The output contains
  structure and data for every table, with a few safety options
  enabled (transaction wrapping, foreign-key checks disabled during
  import).
- **Custom** : a per-table grid with separate Structure and Data
  checkboxes, bulk actions to toggle a whole column at once, and six
  SQL options that adjust the output : DROP statements, IF NOT EXISTS
  guards, transaction wrapping, foreign-key check disable, header
  comment, and rows-per-insert batch size.

The dump adapts to the target database :

- MySQL and MariaDB use `AUTO_INCREMENT`, hexadecimal binary literals,
  and `SET FOREIGN_KEY_CHECKS`
- PostgreSQL uses `SERIAL` columns, binary `bytea` literals, and
  `session_replication_role` to bypass foreign keys
- SQL Server uses `IDENTITY(1,1)`, the `N'…'` prefix for Unicode
  strings, and `IF NOT EXISTS` guards through `sys.tables`
- SQLite uses inline `INTEGER PRIMARY KEY AUTOINCREMENT` and the
  `PRAGMA foreign_keys` pragma

## Retention

Generated files are removed after the configured retention period
(seven days by default). A scheduled command runs every night to
delete expired entries and their files. It accepts a dry-run mode
that prints what would be removed without touching anything.

## A note on direct mode

Asynchronous exports are available to **account users only**. The
worker has no credentials to replay a direct-database session.
Synchronous one-shot exports inside the data view work for every
user, regardless of the login mode.
