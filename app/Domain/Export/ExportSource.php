<?php

declare(strict_types=1);

namespace App\Domain\Export;

use App\Domain\Database\Query\Filter;
use App\Domain\Database\Query\Sort;
use App\Domain\Database\ValueObjects\TableIdentifier;

/**
 * What rows to export. The action layer resolves this into an actual streaming
 * SELECT against the driver.
 *
 * Two flavours :
 *  - {@see fromTable()} : structured (table + filters + sort), used by the natural
 *    explorer view. The action can skip pagination when the user picks "all rows".
 *  - {@see fromRawSql()} : a SQL string verbatim, used by the SQL editor and the
 *    explorer's scratch pad. No row-count cap — the user typed the LIMIT they want.
 */
final readonly class ExportSource
{
    /**
     * @param  list<Filter>  $filters
     * @param  list<Sort>  $sort
     */
    private function __construct(
        public string $kind,        // 'table' | 'raw_sql'
        public ?TableIdentifier $table = null,
        public array $filters = [],
        public array $sort = [],
        public ?string $rawSql = null,
        public ?string $database = null,
    ) {}

    /**
     * @param  list<Filter>  $filters
     * @param  list<Sort>  $sort
     */
    public static function fromTable(TableIdentifier $table, array $filters = [], array $sort = []): self
    {
        return new self(kind: 'table', table: $table, filters: $filters, sort: $sort, database: $table->database);
    }

    public static function fromRawSql(string $sql, ?string $database = null, ?TableIdentifier $sourceTable = null): self
    {
        return new self(kind: 'raw_sql', rawSql: $sql, database: $database, table: $sourceTable);
    }

    public function isTable(): bool
    {
        return $this->kind === 'table';
    }

    public function isRawSql(): bool
    {
        return $this->kind === 'raw_sql';
    }
}
