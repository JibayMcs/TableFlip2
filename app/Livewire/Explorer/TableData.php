<?php

declare(strict_types=1);

namespace App\Livewire\Explorer;

use App\Application\Connections\CurrentConnection;
use App\Application\Schema\TableDataQueryService;
use App\Domain\Database\Query\Filter;
use App\Domain\Database\Query\FilterOperator;
use App\Domain\Database\Query\Sort;
use App\Domain\Database\Query\SortDirection;
use App\Domain\Database\ValueObjects\TableIdentifier;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

class TableData extends Component
{
    public string $database = '';

    public ?string $schema = null;

    public string $table = '';

    public int $page = 1;

    public int $perPage = 50;

    /** @var list<array{column: string, operator: string, value: ?string}> */
    public array $filters = [];

    /** @var list<array{column: string, direction: string}> */
    public array $sort = [];

    public bool $showFilters = false;

    public function mount(string $database, string $table, ?string $schema = null): void
    {
        $this->database = $database;
        $this->table = $table;
        $this->schema = $schema;
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

    public function render(CurrentConnection $current, TableDataQueryService $service): View
    {
        $driver = $current->driver();
        if ($driver === null) {
            return view('livewire.explorer.table-data', [
                'error' => 'No active connection.',
                'rows' => [],
                'columns' => [],
                'total' => 0,
                'totalPages' => 1,
            ]);
        }

        $tableId = new TableIdentifier($this->table, $this->schema, $this->database);

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

        $sort = array_map(
            fn (array $s) => new Sort($s['column'], SortDirection::from($s['direction'])),
            $this->sort,
        );

        try {
            $result = $service->query($driver, $tableId, $filters, $sort, $this->page, $this->perPage);
        } catch (Throwable $e) {
            return view('livewire.explorer.table-data', [
                'error' => $e->getMessage(),
                'rows' => [],
                'columns' => [],
                'total' => 0,
                'totalPages' => 1,
            ]);
        }

        $totalPages = max(1, (int) ceil($result['total'] / $this->perPage));

        return view('livewire.explorer.table-data', [
            'error' => null,
            'rows' => $result['rows'],
            'columns' => $result['columns'],
            'total' => $result['total'],
            'totalPages' => $totalPages,
        ]);
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
