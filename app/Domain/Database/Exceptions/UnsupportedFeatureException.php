<?php

declare(strict_types=1);

namespace App\Domain\Database\Exceptions;

use RuntimeException;

class UnsupportedFeatureException extends RuntimeException
{
    public static function for(string $driver, string $feature): self
    {
        return new self("Driver [{$driver}] does not support feature [{$feature}].");
    }
}
