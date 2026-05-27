<?php

declare(strict_types=1);

namespace App\Application\Schema;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\Query\Filter;
use App\Domain\Database\Query\FilterOperator;
use App\Domain\Database\Query\Sort;
use App\Domain\Database\ValueObjects\TableIdentifier;

class TableDataQueryService
{
    /**
     * Paginated SELECT against the given table, with optional WHERE filters
     * and ORDER BY sort. All user-supplied identifiers are quoted via the
     * driver and all values are passed as bound parameters.
     *
     * @param  list<Filter>  $filters
     * @param  list<Sort>  $sort
     * @return array{rows: list<array<string, mixed>>, total: int, columns: list<string>}
     */
    public function query(
        DatabaseDriverInterface $driver,
        TableIdentifier $table,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $perPage = 50,
    ): array {
        $qualified = $driver->qualify($table);

        [$whereClause, $bindings] = $this->buildWhere($driver, $filters);
        $orderClause = $this->buildOrder($driver, $sort);

        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));
        $offset = ($page - 1) * $perPage;

        $dataSql = "SELECT * FROM {$qualified}{$whereClause}{$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $countSql = "SELECT COUNT(*) AS c FROM {$qualified}{$whereClause}";

        $dataResult = $driver->select($dataSql, $bindings);
        $rows = is_array($dataResult->rows) ? $dataResult->rows : iterator_to_array($dataResult->rows);

        $countResult = $driver->select($countSql, $bindings);
        $countRows = is_array($countResult->rows) ? $countResult->rows : iterator_to_array($countResult->rows);
        $total = (int) ($countRows[0]['c'] ?? 0);

        return [
            'rows' => $rows,
            'total' => $total,
            'columns' => $dataResult->columns,
        ];
    }

    /**
     * @param  list<Filter>  $filters
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildWhere(DatabaseDriverInterface $driver, array $filters): array
    {
        if ($filters === []) {
            return ['', []];
        }

        $clauses = [];
        $bindings = [];

        foreach ($filters as $f) {
            if ($f->column === '') {
                continue;
            }
            [$sql, $vals] = $this->buildClause($driver, $f);
            if ($sql === null) {
                continue;
            }
            $clauses[] = $sql;
            $bindings = array_merge($bindings, $vals);
        }

        return $clauses === [] ? ['', []] : [' WHERE '.implode(' AND ', $clauses), $bindings];
    }

    /**
     * @return array{0: ?string, 1: array<int, mixed>}
     */
    private function buildClause(DatabaseDriverInterface $driver, Filter $filter): array
    {
        $col = $driver->quoteIdentifier($filter->column);
        $value = $filter->value;

        return match ($filter->operator) {
            FilterOperator::IS_NULL => ["{$col} IS NULL", []],
            FilterOperator::IS_NOT_NULL => ["{$col} IS NOT NULL", []],
            FilterOperator::CONTAINS => ["{$col} LIKE ?", ['%'.$this->escapeLike((string) $value).'%']],
            FilterOperator::NOT_CONTAINS => ["{$col} NOT LIKE ?", ['%'.$this->escapeLike((string) $value).'%']],
            FilterOperator::STARTS_WITH => ["{$col} LIKE ?", [$this->escapeLike((string) $value).'%']],
            FilterOperator::ENDS_WITH => ["{$col} LIKE ?", ['%'.$this->escapeLike((string) $value)]],
            FilterOperator::IN => $this->buildInClause($col, (string) $value),
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
            FilterOperator::GT,
            FilterOperator::GTE,
            FilterOperator::LT,
            FilterOperator::LTE => ["{$col} {$filter->operator->value} ?", [$value]],
        };
    }

    /**
     * @return array{0: ?string, 1: array<int, string>}
     */
    private function buildInClause(string $quotedColumn, string $rawValue): array
    {
        $values = array_values(array_filter(array_map('trim', explode(',', $rawValue)), static fn ($v) => $v !== ''));
        if ($values === []) {
            return [null, []];
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return ["{$quotedColumn} IN ({$placeholders})", $values];
    }

    /**
     * @param  list<Sort>  $sort
     */
    private function buildOrder(DatabaseDriverInterface $driver, array $sort): string
    {
        if ($sort === []) {
            return '';
        }

        $parts = array_map(
            fn (Sort $s) => $driver->quoteIdentifier($s->column).' '.strtoupper($s->direction->value),
            $sort,
        );

        return ' ORDER BY '.implode(', ', $parts);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Same WHERE/ORDER BY/LIMIT/OFFSET pipeline as {@see query()} but returns
     * the SQL string + bindings without executing. Used by the export pipeline
     * which streams rows itself via the driver's streamSelect().
     *
     * @param  list<Filter>  $filters
     * @param  list<Sort>  $sort
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function buildSelectForExport(
        DatabaseDriverInterface $driver,
        TableIdentifier $table,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $perPage = 1_000_000_000,
    ): array {
        $qualified = $driver->qualify($table);
        [$whereClause, $bindings] = $this->buildWhere($driver, $filters);
        $orderClause = $this->buildOrder($driver, $sort);

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$qualified}{$whereClause}{$orderClause} LIMIT {$perPage} OFFSET {$offset}";

        return [$sql, $bindings];
    }
}
