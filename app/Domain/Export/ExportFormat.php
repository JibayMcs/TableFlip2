<?php

declare(strict_types=1);

namespace App\Domain\Export;

enum ExportFormat: string
{
    case CSV = 'csv';
    case SQL = 'sql';
    case JSON = 'json';

    public function fileExtension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::SQL => 'application/sql',
            self::JSON => 'application/json',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CSV => 'CSV',
            self::SQL => 'SQL (INSERT)',
            self::JSON => 'JSON',
        };
    }
}
