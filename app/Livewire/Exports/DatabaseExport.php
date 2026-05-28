<?php

declare(strict_types=1);

namespace App\Livewire\Exports;

use App\Application\Connections\ActiveConnectionResolver;
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

    public ?string $error = null;

    public function mount(CurrentConnection $current): void
    {
        if (! Auth::guard('web')->check()) {
            // Direct-DB sessions can't dispatch jobs (worker has no creds).
            $this->error = __('exports.database.no_breeze');
        }
        if ($current->driver() === null) {
            $this->redirect(route('connections.index'), navigate: true);

            return;
        }
        if ($this->database === null) {
            $this->database = $current->defaultDatabase();
        }
    }

    public function start(
        CurrentConnection $current,
        ActiveConnectionResolver $resolver,
        DatabaseOverviewService $overview,
        SchemaIntrospectionService $schema,
    ): void {
        $this->error = null;
        if (! Auth::guard('web')->check()) {
            $this->error = __('exports.database.no_breeze');

            return;
        }
        $connection = $resolver->current();
        $driver = $current->driver();
        if ($connection === null || $driver === null) {
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

        $tables = array_map(
            static fn (array $t) => [
                'name' => $t['name'],
                'schema' => $t['schema'] ?? null,
                'structure' => true,
                'data' => $t['type'] === 'table',
            ],
            $payload['tables'],
        );

        if ($tables === []) {
            $this->error = __('exports.database.empty_database');

            return;
        }

        $base = ExportFilename::resolve($this->filenameTemplate, [
            'database' => $this->database,
            'driver' => $driver->getDriverName(),
            'user' => (string) (Auth::guard('web')->id() ?? ''),
        ]);
        $fileName = ExportFilename::withExtension($base, 'sql', 'none');

        $export = Export::create([
            'user_kind' => 'web',
            'user_identifier' => (string) Auth::guard('web')->id(),
            'connection_id' => $connection->id,
            'database_name' => $this->database,
            'format' => 'sql_dump',
            'options' => [
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
