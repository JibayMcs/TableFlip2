<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\TableIdentifier;

/**
 * Shared boilerplate for the {@see SqlDialect} implementations — delegates
 * identifier quoting / qualification to the driver so we never duplicate
 * those subtle rules. Concrete classes still own the DDL/DML strings that
 * actually vary between engines.
 */
abstract class AbstractSqlDialect implements SqlDialect
{
    public function __construct(protected readonly DatabaseDriverInterface $driver) {}

    public function quoteIdentifier(string $identifier): string
    {
        return $this->driver->quoteIdentifier($identifier);
    }

    public function qualify(TableIdentifier $table): string
    {
        return $this->driver->qualify($table);
    }

    /**
     * Default value-quoting : single-quote escape, doubling embedded single
     * quotes. Sufficient for a dump (pg_dump, mysqldump do the same). Bytes
     * that aren't valid UTF-8 should be routed through {@see binaryLiteral}
     * by the exporter — this method assumes a plain string scalar.
     *
     * Concrete dialects override to add casts (PG bytea, MSSQL 'N' prefix
     * for unicode literals, etc.).
     */
    public function quoteValue(mixed $value): string
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

        $str = (string) $value;
        // Escape : ' → '', and \ → \\ if backslash interpretation is on.
        // Most engines treat backslash as literal inside strings; MySQL by
        // default does NOT (unless NO_BACKSLASH_ESCAPES). We err on the
        // side of MySQL compatibility and double backslashes too.
        $escaped = str_replace(['\\', "'"], ['\\\\', "''"], $str);

        return "'".$escaped."'";
    }
}
