<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Drivers;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\Exceptions\ConnectionException;
use App\Domain\Database\Exceptions\QueryExecutionException;
use App\Domain\Database\Exceptions\UnsupportedFeatureException;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Domain\Database\ValueObjects\QueryResult;
use App\Domain\Database\ValueObjects\TableIdentifier;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

abstract class AbstractDatabaseDriver implements DatabaseDriverInterface
{
    private readonly string $connectionName;

    private ?Connection $connection = null;

    public function __construct(private readonly ConnectionConfig $config)
    {
        $this->connectionName = 'tableflip_'.Str::random(16);
    }

    final public function connectionConfig(): ConnectionConfig
    {
        return $this->config;
    }

    public function ping(): bool
    {
        try {
            $this->connection()->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function listSchemas(?string $database = null): array
    {
        throw UnsupportedFeatureException::for($this->getDriverName(), 'listSchemas');
    }

    public function qualify(TableIdentifier $table): string
    {
        return implode('.', array_map(
            fn (string $part) => $this->quoteIdentifier($part),
            array_filter([$table->database, $table->schema, $table->name]),
        ));
    }

    public function select(string $sql, array $bindings = []): QueryResult
    {
        $start = microtime(true);

        try {
            $rows = $this->connection()->select($sql, $bindings);
        } catch (Throwable $e) {
            throw new QueryExecutionException($e->getMessage(), $sql, $bindings, $e);
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $assoc = array_map(static fn ($row) => (array) $row, $rows);
        $columns = $assoc === [] ? [] : array_keys($assoc[0]);

        return new QueryResult(
            rows: $assoc,
            columns: $columns,
            affectedRows: 0,
            executionTimeMs: $elapsed,
        );
    }

    public function statement(string $sql, array $bindings = []): int
    {
        try {
            return $this->connection()->affectingStatement($sql, $bindings);
        } catch (Throwable $e) {
            throw new QueryExecutionException($e->getMessage(), $sql, $bindings, $e);
        }
    }

    public function transaction(Closure $callback): mixed
    {
        return $this->connection()->transaction(fn () => $callback($this));
    }

    public function disconnect(): void
    {
        DB::purge($this->connectionName);
        Config::set("database.connections.{$this->connectionName}", null);
        $this->connection = null;
    }

    final protected function connection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        Config::set("database.connections.{$this->connectionName}", $this->config->toLaravelConfig());

        try {
            /** @var Connection $conn */
            $conn = DB::connection($this->connectionName);
            $conn->getPdo();
        } catch (Throwable $e) {
            throw ConnectionException::failed($this->getDriverName(), $e->getMessage(), $e);
        }

        return $this->connection = $conn;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    final protected function fetch(string $sql, array $bindings = []): array
    {
        $rows = $this->select($sql, $bindings)->rows;

        return is_array($rows) ? $rows : iterator_to_array($rows, preserve_keys: false);
    }
}
