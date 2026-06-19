<?php

declare(strict_types=1);

namespace App\Application\Schema;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Domain\Database\ValueObjects\ForeignKeyDefinition;
use App\Domain\Database\ValueObjects\IndexDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;
use App\Infrastructure\Database\DatabaseDriverFactory;
use Throwable;

/**
 * Thin facade over a DatabaseDriverInterface that memoises introspection
 * calls per *request*. Multiple components within the same render cycle can
 * call the same lookups without paying for repeated INFORMATION_SCHEMA hits.
 *
 * Cross-database awareness : PostgreSQL connections are bound to one
 * database, so introspecting another DB requires reconnecting. We keep a
 * tiny per-request pool of secondary drivers keyed by target DB so the user
 * can browse every database from a single PG connection.
 */
class SchemaIntrospectionService
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var array<string, DatabaseDriverInterface> */
    private array $perDatabasePool = [];

    public function __construct(private readonly DatabaseDriverFactory $factory) {}

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
        $effective = $this->driverFor($driver, $database);
        $key = "tables:".spl_object_id($effective).":{$database}:{$schema}";

        return $this->cached($key, fn () => $effective->listTables($database, $schema));
    }

    /**
     * @return list<TableIdentifier>
     */
    public function views(DatabaseDriverInterface $driver, ?string $database = null, ?string $schema = null): array
    {
        $effective = $this->driverFor($driver, $database);
        $key = "views:".spl_object_id($effective).":{$database}:{$schema}";

        return $this->cached($key, fn () => $effective->listViews($database, $schema));
    }

    /**
     * @return array{columns: list<ColumnDefinition>, indexes: list<IndexDefinition>, foreignKeys: list<ForeignKeyDefinition>}
     */
    public function tableDetail(DatabaseDriverInterface $driver, TableIdentifier $table): array
    {
        $effective = $this->driverFor($driver, $table->database);
        $key = "detail:".spl_object_id($effective).":{$table}";

        return $this->cached($key, fn () => [
            'columns' => $effective->getColumns($table),
            'indexes' => $effective->getIndexes($table),
            'foreignKeys' => $effective->getForeignKeys($table),
        ]);
    }

    /**
     * Build a `{ tableName: [col1, col2, ...] }` map for the whole database,
     * intended for feeding CodeMirror's SQL autocompletion.
     *
     * Uses the driver's bulk column introspection (one INFORMATION_SCHEMA
     * query for the whole catalog) instead of one query per table — the old
     * per-table loop was O(N) round-trips and timed out (504) on large
     * databases with hundreds of tables.
     *
     * @return array<string, list<string>>
     */
    public function tablesWithColumns(DatabaseDriverInterface $driver, ?string $database = null): array
    {
        if ($database === null || $database === '') {
            return [];
        }

        $key = 'tables_with_columns:'.spl_object_id($driver).":{$database}";

        return $this->cached($key, function () use ($driver, $database) {
            // Route through the per-DB pool so PostgreSQL hits the right
            // connection (one connection = one database there).
            $effective = $this->driverFor($driver, $database);

            try {
                $bulk = $effective->bulkColumns($database);
            } catch (Throwable) {
                return [];
            }

            $result = [];
            foreach ($this->tables($driver, $database) as $table) {
                $bulkKey = strtolower(($table->schema ?? '').'.'.$table->name);
                $cols = $bulk[$bulkKey] ?? null;
                if ($cols !== null) {
                    $result[$table->name] = array_map(static fn ($c) => $c->name, $cols);
                }
            }

            return $result;
        });
    }

    /**
     * COUNT(*) on the given table. Not memoised — callers decide when to
     * pay this cost (it can be slow on large tables). Routes through the
     * per-database pool so PG counts hit the right connection.
     */
    public function rowCount(DatabaseDriverInterface $driver, TableIdentifier $table): int
    {
        $effective = $this->driverFor($driver, $table->database);
        $sql = 'SELECT COUNT(*) AS c FROM '.$effective->qualify($table);
        $result = $effective->select($sql);
        $rows = is_array($result->rows) ? $result->rows : iterator_to_array($result->rows);

        return (int) ($rows[0]['c'] ?? 0);
    }

    /**
     * Return the driver to actually use for the requested database. For most
     * engines (MySQL/MariaDB/MSSQL) any database can be queried through the
     * existing connection — we just return the original driver. For
     * PostgreSQL (where one connection = one DB) we spin up — and cache for
     * the rest of the request — a secondary driver scoped to that DB.
     *
     * Public so non-introspection callers (TableDataQueryService, SqlExecutor)
     * can route their data queries through the same per-DB pool.
     */
    public function driverFor(DatabaseDriverInterface $driver, ?string $database): DatabaseDriverInterface
    {
        if ($database === null || $database === '') {
            return $driver;
        }
        if ($driver->getDriverName() !== 'pgsql') {
            return $driver;
        }

        $currentConfig = $driver->connectionConfig();
        if ($currentConfig->database === $database) {
            return $driver;
        }

        $key = $currentConfig->host.':'.$currentConfig->port.':'.$currentConfig->username.':'.$database;
        if (isset($this->perDatabasePool[$key])) {
            return $this->perDatabasePool[$key];
        }

        $cloned = new ConnectionConfig(
            driver: $currentConfig->driver,
            database: $database,
            host: $currentConfig->host,
            port: $currentConfig->port,
            username: $currentConfig->username,
            password: $currentConfig->password,
            charset: $currentConfig->charset,
            options: $currentConfig->options,
            ssl: $currentConfig->ssl,
            sslOptions: $currentConfig->sslOptions,
        );

        return $this->perDatabasePool[$key] = $this->factory->create($cloned);
    }

    private function cached(string $key, callable $factory): mixed
    {
        return $this->cache[$key] ??= $factory();
    }

    public function __destruct()
    {
        foreach ($this->perDatabasePool as $driver) {
            try {
                $driver->disconnect();
            } catch (Throwable) {
                // best-effort cleanup
            }
        }
    }
}
