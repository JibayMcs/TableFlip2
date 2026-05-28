<?php

declare(strict_types=1);

namespace App\Livewire\Exports;

use App\Application\Connections\CurrentConnection;
use App\Application\Export\ExportFilename;
use App\Application\Schema\DatabaseOverviewService;
use App\Application\Schema\SchemaIntrospectionService;
use App\Jobs\ExportQueryResultJob;
use App\Models\Export;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * phpMyAdmin-style export page for an entire database.
 *
 * V1 (CP3) ships only the "Rapide" mode : SQL dump format with sensible
 * defaults (all tables, structure + data, FK off, transactional, header).
 * The "Personnalisé" mode (per-table toggle, dialect options) lands in CP4.
 *
 * The actual dump runs through the existing Phase 8 pipeline (Export model
 * + ExportQueryResultJob) so the user lands on /exports and watches the
 * status flip from queued → completed without us having to invent a new
 * progress UI.
 */
#[Layout('components.layouts.app', ['flush' => false])]
class DatabaseExport extends Component
{
    #[Url(as: 'db', except: null)]
    public ?string $database = null;

    #[Url(as: 'schema', except: null)]
    public ?string $schema = null;

    /** Format identifier — only 'sql_dump' in V1, kept as a property so CP4 can expand. */
    public string $format = 'sql_dump';

    /** Filename template fed to {@see ExportFilename::resolve}. */
    public string $filenameTemplate = ExportFilename::DEFAULT_TEMPLATE;

    /** Compression mode : none | gzip | zip. */
    public string $compression = 'none';

    /** 'quick' or 'custom' — drives the visible form sections. */
    #[Url(as: 'mode', except: 'quick')]
    public string $mode = 'quick';

    // -- Per-table picker state (custom mode) ---------------------------

    /**
     * Decisions per table : ['<name>' => ['structure' => bool, 'data' => bool]].
     * Primed when the user switches to custom mode the first time.
     *
     * @var array<string, array{structure: bool, data: bool}>
     */
    public array $tableSelection = [];

    public bool $selectionPrimed = false;

    // -- SQL-specific options (custom mode) -----------------------------

    public bool $optAddDrop = true;

    public bool $optIfNotExists = false;

    public bool $optTransactional = true;

    public bool $optDisableFk = true;

    public bool $optAddHeader = true;

    public int $optRowsPerInsert = 100;

    public ?string $error = null;

    /**
     * Switch between Quick and Custom modes. On the first transition to
     * Custom we prime the selection from the database overview so the user
     * sees every table pre-checked (like phpMyAdmin).
     */
    public function setMode(
        string $mode,
        CurrentConnection $current,
        DatabaseOverviewService $overview,
    ): void {
        $this->mode = in_array($mode, ['quick', 'custom'], true) ? $mode : 'quick';

        if ($this->mode === 'custom' && ! $this->selectionPrimed && $this->database !== null) {
            $driver = $current->driver();
            if ($driver !== null) {
                try {
                    $payload = $overview->overview($driver, $this->database, $this->schema);
                    $primed = [];
                    foreach ($payload['tables'] as $t) {
                        $primed[$t['name']] = [
                            'structure' => true,
                            'data' => $t['type'] === 'table',
                        ];
                    }
                    $this->tableSelection = $primed;
                    $this->selectionPrimed = true;
                } catch (Throwable $e) {
                    $this->error = $e->getMessage();
                }
            }
        }
    }

    public function bulkSelect(string $what, bool $value): void
    {
        if (! in_array($what, ['structure', 'data', 'both'], true)) {
            return;
        }
        foreach ($this->tableSelection as $name => &$flags) {
            if ($what === 'both' || $what === 'structure') {
                $flags['structure'] = $value;
            }
            if ($what === 'both' || $what === 'data') {
                $flags['data'] = $value;
            }
        }
    }

    public function mount(CurrentConnection $current): void
    {
        if ($current->driver() === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }
        if ($this->database === null) {
            $this->database = $current->defaultDatabase();
        }
    }

    public function start(
        CurrentConnection $current,
        DatabaseOverviewService $overview,
        SchemaIntrospectionService $schema,
    ): void {
        $this->error = null;
        $driver = $current->driver();
        if ($driver === null) {
            $this->error = __('exports.database.no_connection');

            return;
        }
        if ($this->database === null || $this->database === '') {
            $this->error = __('exports.database.no_database');

            return;
        }

        // Resolve the table list NOW so the user sees the dump's scope in
        // the Export listing and the worker doesn't have to re-walk the
        // catalog later.
        try {
            $payload = $overview->overview($driver, $this->database, $this->schema);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        if ($this->mode === 'custom' && $this->tableSelection !== []) {
            $tables = [];
            foreach ($payload['tables'] as $t) {
                $sel = $this->tableSelection[$t['name']] ?? null;
                if ($sel === null) {
                    continue;
                }
                if (! $sel['structure'] && ! $sel['data']) {
                    continue;
                }
                $tables[] = [
                    'name' => $t['name'],
                    'schema' => $t['schema'] ?? null,
                    'structure' => (bool) $sel['structure'],
                    'data' => (bool) $sel['data'],
                ];
            }
        } else {
            $tables = array_map(
                static fn (array $t) => [
                    'name' => $t['name'],
                    'schema' => $t['schema'] ?? null,
                    'structure' => true,
                    'data' => $t['type'] === 'table',
                ],
                $payload['tables'],
            );
        }

        if ($tables === []) {
            $this->error = __('exports.database.empty_selection');

            return;
        }

        $base = ExportFilename::resolve($this->filenameTemplate, [
            'database' => $this->database,
            'driver' => $driver->getDriverName(),
            'user' => (string) (Auth::guard('db_session')->id() ?? ''),
        ]);
        $fileName = ExportFilename::withExtension($base, 'sql', 'none');

        $export = Export::create([
            'user_kind' => 'direct_db',
            'user_identifier' => (string) Auth::guard('db_session')->id(),
            'database_name' => $this->database,
            'format' => 'sql_dump',
            'options' => $this->mode === 'custom'
                ? [
                    'add_drop' => $this->optAddDrop,
                    'if_not_exists' => $this->optIfNotExists,
                    'transactional' => $this->optTransactional,
                    'disable_fk' => $this->optDisableFk,
                    'add_header' => $this->optAddHeader,
                    'rows_per_insert' => max(1, $this->optRowsPerInsert),
                    'compression' => $this->compression,
                ]
                : [
                    'add_drop' => true,
                    'if_not_exists' => false,
                    'transactional' => true,
                    'disable_fk' => true,
                    'add_header' => true,
                    'rows_per_insert' => 100,
                    'compression' => $this->compression,
                ],
            'source_kind' => 'database',
            'source_payload' => [
                'database' => $this->database,
                'schema' => $this->schema,
                'tables' => $tables,
            ],
            'status' => 'pending',
            'file_name' => $fileName,
            'expires_at' => now()->addDays((int) config('tableflip.exports.retention_days', 7)),
        ]);

        ExportQueryResultJob::dispatch($export->id);

        session()->flash('export_queued', __('exports.database.queued', ['name' => $fileName]));
        $this->redirect(route('exports.index'), navigate: true);
    }

    public function render(CurrentConnection $current, SchemaIntrospectionService $schema): View
    {
        $driver = $current->driver();
        $databases = [];
        if ($driver !== null) {
            try {
                $databases = $schema->databases($driver);
            } catch (Throwable) {
                $databases = [];
            }
        }

        return view('livewire.exports.database-export', [
            'currentLabel' => $current->label(),
            'databases' => $databases,
        ]);
    }
}
