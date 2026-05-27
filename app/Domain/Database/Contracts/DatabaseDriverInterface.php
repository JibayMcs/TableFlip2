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

    public function quoteIdentifier(string $identifier): string;

    public function qualify(TableIdentifier $table): string;

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

    public function disconnect(): void;
}
