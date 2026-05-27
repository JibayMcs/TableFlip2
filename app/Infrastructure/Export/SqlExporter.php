<?php

declare(strict_types=1);

namespace App\Infrastructure\Export;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;
use App\Domain\Export\ExportContext;
use App\Domain\Export\ExporterInterface;
use LogicException;

class SqlExporter implements ExporterInterface
{
    /**
     * Options:
     *   - include_drop      : bool, default false  → emit `DROP TABLE IF EXISTS ...;`
     *   - include_create    : bool, default false  → emit a minimal `CREATE TABLE ...;`
     *   - multi_row_insert  : bool, default true   → batch rows in one INSERT VALUES (..),(..),..
     *   - rows_per_insert   : int,  default 100    → batch size when multi_row
     */
    public function export($stream, iterable $rows, ExportContext $context): void
    {
        if ($context->sourceTable === null) {
            throw new LogicException('SQL export requires a source table to build INSERT statements.');
        }

        $driver = $context->driver;
        $table = $context->sourceTable;
        $qualified = $driver->qualify($table);
        $quotedCols = array_map(static fn (string $c) => $driver->quoteIdentifier($c), $context->columns);

        $this->writeHeader($stream, $driver, $table, $qualified, $context);

        $multiRow = (bool) $context->option('multi_row_insert', true);
        $batchSize = max(1, (int) $context->option('rows_per_insert', 100));
        $insertPrefix = "INSERT INTO {$qualified} (".implode(', ', $quotedCols).") VALUES\n";

        if ($multiRow) {
            $batch = [];
            foreach ($rows as $row) {
                $batch[] = '  ('.$this->formatRow($row, $context->columns).')';
                if (count($batch) >= $batchSize) {
                    fwrite($stream, $insertPrefix.implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                fwrite($stream, $insertPrefix.implode(",\n", $batch).";\n");
            }

            return;
        }

        $singlePrefix = "INSERT INTO {$qualified} (".implode(', ', $quotedCols).') VALUES ';
        foreach ($rows as $row) {
            fwrite($stream, $singlePrefix.'('.$this->formatRow($row, $context->columns).");\n");
        }
    }

    /**
     * @param  resource  $stream
     */
    private function writeHeader($stream, DatabaseDriverInterface $driver, TableIdentifier $table, string $qualified, ExportContext $context): void
    {
        fwrite($stream, '-- TableFlip export · '.now()->toIso8601String()."\n");
        fwrite($stream, "-- Source: {$qualified}\n\n");

        if ((bool) $context->option('include_drop', false)) {
            fwrite($stream, "DROP TABLE IF EXISTS {$qualified};\n\n");
        }

        if ((bool) $context->option('include_create', false) && $context->columnDefinitions !== []) {
            fwrite($stream, $this->buildCreateTable($driver, $table, $context->columnDefinitions)."\n\n");
        }
    }

    /**
     * Minimal CREATE TABLE built from the source introspection. Carries
     * column raw types verbatim, NOT NULL, defaults, AUTO_INCREMENT and a
     * single PRIMARY KEY clause. Indexes, FKs and engine options are left
     * out — V1 produces a portable enough script for re-importing into the
     * same dialect, not a full DDL dump.
     *
     * @param  list<ColumnDefinition>  $columns
     */
    private function buildCreateTable(DatabaseDriverInterface $driver, TableIdentifier $table, array $columns): string
    {
        $lines = [];
        $pkCols = [];

        foreach ($columns as $col) {
            $line = '  '.$driver->quoteIdentifier($col->name).' '.$col->rawType;
            if (! $col->nullable) {
                $line .= ' NOT NULL';
            }
            if ($col->default !== null && ! $col->autoIncrement) {
                $line .= ' DEFAULT '.$this->formatValue($col->default);
            }
            if ($col->autoIncrement) {
                $line .= ' AUTO_INCREMENT';
            }
            $lines[] = $line;
            if ($col->isPrimaryKey) {
                $pkCols[] = $col->name;
            }
        }

        if ($pkCols !== []) {
            $quoted = array_map(static fn (string $c) => $driver->quoteIdentifier($c), $pkCols);
            $lines[] = '  PRIMARY KEY ('.implode(', ', $quoted).')';
        }

        return 'CREATE TABLE '.$driver->qualify($table)." (\n".implode(",\n", $lines)."\n);";
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    private function formatRow(array $row, array $columns): string
    {
        return implode(', ', array_map(
            fn (string $col) => $this->formatValue($row[$col] ?? null),
            $columns,
        ));
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        // Strings (and everything else cast to string): escape single quotes by doubling.
        return "'".str_replace("'", "''", (string) $value)."'";
    }
}
