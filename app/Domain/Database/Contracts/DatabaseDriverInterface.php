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
     * @param  array<int|string, mixed>  $bindings
     */
    public function select(string $sql, array $bindings = []): QueryResult;

    /**
     * @param  array<int|string, mixed>  $bindings
     */
    public function statement(string $sql, array $bindings = []): int;

    public function transaction(Closure $callback): mixed;

    public function disconnect(): void;
}
