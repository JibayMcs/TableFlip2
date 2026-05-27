<?php

declare(strict_types=1);

namespace App\Application\Sql;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Sql\DestructiveSqlException;
use App\Domain\Sql\SqlExecutionResult;
use Throwable;

class SqlExecutor
{
    public function __construct(
        private readonly DestructiveSqlDetector $detector,
        private readonly QueryHistoryService $history,
    ) {}

    /**
     * Execute a raw SQL statement against the given driver.
     *
     * @throws DestructiveSqlException when $sql contains destructive ops and
     *         $confirmDestructive is false. The caller is expected to surface
     *         a confirmation modal and re-call with the flag set to true.
     */
    public function execute(
        DatabaseDriverInterface $driver,
        string $sql,
        ?string $database = null,
        bool $confirmDestructive = false,
    ): SqlExecutionResult {
        $sql = trim($sql);
        if ($sql === '') {
            return SqlExecutionResult::read([], [], 0.0);
        }

        if (! $confirmDestructive) {
            $reasons = $this->detector->analyze($sql);
            if ($reasons !== []) {
                throw new DestructiveSqlException(
                    detectedKeyword: $this->firstKeyword($sql),
                    reason: implode(' ', $reasons),
                );
            }
        }

        $isRead = $this->looksLikeRead($sql);
        $start = microtime(true);

        try {
            // Ensure unqualified table names resolve to the database the user
            // is currently exploring, not the connection's default (which can
            // be `information_schema` when they logged in server-only).
            $this->ensureCurrentDatabase($driver, $database);

            if ($isRead) {
                $queryResult = $driver->select($sql);
                $elapsed = (microtime(true) - $start) * 1000;
                $rows = is_array($queryResult->rows) ? $queryResult->rows : iterator_to_array($queryResult->rows);
                $result = SqlExecutionResult::read($rows, $queryResult->columns, $elapsed);
            } else {
                $affected = $driver->statement($sql);
                $elapsed = (microtime(true) - $start) * 1000;
                $result = SqlExecutionResult::write($affected, $elapsed);
            }
        } catch (Throwable $e) {
            $elapsed = (microtime(true) - $start) * 1000;
            $result = SqlExecutionResult::failure($e->getMessage(), $elapsed, isWrite: ! $isRead);
        }

        $this->history->record($sql, $database, $result);

        return $result;
    }

    /**
     * Switch the connection's current database for the next statement when
     * the driver supports it. Silently ignored on drivers without USE (PG,
     * SQLite) — the user is expected to qualify identifiers there.
     */
    private function ensureCurrentDatabase(\App\Domain\Database\Contracts\DatabaseDriverInterface $driver, ?string $database): void
    {
        if ($database === null || $database === '') {
            return;
        }
        if (! in_array($driver->getDriverName(), ['mysql', 'mariadb', 'sqlsrv'], true)) {
            return;
        }
        try {
            $driver->statement('USE '.$driver->quoteIdentifier($database));
        } catch (Throwable) {
            // Let the user query produce the real error.
        }
    }

    private function looksLikeRead(string $sql): bool
    {
        $first = strtoupper(ltrim($sql));

        foreach (['SELECT', 'SHOW', 'DESCRIBE', 'DESC ', 'EXPLAIN', 'WITH', 'PRAGMA'] as $verb) {
            if (str_starts_with($first, $verb)) {
                return true;
            }
        }

        return false;
    }

    private function firstKeyword(string $sql): string
    {
        if (preg_match('/^\s*(\w+)/', $sql, $m)) {
            return strtoupper($m[1]);
        }

        return '';
    }
}
