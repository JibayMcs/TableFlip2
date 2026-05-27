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
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->page = 1;
    }

    public function applyFilters(): void
    {
        $this->page = 1;
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

    public function render(
        CurrentConnection $current,
        TableDataQueryService $service,
        SchemaIntrospectionService $schema,
    ): View {
        $driver = $current->driver();
        if ($driver === null) {
            return view('livewire.explorer.table-data', $this->emptyView('No active connection.'));
        }

        $tableId = $this->currentTableIdentifier();

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

        // ── Custom SQL scratch pad takes priority over the natural query ──
        // Rows were captured at execution time (see HasSqlScratchPad::applySqlResult)
        // so render is purely a display step — no re-execution, no risk of
        // re-triggering destructive guards or doubling history entries.
        if ($this->customSql !== '') {
            return view('livewire.explorer.table-data', [
                'error' => null,
                'mode' => 'custom',
                'rows' => $this->customRows,
                'columns' => $this->customColumns,
                'columnDefs' => $columnDefs,
                'pkColumns' => $pkColumns,
                'hasPrimaryKey' => $pkColumns !== [],
                'total' => count($this->customRows),
                'totalPages' => 1,
                'autocompleteSchema' => $autocompleteSchema,
                'dialect' => $dialectName,
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

        try {
            $result = $service->query($driver, $tableId, $filters, $sortRules, $this->page, $this->perPage);
        } catch (Throwable $e) {
            return view('livewire.explorer.table-data', $this->emptyView($e->getMessage()));
        }

        $totalPages = max(1, (int) ceil($result['total'] / $this->perPage));

        return view('livewire.explorer.table-data', [
            'error' => null,
            'mode' => 'natural',
            'rows' => $result['rows'],
            'columns' => $result['columns'],
            'columnDefs' => $columnDefs,
            'pkColumns' => $pkColumns,
            'hasPrimaryKey' => $pkColumns !== [],
            'total' => $result['total'],
            'totalPages' => $totalPages,
            'autocompleteSchema' => $autocompleteSchema,
            'dialect' => $dialectName,
        ]);
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
            'columns' => [],
            'columnDefs' => [],
            'pkColumns' => [],
            'hasPrimaryKey' => false,
            'total' => 0,
            'totalPages' => 1,
            'autocompleteSchema' => [],
            'dialect' => 'mysql',
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
