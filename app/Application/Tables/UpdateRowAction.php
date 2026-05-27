<?php

declare(strict_types=1);

namespace App\Application\Tables;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\Exceptions\RowEditingException;
use App\Domain\Database\ValueObjects\TableIdentifier;

class UpdateRowAction
{
    public function __construct(private readonly TableOperationLogger $logger) {}

    /**
     * @param  array<string, mixed>  $rowKey   column => current value (PK columns when available,
     *                                         otherwise ALL columns of the original row)
     * @param  array<string, mixed>  $changes  column => new value
     *
     * Pre-flight COUNT check ensures the WHERE clause matches exactly one row
     * before we mutate anything — this is the safety net for tables without a
     * primary key.
     */
    public function execute(
        DatabaseDriverInterface $driver,
        TableIdentifier $table,
        array $rowKey,
        array $changes,
    ): int {
        if ($changes === []) {
            return 0;
        }
        if ($rowKey === []) {
            throw RowEditingException::tableNotEditable('no row identifier available');
        }

        [$whereClause, $whereBindings] = $this->buildWhere($driver, $rowKey);
        $this->assertExactlyOneMatch($driver, $table, $whereClause, $whereBindings);

        $quotedSetCols = array_map(
            fn (string $c) => $driver->quoteIdentifier($c).' = ?',
            array_keys($changes),
        );

        $sql = 'UPDATE '.$driver->qualify($table).
               ' SET '.implode(', ', $quotedSetCols).
               ' WHERE '.$whereClause;

        $bindings = [...array_values($changes), ...$whereBindings];
        $affected = $driver->statement($sql, $bindings);

        $this->logger->log('update', $table, $sql, $bindings, $affected);

        return $affected;
    }

    /**
     * @param  array<string, mixed>  $rowKey
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildWhere(DatabaseDriverInterface $driver, array $rowKey): array
    {
        $clauses = [];
        $bindings = [];
        foreach ($rowKey as $col => $value) {
            $quoted = $driver->quoteIdentifier($col);
            if ($value === null) {
                $clauses[] = "{$quoted} IS NULL";
            } else {
                $clauses[] = "{$quoted} = ?";
                $bindings[] = $value;
            }
        }

        return [implode(' AND ', $clauses), $bindings];
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function assertExactlyOneMatch(
        DatabaseDriverInterface $driver,
        TableIdentifier $table,
        string $whereClause,
        array $bindings,
    ): void {
        $countSql = 'SELECT COUNT(*) AS c FROM '.$driver->qualify($table).' WHERE '.$whereClause;
        $result = $driver->select($countSql, $bindings);
        $rows = is_array($result->rows) ? $result->rows : iterator_to_array($result->rows);
        $count = (int) ($rows[0]['c'] ?? 0);

        if ($count === 0) {
            throw RowEditingException::rowDisappeared();
        }
        if ($count > 1) {
            throw RowEditingException::ambiguousRow($count);
        }
    }
}
