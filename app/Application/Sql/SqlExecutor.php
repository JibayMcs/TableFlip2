<?php

declare(strict_types=1);

namespace App\Application\Sql;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Sql\DestructiveSqlException;
use App\Domain\Sql\SqlExecutionResult;
use App\Support\CellPreview;
use Throwable;

class SqlExecutor
{
    /**
     * Hard cap on rows pulled into memory for a read query preview. The editor
     * is a preview surface, not a bulk export — without this a `SELECT *` on a
     * huge table OOMs the worker and bloats the Livewire snapshot (413). Use
     * the Exports feature to stream a full result set to a file.
     */
    public const MAX_PREVIEW_ROWS = 1000;

    /**
     * Per-cell byte cap. A single BLOB/TEXT column can be several MB — left
     * whole, a handful of wide rows exhausts the worker's memory long before
     * the row cap is reached. Trimmed values keep the tooltip useful; the full
     * payload is available through Export.
     */
    public const MAX_CELL_BYTES = 2048;

    /**
     * Total byte budget for the accumulated preview. Independent of row/cell
     * caps, this is the backstop that bounds memory on pathological shapes
     * (hundreds of columns, or rows that individually pass the cell cap).
     */
    public const MAX_PREVIEW_BYTES = 16_777_216; // 16 MiB

    /**
     * Server-side cap (bytes) asked of the driver for large LOB/binary columns.
     * SQL Server honours it via SET TEXTSIZE so a varbinary PDF is truncated
     * before it ever reaches PHP — this is what prevents the fetch()-time OOM.
     */
    public const MAX_LOB_FETCH_BYTES = 8192;

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
                // Stream + cap on three axes — rows, per-cell bytes, total bytes.
                // The unbuffered cursor feeds rows one at a time; we trim each as
                // it arrives so a wide BLOB never accumulates, and stop as soon as
                // any cap trips, flagging the result as truncated.
                $rows = [];
                $truncated = false;
                $bytes = 0;
                foreach ($driver->streamSelect($sql, [], self::MAX_LOB_FETCH_BYTES) as $i => $row) {
                    if ($i >= self::MAX_PREVIEW_ROWS) {
                        $truncated = true;
                        break;
                    }
                    $bytes += $this->capRow($row);
                    $rows[] = $row;
                    if ($bytes >= self::MAX_PREVIEW_BYTES) {
                        $truncated = true;
                        break;
                    }
                }
                $elapsed = (microtime(true) - $start) * 1000;
                $columns = $rows === [] ? [] : array_keys($rows[0]);
                $result = SqlExecutionResult::read($rows, $columns, $elapsed, $truncated);
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

    /**
     * Trim a freshly-streamed row in place: binary cells collapse to a marker,
     * long text is cut (multibyte-safe) to {@see MAX_CELL_BYTES}. Returns the
     * row's approximate byte weight so the caller can enforce the total preview
     * budget. The transient full value is freed on the next fetch, so peak
     * memory stays at one wide row, never the whole set.
     *
     * @param  array<string, mixed>  $row
     */
    private function capRow(array &$row): int
    {
        $bytes = 0;
        foreach ($row as $col => $value) {
            [$capped] = CellPreview::cap($value, self::MAX_CELL_BYTES);
            $row[$col] = $capped;

            if (is_string($capped)) {
                $bytes += strlen($capped);
            } elseif ($capped !== null) {
                $bytes += 16; // ballpark for scalars — keeps the budget honest
            }
        }

        return $bytes;
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
