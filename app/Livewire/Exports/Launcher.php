<?php

declare(strict_types=1);

namespace App\Livewire\Exports;

use App\Domain\Export\ExportFormat;
use App\Jobs\ExportQueryResultJob;
use App\Models\Export;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reusable "Export" button + modal embedded by TableData and the SQL editor.
 *
 * The host passes a source descriptor (raw SQL or structured table query) and
 * the launcher takes care of format/options selection then queues an
 * ExportQueryResultJob. The user lands on /exports right after to track it.
 *
 * V1 scope: only Breeze users with a saved connection can export — direct-DB
 * users see a notice because the worker has no way to recover their session
 * credentials.
 */
class Launcher extends Component
{
    public string $sourceKind = 'table';      // 'table' | 'raw_sql'

    /** @var array<string, mixed> */
    public array $sourcePayload = [];

    public string $defaultFileName = 'export';

    public ?string $databaseName = null;

    /** When true, the inline trigger button is suppressed — the host is expected to dispatch `show-export-launcher`. */
    public bool $external = false;

    public bool $open = false;

    public string $format = 'csv';

    public string $fileName = '';

    // CSV options
    public string $csvDelimiter = ',';

    public bool $csvIncludeHeader = true;

    // JSON options
    public string $jsonLayout = 'lines';

    // SQL options
    public bool $sqlIncludeDrop = false;

    public bool $sqlIncludeCreate = false;

    public bool $sqlMultiRowInsert = true;

    public ?string $error = null;

    /**
     * @param  array<string, mixed>  $sourcePayload
     */
    public function mount(
        string $sourceKind,
        array $sourcePayload,
        string $defaultFileName = 'export',
        ?string $databaseName = null,
        bool $external = false,
    ): void {
        $this->sourceKind = $sourceKind;
        $this->sourcePayload = $sourcePayload;
        $this->defaultFileName = Str::slug($defaultFileName) ?: 'export';
        $this->fileName = $this->defaultFileName;
        $this->databaseName = $databaseName;
        $this->external = $external;
    }

    public function show(): void
    {
        $this->error = null;
        $this->fileName = $this->defaultFileName;
        $this->open = true;
    }

    /**
     * Re-arm the launcher with a fresh source (typically the current SQL or
     * the current filters/sort snapshot) before opening. Hosts emit this when
     * the source can change between renders — e.g. the SQL editor where the
     * tab content lives in the browser until execute.
     *
     * @param  array<string, mixed>  $sourcePayload
     */
    #[On('show-export-launcher')]
    public function showFor(string $sourceKind, array $sourcePayload, ?string $defaultFileName = null): void
    {
        $this->sourceKind = $sourceKind;
        $this->sourcePayload = $sourcePayload;
        if ($defaultFileName !== null && trim($defaultFileName) !== '') {
            $this->defaultFileName = Str::slug($defaultFileName) ?: 'export';
        }

        $this->show();
    }

    public function hide(): void
    {
        $this->open = false;
    }

    public function startExport(): void
    {
        if (! Auth::guard('db_session')->check()) {
            $this->error = 'Not authenticated.';

            return;
        }
        if (trim($this->fileName) === '') {
            $this->error = 'Pick a file name.';

            return;
        }

        try {
            $format = ExportFormat::from($this->format);
        } catch (\Throwable) {
            $this->error = 'Unknown format.';

            return;
        }

        $export = Export::create([
            'user_kind' => 'direct_db',
            'user_identifier' => (string) Auth::guard('db_session')->id(),
            'database_name' => $this->databaseName,
            'format' => $format->value,
            'options' => $this->buildOptions($format),
            'source_kind' => $this->sourceKind,
            'source_payload' => $this->sourcePayload,
            'status' => 'pending',
            'file_name' => Str::slug($this->fileName).'.'.$format->fileExtension(),
            'expires_at' => now()->addDays((int) config('tableflip.exports.retention_days', 7)),
        ]);

        ExportQueryResultJob::dispatch($export->id);

        $this->open = false;
        session()->flash('export_queued', "Export queued — track its progress on the Exports page.");
        $this->redirect(route('exports.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(ExportFormat $format): array
    {
        return match ($format) {
            ExportFormat::CSV => [
                'delimiter' => $this->csvDelimiter !== '' ? $this->csvDelimiter : ',',
                'include_header' => $this->csvIncludeHeader,
            ],
            ExportFormat::JSON => [
                'layout' => $this->jsonLayout === 'array' ? 'array' : 'lines',
            ],
            ExportFormat::SQL => [
                'include_drop' => $this->sqlIncludeDrop,
                'include_create' => $this->sqlIncludeCreate,
                'multi_row_insert' => $this->sqlMultiRowInsert,
                'rows_per_insert' => 100,
            ],
        };
    }

    public function render(): View
    {
        return view('livewire.exports.launcher', [
            'available' => Auth::guard('db_session')->check(),
            'showTrigger' => ! $this->external,
        ]);
    }
}
