<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\Exceptions\UnsupportedFeatureException;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Infrastructure\Database\Drivers\MySqlDriver;
use App\Infrastructure\Database\Drivers\PostgreSqlDriver;
use App\Infrastructure\Database\Drivers\SqliteDriver;
use App\Infrastructure\Database\Drivers\SqlServerDriver;

class DatabaseDriverFactory
{
    /** @var array<string, class-string<DatabaseDriverInterface>> */
    private const DRIVERS = [
        'mysql' => MySqlDriver::class,
        'mariadb' => MySqlDriver::class,
        'pgsql' => PostgreSqlDriver::class,
        'sqlite' => SqliteDriver::class,
        'sqlsrv' => SqlServerDriver::class,
    ];

    public function create(ConnectionConfig $config): DatabaseDriverInterface
    {
        $class = self::DRIVERS[$config->driver] ?? null;

        if ($class === null) {
            throw UnsupportedFeatureException::for($config->driver, 'unknown driver');
        }

        return new $class($config);
    }

    /**
     * @return list<string>
     */
    public function supportedDrivers(): array
    {
        return array_keys(self::DRIVERS);
    }
}
