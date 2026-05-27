<?php

declare(strict_types=1);

namespace App\Application\Schema;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\TableIdentifier;
use Throwable;

/**
 * Walk a database's tables + foreign keys and emit a Cytoscape elements
 * payload (`nodes`, `edges`) suitable for the x-cytoscape Alpine directive.
 *
 *  - Each node carries the table name + ordered column list so the side
 *    panel can render columns on click without an extra roundtrip.
 *  - Edges encode the FK direction child → parent; the rendered diagram
 *    uses the standard ERD cardinality (many-to-one).
 *  - Tables whose introspection throws (cross-DB FK targets, missing
 *    permissions) are silently skipped and surfaced via `skippedTables`.
 */
class ErdGenerator
{
    /**
     * @return array{
     *   nodes: list<array<string, mixed>>,
     *   edges: list<array<string, mixed>>,
     *   tableCount: int,
     *   relationshipCount: int,
     *   skippedTables: list<string>,
     * }
     */
    public function generate(
        DatabaseDriverInterface $driver,
        string $database,
        ?string $schema = null,
        bool $compact = false,
    ): array {
        try {
            $tables = $driver->listTables($database, $schema);
        } catch (Throwable $e) {
            throw new \RuntimeException("Cannot list tables for {$database}: ".$e->getMessage(), previous: $e);
        }

        // Two bulk queries (vs 2N) cover all columns + all FKs for the whole
        // database. On a 600-table SQL Server this turns a multi-minute
        // sequential walk into a 2-3 second pair of catalog queries.
        try {
            $bulkColumns = $driver->bulkColumns($database, $schema);
            $bulkFks = $driver->bulkForeignKeys($database, $schema);
        } catch (Throwable $e) {
            throw new \RuntimeException("Bulk introspection failed for {$database}: ".$e->getMessage(), previous: $e);
        }

        $nodes = [];
        $edges = [];
        $skipped = [];
        $knownIds = [];

        foreach ($tables as $table) {
            $bulkKey = strtolower(($table->schema ?? '').'.'.$table->name);
            $columns = $bulkColumns[$bulkKey] ?? null;
            $fks = $bulkFks[$bulkKey] ?? [];

            if ($columns === null) {
                // Driver doesn't expose the table via bulk (rare — schema
                // mismatch on PG, permissions on MSSQL). Fall back to the
                // per-table lookup so we at least try.
                try {
                    $columns = $driver->getColumns($table);
                    $fks = $driver->getForeignKeys($table);
                } catch (Throwable) {
                    $skipped[] = $table->name;

                    continue;
                }
            }

            $nodeId = $this->nodeId($table);
            $knownIds[$nodeId] = true;

            // Mark the columns that participate in a FK so the side panel
            // can flag them, and shrink the payload in compact mode.
            $fkCols = [];
            foreach ($fks as $fk) {
                foreach ($fk->columns as $col) {
                    $fkCols[$col] = true;
                }
            }

            $colDescriptors = [];
            foreach ($columns as $col) {
                $isPk = $col->isPrimaryKey;
                $isFk = isset($fkCols[$col->name]);
                if ($compact && ! $isPk && ! $isFk) {
                    continue;
                }
                $colDescriptors[] = [
                    'name' => $col->name,
                    'type' => $col->rawType ?: $col->type->value,
                    'nullable' => $col->nullable,
                    'pk' => $isPk,
                    'fk' => $isFk,
                ];
            }

            $nodes[] = [
                'data' => [
                    'id' => $nodeId,
                    'label' => $table->name,
                    'qualified' => (string) $table,
                    'database' => $table->database,
                    'schema' => $table->schema,
                    'columnCount' => count($columns),
                    'visibleColumnCount' => count($colDescriptors),
                    'columns' => $colDescriptors,
                ],
            ];

            foreach ($fks as $fk) {
                $targetId = $this->nodeId($fk->referencedTable);
                $edges[] = [
                    'data' => [
                        'id' => $nodeId.'->'.$targetId.':'.$fk->name,
                        'source' => $nodeId,
                        'target' => $targetId,
                        'label' => $fk->name,
                        'sourceColumns' => $fk->columns,
                        'targetColumns' => $fk->referencedColumns,
                    ],
                ];
            }
        }

        // Drop edges that reference nodes we never built (cross-DB FKs on
        // MSSQL or skipped tables) — Cytoscape would refuse to add them.
        $edges = array_values(array_filter($edges, static fn ($e) => isset($knownIds[$e['data']['source']]) && isset($knownIds[$e['data']['target']])));

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'tableCount' => count($nodes),
            'relationshipCount' => count($edges),
            'skippedTables' => $skipped,
        ];
    }

    /**
     * Stable id for a table — used as Cytoscape node id and edge endpoint.
     */
    private function nodeId(TableIdentifier $table): string
    {
        return strtolower(($table->database ?? '').'.'.($table->schema ?? '').'.'.$table->name);
    }
}
