<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

final readonly class QueryResult
{
    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    public function __construct(
        public iterable $rows,
        public array $columns,
        public int $affectedRows,
        public float $executionTimeMs,
    ) {}
}
