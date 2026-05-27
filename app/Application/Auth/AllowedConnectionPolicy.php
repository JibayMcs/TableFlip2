<?php

declare(strict_types=1);

namespace App\Application\Auth;

final readonly class AllowedConnectionPolicy
{
    /**
     * @param  list<string>  $allowedHosts
     * @param  list<string>  $allowedDrivers
     * @param  list<string>  $allowedDatabases
     */
    public function __construct(
        public array $allowedHosts = [],
        public array $allowedDrivers = [],
        public array $allowedDatabases = [],
    ) {}

    public static function fromConfig(): self
    {
        /** @var array<string, list<string>> $scope */
        $scope = config('tableflip.allowed_db');

        return new self(
            allowedHosts: $scope['hosts'] ?? [],
            allowedDrivers: $scope['drivers'] ?? [],
            allowedDatabases: $scope['databases'] ?? [],
        );
    }

    public function isAllowed(string $host, string $driver, string $database): bool
    {
        return $this->matches($host, $this->allowedHosts)
            && $this->matches($driver, $this->allowedDrivers)
            && $this->matches($database, $this->allowedDatabases);
    }

    public function hostLocked(): bool
    {
        return count($this->allowedHosts) === 1;
    }

    public function driverLocked(): bool
    {
        return count($this->allowedDrivers) === 1;
    }

    public function databaseLocked(): bool
    {
        return count($this->allowedDatabases) === 1;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function matches(string $value, array $allowed): bool
    {
        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $pattern) {
            if (fnmatch($pattern, $value, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}
