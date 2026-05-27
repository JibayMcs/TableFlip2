<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

final readonly class ForeignKeyDefinition
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $referencedColumns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public TableIdentifier $referencedTable,
        public array $referencedColumns,
        public ?string $onUpdate = null,
        public ?string $onDelete = null,
    ) {}
}
