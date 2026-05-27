<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

final readonly class Page
{
    /**
     * @param  iterable<int, array<string, mixed>>  $items
     */
    public function __construct(
        public iterable $items,
        public int $number,
        public int $size,
        public ?int $total = null,
    ) {}

    public function totalPages(): ?int
    {
        if ($this->total === null || $this->size <= 0) {
            return null;
        }

        return (int) ceil($this->total / $this->size);
    }

    public function hasPrevious(): bool
    {
        return $this->number > 1;
    }

    public function hasNext(): bool
    {
        $totalPages = $this->totalPages();

        return $totalPages === null ? true : $this->number < $totalPages;
    }
}
