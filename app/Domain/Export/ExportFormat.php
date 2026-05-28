<?php

declare(strict_types=1);

namespace App\Domain\Export;

enum ExportFormat: string
{
    case CSV = 'csv';
    case SQL = 'sql';
    case JSON = 'json';
    case SQL_DUMP = 'sql_dump';

    public function fileExtension(): string
    {
        return match ($this) {
            self::SQL_DUMP => 'sql',
            default => $this->value,
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::SQL, self::SQL_DUMP => 'application/sql',
            self::JSON => 'application/json',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CSV => 'CSV',
            self::SQL => 'SQL (INSERT)',
            self::SQL_DUMP => 'SQL dump (full)',
            self::JSON => 'JSON',
        };
    }
}
