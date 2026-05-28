<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Drivers;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ColumnType;
use App\Domain\Database\ValueObjects\ForeignKeyDefinition;
use App\Domain\Database\ValueObjects\IndexDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class PostgreSqlDriver extends AbstractDatabaseDriver
{
    public function getDriverName(): string
    {
        return 'pgsql';
    }

    public function version(): string
    {
        $row = $this->fetch('SHOW server_version');

        return (string) ($row[0]['server_version'] ?? '');
    }

    public function listDatabases(): array
    {
        $rows = $this->fetch(
            'SELECT datname AS name FROM pg_database
             WHERE datistemplate = false AND datname NOT IN (?, ?)
             ORDER BY datname',
            ['postgres', 'template0'],
        );

        return array_values(array_map(static fn ($r) => (string) $r['name'], $rows));
    }

    public function listSchemas(?string $database = null): array
    {
        $rows = $this->fetch(
            "SELECT schema_name AS name FROM information_schema.schemata
             WHERE schema_name NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
               AND schema_name NOT LIKE 'pg_temp_%' AND schema_name NOT LIKE 'pg_toast_temp_%'
             ORDER BY schema_name",
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
        $schema = $table->schema ?? $this->defaultSchema();
        $rows = $this->fetch(
            'SELECT c.column_name,
                    c.udt_name AS raw_type,
                    c.data_type,
                    c.is_nullable,
                    c.column_default,
                    c.character_maximum_length,
                    c.numeric_precision,
                    c.numeric_scale,
                    pgd.description AS column_comment,
                    EXISTS (
                        SELECT 1 FROM information_schema.table_constraints tc
                        JOIN information_schema.key_column_usage kcu
                          ON kcu.constraint_name = tc.constraint_name
                         AND kcu.table_schema = tc.table_schema
                        WHERE tc.constraint_type = \'PRIMARY KEY\'
                          AND tc.table_schema = c.table_schema
                          AND tc.table_name = c.table_name
                          AND kcu.column_name = c.column_name
                    ) AS is_primary
             FROM information_schema.columns c
             LEFT JOIN pg_catalog.pg_statio_all_tables st
                    ON st.schemaname = c.table_schema AND st.relname = c.table_name
             LEFT JOIN pg_catalog.pg_description pgd
                    ON pgd.objoid = st.relid AND pgd.objsubid = c.ordinal_position
             WHERE c.table_schema = ? AND c.table_name = ?
             ORDER BY c.ordinal_position',
            [$schema, $table->name],
        );

        return array_values(array_map(function (array $r) {
            $raw = (string) $r['raw_type'];
            $default = $r['column_default'];

            return new ColumnDefinition(
                name: (string) $r['column_name'],
                rawType: $raw,
                type: $this->normalizeType($raw, (string) $r['data_type']),
                nullable: strtoupper((string) $r['is_nullable']) === 'YES',
                default: $default,
                autoIncrement: is_string($default) && str_starts_with($default, 'nextval('),
                isPrimaryKey: (bool) $r['is_primary'],
                length: isset($r['character_maximum_length']) ? (int) $r['character_maximum_length'] : null,
                precision: isset($r['numeric_precision']) ? (int) $r['numeric_precision'] : null,
                scale: isset($r['numeric_scale']) ? (int) $r['numeric_scale'] : null,
                comment: $r['column_comment'] ? (string) $r['column_comment'] : null,
            );
        }, $rows));
    }

    public function getIndexes(TableIdentifier $table): array
    {
        $schema = $table->schema ?? $this->defaultSchema();
        $rows = $this->fetch(
            'SELECT i.relname AS index_name,
                    a.attname AS column_name,
                    ix.indisunique AS is_unique,
                    ix.indisprimary AS is_primary,
                    array_position(ix.indkey, a.attnum) AS position
             FROM pg_index ix
             JOIN pg_class t ON t.oid = ix.indrelid
             JOIN pg_class i ON i.oid = ix.indexrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
             WHERE n.nspname = ? AND t.relname = ?
             ORDER BY i.relname, position',
            [$schema, $table->name],
        );

        $grouped = [];
        foreach ($rows as $r) {
            $name = (string) $r['index_name'];
            $grouped[$name] ??= [
                'columns' => [],
                'unique' => (bool) $r['is_unique'],
                'primary' => (bool) $r['is_primary'],
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
        $schema = $table->schema ?? $this->defaultSchema();
        $rows = $this->fetch(
            'SELECT tc.constraint_name AS name,
                    kcu.column_name,
                    kcu.ordinal_position,
                    ccu.table_schema AS ref_schema,
                    ccu.table_name AS ref_table,
                    ccu.column_name AS ref_column,
                    rc.update_rule AS on_update,
                    rc.delete_rule AS on_delete
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
                  ON kcu.constraint_name = tc.constraint_name
                 AND kcu.table_schema = tc.table_schema
             JOIN information_schema.referential_constraints rc
                  ON rc.constraint_name = tc.constraint_name
                 AND rc.constraint_schema = tc.table_schema
             JOIN information_schema.constraint_column_usage ccu
                  ON ccu.constraint_name = tc.constraint_name
                 AND ccu.constraint_schema = tc.table_schema
             WHERE tc.constraint_type = \'FOREIGN KEY\'
               AND tc.table_schema = ? AND tc.table_name = ?
             ORDER BY tc.constraint_name, kcu.ordinal_position',
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
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    /**
     * Setting `session_replication_role = replica` short-circuits ENABLED
     * triggers — including the implicit RI triggers that enforce FK
     * constraints. Reverts to `origin` after the callback.
     *
     * Requires the session role to be a superuser (or to own the tables);
     * non-privileged users will hit a 42501 — caller surfaces the raw error.
     */
    public function runWithoutForeignKeyChecks(\Closure $callback): mixed
    {
        $this->statement("SET session_replication_role = replica");
        try {
            return $callback();
        } finally {
            try {
                $this->statement("SET session_replication_role = origin");
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Per-relation size in bytes (heap + indexes + TOAST), via
     * pg_total_relation_size. Scoped to the requested schema (defaults to
     * the connection's search_path head).
     *
     * @return array<string, ?int>
     */
    public function tableSizes(string $database, ?string $schema = null): array
    {
        $schema ??= $this->defaultSchema();

        try {
            $rows = $this->fetch(
                "SELECT c.relname, pg_total_relation_size(c.oid)::BIGINT AS size_bytes
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = ? AND c.relkind IN ('r', 'v', 'm', 'p')",
                [$schema],
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $name = strtolower((string) $r['relname']);
            $out[$name] = isset($r['size_bytes']) ? (int) $r['size_bytes'] : null;
        }

        return $out;
    }

    /**
     * Signature on the connected database only — PG can't query other DBs
     * without reconnecting. The schema index service runs against the pool
     * key (one per saved connection), so signing the connected DB's schema
     * is enough to detect table-level changes that the user is likely to
     * notice. New databases on the cluster require manual reindex.
     */
    public function schemaSignature(): ?string
    {
        try {
            $rows = $this->fetch(
                "SELECT schemaname, COUNT(*) AS cnt
                 FROM pg_tables
                 WHERE schemaname NOT IN ('pg_catalog','information_schema')
                 GROUP BY schemaname
                 ORDER BY schemaname",
            );
            $dbRow = $this->fetch('SELECT current_database() AS db');
        } catch (\Throwable) {
            return null;
        }

        $pairs = array_map(static fn ($r) => $r['schemaname'].':'.$r['cnt'], $rows);

        return md5(($dbRow[0]['db'] ?? '').'|'.implode('|', $pairs));
    }

    /**
     * Bulk columns of a database in 1 query — used by ErdGenerator to dodge
     * N+1 on big schemas. Falls back to default per-table loop if the
     * caller doesn't pass a schema (search_path is per-connection on PG).
     */
    public function bulkColumns(string $database, ?string $schema = null): array
    {
        $schema ??= $this->defaultSchema();

        $rows = $this->fetch(
            'SELECT c.table_name, c.column_name, c.udt_name AS raw_type, c.data_type,
                    c.is_nullable, c.column_default, c.character_maximum_length,
                    c.numeric_precision, c.numeric_scale,
                    EXISTS (
                        SELECT 1 FROM information_schema.table_constraints tc
                        JOIN information_schema.key_column_usage kcu
                          ON kcu.constraint_name = tc.constraint_name
                         AND kcu.table_schema = tc.table_schema
                        WHERE tc.constraint_type = \'PRIMARY KEY\'
                          AND tc.table_schema = c.table_schema
                          AND tc.table_name = c.table_name
                          AND kcu.column_name = c.column_name
                    ) AS is_primary
             FROM information_schema.columns c
             WHERE c.table_schema = ?
             ORDER BY c.table_name, c.ordinal_position',
            [$schema],
        );

        $out = [];
        foreach ($rows as $r) {
            $key = strtolower($schema.'.'.$r['table_name']);
            $raw = (string) $r['raw_type'];
            $default = $r['column_default'];

            $out[$key][] = new ColumnDefinition(
                name: (string) $r['column_name'],
                rawType: $raw,
                type: $this->normalizeType($raw, (string) $r['data_type']),
                nullable: strtoupper((string) $r['is_nullable']) === 'YES',
                default: $default,
                autoIncrement: is_string($default) && str_starts_with($default, 'nextval('),
                isPrimaryKey: (bool) $r['is_primary'],
                length: isset($r['character_maximum_length']) ? (int) $r['character_maximum_length'] : null,
                precision: isset($r['numeric_precision']) ? (int) $r['numeric_precision'] : null,
                scale: isset($r['numeric_scale']) ? (int) $r['numeric_scale'] : null,
            );
        }

        return $out;
    }

    public function bulkForeignKeys(string $database, ?string $schema = null): array
    {
        $schema ??= $this->defaultSchema();

        $rows = $this->fetch(
            'SELECT tc.constraint_name AS name,
                    tc.table_name,
                    kcu.column_name,
                    kcu.ordinal_position,
                    ccu.table_schema AS ref_schema,
                    ccu.table_name AS ref_table,
                    ccu.column_name AS ref_column,
                    rc.update_rule AS on_update,
                    rc.delete_rule AS on_delete
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
                  ON kcu.constraint_name = tc.constraint_name
                 AND kcu.table_schema = tc.table_schema
             JOIN information_schema.referential_constraints rc
                  ON rc.constraint_name = tc.constraint_name
                 AND rc.constraint_schema = tc.table_schema
             JOIN information_schema.constraint_column_usage ccu
                  ON ccu.constraint_name = tc.constraint_name
                 AND ccu.constraint_schema = tc.table_schema
             WHERE tc.constraint_type = \'FOREIGN KEY\'
               AND tc.table_schema = ?
             ORDER BY tc.table_name, tc.constraint_name, kcu.ordinal_position',
            [$schema],
        );

        $grouped = [];
        foreach ($rows as $r) {
            $key = strtolower($schema.'.'.$r['table_name']);
            $fk = (string) $r['name'];
            $grouped[$key][$fk] ??= [
                'columns' => [],
                'ref_columns' => [],
                'ref_table' => new TableIdentifier(name: (string) $r['ref_table'], schema: (string) $r['ref_schema']),
                'on_update' => $r['on_update'] ? (string) $r['on_update'] : null,
                'on_delete' => $r['on_delete'] ? (string) $r['on_delete'] : null,
            ];
            $grouped[$key][$fk]['columns'][] = (string) $r['column_name'];
            $grouped[$key][$fk]['ref_columns'][] = (string) $r['ref_column'];
        }

        $out = [];
        foreach ($grouped as $key => $byFk) {
            foreach ($byFk as $fkName => $data) {
                $out[$key][] = new ForeignKeyDefinition(
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
     * Read pg_class.reltuples, the planner's row estimate. Refreshed by
     * ANALYZE / autovacuum; can be slightly stale but is instant.
     */
    public function estimateRowCount(TableIdentifier $table): ?int
    {
        $schema = $table->schema ?? $this->defaultSchema();

        try {
            $rows = $this->fetch(
                "SELECT reltuples::BIGINT AS estimate
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = ? AND c.relname = ?",
                [$schema, $table->name],
            );
        } catch (\Throwable) {
            return null;
        }

        $value = $rows[0]['estimate'] ?? null;

        // PG returns -1 for tables that have never been analyzed; treat that
        // as "no estimate available" rather than negative rows.
        if ($value === null || (int) $value < 0) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return list<TableIdentifier>
     */
    private function fetchTables(?string $schema, string $type): array
    {
        $schema ??= $this->defaultSchema();
        $rows = $this->fetch(
            'SELECT table_name AS name FROM information_schema.tables
             WHERE table_schema = ? AND table_type = ?
             ORDER BY table_name',
            [$schema, $type],
        );

        return array_values(array_map(
            static fn (array $r) => new TableIdentifier(name: (string) $r['name'], schema: $schema),
            $rows,
        ));
    }

    private function defaultSchema(): string
    {
        return $this->connectionConfig()->options['schema'] ?? 'public';
    }

    private function normalizeType(string $raw, string $dataType): ColumnType
    {
        return match (strtolower($raw)) {
            'bool', 'boolean' => ColumnType::BOOLEAN,
            'int2', 'smallint', 'int4', 'integer', 'int8', 'bigint',
            'smallserial', 'serial', 'bigserial' => ColumnType::INTEGER,
            'numeric', 'decimal', 'money' => ColumnType::DECIMAL,
            'float4', 'real', 'float8', 'double precision' => ColumnType::FLOAT,
            'varchar', 'character varying', 'char', 'character', 'bpchar' => ColumnType::STRING,
            'text', 'citext' => ColumnType::TEXT,
            'bytea' => ColumnType::BINARY,
            'json', 'jsonb' => ColumnType::JSON,
            'uuid' => ColumnType::UUID,
            'date' => ColumnType::DATE,
            'time', 'timetz' => ColumnType::TIME,
            'timestamp', 'timestamptz' => ColumnType::TIMESTAMP,
            default => str_contains($raw, '[]') || strtolower($dataType) === 'array'
                ? ColumnType::ARRAY
                : ColumnType::OTHER,
        };
    }
}
