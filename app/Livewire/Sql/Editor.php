<?php

declare(strict_types=1);

namespace App\Livewire\Sql;

use App\Application\Connections\CurrentConnection;
use App\Application\Schema\SchemaIntrospectionService;
use App\Application\Sql\DestructiveSqlDetector;
use App\Application\Sql\QueryHistoryService;
use App\Application\Sql\SqlExecutor;
use App\Domain\Sql\DestructiveSqlException;
use App\Domain\Sql\SqlExecutionResult;
use App\Models\QueryHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app', ['flush' => true])]
class Editor extends Component
{
    /** @var list<array{id: string, title: string, sql: string}> */
    public array $tabs = [];

    public string $activeTabId = '';

    public string $currentSql = '';

    public ?string $currentDatabase = null;

    public string $historySearch = '';

    /** Last execution result for the active tab, serialised for the view. */
    public ?array $lastResult = null;

    /** When set, the destructive confirmation modal is shown. */
    public ?array $pendingDestructive = null;

    /**
     * Memoised list of database names for the picker. Built once (cheap
     * payload : just names) so every history-search keystroke / query run
     * doesn't re-hit the server with SHOW DATABASES.
     *
     * @var list<string>
     */
    public array $databasesList = [];

    public function mount(CurrentConnection $current): void
    {
        if ($current->driver() === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->currentDatabase = $current->defaultDatabase();
        $this->newTab();
    }

    #[On('connection-switched')]
    public function onConnectionSwitched(CurrentConnection $current): void
    {
        $this->currentDatabase = $current->defaultDatabase();
        $this->lastResult = null;
        $this->pushSchemaToEditor();
    }

    /**
     * Build the autocomplete schema and push it to the editor. Called once
     * after first paint via `wire:init` so the (potentially slow) catalog
     * introspection happens off the critical path — the page is already
     * interactive while this runs.
     */
    public function loadSchema(CurrentConnection $current, SchemaIntrospectionService $schemaService): void
    {
        if ($current->driver() === null || $this->currentDatabase === null) {
            return;
        }

        $this->dispatch('sql-editor-set-schema',
            dialect: $this->dialectName($current),
            schema: $this->buildSchema($current, $schemaService),
        );
    }

    // ---------------------------------------------------------------------
    // Tabs
    // ---------------------------------------------------------------------

    public function newTab(): void
    {
        $this->saveActiveTabSql();

        $id = (string) Str::uuid();
        $this->tabs[] = ['id' => $id, 'title' => 'Query '.(count($this->tabs) + 1), 'sql' => ''];
        $this->activeTabId = $id;
        $this->currentSql = '';
        $this->lastResult = null;
        $this->dispatch('sql-editor-set-content', sql: '');
    }

    public function closeTab(string $id): void
    {
        if (count($this->tabs) === 1) {
            // Replace last tab with a fresh empty one instead of leaving nothing.
            $this->tabs = [['id' => (string) Str::uuid(), 'title' => 'Query 1', 'sql' => '']];
            $this->activeTabId = $this->tabs[0]['id'];
            $this->currentSql = '';
            $this->lastResult = null;
            $this->dispatch('sql-editor-set-content', sql: '');

            return;
        }

        $wasActive = $id === $this->activeTabId;
        $this->tabs = array_values(array_filter($this->tabs, fn ($t) => $t['id'] !== $id));

        if ($wasActive) {
            $this->activeTabId = $this->tabs[0]['id'];
            $this->currentSql = $this->tabs[0]['sql'];
            $this->lastResult = null;
            $this->dispatch('sql-editor-set-content', sql: $this->currentSql);
        }
    }

    public function activateTab(string $id): void
    {
        if ($id === $this->activeTabId) {
            return;
        }

        $this->saveActiveTabSql();

        $target = collect($this->tabs)->firstWhere('id', $id);
        if ($target === null) {
            return;
        }

        $this->activeTabId = $id;
        $this->currentSql = $target['sql'];
        $this->lastResult = null;
        $this->dispatch('sql-editor-set-content', sql: $this->currentSql);
    }

    private function saveActiveTabSql(): void
    {
        foreach ($this->tabs as $i => $tab) {
            if ($tab['id'] === $this->activeTabId) {
                $this->tabs[$i]['sql'] = $this->currentSql;
                $title = trim(strtok($this->currentSql, "\n") ?: '');
                if ($title !== '') {
                    $this->tabs[$i]['title'] = Str::limit($title, 30);
                }
                break;
            }
        }
    }

    // ---------------------------------------------------------------------
    // Database switch
    // ---------------------------------------------------------------------

    public function changeDatabase(string $database): void
    {
        $this->currentDatabase = $database !== '' ? $database : null;
        $this->pushSchemaToEditor();
    }

    private function pushSchemaToEditor(): void
    {
        $schema = $this->buildSchema(app(CurrentConnection::class), app(SchemaIntrospectionService::class));
        $this->dispatch('sql-editor-set-schema',
            dialect: $this->dialectName(app(CurrentConnection::class)),
            schema: $schema,
        );
    }

    // ---------------------------------------------------------------------
    // Execute
    // ---------------------------------------------------------------------

    public function executeActive(
        ?string $sql,
        CurrentConnection $current,
        SqlExecutor $executor,
    ): void {
        $sql = trim($sql ?? $this->currentSql);
        if ($sql === '') {
            return;
        }

        $this->currentSql = $sql;
        $this->saveActiveTabSql();

        $driver = $current->driver();
        if ($driver === null) {
            $this->lastResult = ['error' => 'No active connection.'];

            return;
        }

        try {
            $result = $executor->execute($driver, $sql, $this->currentDatabase);
            $this->lastResult = $this->serialiseResult($result);
        } catch (DestructiveSqlException $e) {
            $this->pendingDestructive = [
                'sql' => $sql,
                'keyword' => $e->detectedKeyword,
                'reason' => $e->reason,
            ];
        }
    }

    public function confirmDestructive(
        CurrentConnection $current,
        SqlExecutor $executor,
    ): void {
        if ($this->pendingDestructive === null) {
            return;
        }

        $sql = (string) $this->pendingDestructive['sql'];
        $this->pendingDestructive = null;

        $driver = $current->driver();
        if ($driver === null) {
            $this->lastResult = ['error' => 'No active connection.'];

            return;
        }

        try {
            $result = $executor->execute($driver, $sql, $this->currentDatabase, confirmDestructive: true);
            $this->lastResult = $this->serialiseResult($result);
        } catch (Throwable $e) {
            $this->lastResult = ['error' => $e->getMessage()];
        }
    }

    public function cancelDestructive(): void
    {
        $this->pendingDestructive = null;
    }

    // ---------------------------------------------------------------------
    // History
    // ---------------------------------------------------------------------

    public function loadFromHistory(int $id): void
    {
        $entry = QueryHistory::find($id);
        if ($entry === null) {
            return;
        }
        $this->currentSql = (string) $entry->sql_text;
        $this->saveActiveTabSql();
        $this->dispatch('sql-editor-set-content', sql: $this->currentSql);
    }

    public function deleteHistoryEntry(int $id, QueryHistoryService $history): void
    {
        $history->deleteEntry($id);
    }

    /**
     * Pushes the latest editor content into the embedded Launcher and opens
     * its modal. We can't rely on the Launcher's own props because they
     * snapshot at mount time — the SQL evolves with each keystroke.
     */
    public function openExportLauncher(): void
    {
        $sql = trim($this->currentSql);
        if ($sql === '') {
            return;
        }

        $title = Str::limit(trim(strtok($sql, "\n") ?: 'query'), 40, '');
        $this->dispatch(
            'show-export-launcher',
            sourceKind: 'raw_sql',
            sourcePayload: ['sql' => $sql],
            defaultFileName: $title !== '' ? $title : 'query',
        )->to(\App\Livewire\Exports\Launcher::class);
    }

    public function clearAllHistory(QueryHistoryService $history): void
    {
        $history->clearAll();
    }

    // ---------------------------------------------------------------------
    // Render
    // ---------------------------------------------------------------------

    public function render(
        CurrentConnection $current,
        SchemaIntrospectionService $schemaService,
        QueryHistoryService $history,
        DestructiveSqlDetector $detector,
    ): View {
        $driver = $current->driver();
        $dialect = 'mysql';

        if ($driver !== null) {
            // Database list : built once, then reused from the public prop.
            if ($this->databasesList === []) {
                try {
                    $this->databasesList = $schemaService->databases($driver);
                } catch (Throwable) {
                    $this->databasesList = [];
                }
            }

            $dialect = $this->dialectName($current);
        }

        // Result rows live in the cache, not the snapshot — pull them back only
        // when the last run was a successful read (keeps writes/errors cheap).
        $resultRows = ($this->lastResult !== null
            && empty($this->lastResult['isWrite'])
            && empty($this->lastResult['error']))
            ? $this->resultRows()
            : [];

        // The autocomplete schema is NOT built here : on large databases the
        // introspection alone can exceed the proxy timeout (504). The page
        // paints with an empty schema and `loadSchema()` (wire:init) fills it
        // in a follow-up request, pushed straight to CodeMirror via event.
        return view('livewire.sql.editor', [
            'currentLabel' => $current->label(),
            'databases' => $this->databasesList,
            'schema' => [],
            'dialect' => $dialect,
            'history' => $history->recent($this->historySearch),
            'resultRows' => $resultRows,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildSchema(CurrentConnection $current, SchemaIntrospectionService $schemaService): array
    {
        $driver = $current->driver();
        if ($driver === null || $this->currentDatabase === null) {
            return [];
        }

        try {
            return $schemaService->tablesWithColumns($driver, $this->currentDatabase);
        } catch (Throwable) {
            return [];
        }
    }

    private function dialectName(CurrentConnection $current): string
    {
        return $current->driver()?->getDriverName() ?? 'mysql';
    }

    /**
     * Build the small result metadata kept in the public $lastResult property
     * (serialised in the Livewire snapshot on every request). The actual rows
     * are cached server-side — keeping them out of the snapshot is what avoids
     * the 413 (request entity too large) on big result sets.
     *
     * @return array<string, mixed>
     */
    private function serialiseResult(SqlExecutionResult $result): array
    {
        if ($result->isWrite || $result->error !== null) {
            $this->forgetResultRows();

            return [
                'isWrite' => $result->isWrite,
                'columns' => [],
                'rowCount' => 0,
                'affectedRows' => $result->affectedRows,
                'durationMs' => $result->durationMs,
                'error' => $result->error,
                'truncated' => false,
            ];
        }

        $rows = is_array($result->rows) ? $result->rows : iterator_to_array($result->rows);
        $this->storeResultRows($rows);

        return [
            'isWrite' => false,
            'columns' => $result->columns,
            'rowCount' => count($rows),
            'affectedRows' => 0,
            'durationMs' => $result->durationMs,
            'error' => null,
            'truncated' => $result->truncated,
        ];
    }

    private function resultRowsCacheKey(): string
    {
        return 'tableflip:sql:rows:'.$this->getId();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function storeResultRows(array $rows): void
    {
        \Illuminate\Support\Facades\Cache::put($this->resultRowsCacheKey(), $rows, now()->addHours(2));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resultRows(): array
    {
        $cached = \Illuminate\Support\Facades\Cache::get($this->resultRowsCacheKey());

        return is_array($cached) ? $cached : [];
    }

    private function forgetResultRows(): void
    {
        \Illuminate\Support\Facades\Cache::forget($this->resultRowsCacheKey());
    }
}
