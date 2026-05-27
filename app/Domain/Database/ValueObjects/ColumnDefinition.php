<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

final readonly class ColumnDefinition
{
    /**
     * @param  list<string>|null  $enumValues
     */
    public function __construct(
        public string $name,
        public string $rawType,
        public ColumnType $type,
        public bool $nullable,
        public string|int|float|bool|null $default = null,
        public bool $autoIncrement = false,
        public bool $isPrimaryKey = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public ?array $enumValues = null,
        public ?string $comment = null,
    ) {}
}
