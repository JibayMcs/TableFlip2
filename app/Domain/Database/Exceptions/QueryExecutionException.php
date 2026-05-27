<?php

declare(strict_types=1);

namespace App\Domain\Database\Exceptions;

use RuntimeException;
use Throwable;

class QueryExecutionException extends RuntimeException
{
    public readonly string $sql;

    /** @var array<int|string, mixed> */
    public readonly array $bindings;

    /**
     * @param  array<int|string, mixed>  $bindings
     */
    public function __construct(string $message, string $sql, array $bindings = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->sql = $sql;
        $this->bindings = $bindings;
    }
}
