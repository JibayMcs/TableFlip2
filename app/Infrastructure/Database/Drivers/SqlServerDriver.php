<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Drivers;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ColumnType;
use App\Domain\Database\ValueObjects\ForeignKeyDefinition;
use App\Domain\Database\ValueObjects\IndexDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class SqlServerDriver extends AbstractDatabaseDriver
{
    public function getDriverName(): string
    {
        return 'sqlsrv';
    }

    public function version(): string
    {
        $row = $this->fetch('SELECT @@VERSION AS v');

        return (string) ($row[0]['v'] ?? '');
    }

    public function listDatabases(): array
    {
        // Skip system databases (database_id 1..4 = master/tempdb/model/msdb).
        $rows = $this->fetch(
            'SELECT name FROM sys.databases WHERE database_id > 4 ORDER BY name',
        );

        return array_values(array_map(static fn ($r) => (string) $r['name'], $rows));
    }

    public function listSchemas(?string $database = null): array
    {
        $rows = $this->fetch(
            "SELECT name FROM sys.schemas
             WHERE name NOT IN ('sys', 'guest', 'INFORMATION_SCHEMA', 'db_owner', 'db_accessadmin',
                                 'db_securityadmin', 'db_ddladmin', 'db_backupoperator',
                                 'db_datareader', 'db_datawriter', 'db_denydatareader', 'db_denydatawriter')
             ORDER BY name",
        );

        return array_values(array_map(static fn ($r) => (string) $r['name'], $rows));
    }

    public function listTables(?string $database = null, ?string $schema = null): array
    {
        return $this->fetchTables($schema, 'BASE TABLE');
    }

    public function listViews(?string $database = null, ?string $schema = null): array
    {
        return $this->fetchTables($schema, 'VIEW');
    }

    public function getColumns(TableIdentifier $table): array
    {
        $schema = $table->schema ?? 'dbo';
        $rows = $this->fetch(
            "SELECT c.COLUMN_NAME,
                    c.DATA_TYPE,
                    c.IS_NULLABLE,
                    c.COLUMN_DEFAULT,
                    c.CHARACTER_MAXIMUM_LENGTH,
                    c.NUMERIC_PRECISION,
                    c.NUMERIC_SCALE,
                    COLUMNPROPERTY(OBJECT_ID(c.TABLE_SCHEMA + '.' + c.TABLE_NAME), c.COLUMN_NAME, 'IsIdentity') AS is_identity,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                        JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                          ON kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                         AND kcu.TABLE_SCHEMA = tc.TABLE_SCHEMA
                        WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
                          AND tc.TABLE_SCHEMA = c.TABLE_SCHEMA
                          AND tc.TABLE_NAME = c.TABLE_NAME
                          AND kcu.COLUMN_NAME = c.COLUMN_NAME
                    ) THEN 1 ELSE 0 END AS is_primary
             FROM INFORMATION_SCHEMA.COLUMNS c
             WHERE c.TABLE_SCHEMA = ? AND c.TABLE_NAME = ?
             ORDER BY c.ORDINAL_POSITION",
            [$schema, $table->name],
        );

        return array_values(array_map(function (array $r) {
            $raw = (string) $r['DATA_TYPE'];

            return new ColumnDefinition(
                name: (string) $r['COLUMN_NAME'],
                rawType: $raw,
                type: $this->normalizeType($raw),
                nullable: strtoupper((string) $r['IS_NULLABLE']) === 'YES',
                default: $r['COLUMN_DEFAULT'],
                autoIncrement: (int) $r['is_identity'] === 1,
                isPrimaryKey: (int) $r['is_primary'] === 1,
                length: isset($r['CHARACTER_MAXIMUM_LENGTH']) ? (int) $r['CHARACTER_MAXIMUM_LENGTH'] : null,
                precision: isset($r['NUMERIC_PRECISION']) ? (int) $r['NUMERIC_PRECISION'] : null,
                scale: isset($r['NUMERIC_SCALE']) ? (int) $r['NUMERIC_SCALE'] : null,
            );
        }, $rows));
    }

    public function getIndexes(TableIdentifier $table): array
    {
        $schema = $table->schema ?? 'dbo';
        $rows = $this->fetch(
            'SELECT i.name AS index_name,
                    c.name AS column_name,
                    i.is_unique,
                    i.is_primary_key,
                    ic.key_ordinal
             FROM sys.indexes i
             JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
             JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
             JOIN sys.tables t ON t.object_id = i.object_id
             JOIN sys.schemas s ON s.schema_id = t.schema_id
             WHERE s.name = ? AND t.name = ? AND i.name IS NOT NULL
             ORDER BY i.name, ic.key_ordinal',
            [$schema, $table->name],
        );

        $grouped = [];
        foreach ($rows as $r) {
            $name = (string) $r['index_name'];
            $grouped[$name] ??= [
                'columns' => [],
                'unique' => (bool) $r['is_unique'],
                'primary' => (bool) $r['is_primary_key'],
            ];
            $grouped[$name]['columns'][] = (string) $r['column_name'];
        }

        $indexes = [];
        foreach ($grouped as $name => $data) {
            $indexes[] = new IndexDefinition(
                name: $name,
                columns: $data['columns'],
                unique: $data['unique'],
                primary: $data['primary'],
            );
        }

        return $indexes;
    }

    public function getForeignKeys(TableIdentifier $table): array
    {
        $schema = $table->schema ?? 'dbo';
        $rows = $this->fetch(
            'SELECT fk.name AS name,
                    pc.name AS column_name,
                    rs.name AS ref_schema,
                    rt.name AS ref_table,
                    rc.name AS ref_column,
                    fk.update_referential_action_desc AS on_update,
                    fk.delete_referential_action_desc AS on_delete,
                    fkc.constraint_column_id
             FROM sys.foreign_keys fk
             JOIN sys.tables pt ON pt.object_id = fk.parent_object_id
             JOIN sys.schemas ps ON ps.schema_id = pt.schema_id
             JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
             JOIN sys.columns pc ON pc.object_id = pt.object_id AND pc.column_id = fkc.parent_column_id
             JOIN sys.tables rt ON rt.object_id = fk.referenced_object_id
             JOIN sys.schemas rs ON rs.schema_id = rt.schema_id
             JOIN sys.columns rc ON rc.object_id = rt.object_id AND rc.column_id = fkc.referenced_column_id
             WHERE ps.name = ? AND pt.name = ?
             ORDER BY fk.name, fkc.constraint_column_id',
            [$schema, $table->name],
        );

        $grouped = [];
        foreach ($rows as $r) {
            $name = (string) $r['name'];
            $grouped[$name] ??= [
                'columns' => [],
                'ref_columns' => [],
                'ref_table' => new TableIdentifier(
                    name: (string) $r['ref_table'],
                    schema: (string) $r['ref_schema'],
                ),
                'on_update' => $this->normalizeAction((string) $r['on_update']),
                'on_delete' => $this->normalizeAction((string) $r['on_delete']),
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

    /**
     * Lightweight signature : hash the list of user databases. Adding /
     * dropping a DB triggers a re-index. Per-DB table changes within
     * existing databases are NOT detected (would require N catalog queries
     * across DBs) — the user can hit "Reindex" manually after a schema
     * change inside a DB.
     */
    public function schemaSignature(): ?string
    {
        try {
            $rows = $this->fetch(
                'SELECT name FROM sys.databases WHERE database_id > 4 ORDER BY name',
            );
        } catch (\Throwable) {
            return null;
        }

        return md5(implode('|', array_map(static fn ($r) => (string) $r['name'], $rows)));
    }

    /**
     * Read the heap/clustered-index row count from sys.dm_db_partition_stats.
     * index_id IN (0, 1) covers heap and clustered indexes — summing them
     * across partitions gives the table's effective row count. This is
     * orders of magnitude faster than COUNT(*) on big tables.
     */
    public function estimateRowCount(TableIdentifier $table): ?int
    {
        $schema = $table->schema ?? 'dbo';
        $object = $this->quoteIdentifier($schema).'.'.$this->quoteIdentifier($table->name);

        try {
            $rows = $this->fetch(
                'SELECT CAST(SUM(p.row_count) AS BIGINT) AS estimate
                 FROM sys.dm_db_partition_stats p
                 WHERE p.object_id = OBJECT_ID(?) AND p.index_id IN (0, 1)',
                [$object],
            );
        } catch (\Throwable) {
            return null;
        }

        $value = $rows[0]['estimate'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }

    /**
     * One INFORMATION_SCHEMA query covers the whole database — saves N
     * round-trips on big catalogs (Sage SS2I tops 600 tables).
     */
    public function bulkColumns(string $database, ?string $schema = null): array
    {
        // Identify PKs first in one shot.
        $pkRows = $this->fetch(
            "SELECT tc.TABLE_SCHEMA, tc.TABLE_NAME, kcu.COLUMN_NAME
             FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                  ON kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                 AND kcu.TABLE_SCHEMA = tc.TABLE_SCHEMA
             WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
               AND tc.TABLE_CATALOG = ?
               ".($schema !== null ? "AND tc.TABLE_SCHEMA = ?" : ''),
            $schema !== null ? [$database, $schema] : [$database],
        );

        $pk = [];
        foreach ($pkRows as $r) {
            $pk[strtolower($r['TABLE_SCHEMA'].'.'.$r['TABLE_NAME']).':'.$r['COLUMN_NAME']] = true;
        }

        // All columns of all tables in one query, plus identity flag.
        $rows = $this->fetch(
            "SELECT c.TABLE_SCHEMA, c.TABLE_NAME, c.COLUMN_NAME, c.DATA_TYPE,
                    c.IS_NULLABLE, c.COLUMN_DEFAULT,
                    c.CHARACTER_MAXIMUM_LENGTH, c.NUMERIC_PRECISION, c.NUMERIC_SCALE,
                    COLUMNPROPERTY(OBJECT_ID(c.TABLE_SCHEMA + '.' + c.TABLE_NAME), c.COLUMN_NAME, 'IsIdentity') AS is_identity
             FROM INFORMATION_SCHEMA.COLUMNS c
             WHERE c.TABLE_CATALOG = ?
               ".($schema !== null ? "AND c.TABLE_SCHEMA = ?" : '')."
             ORDER BY c.TABLE_SCHEMA, c.TABLE_NAME, c.ORDINAL_POSITION",
            $schema !== null ? [$database, $schema] : [$database],
        );

        $out = [];
        foreach ($rows as $r) {
            $key = strtolower($r['TABLE_SCHEMA'].'.'.$r['TABLE_NAME']);
            $raw = (string) $r['DATA_TYPE'];

            $out[$key][] = new ColumnDefinition(
                name: (string) $r['COLUMN_NAME'],
                rawType: $raw,
                type: $this->normalizeType($raw),
                nullable: strtoupper((string) $r['IS_NULLABLE']) === 'YES',
                default: $r['COLUMN_DEFAULT'],
                autoIncrement: (int) ($r['is_identity'] ?? 0) === 1,
                isPrimaryKey: isset($pk[$key.':'.$r['COLUMN_NAME']]),
                length: isset($r['CHARACTER_MAXIMUM_LENGTH']) ? (int) $r['CHARACTER_MAXIMUM_LENGTH'] : null,
                precision: isset($r['NUMERIC_PRECISION']) ? (int) $r['NUMERIC_PRECISION'] : null,
                scale: isset($r['NUMERIC_SCALE']) ? (int) $r['NUMERIC_SCALE'] : null,
            );
        }

        return $out;
    }

    /**
     * All FKs of the database via sys.* catalogs in one query, grouped per
     * (parent_schema, parent_table) then per constraint name to rebuild
     * composite keys correctly.
     */
    public function bulkForeignKeys(string $database, ?string $schema = null): array
    {
        $rows = $this->fetch(
            'SELECT
                ps.name AS parent_schema, pt.name AS parent_table,
                fk.name AS fk_name,
                pc.name AS parent_column,
                rs.name AS ref_schema, rt.name AS ref_table, rc.name AS ref_column,
                fk.update_referential_action_desc AS on_update,
                fk.delete_referential_action_desc AS on_delete,
                fkc.constraint_column_id
             FROM sys.foreign_keys fk
             JOIN sys.tables pt ON pt.object_id = fk.parent_object_id
             JOIN sys.schemas ps ON ps.schema_id = pt.schema_id
             JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
             JOIN sys.columns pc ON pc.object_id = pt.object_id AND pc.column_id = fkc.parent_column_id
             JOIN sys.tables rt ON rt.object_id = fk.referenced_object_id
             JOIN sys.schemas rs ON rs.schema_id = rt.schema_id
             JOIN sys.columns rc ON rc.object_id = rt.object_id AND rc.column_id = fkc.referenced_column_id
             '.($schema !== null ? 'WHERE ps.name = ?' : '').'
             ORDER BY ps.name, pt.name, fk.name, fkc.constraint_column_id',
            $schema !== null ? [$schema] : [],
        );

        // Group rows of the same FK so composite keys come out as one entry.
        $grouped = [];
        foreach ($rows as $r) {
            $tableKey = strtolower($r['parent_schema'].'.'.$r['parent_table']);
            $fkName = (string) $r['fk_name'];
            $grouped[$tableKey][$fkName] ??= [
                'columns' => [],
                'ref_columns' => [],
                'ref_table' => new TableIdentifier(name: $r['ref_table'], schema: $r['ref_schema']),
                'on_update' => $this->normalizeAction((string) $r['on_update']),
                'on_delete' => $this->normalizeAction((string) $r['on_delete']),
            ];
            $grouped[$tableKey][$fkName]['columns'][] = (string) $r['parent_column'];
            $grouped[$tableKey][$fkName]['ref_columns'][] = (string) $r['ref_column'];
        }

        $out = [];
        foreach ($grouped as $tableKey => $byFk) {
            foreach ($byFk as $fkName => $data) {
                $out[$tableKey][] = new ForeignKeyDefinition(
                    name: $fkName,
                    columns: $data['columns'],
                    referencedTable: $data['ref_table'],
                    referencedColumns: $data['ref_columns'],
                    onUpdate: $data['on_update'],
                    onDelete: $data['on_delete'],
                );
            }
        }

        return $out;
    }

    /**
     * T-SQL pagination requires `OFFSET n ROWS FETCH NEXT m ROWS ONLY` AND an
     * ORDER BY. When the caller did not provide one we inject a deterministic
     * placeholder (`ORDER BY (SELECT NULL)`) which SQL Server accepts.
     */
    public function paginate(string $baseSql, string $orderClause, int $limit, int $offset): string
    {
        $order = $orderClause !== '' ? $orderClause : ' ORDER BY (SELECT NULL)';

        return $baseSql.$order." OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";
    }

    /**
     * @return list<TableIdentifier>
     */
    private function fetchTables(?string $schema, string $type): array
    {
        $schema ??= 'dbo';
        $rows = $this->fetch(
            'SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?
             ORDER BY TABLE_NAME',
            [$schema, $type],
        );

        return array_values(array_map(
            static fn (array $r) => new TableIdentifier(name: (string) $r['name'], schema: $schema),
            $rows,
        ));
    }

    private function normalizeAction(string $raw): ?string
    {
        return match (strtoupper($raw)) {
            'NO_ACTION', '' => null,
            'CASCADE' => 'CASCADE',
            'SET_NULL' => 'SET NULL',
            'SET_DEFAULT' => 'SET DEFAULT',
            default => $raw,
        };
    }

    private function normalizeType(string $raw): ColumnType
    {
        return match (strtolower($raw)) {
            'char', 'varchar', 'nchar', 'nvarchar' => ColumnType::STRING,
            'text', 'ntext' => ColumnType::TEXT,
            'tinyint', 'smallint', 'int', 'bigint' => ColumnType::INTEGER,
            'decimal', 'numeric', 'money', 'smallmoney' => ColumnType::DECIMAL,
            'float', 'real' => ColumnType::FLOAT,
            'bit' => ColumnType::BOOLEAN,
            'date' => ColumnType::DATE,
            'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset' => ColumnType::DATETIME,
            'time' => ColumnType::TIME,
            'timestamp', 'rowversion' => ColumnType::TIMESTAMP,
            'binary', 'varbinary', 'image' => ColumnType::BINARY,
            'uniqueidentifier' => ColumnType::UUID,
            'xml' => ColumnType::TEXT,
            default => ColumnType::OTHER,
        };
    }
}
