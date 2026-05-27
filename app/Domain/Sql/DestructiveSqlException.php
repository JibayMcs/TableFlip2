<?php

declare(strict_types=1);

namespace App\Domain\Sql;

use RuntimeException;

class DestructiveSqlException extends RuntimeException
{
    public function __construct(public readonly string $detectedKeyword, public readonly string $reason)
    {
        parent::__construct("Destructive SQL detected: {$reason}");
    }
}
