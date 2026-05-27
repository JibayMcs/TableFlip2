<?php

declare(strict_types=1);

namespace App\Domain\Database\Query;

final readonly class Filter
{
    public function __construct(
        public string $column,
        public FilterOperator $operator,
        public string|int|float|null $value = null,
    ) {}
}
