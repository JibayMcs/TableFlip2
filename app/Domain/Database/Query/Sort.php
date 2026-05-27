<?php

declare(strict_types=1);

namespace App\Domain\Database\Query;

final readonly class Sort
{
    public function __construct(
        public string $column,
        public SortDirection $direction = SortDirection::ASC,
    ) {}
}
