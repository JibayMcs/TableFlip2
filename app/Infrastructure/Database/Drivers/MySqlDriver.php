<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Drivers;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ColumnType;
use App\Domain\Database\ValueObjects\ForeignKeyDefinition;
use App\Domain\Database\ValueObjects\IndexDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class MySqlDriver extends AbstractDatabaseDriver
{
    private const SYSTEM_DATABASES = ['information_schema', 'mysql', 'performance_schema', 'sys'];

    public function getDriverName(): string
    {
        return $this->connectionConfig()->driver;
    }

    public function version(): string
    {
        $row = $this->fetch('SELECT VERSION() AS v');

        return (string) ($row[0]['v'] ?? '');
    }

    public function listDatabases(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::SYSTEM_DATABASES), '?'));
        $rows = $this->fetch(
            "SELECT SCHEMA_NAME AS name FROM INFORMATION_SCHEMA.SCHEMATA
             WHERE SCHEMA_NAME NOT IN ({$placeholders}) ORDER BY SCHEMA_NAME",
            self::SYSTEM_DATABASES,
        );

        return array_values(array_map(static fn ($r) => (string) $r['name'], $rows));
    }

    public function listTables(?string $database = null, ?string $schema = null): array
    {
        return $this->fetchTables($database, 'BASE TABLE');
    }

    public function listViews(?string $database = null, ?string $schema = null): array
    {
        return $this->fetchTables($database, 'VIEW');
    }

    public function getColumns(TableIdentifier $table): array
    {
        $database = $table->database ?? $this->connectionConfig()->database;
        $rows = $this->fetch(
            "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                    EXTRA, COLUMN_KEY, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_COMMENT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION",
            [$database, $table->name],
        );

        return array_values(array_map(function (array $r) {
            $rawType = (string) $r['DATA_TYPE'];
            $columnType = (string) $r['COLUMN_TYPE'];

            return new ColumnDefinition(
                name: (string) $r['COLUMN_NAME'],
                rawType: $columnType,
                type: $this->normalizeType($rawType, $columnType),
                nullable: strtoupper((string) $r['IS_NULLABLE']) === 'YES',
                default: $r['COLUMN_DEFAULT'],
                autoIncrement: str_contains(strtolower((string) $r['EXTRA']), 'auto_increment'),
                isPrimaryKey: (string) $r['COLUMN_KEY'] === 'PRI',
                length: isset($r['CHARACTER_MAXIMUM_LENGTH']) ? (int) $r['CHARACTER_MAXIMUM_LENGTH'] : null,
                precision: isset($r['NUMERIC_PRECISION']) ? (int) $r['NUMERIC_PRECISION'] : null,
                scale: isset($r['NUMERIC_SCALE']) ? (int) $r['NUMERIC_SCALE'] : null,
                enumValues: $rawType === 'enum' ? $this->parseEnumValues($columnType) : null,
                comment: ($c = (string) $r['COLUMN_COMMENT']) !== '' ? $c : null,
            );
        }, $rows));
    }

    public function getIndexes(TableIdentifier $table): array
    {
        $database = $table->database ?? $this->connectionConfig()->database;
        $rows = $this->fetch(
            'SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, SEQ_IN_INDEX
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$database, $table->name],
        );

        $grouped = [];
        foreach ($rows as $r) {
            $name = (string) $r['INDEX_NAME'];
            $grouped[$name] ??= ['columns' => [], 'unique' => ((int) $r['NON_UNIQUE']) === 0];
            $grouped[$name]['columns'][] = (string) $r['COLUMN_NAME'];
        }

        $indexes = [];
        foreach ($grouped as $name => $data) {
            $indexes[] = new IndexDefinition(
                name: $name,
                columns: $data['columns'],
                unique: $data['unique'],
                primary: $name === 'PRIMARY',
            );
        }

        return $indexes;
    }

    public function getForeignKeys(TableIdentifier $table): array
    {
        $database = $table->database ?? $this->connectionConfig()->database;
        $rows = $this->fetch(
            'SELECT rc.CONSTRAINT_NAME AS name,
                    kcu.COLUMN_NAME AS column_name,
                    kcu.REFERENCED_TABLE_SCHEMA AS ref_schema,
                    kcu.REFERENCED_TABLE_NAME AS ref_table,
                    kcu.REFERENCED_COLUMN_NAME AS ref_column,
                    rc.UPDATE_RULE AS on_update,
                    rc.DELETE_RULE AS on_delete,
                    kcu.ORDINAL_POSITION
             FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                  ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                 AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = ? AND kcu.TABLE_NAME = ?
             ORDER BY rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION',
            [$database, $table->name],
        );

        $grouped = [];
        foreach ($rows as $r) {
            $name = (string) $r['name'];
            $grouped[$name] ??= [
                'columns' => [],
                'ref_columns' => [],
                'ref_table' => new TableIdentifier(
                    name: (string) $r['ref_table'],
                    database: (string) $r['ref_schema'],
                ),
                'on_update' => $r['on_update'] ? (string) $r['on_update'] : null,
                'on_delete' => $r['on_delete'] ? (string) $r['on_delete'] : null,
            ];
            $grouped[$name]['columns'][] = (string) $r['column_name'];
            $grouped[$name]['ref_columns'][] = (string) $r['ref_column'];
        }

        $fks = [];
        foreach ($grouped as $name => $data) {
            $fks[] = new ForeignKeyDefinition(
                name: $name,
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
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    /**
     * @return list<TableIdentifier>
     */
    private function fetchTables(?string $database, string $type): array
    {
        $database ??= $this->connectionConfig()->database;
        $rows = $this->fetch(
            'SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?
             ORDER BY TABLE_NAME',
            [$database, $type],
        );

        return array_values(array_map(
            static fn (array $r) => new TableIdentifier(name: (string) $r['name'], database: $database),
            $rows,
        ));
    }

    private function normalizeType(string $dataType, string $columnType): ColumnType
    {
        return match (strtolower($dataType)) {
            'char', 'varchar' => ColumnType::STRING,
            'tinytext', 'text', 'mediumtext', 'longtext' => ColumnType::TEXT,
            'tinyint' => str_contains($columnType, '(1)') ? ColumnType::BOOLEAN : ColumnType::INTEGER,
            'smallint', 'mediumint', 'int', 'integer', 'bigint' => ColumnType::INTEGER,
            'decimal', 'numeric' => ColumnType::DECIMAL,
            'float', 'double', 'real' => ColumnType::FLOAT,
            'bit', 'boolean', 'bool' => ColumnType::BOOLEAN,
            'date' => ColumnType::DATE,
            'datetime', 'year' => ColumnType::DATETIME,
            'timestamp' => ColumnType::TIMESTAMP,
            'time' => ColumnType::TIME,
            'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob' => ColumnType::BINARY,
            'json' => ColumnType::JSON,
            'enum' => ColumnType::ENUM,
            'set' => ColumnType::ARRAY,
            default => ColumnType::OTHER,
        };
    }

    /**
     * @return list<string>
     */
    private function parseEnumValues(string $columnType): array
    {
        if (! preg_match('/^enum\((.+)\)$/i', $columnType, $m)) {
            return [];
        }

        $values = [];
        foreach (str_getcsv($m[1], ',', "'") as $v) {
            $values[] = (string) $v;
        }

        return $values;
    }
}
