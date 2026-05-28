<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

class SqliteDialect extends AbstractSqlDialect
{
    public function getName(): string
    {
        return 'sqlite';
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
        // SQLite : when a single INTEGER PRIMARY KEY column is also
        // AUTOINCREMENT, the PRIMARY KEY is declared inline (see
        // columnLine). Otherwise add the constraint at the end.
        $hasInlinePk = count($pkCols) === 1
            && (function () use ($columns): bool {
                foreach ($columns as $c) {
                    if ($c->isPrimaryKey && $c->autoIncrement && stripos($c->rawType, 'int') !== false) {
                        return true;
                    }
                }
                return false;
            })();

        if ($pkCols !== [] && ! $hasInlinePk) {
            $lines[] = '  PRIMARY KEY ('.implode(', ', $pkCols).')';
        }

        return $prefix.' '.$qualified." (\n".implode(",\n", $lines)."\n)";
    }

    public function disableForeignKeyChecks(): string
    {
        return 'PRAGMA foreign_keys = OFF';
    }

    public function enableForeignKeyChecks(): string
    {
        return 'PRAGMA foreign_keys = ON';
    }

    public function transactionStart(): string
    {
        return 'BEGIN TRANSACTION';
    }

    public function transactionEnd(): string
    {
        return 'COMMIT';
    }

    public function binaryLiteral(string $bytes): string
    {
        return "X'".bin2hex($bytes)."'";
    }

    private function columnLine(ColumnDefinition $col): string
    {
        $parts = [$this->quoteIdentifier($col->name), $col->rawType];

        // SQLite rowid alias trick : "INTEGER PRIMARY KEY AUTOINCREMENT"
        // gives you a real auto-incrementing PK. Other columns just get
        // their type + NULL/NOT NULL.
        if ($col->isPrimaryKey && $col->autoIncrement && stripos($col->rawType, 'int') !== false) {
            $parts[] = 'PRIMARY KEY AUTOINCREMENT';
        } elseif (! $col->nullable) {
            $parts[] = 'NOT NULL';
        }

        if ($col->default !== null) {
            $parts[] = 'DEFAULT '.$this->quoteValue((string) $col->default);
        }

        return implode(' ', $parts);
    }
}
