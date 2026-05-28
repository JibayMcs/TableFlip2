<?php

declare(strict_types=1);

namespace App\Application\Tables;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\TableIdentifier;

/**
 * Empty a table's content (rows) while preserving its structure. Mirrors
 * SQL TRUNCATE semantics: instant, no per-row triggers, resets auto_increment.
 *
 * SQLite has no native TRUNCATE so we fall back to `DELETE FROM` + a
 * sequence reset.
 */
class TruncateTableAction
{
    public function __construct(private readonly TableOperationLogger $logger) {}

    public function execute(DatabaseDriverInterface $driver, TableIdentifier $table): int
    {
        $qualified = $driver->qualify($table);
        $driverName = $driver->getDriverName();

        if ($driverName === 'sqlite') {
            $sql = "DELETE FROM {$qualified}";
            $affected = $driver->statement($sql);
            // Best-effort sequence reset; ignore failure if sqlite_sequence
            // doesn't exist (table never had an autoincrement column).
            try {
                $driver->statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table->name]);
            } catch (\Throwable) {
            }
        } else {
            $sql = "TRUNCATE TABLE {$qualified}";
            $affected = $driver->statement($sql);
        }

        $this->logger->log('truncate', $table, $sql, [], $affected);

        return $affected;
    }
}
