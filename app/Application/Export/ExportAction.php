<?php

declare(strict_types=1);

namespace App\Application\Export;

use App\Application\Export\Sql\SqlDumpExporter;
use App\Application\Schema\SchemaIntrospectionService;
use App\Application\Schema\TableDataQueryService;
use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\Query\Filter;
use App\Domain\Database\Query\FilterOperator;
use App\Domain\Database\Query\Sort;
use App\Domain\Database\Query\SortDirection;
use App\Domain\Database\ValueObjects\TableIdentifier;
use App\Domain\Export\ExportContext;
use App\Domain\Export\ExportFormat;
use App\Infrastructure\Database\DatabaseDriverFactory;
use App\Infrastructure\Export\ExporterFactory;
use App\Models\Export;
use Generator;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Orchestrates an export end-to-end :
 *   1. Build the source SELECT (from a stored TableSource or raw SQL)
 *   2. Open a writable stream on the user's export disk path
 *   3. Pipe the driver's streamSelect() Generator into the matching exporter
 *   4. Persist file metadata (size, row count) onto the Export model
 *
 * The action mutates the model status as it progresses, so a polling UI can
 * surface "running" → "completed" without further wiring.
 */
class ExportAction
{
    public function __construct(
        private readonly DatabaseDriverFactory $driverFactory,
        private readonly ExporterFactory $exporterFactory,
        private readonly TableDataQueryService $tableQueryService,
        private readonly SchemaIntrospectionService $schemaService,
        private readonly SqlDumpExporter $sqlDumpExporter,
    ) {}

    public function run(Export $export): void
    {
        $export->update(['status' => 'running']);

        try {
            $this->execute($export);
            $export->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function execute(Export $export): void
    {
        $connection = $export->connection;
        if ($connection === null) {
            throw new \RuntimeException('Source connection has been deleted.');
        }

        $driver = $this->driverFactory->create($connection->toConnectionConfig());

        try {
            // Database-wide SQL dump : a different pipeline (multi-table,
            // structure + data, dialect-aware) than the per-row streaming
            // exporters used for CSV/JSON/legacy SQL.
            if ($export->source_kind === 'database') {
                $this->executeDatabaseDump($export, $driver);

                return;
            }

            [$sql, $bindings, $sourceTable] = $this->resolveQuery($driver, $export);

            // Switch the connection's current database when the export targets
            // a specific one (mirrors SqlExecutor::ensureCurrentDatabase).
            if ($export->database_name && in_array($driver->getDriverName(), ['mysql', 'mariadb', 'sqlsrv'], true)) {
                try {
                    $driver->statement('USE '.$driver->quoteIdentifier($export->database_name));
                } catch (Throwable) {
                    // Let the SELECT surface the real error.
                }
            }

            $format = ExportFormat::from($export->format);
            $exporter = $this->exporterFactory->create($format);

            // Peek at the first row to know the column order before opening
            // the file (the exporter writes a header / opening brace).
            $rowsGenerator = $driver->streamSelect($sql, $bindings);
            $rowsGenerator->rewind();
            $first = $rowsGenerator->valid() ? $rowsGenerator->current() : null;
            $columns = $first !== null ? array_keys($first) : [];

            $columnDefinitions = $sourceTable !== null
                ? $this->schemaService->tableDetail($driver, $sourceTable)['columns']
                : [];

            $relativePath = $this->buildRelativePath($export);
            $absolutePath = Storage::disk($this->disk())->path($relativePath);
            $this->ensureDirectoryExists($absolutePath);

            $stream = fopen($absolutePath, 'wb');
            if ($stream === false) {
                throw new \RuntimeException("Cannot open export file: {$relativePath}");
            }

            try {
                $context = new ExportContext(
                    columns: $columns,
                    columnDefinitions: $columnDefinitions,
                    sourceTable: $sourceTable,
                    driver: $driver,
                    options: (array) ($export->options ?? []),
                );

                $rowCount = 0;
                $exporter->export($stream, $this->yieldRows($rowsGenerator, $first, $rowCount), $context);
            } finally {
                fclose($stream);
            }

            $export->update([
                'file_path' => $relativePath,
                'row_count' => $rowCount,
                'byte_size' => (int) (file_exists($absolutePath) ? filesize($absolutePath) : 0),
            ]);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Run the multi-table SQL dump pipeline. Source payload shape:
     *   {
     *     database: 'db_name',
     *     schema?: 'schema_name',
     *     tables: [{name, structure: bool, data: bool}, ...]
     *   }
     */
    private function executeDatabaseDump(Export $export, DatabaseDriverInterface $driver): void
    {
        $payload = (array) ($export->source_payload ?? []);
        $database = (string) ($payload['database'] ?? $export->database_name ?? '');
        $schema = $payload['schema'] ?? null;
        $tables = (array) ($payload['tables'] ?? []);

        if ($database === '' || $tables === []) {
            throw new \RuntimeException('Database dump requires "database" and a non-empty "tables" list.');
        }

        if (in_array($driver->getDriverName(), ['mysql', 'mariadb', 'sqlsrv'], true)) {
            try {
                $driver->statement('USE '.$driver->quoteIdentifier($database));
            } catch (Throwable) {
            }
        }

        $relativePath = $this->buildRelativePath($export);
        $absolutePath = Storage::disk($this->disk())->path($relativePath);
        $this->ensureDirectoryExists($absolutePath);

        $stream = fopen($absolutePath, 'wb');
        if ($stream === false) {
            throw new \RuntimeException("Cannot open export file: {$relativePath}");
        }

        try {
            $this->sqlDumpExporter->dump($stream, $driver, $database, $tables, (array) ($export->options ?? []));
        } finally {
            fclose($stream);
        }

        $export->update([
            'file_path' => $relativePath,
            'row_count' => 0, // not tracked for dumps; could be totalled per-table later
            'byte_size' => (int) (file_exists($absolutePath) ? filesize($absolutePath) : 0),
        ]);
    }

    /**
     * Wrap the driver's Generator so we can count rows AND re-emit the
     * pre-consumed first row (used to peek at column names).
     *
     * @param  array<string, mixed>|null  $first
     */
    private function yieldRows(Generator $generator, ?array $first, int &$rowCount): Generator
    {
        if ($first !== null) {
            $rowCount++;
            yield $first;

            $generator->next();
            while ($generator->valid()) {
                $rowCount++;
                yield $generator->current();
                $generator->next();
            }
        }
    }

    /**
     * Build the SELECT SQL + bindings + (optional) source TableIdentifier
     * from the Export's source payload.
     *
     * @return array{0: string, 1: array<int, mixed>, 2: ?TableIdentifier}
     */
    private function resolveQuery(DatabaseDriverInterface $driver, Export $export): array
    {
        $payload = (array) ($export->source_payload ?? []);

        if ($export->source_kind === 'raw_sql') {
            $sql = (string) ($payload['sql'] ?? '');
            $sourceTable = isset($payload['source_table'])
                ? new TableIdentifier(
                    name: (string) $payload['source_table']['name'],
                    schema: $payload['source_table']['schema'] ?? null,
                    database: $payload['source_table']['database'] ?? null,
                )
                : null;

            return [$sql, [], $sourceTable];
        }

        // 'table' kind : compose a SELECT from the structured source so the
        // user benefits from the same filter/sort logic as the explorer.
        $table = new TableIdentifier(
            name: (string) $payload['name'],
            schema: $payload['schema'] ?? null,
            database: $payload['database'] ?? null,
        );

        $filters = array_values(array_filter(array_map(
            static function (array $f) {
                if (! isset($f['column'], $f['operator']) || $f['column'] === '') {
                    return null;
                }
                try {
                    $op = FilterOperator::from((string) $f['operator']);
                } catch (Throwable) {
                    return null;
                }

                return new Filter((string) $f['column'], $op, $f['value'] ?? null);
            },
            (array) ($payload['filters'] ?? []),
        ), static fn ($f) => $f !== null));

        $sort = array_map(
            static fn (array $s) => new Sort((string) $s['column'], SortDirection::from((string) $s['direction'])),
            (array) ($payload['sort'] ?? []),
        );

        $perPage = isset($payload['per_page']) ? max(1, (int) $payload['per_page']) : 1_000_000_000;
        $page = isset($payload['page']) ? max(1, (int) $payload['page']) : 1;

        [$sql, $bindings] = $this->tableQueryService->buildSelectForExport($driver, $table, $filters, $sort, $page, $perPage);

        return [$sql, $bindings, $table];
    }

    private function buildRelativePath(Export $export): string
    {
        return "exports/{$export->user_identifier}/{$export->id}-{$export->file_name}";
    }

    private function ensureDirectoryExists(string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, recursive: true);
        }
    }

    private function disk(): string
    {
        return (string) config('tableflip.exports.disk', 'local');
    }
}
