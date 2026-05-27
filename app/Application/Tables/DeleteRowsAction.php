<?php

declare(strict_types=1);

namespace App\Application\Tables;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\Exceptions\RowEditingException;
use App\Domain\Database\ValueObjects\TableIdentifier;

class DeleteRowsAction
{
    public function __construct(private readonly TableOperationLogger $logger) {}

    /**
     * Delete the rows identified by the given keys.
     *
     * Each key in $rowKeys is a column => value map. For tables with a PK,
     * only the PK columns are provided. For tables without a PK, ALL original
     * column values are provided. A pre-flight COUNT check is performed for
     * EACH row so we never mass-delete by accident.
     *
     * @param  list<array<string, mixed>>  $rowKeys
     */
    public function execute(
        DatabaseDriverInterface $driver,
        TableIdentifier $table,
        array $rowKeys,
    ): int {
        if ($rowKeys === []) {
            return 0;
        }

        $totalAffected = 0;

        $driver->transaction(function () use ($driver, $table, $rowKeys, &$totalAffected): void {
            foreach ($rowKeys as $rowKey) {
                $totalAffected += $this->deleteOne($driver, $table, $rowKey);
            }
        });

        return $totalAffected;
    }

    /**
     * @param  array<string, mixed>  $rowKey
     */
    private function deleteOne(DatabaseDriverInterface $driver, TableIdentifier $table, array $rowKey): int
    {
        if ($rowKey === []) {
            throw RowEditingException::tableNotEditable('no row identifier available');
        }

        [$whereClause, $whereBindings] = $this->buildWhere($driver, $rowKey);

        $countSql = 'SELECT COUNT(*) AS c FROM '.$driver->qualify($table).' WHERE '.$whereClause;
        $result = $driver->select($countSql, $whereBindings);
        $rows = is_array($result->rows) ? $result->rows : iterator_to_array($result->rows);
        $count = (int) ($rows[0]['c'] ?? 0);

        if ($count === 0) {
            throw RowEditingException::rowDisappeared();
        }
        if ($count > 1) {
            throw RowEditingException::ambiguousRow($count);
        }

        $sql = 'DELETE FROM '.$driver->qualify($table).' WHERE '.$whereClause;
        $affected = $driver->statement($sql, $whereBindings);

        $this->logger->log('delete', $table, $sql, $whereBindings, $affected);

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
}
