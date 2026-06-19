<?php

declare(strict_types=1);

namespace App\Livewire\Explorer;

use App\Application\Connections\CurrentConnection;
use App\Application\Schema\DatabaseOverviewService;
use App\Application\Schema\SchemaIndexService;
use App\Application\Schema\SchemaIntrospectionService;
use App\Application\Tables\DropTableAction;
use App\Application\Tables\TruncateTableAction;
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

    /**
     * Grid or list view for the database overview panel.
     *
     * NOT a #[Url] property : the toggle is pure client-side Alpine, which
     * owns the `?view=` query param via history.replaceState. Were this
     * #[Url], every unrelated Livewire round-trip (e.g. a bulk-select)
     * would re-assert the server value and clobber the client's choice.
     * We seed it from the request query in mount() so deeplinks still
     * work on first load.
     */
    public string $overviewView = 'grid';

    /**
     * Tables checked for bulk truncate/drop in the overview panel.
     *
     * @var list<string>
     */
    public array $bulkSelected = [];

    /** When set, the type-to-confirm modal is shown for the requested action. */
    public ?array $pendingBulkAction = null;

    /**
     * Toggle in the confirm modal — when false, the action runs with
     * foreign-key enforcement disabled (phpMyAdmin-style). Default true.
     */
    public bool $enforceForeignKeys = true;

    public ?string $overviewError = null;

    public ?string $overviewStatus = null;

    public function mount(CurrentConnection $current): void
    {
        if ($current->driver() === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        // Seed the overview view from the query string so deeplinks /
        // reloads keep the choice. After mount, the Alpine toggle owns it.
        if (request()->query('view') === 'list') {
            $this->overviewView = 'list';
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
        // Click on a DB name = expand it (if collapsed) AND select it as
        // the focus of the main panel, clearing any selected table so the
        // overview lands. Re-click a DB that's already expanded + selected
        // collapses it.
        $wasExpanded = in_array($name, $this->expanded, true);
        $wasSelected = $this->selectedDatabase === $name && $this->selectedTable === null;

        if ($wasExpanded && $wasSelected) {
            $this->expanded = array_values(array_diff($this->expanded, [$name]));
            $this->selectedDatabase = null;

            return;
        }

        if (! $wasExpanded) {
            $this->expanded[] = $name;
        }

        $this->selectedDatabase = $name;
        $this->selectedSchema = null;
        $this->selectedTable = null;
        $this->tab = 'schema';
        $this->bulkSelected = [];
        $this->overviewError = null;
        $this->overviewStatus = null;
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

    /**
     * Clear the table selection so the main panel falls back to the
     * database overview (kept in sync with the breadcrumb's clickable
     * database segment).
     */
    public function backToOverview(): void
    {
        $this->selectedTable = null;
        $this->selectedSchema = null;
        $this->tab = 'schema';
        $this->rowCount = null;
        $this->rowCountFailed = false;
        $this->rowCountError = null;
        $this->dispatch('explorer-table-changed');
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

    // -- Database overview : bulk actions -------------------------------

    public function toggleBulk(string $tableName): void
    {
        if (in_array($tableName, $this->bulkSelected, true)) {
            $this->bulkSelected = array_values(array_diff($this->bulkSelected, [$tableName]));
        } else {
            $this->bulkSelected[] = $tableName;
        }
    }

    public function clearBulk(): void
    {
        $this->bulkSelected = [];
    }

    public function requestBulkAction(string $action): void
    {
        if (! in_array($action, ['truncate', 'drop'], true) || $this->bulkSelected === []) {
            return;
        }
        $this->pendingBulkAction = [
            'action' => $action,
            'tables' => $this->bulkSelected,
            'count' => count($this->bulkSelected),
        ];
    }

    public function cancelBulkAction(): void
    {
        $this->pendingBulkAction = null;
    }

    public function confirmBulkAction(
        CurrentConnection $current,
        SchemaIntrospectionService $schema,
        TruncateTableAction $truncate,
        DropTableAction $drop,
        SchemaIndexService $indexer,
    ): void {
        if ($this->pendingBulkAction === null || $this->selectedDatabase === null) {
            return;
        }

        $rawDriver = $current->driver();
        if ($rawDriver === null) {
            $this->overviewError = 'No active connection.';
            $this->pendingBulkAction = null;

            return;
        }
        $driver = $schema->driverFor($rawDriver, $this->selectedDatabase);

        $action = (string) $this->pendingBulkAction['action'];
        $tables = (array) $this->pendingBulkAction['tables'];
        $ok = 0;
        $errors = [];

        $runBatch = function () use ($driver, $tables, $action, $truncate, $drop, &$ok, &$errors): void {
            foreach ($tables as $name) {
                try {
                    $tableId = new TableIdentifier(
                        name: (string) $name,
                        schema: $this->selectedSchema,
                        database: $this->selectedDatabase,
                    );
                    if ($action === 'truncate') {
                        $truncate->execute($driver, $tableId);
                    } else {
                        $drop->execute($driver, $tableId);
                    }
                    $ok++;
                } catch (Throwable $e) {
                    $errors[] = "{$name}: ".$e->getMessage();
                }
            }
        };

        if ($this->enforceForeignKeys) {
            $runBatch();
        } else {
            $driver->runWithoutForeignKeyChecks($runBatch);
        }

        $this->bulkSelected = [];
        $this->pendingBulkAction = null;
        $verb = $action === 'truncate' ? 'truncated' : 'dropped';
        $this->overviewStatus = "{$verb} {$ok}/".count($tables);
        if ($errors !== []) {
            $this->overviewError = implode("\n", $errors);
        }

        // Drop = schema shape changed → refresh the search index so the
        // sidebar reflects reality.
        if ($action === 'drop') {
            $poolId = $current->poolId();
            if ($poolId !== null) {
                try {
                    $indexer->refresh($driver, $poolId);
                } catch (Throwable) {
                }
            }
        }
    }

    public function dismissOverviewStatus(): void
    {
        $this->overviewError = null;
        $this->overviewStatus = null;
    }

    public function reindexSchema(
        CurrentConnection $current,
        SchemaIndexService $indexer,
    ): void {
        $driver = $current->driver();
        $poolId = $current->poolId();
        if ($driver === null || $poolId === null) {
            return;
        }

        $indexer->refresh($driver, $poolId);
    }

    public function render(
        CurrentConnection $current,
        SchemaIntrospectionService $schema,
        SchemaIndexService $indexer,
        DatabaseOverviewService $overviewSvc,
    ): View {
        $driver = $current->driver();
        $databases = [];
        $tablesByDb = [];
        $viewsByDb = [];
        $detail = null;
        $searchIndex = [];
        $overview = null;

        if ($driver !== null) {
            $databases = $schema->databases($driver);

            // Cross-database index for the sidebar search — cached forever,
            // refreshed automatically when the engine's schema signature
            // changes, or manually via the Reindex button.
            $poolId = $current->poolId();
            if ($poolId !== null) {
                try {
                    $searchIndex = $indexer->index($driver, $poolId);
                } catch (Throwable) {
                    $searchIndex = [];
                }
            }

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

            // Overview = a DB is selected but no specific table — show the
            // grid/list of tables with rows + size.
            if ($this->selectedDatabase !== null && $this->selectedTable === null) {
                try {
                    $overview = $overviewSvc->overview($driver, $this->selectedDatabase, $this->selectedSchema);
                } catch (Throwable $e) {
                    $overview = ['error' => $e->getMessage()];
                }
            }
        }

        return view('livewire.explorer.index', [
            'currentLabel' => $current->label(),
            'databases' => $databases,
            'tablesByDb' => $tablesByDb,
            'viewsByDb' => $viewsByDb,
            'searchIndex' => $searchIndex,
            'detail' => $detail,
            'overview' => $overview,
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
