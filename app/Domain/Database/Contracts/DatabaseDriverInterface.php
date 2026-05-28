<?php

declare(strict_types=1);

namespace App\Domain\Database\Contracts;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Domain\Database\ValueObjects\ForeignKeyDefinition;
use App\Domain\Database\ValueObjects\IndexDefinition;
use App\Domain\Database\ValueObjects\QueryResult;
use App\Domain\Database\ValueObjects\TableIdentifier;
use Closure;

interface DatabaseDriverInterface
{
    public function getDriverName(): string;

    public function connectionConfig(): ConnectionConfig;

    public function ping(): bool;

    public function version(): string;

    /**
     * @return list<string>
     */
    public function listDatabases(): array;

    /**
     * @return list<string>
     */
    public function listSchemas(?string $database = null): array;

    /**
     * @return list<TableIdentifier>
     */
    public function listTables(?string $database = null, ?string $schema = null): array;

    /**
     * @return list<TableIdentifier>
     */
    public function listViews(?string $database = null, ?string $schema = null): array;

    /**
     * @return list<ColumnDefinition>
     */
    public function getColumns(TableIdentifier $table): array;

    /**
     * @return list<IndexDefinition>
     */
    public function getIndexes(TableIdentifier $table): array;

    /**
     * @return list<ForeignKeyDefinition>
     */
    public function getForeignKeys(TableIdentifier $table): array;

    /**
     * Fetch all columns of a database in one query (avoids N+1 on large
     * catalogs). The default impl loops over {@see getColumns}; drivers
     * override with a single INFORMATION_SCHEMA query.
     *
     * @return array<string, list<ColumnDefinition>>  key = strtolower("schema.table")
     */
    public function bulkColumns(string $database, ?string $schema = null): array;

    /**
     * Same idea for foreign keys.
     *
     * @return array<string, list<ForeignKeyDefinition>>  key = strtolower("schema.table")
     */
    public function bulkForeignKeys(string $database, ?string $schema = null): array;

    public function quoteIdentifier(string $identifier): string;

    public function qualify(TableIdentifier $table): string;

    /**
     * On-disk size (data + indexes) of every table/view in a database, in
     * bytes. Used by the Database Overview panel to show per-table weight.
     *
     * @return array<string, ?int>  lowercase table name → bytes (or null when unknown)
     */
    public function tableSizes(string $database, ?string $schema = null): array;

    /**
     * Cheap "fingerprint" of the visible schema — used by
     * {@see \App\Application\Schema\SchemaIndexService} to detect when its
     * cached index needs to be refreshed without paying for a full re-walk.
     *
     * Implementations SHOULD return a string that changes if a database is
     * added/removed or if a table is added/removed/renamed. Returning null
     * means "no cheap fingerprint available" — the index will only be
     * refreshed on explicit user request.
     */
    public function schemaSignature(): ?string;

    /**
     * Best-effort approximate row count, read from the engine's catalog/stats
     * (sys.dm_db_partition_stats on MSSQL, pg_class.reltuples on PostgreSQL,
     * information_schema.tables.TABLE_ROWS on MySQL/MariaDB). Returns null when
     * no estimate is available — the caller is expected to fall back to a
     * full COUNT(*).
     *
     * Estimates can be stale by several minutes; they are meant for UX
     * affordances ("≈281k rows") on huge tables where COUNT(*) would scan the
     * whole heap.
     */
    public function estimateRowCount(TableIdentifier $table): ?int;

    /**
     * Append the dialect-correct pagination suffix to a SELECT body.
     * Each driver decides whether to emit `LIMIT … OFFSET …` (MySQL/PG/SQLite)
     * or `OFFSET … ROWS FETCH NEXT … ROWS ONLY` (SQL Server, which also
     * requires an ORDER BY — the driver injects a stable one if missing).
     *
     * @param  string  $baseSql      everything before the ORDER BY
     * @param  string  $orderClause  the ORDER BY clause (with leading space) or empty
     */
    public function paginate(string $baseSql, string $orderClause, int $limit, int $offset): string;

    /**
     * @param  array<int|string, mixed>  $bindings
     */
    public function select(string $sql, array $bindings = []): QueryResult;

    /**
     * Yield rows one by one without buffering the whole result set in memory.
     * Used by exporters and any caller that needs to iterate a potentially
     * huge SELECT.
     *
     * @param  array<int|string, mixed>  $bindings
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamSelect(string $sql, array $bindings = []): \Generator;

    /**
     * @param  array<int|string, mixed>  $bindings
     */
    public function statement(string $sql, array $bindings = []): int;

    public function transaction(Closure $callback): mixed;

    /**
     * Run the callback with foreign-key enforcement temporarily disabled.
     * Used by TRUNCATE / DROP bulk actions when the user explicitly opts to
     * bypass FK checks (phpMyAdmin-style).
     *
     * Drivers that have no toggle (SQL Server requires per-FK NOCHECK) MUST
     * still run the callback — they may just leave the checks enforced and
     * let the error surface. The caller is expected to display the raw
     * driver error when the operation fails.
     */
    public function runWithoutForeignKeyChecks(Closure $callback): mixed;

    public function disconnect(): void;
}
