<?php

declare(strict_types=1);

namespace App\Livewire\Explorer;

use App\Application\Connections\CurrentConnection;
use App\Application\Schema\SchemaIntrospectionService;
use App\Application\Schema\TableDataQueryService;
use App\Domain\Database\Query\Filter;
use App\Domain\Database\Query\FilterOperator;
use App\Domain\Database\Query\Sort;
use App\Domain\Database\Query\SortDirection;
use App\Domain\Database\ValueObjects\TableIdentifier;
use App\Livewire\Explorer\Concerns\HasRowEditing;
use App\Livewire\Explorer\Concerns\HasSqlScratchPad;
use App\Support\CellPreview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class TableData extends Component
{
    use HasRowEditing;
    use HasSqlScratchPad;

    public string $database = '';

    public ?string $schema = null;

    public string $table = '';

    #[Url(as: 'p', except: 1)]
    public int $page = 1;

    #[Url(as: 'pp', except: 50)]
    public int $perPage = 50;

    /** @var list<array{column: string, operator: string, value: ?string}> */
    #[Url(as: 'f', except: [])]
    public array $filters = [];

    /** @var list<array{column: string, direction: string}> */
    #[Url(as: 's', except: [])]
    public array $sort = [];

    public bool $showFilters = false;

    /**
     * Manually-hidden columns. URL-synced so the user's preference survives a
     * navigate / reload (it's per-table because each table has its own column
     * set; signature changes on table switch via {@see clearOnTableChange}).
     *
     * @var list<string>
     */
    #[Url(as: 'hide', except: [])]
    public array $hiddenColumns = [];

    /**
     * Hide columns that are NULL/empty for every row on the current page.
     * Crucial on wide ERP tables (Sage SaleDocument has 549 columns, only a
     * few dozen ever populated for a given document type).
     */
    #[Url(as: 'ahe', except: true)]
    public bool $autoHideEmpty = true;

    /**
     * Cached row count + signature of the filters it was computed against.
     * Pagination/sort changes reuse the cached value (massive win on slow
     * remote servers where COUNT(*) on a 280k-row table costs as much as the
     * SELECT itself); filter changes invalidate it.
     */
    public ?int $cachedTotal = null;

    public string $cachedTotalSignature = '';

    public bool $totalIsEstimate = false;

    public function mount(string $database, string $table, ?string $schema = null): void
    {
        $this->database = $database;
        $this->table = $table;
        $this->schema = $schema;

        // Auto-show the filter builder when arriving via a deeplink that
        // already carries filters.
        if ($this->filters !== []) {
            $this->showFilters = true;
        }
    }

    /**
     * Triggered by Explorer/Index when the user picks a different table in the
     * sidebar. This component is about to be destroyed (key change), but we
     * clear the URL params first so the new instance starts with a clean slate.
     */
    #[On('explorer-table-changed')]
    public function clearOnTableChange(): void
    {
        $this->filters = [];
        $this->sort = [];
        $this->page = 1;
        $this->hiddenColumns = [];
        $this->invalidateTotal();
    }

    /**
     * Toggle a single column's visibility. Local roundtrip; the picker UI
     * does its checkbox toggling in Alpine then commits on close.
     */
    public function toggleColumnVisibility(string $column): void
    {
        if (in_array($column, $this->hiddenColumns, true)) {
            $this->hiddenColumns = array_values(array_diff($this->hiddenColumns, [$column]));
        } else {
            $this->hiddenColumns[] = $column;
        }
    }

    public function showAllColumns(): void
    {
        $this->hiddenColumns = [];
        $this->autoHideEmpty = false;
    }

    public function toggleAutoHideEmpty(): void
    {
        $this->autoHideEmpty = ! $this->autoHideEmpty;
    }

    /**
     * Drop the memoized count + estimate flag. Call from anywhere the table
     * shape or filter set changes in a way that invalidates the previous
     * total (filter apply/clear, table switch, custom-SQL execute…).
     */
    public function invalidateTotal(): void
    {
        $this->cachedTotal = null;
        $this->cachedTotalSignature = '';
        $this->totalIsEstimate = false;
    }

    /**
     * Force a real COUNT(*) on the next render even if we have an estimate.
     * Wired to a small "compter exact" button when total is approximate.
     */
    public function refreshExactCount(): void
    {
        $this->invalidateTotal();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
        if ($this->showFilters && $this->filters === []) {
            $this->addFilter();
        }
    }

    public function addFilter(): void
    {
        $this->filters[] = ['column' => '', 'operator' => '=', 'value' => null];
    }

    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
        $this->page = 1;
        $this->invalidateTotal();
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->page = 1;
        $this->invalidateTotal();
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->invalidateTotal();
    }

    public function updatedPerPage(): void
    {
        $this->page = 1;
    }

    public function toggleSort(string $column, bool $shift = false): void
    {
        $index = null;
        foreach ($this->sort as $i => $s) {
            if ($s['column'] === $column) {
                $index = $i;
                break;
            }
        }

        if (! $shift) {
            // Single-column sort: toggle direction or set asc
            if ($index !== null) {
                $current = $this->sort[$index]['direction'];
                $this->sort = [['column' => $column, 'direction' => $current === 'asc' ? 'desc' : 'asc']];
            } else {
                $this->sort = [['column' => $column, 'direction' => 'asc']];
            }
        } else {
            // Multi-column: shift+click adds or toggles in place
            if ($index !== null) {
                $current = $this->sort[$index]['direction'];
                $this->sort[$index]['direction'] = $current === 'asc' ? 'desc' : 'asc';
            } else {
                $this->sort[] = ['column' => $column, 'direction' => 'asc'];
            }
        }

        $this->page = 1;
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * Maximum number of characters to inline into the rendered HTML for any
     * single cell. Without this cap, a SaleDocument-style table with a few
     * nvarchar(max) / XML columns can produce a 30+ MB Livewire response per
     * page just from text values dragged into `title` attributes and click
     * handlers. The full value is still fetched on demand for editing.
     */
    private const CELL_DISPLAY_CAP = 240;

    /**
     * Bytes requested from the driver per large LOB/binary column on the page
     * preview. SQL Server truncates server-side (SET TEXTSIZE) so a varbinary
     * column storing a PDF never drags the whole blob into PHP. Comfortably
     * above {@see CELL_DISPLAY_CAP} so text we'd actually show isn't clipped.
     */
    private const LOB_FETCH_BYTES = 8192;

    /**
     * Resolve the driver to use for data operations on the current table.
     * On PostgreSQL this swaps to a connection scoped to the table's DB
     * (see {@see SchemaIntrospectionService::driverFor}); on other engines
     * it's a no-op.
     */
    protected function effectiveDriver(CurrentConnection $current): ?\App\Domain\Database\Contracts\DatabaseDriverInterface
    {
        $raw = $current->driver();
        if ($raw === null) {
            return null;
        }

        return app(\App\Application\Schema\SchemaIntrospectionService::class)
            ->driverFor($raw, $this->database);
    }

    /**
     * Cell value asked back from the browser when the user clicks a truncated
     * cell. Returns the full value via a single-row SELECT, scoped by PK.
     *
     * @param  array<string, scalar|null>  $rowKey
     */
    public function loadCellValue(array $rowKey, string $column, CurrentConnection $current): ?string
    {
        $driver = $this->effectiveDriver($current);
        if ($driver === null || $rowKey === []) {
            return null;
        }

        // Build a WHERE matching the row's PK columns only — same shape as
        // the inline edit pipeline uses, but for a single column projection.
        $where = [];
        $bindings = [];
        foreach ($rowKey as $col => $value) {
            $where[] = $driver->quoteIdentifier((string) $col).' = ?';
            $bindings[] = $value;
        }

        $sql = 'SELECT '.$driver->quoteIdentifier($column)
            .' AS v FROM '.$driver->qualify($this->currentTableIdentifier())
            .' WHERE '.implode(' AND ', $where);

        try {
            $result = $driver->select($sql, $bindings);
        } catch (Throwable) {
            return null;
        }

        $rows = is_array($result->rows) ? $result->rows : iterator_to_array($result->rows);
        $value = $rows[0]['v'] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * Trim wide cells for display. Binary values (varbinary/BLOB/image storing
     * a PDF, a photo, …) collapse to a marker; long text is cut to
     * {@see CELL_DISPLAY_CAP} bytes. Skips PK columns (used to build rowKey)
     * and non-strings. Returns the trimmed rows plus a parallel map
     * [rowIndex => [col => true]] flagging the cells offered for expand — only
     * clipped text, never the binary marker (no point fetching a raw blob).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $pkColumns
     * @return array{0: list<array<string, mixed>>, 1: array<int, array<string, true>>}
     */
    private function capRowsForDisplay(array $rows, array $pkColumns): array
    {
        $truncated = [];

        foreach ($rows as $i => $row) {
            foreach ($row as $col => $value) {
                if (in_array($col, $pkColumns, true) || ! is_string($value)) {
                    continue;
                }
                [$capped, $wasTruncatedText] = CellPreview::cap($value, self::CELL_DISPLAY_CAP);
                if ($capped !== $value) {
                    $rows[$i][$col] = $capped;
                }
                if ($wasTruncatedText) {
                    $truncated[$i][$col] = true;
                }
            }
        }

        return [$rows, $truncated];
    }

    /**
     * Detect columns that are NULL or empty-string for every row on the
     * current page. Returns the list of column names that *can* be hidden by
     * the auto-hide feature (caller decides whether to actually hide them).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function detectEmptyColumns(array $rows, array $columns): array
    {
        if ($rows === []) {
            return [];
        }

        $empty = [];
        foreach ($columns as $col) {
            $allEmpty = true;
            foreach ($rows as $row) {
                $value = $row[$col] ?? null;
                if ($value !== null && $value !== '' && $value !== 0 && $value !== '0' && $value !== false) {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                $empty[] = $col;
            }
        }

        return $empty;
    }

    /**
     * Final list of columns the view should actually render, accounting for
     * manual hides + auto-hide empty + PK columns being always visible.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $emptyColumns
     * @param  list<string>  $pkColumns
     * @return list<string>
     */
    private function resolveVisibleColumns(array $columns, array $emptyColumns, array $pkColumns): array
    {
        return array_values(array_filter($columns, function (string $col) use ($emptyColumns, $pkColumns) {
            // PK columns are always visible — needed to identify rows for edit / delete.
            if (in_array($col, $pkColumns, true)) {
                return true;
            }
            if (in_array($col, $this->hiddenColumns, true)) {
                return false;
            }
            if ($this->autoHideEmpty && in_array($col, $emptyColumns, true)) {
                return false;
            }

            return true;
        }));
    }

    public function render(
        CurrentConnection $current,
        TableDataQueryService $service,
        SchemaIntrospectionService $schema,
    ): View {
        $rawDriver = $current->driver();
        if ($rawDriver === null) {
            return view('livewire.explorer.table-data', $this->emptyView('No active connection.'));
        }

        $tableId = $this->currentTableIdentifier();
        // PG one-connection-per-DB constraint : swap to a driver scoped to the
        // table's database when needed. No-op for MySQL/MariaDB/MSSQL.
        $driver = $schema->driverFor($rawDriver, $tableId->database);

        // Resolve column metadata (types, PK flags). Used by the editing trait
        // for inline inputs and by the validator for hybrid type checks.
        try {
            $detail = $schema->tableDetail($driver, $tableId);
            $columnDefs = collect($detail['columns'])->keyBy('name')->all();
            $pkColumns = $this->pkColumnNames($detail['columns']);
        } catch (Throwable $e) {
            return view('livewire.explorer.table-data', $this->emptyView($e->getMessage()));
        }

        $autocompleteSchema = [
            $this->table => array_keys($columnDefs),
        ];
        $dialectName = $driver->getDriverName();
        $exportSourcePayload = $this->buildExportSourcePayload();

        // ── Custom SQL scratch pad takes priority over the natural query ──
        // Rows were captured at execution time (see HasSqlScratchPad::applySqlResult)
        // so render is purely a display step — no re-execution, no risk of
        // re-triggering destructive guards or doubling history entries.
        if ($this->customSql !== '') {
            ['rows' => $customRows, 'columns' => $customColumns] = $this->customResult();
            [$customRowsForDisplay, $customTruncatedCells] = $this->capRowsForDisplay($customRows, $pkColumns);
            $customEmpty = $this->detectEmptyColumns($customRowsForDisplay, $customColumns);
            $customVisible = $this->resolveVisibleColumns($customColumns, $customEmpty, $pkColumns);

            return view('livewire.explorer.table-data', [
                'error' => null,
                'mode' => 'custom',
                'rows' => $customRowsForDisplay,
                'truncatedCells' => $customTruncatedCells,
                'columns' => $customColumns,
                'visibleColumns' => $customVisible,
                'emptyColumns' => $customEmpty,
                'columnDefs' => $columnDefs,
                'pkColumns' => $pkColumns,
                'hasPrimaryKey' => $pkColumns !== [],
                'total' => count($customRows),
                'totalIsEstimate' => false,
                'totalPages' => 1,
                'autocompleteSchema' => $autocompleteSchema,
                'dialect' => $dialectName,
                'exportSourceKind' => 'raw_sql',
                'exportSourcePayload' => ['sql' => $this->customSql],
            ]);
        }

        // ── Natural mode : filters + sort + pagination ──
        $filters = array_values(array_filter(array_map(
            function (array $f) {
                if ($f['column'] === '') {
                    return null;
                }
                try {
                    $op = FilterOperator::from($f['operator']);
                } catch (Throwable) {
                    return null;
                }
                if ($op->requiresValue() && ($f['value'] === null || $f['value'] === '')) {
                    return null;
                }

                return new Filter($f['column'], $op, $f['value']);
            },
            $this->filters,
        ), static fn ($f) => $f !== null));

        $sortRules = array_map(
            fn (array $s) => new Sort($s['column'], SortDirection::from($s['direction'])),
            $this->sort,
        );

        // Decide whether we can skip COUNT(*). We always need a count for the
        // pagination math, but the same value can be reused across page/sort
        // changes as long as the filter set is identical.
        $signature = $this->filtersSignature($filters);
        $skipCount = $this->cachedTotal !== null && $this->cachedTotalSignature === $signature;

        try {
            $result = $service->query(
                $driver, $tableId, $filters, $sortRules, $this->page, $this->perPage,
                skipCount: $skipCount,
                maxLobBytes: self::LOB_FETCH_BYTES,
            );
        } catch (Throwable $e) {
            return view('livewire.explorer.table-data', $this->emptyView($e->getMessage()));
        }

        // Resolve the total : fresh COUNT > memoized exact count > approximate
        // estimate from the engine catalog (only when no filter is active).
        if ($result['total'] >= 0) {
            $this->cachedTotal = $result['total'];
            $this->cachedTotalSignature = $signature;
            $this->totalIsEstimate = false;
        } elseif ($this->cachedTotal === null) {
            // Should not happen (skipCount only true when cached), but defend.
            $this->cachedTotal = 0;
        }

        if ($this->cachedTotal === 0 && $filters === [] && ! $this->totalIsEstimate) {
            $estimate = $driver->estimateRowCount($tableId);
            if ($estimate !== null && $estimate > 0) {
                $this->cachedTotal = $estimate;
                $this->cachedTotalSignature = $signature;
                $this->totalIsEstimate = true;
            }
        }

        $total = $this->cachedTotal ?? 0;
        $totalPages = max(1, (int) ceil($total / $this->perPage));

        [$rowsForDisplay, $truncatedCells] = $this->capRowsForDisplay($result['rows'], $pkColumns);
        $emptyColumns = $this->detectEmptyColumns($rowsForDisplay, $result['columns']);
        $visibleColumns = $this->resolveVisibleColumns($result['columns'], $emptyColumns, $pkColumns);

        return view('livewire.explorer.table-data', [
            'error' => null,
            'mode' => 'natural',
            'rows' => $rowsForDisplay,
            'truncatedCells' => $truncatedCells,
            'columns' => $result['columns'],
            'visibleColumns' => $visibleColumns,
            'emptyColumns' => $emptyColumns,
            'columnDefs' => $columnDefs,
            'pkColumns' => $pkColumns,
            'hasPrimaryKey' => $pkColumns !== [],
            'total' => $total,
            'totalIsEstimate' => $this->totalIsEstimate,
            'totalPages' => $totalPages,
            'autocompleteSchema' => $autocompleteSchema,
            'dialect' => $dialectName,
            'exportSourceKind' => 'table',
            'exportSourcePayload' => $exportSourcePayload,
        ]);
    }

    /**
     * Stable signature of the active filter set — used to decide whether the
     * memoized total is still valid.
     *
     * @param  list<\App\Domain\Database\Query\Filter>  $filters
     */
    private function filtersSignature(array $filters): string
    {
        if ($filters === []) {
            return '';
        }

        return md5(serialize(array_map(
            static fn ($f) => [$f->column, $f->operator->value, $f->value],
            $filters,
        )));
    }

    /**
     * Build the source payload used by the export launcher in natural mode.
     * Only meaningful filters/sort are included so the worker rebuilds the
     * exact same SELECT (minus pagination — exports stream all rows).
     *
     * @return array<string, mixed>
     */
    private function buildExportSourcePayload(): array
    {
        return [
            'name' => $this->table,
            'schema' => $this->schema,
            'database' => $this->database,
            'filters' => array_values(array_filter(
                $this->filters,
                static fn (array $f) => ($f['column'] ?? '') !== '',
            )),
            'sort' => $this->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyView(string $error): array
    {
        return [
            'error' => $error,
            'mode' => 'natural',
            'rows' => [],
            'truncatedCells' => [],
            'columns' => [],
            'visibleColumns' => [],
            'emptyColumns' => [],
            'columnDefs' => [],
            'pkColumns' => [],
            'hasPrimaryKey' => false,
            'total' => 0,
            'totalIsEstimate' => false,
            'totalPages' => 1,
            'autocompleteSchema' => [],
            'dialect' => 'mysql',
            'exportSourceKind' => 'table',
            'exportSourcePayload' => [],
        ];
    }

    protected function currentTableIdentifier(): TableIdentifier
    {
        return new TableIdentifier($this->table, $this->schema, $this->database);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function operatorChoices(): array
    {
        return array_map(
            static fn (FilterOperator $op) => ['value' => $op->value, 'label' => $op->label()],
            FilterOperator::cases(),
        );
    }
}
