<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

use Stringable;

final readonly class TableIdentifier implements Stringable
{
    public function __construct(
        public string $name,
        public ?string $schema = null,
        public ?string $database = null,
    ) {}

    public function __toString(): string
    {
        return implode('.', array_filter([$this->database, $this->schema, $this->name]));
    }
}
