# Contributing to TableFlip

## Stack

- PHP 8.3+ / Laravel 13.x
- Livewire v4 (Alpine.js is bundled — **do not** `npm install alpinejs`)
- Tailwind CSS 4 via the official Vite plugin
- CodeMirror 6 for the SQL editor

## Architecture

TableFlip is layered. Respect the boundaries when adding code.

```
app/
├── Domain/           Pure business rules, value objects, contracts. No framework dependencies.
│   └── Database/
│       ├── Contracts/         Interfaces (e.g. DatabaseDriverInterface)
│       ├── ValueObjects/      Readonly DTOs (ConnectionConfig, ColumnDefinition, …)
│       └── Exceptions/        Domain-specific exceptions
├── Application/      Use cases, Actions, Commands. Orchestrates the Domain.
├── Infrastructure/   Concrete implementations. Talks to PDO, OS, filesystem, vendor SDKs.
│   └── Database/Drivers/      MySqlDriver, PostgreSqlDriver, SqliteDriver, SqlServerDriver
└── Livewire/         UI components. Thin. Calls Actions, never holds business logic.
```

Dependency direction: **UI → Application → Domain ← Infrastructure**.
The Domain never imports from Infrastructure or Livewire.

## Conventions

- **No god-components**: a Livewire component over ~300 LOC must be split.
- **No god-services**: prefer single-purpose Actions/Commands over service classes that grow forever.
- **No business logic in views or Blade components.**
- **No SQL dialect leakage outside `Infrastructure/Database/Drivers/`** — the Domain speaks in value objects, never raw SQL.
- **Value objects are `readonly` PHP 8.3+ classes**, not arrays.
- **Database credentials are always encrypted at rest** (Laravel `encrypted` cast).
- **One feature, one PR** — keep diffs reviewable.
