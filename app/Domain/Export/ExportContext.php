<?php

declare(strict_types=1);

namespace App\Domain\Export;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\TableIdentifier;

/**
 * Everything an exporter may need beyond the raw rows :
 *  - column names (so CSV/JSON know the header order)
 *  - column definitions for the SQL exporter (PK, types, etc. for CREATE TABLE)
 *  - source table identifier (for SQL exporter's INSERT INTO ...)
 *  - the driver (for dialect-aware identifier quoting)
 *  - format-specific options bag.
 */
final readonly class ExportContext
{
    /**
     * @param  list<string>  $columns
     * @param  list<ColumnDefinition>  $columnDefinitions
     * @param  array<string, mixed>  $options  format-specific knobs (see each exporter)
     */
    public function __construct(
        public array $columns,
        public array $columnDefinitions,
        public ?TableIdentifier $sourceTable,
        public DatabaseDriverInterface $driver,
        public array $options = [],
    ) {}

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
