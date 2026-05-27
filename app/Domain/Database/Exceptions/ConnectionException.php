<?php

declare(strict_types=1);

namespace App\Domain\Database\Exceptions;

use RuntimeException;
use Throwable;

class ConnectionException extends RuntimeException
{
    public static function failed(string $driver, string $reason, ?Throwable $previous = null): self
    {
        return new self("Cannot connect using [{$driver}] driver: {$reason}", 0, $previous);
    }
}
