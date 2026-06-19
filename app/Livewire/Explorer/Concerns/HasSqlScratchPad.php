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

    /** Last result rows from the custom SQL execution. */
    /** @var list<array<string, mixed>> */
    public array $customRows = [];

    /** @var list<string> */
    public array $customColumns = [];

    /** Holds the SQL awaiting confirmation when the destructive detector fires. */
    public ?array $pendingSqlDestructive = null;

    public function clearCustomSql(): void
    {
        $this->customSql = '';
        $this->customSqlError = null;
        $this->customSqlMeta = null;
        $this->customRows = [];
        $this->customColumns = [];
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
            $this->customRows = $rows;
            $this->customColumns = $result->columns;
        } else {
            $this->customRows = [];
            $this->customColumns = [];
        }

        // Reset bulk selection — different rows are about to be displayed.
        $this->selectedRowKeys = [];
        $this->page = 1;
    }
}
