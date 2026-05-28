<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Drivers;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ColumnType;
use App\Domain\Database\ValueObjects\ForeignKeyDefinition;
use App\Domain\Database\ValueObjects\IndexDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class SqliteDriver extends AbstractDatabaseDriver
{
    public function getDriverName(): string
    {
        return 'sqlite';
    }

    public function version(): string
    {
        $row = $this->fetch('SELECT sqlite_version() AS v');

        return (string) ($row[0]['v'] ?? '');
    }

    /**
     * SQLite FK enforcement is per-connection via PRAGMA. Toggle it for the
     * callback's lifetime, then restore.
     */
    public function runWithoutForeignKeyChecks(\Closure $callback): mixed
    {
        $this->statement('PRAGMA foreign_keys = OFF');
        try {
            return $callback();
        } finally {
            try {
                $this->statement('PRAGMA foreign_keys = ON');
            } catch (\Throwable) {
            }
        }
    }

    public function listDatabases(): array
    {
        return [$this->connectionConfig()->database];
    }

    public function listTables(?string $database = null, ?string $schema = null): array
    {
        $rows = $this->fetch(
            "SELECT name FROM sqlite_master
             WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
             ORDER BY name",
        );

        return array_values(array_map(
            static fn (array $r) => new TableIdentifier(name: (string) $r['name']),
            $rows,
        ));
    }

    public function listViews(?string $database = null, ?string $schema = null): array
    {
        $rows = $this->fetch(
            "SELECT name FROM sqlite_master WHERE type = 'view' ORDER BY name",
        );

        return array_values(array_map(
            static fn (array $r) => new TableIdentifier(name: (string) $r['name']),
            $rows,
        ));
    }

    public function getColumns(TableIdentifier $table): array
    {
        $quoted = $this->quoteIdentifier($table->name);
        $rows = $this->fetch("PRAGMA table_info({$quoted})");

        // SQLite flags a column as AUTOINCREMENT in its CREATE TABLE statement,
        // which is the only authoritative source (sqlite_sequence is only
        // populated after the first INSERT).
        $tableSql = $this->fetchCreateSql($table->name);
        $hasAutoIncrement = $tableSql !== null && stripos($tableSql, 'AUTOINCREMENT') !== false;

        return array_values(array_map(function (array $r) use ($hasAutoIncrement) {
            $raw = (string) $r['type'];
            $isPk = ((int) $r['pk']) > 0;

            return new ColumnDefinition(
                name: (string) $r['name'],
                rawType: $raw,
                type: $this->normalizeType($raw),
                nullable: ((int) $r['notnull']) === 0,
                default: $r['dflt_value'],
                autoIncrement: $hasAutoIncrement && $isPk && stripos($raw, 'integer') !== false,
                isPrimaryKey: $isPk,
            );
        }, $rows));
    }

    private function fetchCreateSql(string $table): ?string
    {
        $rows = $this->fetch(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table],
        );

        return isset($rows[0]['sql']) ? (string) $rows[0]['sql'] : null;
    }

    public function getIndexes(TableIdentifier $table): array
    {
        $quoted = $this->quoteIdentifier($table->name);
        $indexList = $this->fetch("PRAGMA index_list({$quoted})");

        $indexes = [];
        foreach ($indexList as $idx) {
            $name = (string) $idx['name'];
            $info = $this->fetch('PRAGMA index_info('.$this->quoteIdentifier($name).')');
            $columns = array_map(static fn ($c) => (string) $c['name'], $info);

            $indexes[] = new IndexDefinition(
                name: $name,
                columns: $columns,
                unique: ((int) $idx['unique']) === 1,
                primary: ((string) ($idx['origin'] ?? '')) === 'pk',
            );
        }

        return $indexes;
    }

    public function getForeignKeys(TableIdentifier $table): array
    {
        $quoted = $this->quoteIdentifier($table->name);
        $rows = $this->fetch("PRAGMA foreign_key_list({$quoted})");

        $grouped = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $grouped[$id] ??= [
                'columns' => [],
                'ref_columns' => [],
                'ref_table' => new TableIdentifier(name: (string) $r['table']),
                'on_update' => $r['on_update'] ?: null,
                'on_delete' => $r['on_delete'] ?: null,
            ];
            $grouped[$id]['columns'][] = (string) $r['from'];
            $grouped[$id]['ref_columns'][] = (string) $r['to'];
        }

        $fks = [];
        foreach ($grouped as $id => $data) {
            $fks[] = new ForeignKeyDefinition(
                name: "fk_{$table->name}_{$id}",
                columns: $data['columns'],
                referencedTable: $data['ref_table'],
                referencedColumns: $data['ref_columns'],
                onUpdate: $data['on_update'],
                onDelete: $data['on_delete'],
            );
        }

        return $fks;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function normalizeType(string $raw): ColumnType
    {
        $lower = strtolower(trim($raw));

        return match (true) {
            $lower === '' => ColumnType::OTHER,
            str_contains($lower, 'int') => ColumnType::INTEGER,
            str_contains($lower, 'bool') => ColumnType::BOOLEAN,
            str_contains($lower, 'char'),
            str_contains($lower, 'clob'),
            str_contains($lower, 'text') => str_contains($lower, 'text') ? ColumnType::TEXT : ColumnType::STRING,
            str_contains($lower, 'blob') => ColumnType::BINARY,
            str_contains($lower, 'real'),
            str_contains($lower, 'floa'),
            str_contains($lower, 'doub') => ColumnType::FLOAT,
            str_contains($lower, 'dec'),
            str_contains($lower, 'num') => ColumnType::DECIMAL,
            str_contains($lower, 'datetime'),
            str_contains($lower, 'timestamp') => ColumnType::DATETIME,
            str_contains($lower, 'date') => ColumnType::DATE,
            str_contains($lower, 'time') => ColumnType::TIME,
            str_contains($lower, 'json') => ColumnType::JSON,
            str_contains($lower, 'uuid') => ColumnType::UUID,
            default => ColumnType::OTHER,
        };
    }
}
