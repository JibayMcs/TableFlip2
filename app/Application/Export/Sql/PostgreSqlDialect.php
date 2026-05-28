<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class PostgreSqlDialect extends AbstractSqlDialect
{
    public function getName(): string
    {
        return 'pgsql';
    }

    public function dropTableIfExists(TableIdentifier $table): string
    {
        return 'DROP TABLE IF EXISTS '.$this->qualify($table).' CASCADE';
    }

    public function createTableDdl(TableIdentifier $table, array $columns, bool $ifNotExists = false): string
    {
        $qualified = $this->qualify($table);
        $prefix = $ifNotExists ? 'CREATE TABLE IF NOT EXISTS' : 'CREATE TABLE';

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

        return $prefix.' '.$qualified." (\n".implode(",\n", $lines)."\n)";
    }

    public function disableForeignKeyChecks(): string
    {
        return "SET session_replication_role = 'replica'";
    }

    public function enableForeignKeyChecks(): string
    {
        return "SET session_replication_role = 'origin'";
    }

    public function transactionStart(): string
    {
        return 'BEGIN';
    }

    public function transactionEnd(): string
    {
        return 'COMMIT';
    }

    public function binaryLiteral(string $bytes): string
    {
        return "'\\x".bin2hex($bytes)."'::bytea";
    }

    private function columnLine(ColumnDefinition $col): string
    {
        // Autoincrement on PG : SERIAL is the modern idiom but the actual
        // type stored by information_schema is plain INTEGER + a sequence
        // default. We emit SERIAL when the source flags it, otherwise the
        // raw type from the catalog.
        $type = $col->autoIncrement && in_array(strtolower($col->rawType), ['int4', 'integer', 'int', 'bigint', 'int8'], true)
            ? (str_starts_with(strtolower($col->rawType), 'big') ? 'BIGSERIAL' : 'SERIAL')
            : $col->rawType;

        $parts = [$this->quoteIdentifier($col->name), $type];
        if (! $col->nullable) {
            $parts[] = 'NOT NULL';
        }
        if ($col->default !== null && ! $col->autoIncrement) {
            $parts[] = 'DEFAULT '.$this->defaultLiteral($col);
        }

        return implode(' ', $parts);
    }

    private function defaultLiteral(ColumnDefinition $col): string
    {
        $value = (string) $col->default;
        // PG defaults from the catalog often come in their final SQL form
        // already (nextval('seq'), now(), 'literal'::type) — pass through.
        if (str_contains($value, '::') || str_contains($value, '(') || str_contains($value, "'")) {
            return $value;
        }

        return $this->quoteValue($value);
    }
}
