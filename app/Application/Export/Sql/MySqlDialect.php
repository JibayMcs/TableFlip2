<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class MySqlDialect extends AbstractSqlDialect
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function dropTableIfExists(TableIdentifier $table): string
    {
        return 'DROP TABLE IF EXISTS '.$this->qualify($table);
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

        return $prefix.' '.$qualified." (\n".implode(",\n", $lines)."\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    public function disableForeignKeyChecks(): string
    {
        return 'SET FOREIGN_KEY_CHECKS = 0';
    }

    public function enableForeignKeyChecks(): string
    {
        return 'SET FOREIGN_KEY_CHECKS = 1';
    }

    public function transactionStart(): string
    {
        return 'START TRANSACTION';
    }

    public function transactionEnd(): string
    {
        return 'COMMIT';
    }

    public function binaryLiteral(string $bytes): string
    {
        return '0x'.bin2hex($bytes);
    }

    private function columnLine(ColumnDefinition $col): string
    {
        $parts = [$this->quoteIdentifier($col->name), $col->rawType];
        if (! $col->nullable) {
            $parts[] = 'NOT NULL';
        }
        if ($col->autoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
        }
        if ($col->default !== null) {
            $parts[] = 'DEFAULT '.$this->defaultLiteral($col);
        } elseif ($col->nullable) {
            $parts[] = 'DEFAULT NULL';
        }
        if ($col->comment !== null && $col->comment !== '') {
            $parts[] = "COMMENT ".$this->quoteValue($col->comment);
        }

        return implode(' ', $parts);
    }

    private function defaultLiteral(ColumnDefinition $col): string
    {
        $value = (string) $col->default;
        // Pass through SQL functions like CURRENT_TIMESTAMP / NOW() — they
        // are already in their final form.
        if (str_contains(strtoupper($value), 'CURRENT_TIMESTAMP') || str_ends_with($value, '()')) {
            return $value;
        }

        return $this->quoteValue($value);
    }
}
