<?php

declare(strict_types=1);

namespace App\Domain\Sql;

final readonly class SqlExecutionResult
{
    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    public function __construct(
        public bool $isWrite,
        public iterable $rows,
        public array $columns,
        public int $affectedRows,
        public float $durationMs,
        public ?string $error = null,
        public bool $truncated = false,
    ) {}

    public static function read(iterable $rows, array $columns, float $durationMs, bool $truncated = false): self
    {
        return new self(false, $rows, $columns, 0, $durationMs, null, $truncated);
    }

    public static function write(int $affectedRows, float $durationMs): self
    {
        return new self(true, [], [], $affectedRows, $durationMs);
    }

    public static function failure(string $error, float $durationMs, bool $isWrite = false): self
    {
        return new self($isWrite, [], [], 0, $durationMs, $error);
    }

    public function succeeded(): bool
    {
        return $this->error === null;
    }
}
