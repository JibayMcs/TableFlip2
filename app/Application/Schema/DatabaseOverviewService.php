<?php

declare(strict_types=1);

namespace App\Application\Schema;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use Throwable;

/**
 * Builds the payload powering the "Database Overview" panel in the
 * Explorer. Combines (in one shot):
 *   - listTables + listViews
 *   - estimateRowCount per table (driver bulk catalog query)
 *   - tableSizes (data + index bytes per table)
 *
 * The result is a list of enriched rows ready to be rendered as a card
 * grid or a flat list.
 */
class DatabaseOverviewService
{
    public function __construct(
        private readonly SchemaIntrospectionService $introspection,
    ) {}

    /**
     * @return array{
     *   tables: list<array{
     *     name: string,
     *     type: 'table'|'view',
     *     schema: ?string,
     *     rows: ?int,
     *     size: ?int,
     *   }>,
     *   totals: array{count: int, rows: int, size: int},
     * }
     */
    public function overview(DatabaseDriverInterface $driver, string $database, ?string $schema = null): array
    {
        $effective = $this->introspection->driverFor($driver, $database);

        $tables = [];
        $views = [];
        try {
            $tables = $effective->listTables($database, $schema);
        } catch (Throwable) {
            $tables = [];
        }
        try {
            $views = $effective->listViews($database, $schema);
        } catch (Throwable) {
            $views = [];
        }

        // Bulk size map, lookup by lowercase name.
        $sizes = [];
        try {
            $sizes = $effective->tableSizes($database, $schema);
        } catch (Throwable) {
            $sizes = [];
        }

        $out = [];
        $totalRows = 0;
        $totalSize = 0;

        foreach ($tables as $t) {
            $rows = $this->safeRowCount($effective, $t);
            $size = $sizes[strtolower($t->name)] ?? null;
            $out[] = [
                'name' => $t->name,
                'type' => 'table',
                'schema' => $t->schema,
                'rows' => $rows,
                'size' => $size,
            ];
            $totalRows += $rows ?? 0;
            $totalSize += $size ?? 0;
        }

        foreach ($views as $v) {
            // Views don't have rows/size in the catalog (or it's misleading
            // — depends on the underlying SELECT). Show them as N/A.
            $out[] = [
                'name' => $v->name,
                'type' => 'view',
                'schema' => $v->schema,
                'rows' => null,
                'size' => null,
            ];
        }

        return [
            'tables' => $out,
            'totals' => [
                'count' => count($out),
                'rows' => $totalRows,
                'size' => $totalSize,
            ],
        ];
    }

    private function safeRowCount(DatabaseDriverInterface $driver, $table): ?int
    {
        try {
            return $driver->estimateRowCount($table);
        } catch (Throwable) {
            return null;
        }
    }
}
