<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Application\Schema\SchemaIntrospectionService;
use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\TableIdentifier;
use Throwable;

/**
 * Produces a full SQL dump (style mysqldump / pg_dump) of one or several
 * tables into a writable stream. Driven by an option array so the Livewire
 * UI can wire user toggles directly.
 *
 * Supported options (with defaults) :
 *   - 'add_drop'         (bool, false)
 *   - 'if_not_exists'    (bool, true)
 *   - 'transactional'    (bool, true)   wrap whole dump in BEGIN / COMMIT
 *   - 'disable_fk'       (bool, true)   add SET FOREIGN_KEY_CHECKS = 0 around the data
 *   - 'add_header'       (bool, true)   timestamp + server version comment block
 *   - 'rows_per_insert'  (int, 100)     multi-row insert chunk size
 *   - 'include_data'     (bool, true)   default for tables that don't override it
 *   - 'include_structure'(bool, true)   default for tables that don't override it
 *
 * The exporter writes to the stream as it iterates, never materialising
 * full rowsets in memory.
 */
class SqlDumpExporter
{
    /** @var array<string, mixed> */
    private array $defaults = [
        'add_drop' => false,
        'if_not_exists' => true,
        'transactional' => true,
        'disable_fk' => true,
        'add_header' => true,
        'rows_per_insert' => 100,
        'include_data' => true,
        'include_structure' => true,
    ];

    public function __construct(
        private readonly SqlDialectFactory $dialectFactory,
        private readonly SchemaIntrospectionService $introspection,
    ) {}

    /**
     * @param  resource  $stream
     * @param  list<array{name: string, schema?: ?string, structure?: bool, data?: bool}>  $tables
     * @param  array<string, mixed>  $options
     */
    public function dump(
        $stream,
        DatabaseDriverInterface $driver,
        string $database,
        array $tables,
        array $options = [],
    ): void {
        $opts = array_merge($this->defaults, $options);
        $dialect = $this->dialectFactory->for($driver);

        if ($opts['add_header']) {
            $this->writeHeader($stream, $driver, $database);
        }

        $fkOff = $opts['disable_fk'] ? $dialect->disableForeignKeyChecks() : null;
        $fkOn = $opts['disable_fk'] ? $dialect->enableForeignKeyChecks() : null;

        if ($fkOff !== null) {
            $this->writeStatement($stream, $fkOff);
        }
        if ($opts['transactional']) {
            $this->writeStatement($stream, $dialect->transactionStart());
        }

        foreach ($tables as $entry) {
            $name = (string) $entry['name'];
            $schema = $entry['schema'] ?? null;
            $emitStructure = $entry['structure'] ?? $opts['include_structure'];
            $emitData = $entry['data'] ?? $opts['include_data'];

            $tableId = new TableIdentifier(name: $name, schema: $schema, database: $database);
            $this->writeLine($stream, '');
            $this->writeLine($stream, '-- ----------------------------------------------------------');
            $this->writeLine($stream, "-- Table: ".$dialect->qualify($tableId));
            $this->writeLine($stream, '-- ----------------------------------------------------------');

            if ($emitStructure) {
                $this->dumpStructure($stream, $driver, $dialect, $tableId, $opts);
            }
            if ($emitData) {
                $this->dumpData($stream, $driver, $dialect, $tableId, $opts);
            }
        }

        if ($opts['transactional']) {
            $this->writeStatement($stream, $dialect->transactionEnd());
        }
        if ($fkOn !== null) {
            $this->writeStatement($stream, $fkOn);
        }
    }

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $opts
     */
    private function dumpStructure($stream, DatabaseDriverInterface $driver, SqlDialect $dialect, TableIdentifier $table, array $opts): void
    {
        try {
            $detail = $this->introspection->tableDetail($driver, $table);
            $columns = $detail['columns'];
        } catch (Throwable $e) {
            $this->writeLine($stream, '-- !! cannot introspect '.$dialect->qualify($table).' : '.$e->getMessage());

            return;
        }

        if ($opts['add_drop']) {
            $drop = $dialect->dropTableIfExists($table);
            if ($drop !== null) {
                $this->writeStatement($stream, $drop);
            }
        }
        $this->writeStatement($stream, $dialect->createTableDdl($table, $columns, (bool) $opts['if_not_exists']));
    }

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $opts
     */
    private function dumpData($stream, DatabaseDriverInterface $driver, SqlDialect $dialect, TableIdentifier $table, array $opts): void
    {
        $qualified = $dialect->qualify($table);
        $sql = "SELECT * FROM {$qualified}";
        $batchSize = max(1, (int) $opts['rows_per_insert']);

        $buffer = [];
        $columnNames = [];

        foreach ($driver->streamSelect($sql) as $row) {
            if ($columnNames === []) {
                $columnNames = array_keys($row);
            }
            $buffer[] = $this->valuesTuple($dialect, $row);
            if (count($buffer) >= $batchSize) {
                $this->flushInsert($stream, $dialect, $qualified, $columnNames, $buffer);
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            $this->flushInsert($stream, $dialect, $qualified, $columnNames, $buffer);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function valuesTuple(SqlDialect $dialect, array $row): string
    {
        $parts = [];
        foreach ($row as $value) {
            $parts[] = $this->literal($dialect, $value);
        }

        return '('.implode(', ', $parts).')';
    }

    private function literal(SqlDialect $dialect, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_string($value) && ! preg_match('//u', $value)) {
            // Non-UTF-8 string → assume binary, emit hex literal.
            return $dialect->binaryLiteral($value);
        }

        return $dialect->quoteValue($value);
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $columnNames
     * @param  list<string>  $tuples
     */
    private function flushInsert($stream, SqlDialect $dialect, string $qualified, array $columnNames, array $tuples): void
    {
        $cols = implode(', ', array_map(static fn (string $c) => $dialect->quoteIdentifier($c), $columnNames));
        $sql = "INSERT INTO {$qualified} ({$cols}) VALUES\n  ".implode(",\n  ", $tuples).';';
        $this->writeLine($stream, $sql);
    }

    /** @param  resource  $stream */
    private function writeHeader($stream, DatabaseDriverInterface $driver, string $database): void
    {
        $version = '';
        try {
            $version = $driver->version();
        } catch (Throwable) {
        }
        $lines = [
            '-- TableFlip SQL dump',
            '-- Generated : '.now()->toIso8601String(),
            '-- Database  : '.$database,
            '-- Driver    : '.$driver->getDriverName().($version !== '' ? ' / '.$version : ''),
            '-- --',
            '',
        ];
        foreach ($lines as $line) {
            $this->writeLine($stream, $line);
        }
    }

    /** @param  resource  $stream */
    private function writeStatement($stream, string $sql): void
    {
        $this->writeLine($stream, rtrim($sql, "; \t\n").';');
    }

    /** @param  resource  $stream */
    private function writeLine($stream, string $line): void
    {
        fwrite($stream, $line."\n");
    }
}
