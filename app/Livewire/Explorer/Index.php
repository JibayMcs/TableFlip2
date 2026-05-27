<?php

declare(strict_types=1);

namespace App\Livewire\Explorer;

use App\Application\Connections\CurrentConnection;
use App\Application\Schema\SchemaIntrospectionService;
use App\Domain\Database\Exceptions\UnsupportedFeatureException;
use App\Domain\Database\ValueObjects\TableIdentifier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app', ['flush' => true])]
class Index extends Component
{
    /** @var list<string> Databases currently expanded in the sidebar (not URL-synced, UI state). */
    public array $expanded = [];

    #[Url(as: 'db', except: null)]
    public ?string $selectedDatabase = null;

    #[Url(as: 'schema', except: null)]
    public ?string $selectedSchema = null;

    #[Url(as: 'table', except: null)]
    public ?string $selectedTable = null;

    #[Url(as: 'tab', except: 'schema')]
    public string $tab = 'schema';

    public ?int $rowCount = null;

    public bool $rowCountFailed = false;

    public ?string $rowCountError = null;

    public function mount(CurrentConnection $current): void
    {
        if ($current->driver() === null) {
            $this->redirect(route('connections.index'), navigate: true);

            return;
        }

        // Default to the connection's preferred database only when no URL state.
        if ($this->selectedDatabase === null) {
            $default = $current->defaultDatabase();
            if ($default !== null) {
                $this->selectedDatabase = $default;
                $this->expanded = [$default];
            }
        } else {
            // Ensure the deep-linked database is expanded in the tree.
            $this->expanded = [$this->selectedDatabase];
        }
    }

    #[On('connection-switched')]
    public function resetExplorer(): void
    {
        $this->expanded = [];
        $this->selectedDatabase = null;
        $this->selectedSchema = null;
        $this->selectedTable = null;
        $this->tab = 'schema';
        $this->rowCount = null;
        $this->rowCountFailed = false;
    }

    public function toggleDatabase(string $name): void
    {
        if (in_array($name, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$name]));

            return;
        }

        $this->expanded[] = $name;
    }

    public function selectTable(string $database, string $table, ?string $schema = null): void
    {
        $changed = $database !== $this->selectedDatabase || $table !== $this->selectedTable;

        $this->selectedDatabase = $database;
        $this->selectedSchema = $schema;
        $this->selectedTable = $table;
        $this->rowCount = null;
        $this->rowCountFailed = false;

        if (! in_array($database, $this->expanded, true)) {
            $this->expanded[] = $database;
        }

        // When picking a different table, snap back to the schema tab so the
        // user gets oriented before pulling potentially-heavy data, and tell
        // any live TableData child to drop its filters/sort/page from the URL
        // before it gets re-mounted with the new table.
        if ($changed) {
            $this->tab = 'schema';
            $this->dispatch('explorer-table-changed');
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['schema', 'data'], true) ? $tab : 'schema';
    }

    public function loadRowCount(CurrentConnection $current, SchemaIntrospectionService $schema): void
    {
        if ($this->selectedDatabase === null || $this->selectedTable === null) {
            return;
        }

        $driver = $current->driver();
        if ($driver === null) {
            return;
        }

        $table = $this->currentTableIdentifier();

        try {
            $this->rowCount = $schema->rowCount($driver, $table);
            $this->rowCountFailed = false;
            $this->rowCountError = null;
        } catch (Throwable $e) {
            $this->rowCount = null;
            $this->rowCountFailed = true;
            $this->rowCountError = $e->getMessage();
        }
    }

    public function render(CurrentConnection $current, SchemaIntrospectionService $schema): View
    {
        $driver = $current->driver();
        $databases = [];
        $tablesByDb = [];
        $viewsByDb = [];
        $detail = null;

        if ($driver !== null) {
            $databases = $schema->databases($driver);

            foreach ($this->expanded as $db) {
                try {
                    $tablesByDb[$db] = $schema->tables($driver, $db);
                } catch (Throwable) {
                    $tablesByDb[$db] = [];
                }

                try {
                    $viewsByDb[$db] = $schema->views($driver, $db);
                } catch (UnsupportedFeatureException|Throwable) {
                    $viewsByDb[$db] = [];
                }
            }

            if ($this->selectedDatabase !== null && $this->selectedTable !== null && $this->tab === 'schema') {
                try {
                    $detail = $schema->tableDetail($driver, $this->currentTableIdentifier());
                } catch (Throwable $e) {
                    $detail = ['error' => $e->getMessage()];
                }
            }
        }

        return view('livewire.explorer.index', [
            'currentLabel' => $current->label(),
            'databases' => $databases,
            'tablesByDb' => $tablesByDb,
            'viewsByDb' => $viewsByDb,
            'detail' => $detail,
            'dialect' => $driver?->getDriverName() ?? 'mysql',
        ]);
    }

    private function currentTableIdentifier(): TableIdentifier
    {
        return new TableIdentifier(
            name: (string) $this->selectedTable,
            schema: $this->selectedSchema,
            database: $this->selectedDatabase,
        );
    }
}
