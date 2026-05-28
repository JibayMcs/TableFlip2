<?php

declare(strict_types=1);

namespace App\Application\Export\Sql;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use RuntimeException;

class SqlDialectFactory
{
    public function for(DatabaseDriverInterface $driver): SqlDialect
    {
        return match ($driver->getDriverName()) {
            'mysql', 'mariadb' => new MySqlDialect($driver),
            'pgsql' => new PostgreSqlDialect($driver),
            'sqlsrv' => new SqlServerDialect($driver),
            'sqlite' => new SqliteDialect($driver),
            default => throw new RuntimeException(
                "No SqlDialect registered for driver [{$driver->getDriverName()}]."
            ),
        };
    }
}
