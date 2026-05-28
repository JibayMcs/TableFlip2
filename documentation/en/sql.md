---
title: SQL editor
order: 5
---

# SQL editor

The SQL editor is a multi-tab CodeMirror surface with
dialect-aware syntax highlighting, schema autocompletion, and a query
history side panel.

## Running queries

Three keyboard shortcuts cover the common interactions :

- **Ctrl + Enter** (or Cmd + Enter on macOS) runs the current statement
- **Ctrl + /** (or Cmd + /) toggles a comment on the current selection
- **Ctrl + Space** opens autocompletion manually ; it also appears
  automatically while typing

A small badge below the editor displays the SQL dialect TableFlip is
talking to (MySQL, MariaDB, PostgreSQL, SQL Server or SQLite),
based on the database selected above the editor.

## Confirmation on destructive queries

A confirmation dialog appears when the editor detects a statement
that could damage data without a clear scope :

- `DELETE FROM …` without a `WHERE` clause
- `UPDATE …` without a `WHERE` clause
- `TRUNCATE`, `DROP`, `ALTER`, `RENAME`

The dialog shows the pattern that triggered the check and the full
statement. The reader must type `CONFIRM` to proceed. This is the
same safeguard used by the bulk-delete dialog in the Explorer.

## Query history

A side panel lists previously executed queries for the current user,
most recent first. Identical statements run back-to-back are deduped
(the timestamp is updated rather than a new line being added). Click
an entry to load it into the active tab. A close icon on each row
deletes that single entry ; a clear-all action in the panel header
removes them all.

## SQL inside the Explorer

The same editor is embedded in the data view of the Explorer. When
a custom SQL statement drives the table, the sort, filter and
pagination controls are hidden, and a banner offers a single click
to return to the filtered view.
