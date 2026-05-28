<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class SqlServerDialect extends AbstractSqlDialect
{
    public function getName(): string
    {
        return 'sqlsrv';
    }

    public function dropTableIfExists(TableIdentifier $table): string
    {
        // SQL Server 2016+ understands DROP TABLE IF EXISTS natively.
        return 'DROP TABLE IF EXISTS '.$this->qualify($table);
    }

    public function createTableDdl(TableIdentifier $table, array $columns, bool $ifNotExists = false): string
    {
        $qualified = $this->qualify($table);

        $lines = [];
        $pkCols = [];
        foreach ($columns as $col) {
            $lines[] = '  '.$this->columnLine($col);
            if ($col->isPrimaryKey) {
                $pkCols[] = $this->quoteIdentifier($col->name);
            }
        }
        if ($pkCols !== []) {
            $lines[] = '  PRIMARY KEY ('.implode(', ', $pkCols).')';
        }

        $body = $qualified." (\n".implode(",\n", $lines)."\n)";
        if (! $ifNotExists) {
            return 'CREATE TABLE '.$body;
        }

        // T-SQL doesn't have CREATE TABLE IF NOT EXISTS — guard with sys.tables.
        $name = $table->name;
        $schema = $table->schema ?? 'dbo';

        return "IF NOT EXISTS (SELECT 1 FROM sys.tables t JOIN sys.schemas s ON s.schema_id = t.schema_id WHERE s.name = '{$schema}' AND t.name = '{$name}')\nCREATE TABLE ".$body;
    }

    public function disableForeignKeyChecks(): ?string
    {
        // T-SQL has no global toggle. The Livewire layer falls back to per-
        // table NOCHECK CONSTRAINT, but that doesn't fit the wrap pattern —
        // we return null and let the dump rely on emitting tables in
        // dependency order (or the user disables checks at restore time).
        return null;
    }

    public function enableForeignKeyChecks(): ?string
    {
        return null;
    }

    public function transactionStart(): string
    {
        return 'BEGIN TRANSACTION';
    }

    public function transactionEnd(): string
    {
        return 'COMMIT TRANSACTION';
    }

    public function binaryLiteral(string $bytes): string
    {
        return '0x'.bin2hex($bytes);
    }

    public function quoteValue(mixed $value): string
    {
        if (is_string($value)) {
            // 'N' prefix tells T-SQL to treat the literal as NVARCHAR (unicode).
            return 'N'.parent::quoteValue($value);
        }

        return parent::quoteValue($value);
    }

    private function columnLine(ColumnDefinition $col): string
    {
        $parts = [$this->quoteIdentifier($col->name), $col->rawType];
        if ($col->autoIncrement) {
            $parts[] = 'IDENTITY(1,1)';
        }
        if (! $col->nullable) {
            $parts[] = 'NOT NULL';
        } else {
            $parts[] = 'NULL';
        }
        if ($col->default !== null && ! $col->autoIncrement) {
            $parts[] = 'DEFAULT '.$this->quoteValue((string) $col->default);
        }

        return implode(' ', $parts);
    }
}
