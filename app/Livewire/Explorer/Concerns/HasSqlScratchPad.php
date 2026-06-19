<?php

declare(strict_types=1);

namespace App\Livewire\Explorer\Concerns;

use App\Application\Connections\CurrentConnection;
use App\Application\Sql\SqlExecutor;
use App\Domain\Sql\DestructiveSqlException;
use App\Domain\Sql\SqlExecutionResult;
use Throwable;

/**
 * SQL scratch pad concerns for {@see \App\Livewire\Explorer\TableData}.
 *
 * When `customSql` is non-empty, the host component renders the rows returned
 * by that query instead of the natural filtered/sorted/paginated query. The
 * single `<table>` keeps its edit affordances (bulk select, inline edit,
 * delete) so the user can act on the custom result set just like on the
 * natural view — limited only by whether the result includes the source
 * table's primary key columns.
 */
trait HasSqlScratchPad
{
    /** Active custom SQL — when non-empty, drives the table content. */
    public string $customSql = '';

    public ?string $customSqlError = null;

    /** @var array{durationMs: float, affectedRows: int, isWrite: bool}|null */
    public ?array $customSqlMeta = null;

    /** Holds the SQL awaiting confirmation when the destructive detector fires. */
    public ?array $pendingSqlDestructive = null;

    /**
     * Custom result rows / columns are cached server-side (keyed by the
     * Livewire component id) instead of living in public properties. A custom
     * SELECT can return hundreds of rows ; keeping them out of the snapshot
     * avoids serialising megabytes into the DOM and echoing them up on every
     * unrelated round-trip (inline edit, bulk select…). TTL is generous —
     * they're dropped on clearCustomSql() anyway.
     */
    private function customResultCacheKey(): string
    {
        return 'tableflip:tabledata:custom:'.$this->getId();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, columns: list<string>}
     */
    protected function customResult(): array
    {
        $cached = \Illuminate\Support\Facades\Cache::get($this->customResultCacheKey());

        return is_array($cached) ? $cached : ['rows' => [], 'columns' => []];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function storeCustomResult(array $rows, array $columns): void
    {
        \Illuminate\Support\Facades\Cache::put(
            $this->customResultCacheKey(),
            ['rows' => $rows, 'columns' => $columns],
            now()->addHours(2),
        );
    }

    private function forgetCustomResult(): void
    {
        \Illuminate\Support\Facades\Cache::forget($this->customResultCacheKey());
    }

    public function clearCustomSql(): void
    {
        $this->customSql = '';
        $this->customSqlError = null;
        $this->customSqlMeta = null;
        $this->forgetCustomResult();
        $this->pendingSqlDestructive = null;
        // Notify the editor so it visually drops back to the seed query.
        $this->dispatch('table-data-sql-set', sql: $this->scratchPadSeedSql());
    }

    public function executeCustomSql(
        ?string $sql,
        CurrentConnection $current,
        SqlExecutor $executor,
    ): void {
        $sql = trim((string) $sql);
        if ($sql === '') {
            return;
        }

        $driver = $this->effectiveDriver($current);
        if ($driver === null) {
            $this->customSqlError = 'No active connection.';

            return;
        }

        try {
            $result = $executor->execute($driver, $sql, $this->database);
            $this->applySqlResult($sql, $result);
        } catch (DestructiveSqlException $e) {
            $this->pendingSqlDestructive = [
                'sql' => $sql,
                'reason' => $e->reason,
            ];
        }
    }

    public function confirmCustomDestructive(
        CurrentConnection $current,
        SqlExecutor $executor,
    ): void {
        if ($this->pendingSqlDestructive === null) {
            return;
        }

        $sql = (string) $this->pendingSqlDestructive['sql'];
        $this->pendingSqlDestructive = null;

        $driver = $this->effectiveDriver($current);
        if ($driver === null) {
            $this->customSqlError = 'No active connection.';

            return;
        }

        try {
            $result = $executor->execute($driver, $sql, $this->database, confirmDestructive: true);
            $this->applySqlResult($sql, $result);
        } catch (Throwable $e) {
            $this->customSqlError = $e->getMessage();
        }
    }

    public function cancelCustomDestructive(): void
    {
        $this->pendingSqlDestructive = null;
    }

    /**
     * Default seed query used when opening the scratch pad on a freshly
     * selected table. Quoting matches the active dialect.
     */
    public function scratchPadSeedSql(): string
    {
        if ($this->table === '') {
            return '';
        }
        $quoted = match ($this->dialect()) {
            'pgsql', 'sqlite' => "\"{$this->table}\"",
            'sqlsrv' => "[{$this->table}]",
            default => "`{$this->table}`",
        };

        return "SELECT * FROM {$quoted} LIMIT 100;";
    }

    /**
     * Lookup of the active dialect string for the running connection. Defined
     * here as a default; host can override if it has cheaper access.
     */
    protected function dialect(): string
    {
        try {
            return app(CurrentConnection::class)->driver()?->getDriverName() ?? 'mysql';
        } catch (Throwable) {
            return 'mysql';
        }
    }

    private function applySqlResult(string $sql, SqlExecutionResult $result): void
    {
        $this->customSql = $sql;
        $this->customSqlError = $result->error;
        $this->customSqlMeta = [
            'durationMs' => $result->durationMs,
            'affectedRows' => $result->affectedRows,
            'isWrite' => $result->isWrite,
        ];

        // Capture rows for display. Writes return no rows but we still keep
        // the meta to show "Affected: N" in the result area.
        if ($result->succeeded() && ! $result->isWrite) {
            $rows = is_array($result->rows) ? $result->rows : iterator_to_array($result->rows);
            $this->storeCustomResult($rows, $result->columns);
        } else {
            $this->forgetCustomResult();
        }

        // Reset bulk selection — different rows are about to be displayed.
        $this->selectedRowKeys = [];
        $this->page = 1;
    }
}
