<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

/**
 * Encapsulates per-dialect SQL fragments used by {@see SqlDumpExporter}.
 *
 * Centralised so the exporter stays generic and each driver can decide :
 *   - how to quote identifiers (backticks, double-quotes, brackets)
 *   - how to spell DROP / CREATE / sequence / auto-increment
 *   - how to disable FK checks during a multi-table restore
 *   - how to wrap a dump in a transaction
 *   - how to literal-encode binary values
 *
 * The interface intentionally returns strings (or null when not applicable)
 * so each implementation stays declarative ; the exporter does the I/O.
 */
interface SqlDialect
{
    public function getName(): string;

    /** Quoted identifier — `name` / "name" / [name] depending on driver. */
    public function quoteIdentifier(string $identifier): string;

    /** Fully-qualified table (schema/database aware). */
    public function qualify(TableIdentifier $table): string;

    /** "DROP TABLE IF EXISTS …" (or equivalent) — null if unsupported. */
    public function dropTableIfExists(TableIdentifier $table): ?string;

    /**
     * Minimal CREATE TABLE statement built from ColumnDefinition[].
     * Includes PRIMARY KEY but NOT indexes/foreign keys (those go in
     * separate emit steps if requested).
     *
     * @param  list<ColumnDefinition>  $columns
     */
    public function createTableDdl(TableIdentifier $table, array $columns, bool $ifNotExists = false): string;

    /** "SET FOREIGN_KEY_CHECKS = 0" / "SET session_replication_role = replica" / null. */
    public function disableForeignKeyChecks(): ?string;

    public function enableForeignKeyChecks(): ?string;

    /** Transaction wrappers — usually "START TRANSACTION" / "COMMIT". */
    public function transactionStart(): string;

    public function transactionEnd(): string;

    /**
     * Encode a binary value as a SQL literal (hex form preferred).
     * Receives raw bytes (string).
     */
    public function binaryLiteral(string $bytes): string;

    /**
     * Escape and quote a scalar value for inclusion in an INSERT statement.
     * Drivers may delegate to PDO::quote() if available.
     */
    public function quoteValue(mixed $value): string;
}
