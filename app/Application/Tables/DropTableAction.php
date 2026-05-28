<?php

declare(strict_types=1);

namespace App\Application\Tables;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\TableIdentifier;

/**
 * Drop a table from the database. Irreversible — the caller is responsible
 * for confirming user intent before invoking this.
 */
class DropTableAction
{
    public function __construct(private readonly TableOperationLogger $logger) {}

    public function execute(DatabaseDriverInterface $driver, TableIdentifier $table): int
    {
        $qualified = $driver->qualify($table);
        $sql = "DROP TABLE {$qualified}";
        $affected = $driver->statement($sql);

        $this->logger->log('drop', $table, $sql, [], $affected);

        return $affected;
    }
}
