<?php

declare(strict_types=1);

namespace App\Application\Auth;

final readonly class DirectDbCredentials
{
    public function __construct(
        public string $driver,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {}
}
