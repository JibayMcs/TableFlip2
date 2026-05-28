---
title: Explorer
order: 4
---

# Explorer

The Explorer is the main page for browsing and editing database
content. The left sidebar lists databases, tables and views ; the main
area shows the schema and the data of the selected table.

## Sidebar

Click a database name to expand it. Tables and views are loaded on
demand. The search field at the top matches a database name *or* any
table or view inside an expanded database. Collapsed databases are not
included in the search until they are expanded ; a small hint reminds
the reader when the search is active.

## Schema tab

The schema view lists every column with the following information :
primary-key marker, foreign-key marker, data type, nullability and
default value. The actions column on the right (truncate, drop) stays
visible during horizontal scrolling, which keeps wide tables usable.

## Data tab

The data view is read-only by default. Click a column header to sort,
use the **Filter** button to compose conditions locally, and paginate
at the bottom. For very large tables, the total row count is displayed
as an estimate (for example, "≈ 281,125 rows"). A button next to the
counter triggers an exact `COUNT(*)` on demand.

### Editing a cell

Click a cell to switch to edit mode. The input adapts to the column
type : date and time pickers for temporal types, number inputs for
numeric types, a NULL shortcut for nullable columns. **Save** issues
an `UPDATE` statement restricted to the row's primary key and records
the change in the audit log.

### Inserting and deleting rows

- **Add row** opens a form with one input per column. Required
  markers reflect `NOT NULL` columns.
- A delete button on each row removes that row after confirmation.
- Checkboxes on the left enable bulk selection. A confirmation
  dialog asks for a typed confirmation when the selection exceeds a
  configurable threshold (10 rows by default).

Every write operation is recorded in the audit log, available to
administrators at `/admin/audit`.

## Embedded SQL editor

The **SQL** button above the table opens an editor that drives the
current table. Run a `SELECT` query with custom clauses and the table
displays the result. A banner reminds the reader that the table is
running in custom mode, and a single click returns to the filtered
view.

## Working with wide tables

For schemas with hundreds of columns, two safeguards keep the page
responsive :

- empty columns are hidden automatically on each page
- string cells are truncated to 240 characters and marked as such ;
  clicking a truncated cell fetches the full value

A column picker above the table lets the reader toggle visibility for
individual columns. A search field inside the picker helps locate a
column quickly in a long list.
