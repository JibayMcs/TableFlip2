<?php

declare(strict_types=1);

namespace App\Application\Schema;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ForeignKeyDefinition;
use App\Domain\Database\ValueObjects\IndexDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

/**
 * Thin facade over a DatabaseDriverInterface that memoises introspection
 * calls per *request*. Multiple components within the same render cycle can
 * call the same lookups without paying for repeated INFORMATION_SCHEMA hits.
 */
class SchemaIntrospectionService
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /**
     * @return list<string>
     */
    public function databases(DatabaseDriverInterface $driver): array
    {
        return $this->cached('dbs:'.spl_object_id($driver), fn () => $driver->listDatabases());
    }

    /**
     * @return list<TableIdentifier>
     */
    public function tables(DatabaseDriverInterface $driver, ?string $database = null, ?string $schema = null): array
    {
        $key = "tables:".spl_object_id($driver).":{$database}:{$schema}";

        return $this->cached($key, fn () => $driver->listTables($database, $schema));
    }

    /**
     * @return list<TableIdentifier>
     */
    public function views(DatabaseDriverInterface $driver, ?string $database = null, ?string $schema = null): array
    {
        $key = "views:".spl_object_id($driver).":{$database}:{$schema}";

        return $this->cached($key, fn () => $driver->listViews($database, $schema));
    }

    /**
     * @return array{columns: list<ColumnDefinition>, indexes: list<IndexDefinition>, foreignKeys: list<ForeignKeyDefinition>}
     */
    public function tableDetail(DatabaseDriverInterface $driver, TableIdentifier $table): array
    {
        $key = "detail:".spl_object_id($driver).":{$table}";

        return $this->cached($key, fn () => [
            'columns' => $driver->getColumns($table),
            'indexes' => $driver->getIndexes($table),
            'foreignKeys' => $driver->getForeignKeys($table),
        ]);
    }

    /**
     * COUNT(*) on the given table. Not memoised — callers decide when to
     * pay this cost (it can be slow on large tables).
     */
    public function rowCount(DatabaseDriverInterface $driver, TableIdentifier $table): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM '.$driver->qualify($table);
        $result = $driver->select($sql);
        $rows = is_array($result->rows) ? $result->rows : iterator_to_array($result->rows);

        return (int) ($rows[0]['c'] ?? 0);
    }

    private function cached(string $key, callable $factory): mixed
    {
        return $this->cache[$key] ??= $factory();
    }
}
