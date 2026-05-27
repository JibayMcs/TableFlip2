<?php

declare(strict_types=1);

namespace App\Application\Tables;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\TableIdentifier;

class InsertRowAction
{
    public function __construct(private readonly TableOperationLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data  column => value
     */
    public function execute(DatabaseDriverInterface $driver, TableIdentifier $table, array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $cols = array_keys($data);
        $quotedCols = array_map(fn (string $c) => $driver->quoteIdentifier($c), $cols);
        $placeholders = array_fill(0, count($cols), '?');

        $sql = 'INSERT INTO '.$driver->qualify($table).
               ' ('.implode(', ', $quotedCols).')'.
               ' VALUES ('.implode(', ', $placeholders).')';

        $bindings = array_values($data);
        $affected = $driver->statement($sql, $bindings);

        $this->logger->log('insert', $table, $sql, $bindings, $affected);

        return $affected;
    }
}
