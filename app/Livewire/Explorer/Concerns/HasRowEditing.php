<?php

declare(strict_types=1);

namespace App\Livewire\Explorer\Concerns;

use App\Application\Connections\CurrentConnection;
use App\Application\Schema\SchemaIntrospectionService;
use App\Application\Tables\DeleteRowsAction;
use App\Application\Tables\InsertRowAction;
use App\Application\Tables\RowValidator;
use App\Application\Tables\UpdateRowAction;
use App\Domain\Database\Exceptions\RowEditingException;
use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ColumnType;
use App\Domain\Database\ValueObjects\TableIdentifier;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Inline editing concerns for {@see \App\Livewire\Explorer\TableData}. Kept in
 * a trait so the component class stays focused on display/pagination/filtering.
 *
 * Consumes from the host component: $database, $schema, $table (TableIdentifier
 * parts) plus the `currentTableIdentifier()` helper.
 */
trait HasRowEditing
{
    // -- Edit state ---------------------------------------------------------
    public ?int $editingRowIndex = null;

    public ?string $editingColumn = null;

    public mixed $editingValue = null;

    /** ColumnType value (e.g. 'datetime') captured at startEdit time so save can normalise format. */
    public ?string $editingColumnType = null;

    /** @var array<string, mixed>|null */
    public ?array $editingRowKey = null;

    // -- Insert state -------------------------------------------------------
    /** @var array<string, mixed>|null */
    public ?array $insertDraft = null;

    // -- Bulk selection -----------------------------------------------------
    /** @var list<array<string, mixed>> Row keys currently checked for bulk ops */
    public array $selectedRowKeys = [];

    public bool $confirmBulkDelete = false;

    // -- Inline feedback ----------------------------------------------------
    public ?string $editError = null;

    public ?string $editStatus = null;

    // ---------------------------------------------------------------------------
    // Cell edit
    // ---------------------------------------------------------------------------

    public function startEdit(
        int $rowIndex,
        string $column,
        mixed $value,
        array $rowKey,
        ?string $columnType = null,
    ): void {
        $this->editError = null;
        $this->editingRowIndex = $rowIndex;
        $this->editingColumn = $column;
        $this->editingColumnType = $columnType;
        $this->editingValue = $this->normaliseForInput($columnType, $value);
        $this->editingRowKey = $rowKey;
    }

    /**
     * Variant of {@see startEdit()} for cells whose value was truncated in
     * the rendered HTML — we re-fetch the full value via a single-cell SELECT
     * scoped by PK before opening the editor.
     */
    public function startEditFetch(
        int $rowIndex,
        string $column,
        array $rowKey,
        ?string $columnType = null,
    ): void {
        $value = $this->loadCellValue($rowKey, $column, app(CurrentConnection::class));
        $this->startEdit($rowIndex, $column, $value, $rowKey, $columnType);
    }

    public function cancelEdit(): void
    {
        $this->editingRowIndex = null;
        $this->editingColumn = null;
        $this->editingColumnType = null;
        $this->editingValue = null;
        $this->editingRowKey = null;
        $this->editError = null;
    }

    public function saveEdit(
        CurrentConnection $current,
        SchemaIntrospectionService $schema,
        RowValidator $validator,
        UpdateRowAction $action,
    ): void {
        if ($this->editingColumn === null || $this->editingRowKey === null) {
            return;
        }

        $driver = $this->effectiveDriver($current);
        if ($driver === null) {
            $this->editError = 'No active connection.';

            return;
        }

        $table = $this->currentTableIdentifier();
        $columns = $schema->tableDetail($driver, $table)['columns'];

        try {
            $valueForStorage = $this->normaliseForStorage($this->editingColumnType, $this->editingValue);
            $changes = $validator->validate($columns, [$this->editingColumn => $valueForStorage], isInsert: false);
            $action->execute($driver, $table, $this->editingRowKey, $changes);
            $this->editStatus = "Updated {$this->editingColumn}.";
            $this->cancelEdit();
        } catch (ValidationException $e) {
            $this->editError = collect($e->errors())->flatten()->first() ?? 'Validation failed.';
        } catch (RowEditingException|Throwable $e) {
            $this->editError = $e->getMessage();
        }
    }

    // ---------------------------------------------------------------------------
    // Insert
    // ---------------------------------------------------------------------------

    public function startInsert(CurrentConnection $current, SchemaIntrospectionService $schema): void
    {
        $driver = $this->effectiveDriver($current);
        if ($driver === null) {
            return;
        }

        $columns = $schema->tableDetail($driver, $this->currentTableIdentifier())['columns'];
        $draft = [];
        foreach ($columns as $col) {
            if ($col->autoIncrement) {
                continue;
            }
            $draft[$col->name] = $this->insertDefaultFor($col);
        }

        $this->insertDraft = $draft;
        $this->editError = null;
    }

    public function cancelInsert(): void
    {
        $this->insertDraft = null;
        $this->editError = null;
    }

    public function saveInsert(
        CurrentConnection $current,
        SchemaIntrospectionService $schema,
        RowValidator $validator,
        InsertRowAction $action,
    ): void {
        if ($this->insertDraft === null) {
            return;
        }

        $driver = $this->effectiveDriver($current);
        if ($driver === null) {
            $this->editError = 'No active connection.';

            return;
        }

        $table = $this->currentTableIdentifier();
        $columns = $schema->tableDetail($driver, $table)['columns'];

        // Drop empty strings on nullable columns (→ NULL), and normalise the
        // datetime-local format back to the SQL "Y-m-d H:i:s" shape.
        $payload = $this->insertDraft;
        foreach ($columns as $col) {
            if (! array_key_exists($col->name, $payload)) {
                continue;
            }
            if ($payload[$col->name] === '' && $col->nullable) {
                $payload[$col->name] = null;

                continue;
            }
            $payload[$col->name] = $this->normaliseForStorage($col->type->value, $payload[$col->name]);
        }

        try {
            $clean = $validator->validate($columns, $payload, isInsert: true);
            $action->execute($driver, $table, $clean);
            $this->editStatus = 'Row inserted.';
            $this->insertDraft = null;
        } catch (ValidationException $e) {
            $this->editError = collect($e->errors())->flatten()->first() ?? 'Validation failed.';
        } catch (Throwable $e) {
            $this->editError = $e->getMessage();
        }
    }

    /**
     * Pick the best default value to prefill a draft insert cell. For date/
     * time columns we prefill with NOW() in the HTML input's expected format,
     * mirroring PMA's UX. For enum columns without a DB default we fall back
     * to the first enum value (so NOT NULL columns aren't left invalid).
     */
    private function insertDefaultFor(ColumnDefinition $col): mixed
    {
        $now = now();

        return match ($col->type) {
            ColumnType::DATE => $now->format('Y-m-d'),
            ColumnType::DATETIME, ColumnType::TIMESTAMP => $now->format('Y-m-d\\TH:i:s'),
            ColumnType::TIME => $now->format('H:i:s'),
            ColumnType::ENUM => $col->default ?? (! $col->nullable && $col->enumValues ? $col->enumValues[0] : null),
            ColumnType::BOOLEAN => $col->default ?? (! $col->nullable ? '0' : null),
            default => $col->default,
        };
    }

    /**
     * Convert a value coming from the database into the format the HTML input
     * for $columnType expects. Mostly a no-op except for datetime / timestamp
     * where MySQL returns "Y-m-d H:i:s" but datetime-local wants "Y-m-d\TH:i:s".
     */
    private function normaliseForInput(?string $columnType, mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return match ($columnType) {
            'datetime', 'timestamp' => str_replace(' ', 'T', $value),
            default => $value,
        };
    }

    /**
     * Inverse of {@see normaliseForInput()}.
     */
    private function normaliseForStorage(?string $columnType, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match ($columnType) {
            'datetime', 'timestamp' => str_replace('T', ' ', $value),
            default => $value,
        };
    }

    // ---------------------------------------------------------------------------
    // Delete (single + bulk)
    // ---------------------------------------------------------------------------

    public function deleteRow(array $rowKey, CurrentConnection $current, DeleteRowsAction $action): void
    {
        $driver = $this->effectiveDriver($current);
        if ($driver === null) {
            $this->editError = 'No active connection.';

            return;
        }

        try {
            $action->execute($driver, $this->currentTableIdentifier(), [$rowKey]);
            $this->editStatus = 'Row deleted.';
        } catch (Throwable $e) {
            $this->editError = $e->getMessage();
        }
    }

    public function toggleSelectRow(array $rowKey): void
    {
        $index = $this->indexOfSelection($rowKey);
        if ($index === null) {
            $this->selectedRowKeys[] = $rowKey;
        } else {
            unset($this->selectedRowKeys[$index]);
            $this->selectedRowKeys = array_values($this->selectedRowKeys);
        }
    }

    public function clearSelection(): void
    {
        $this->selectedRowKeys = [];
        $this->confirmBulkDelete = false;
    }

    /**
     * @param  array<string, mixed>  $rowKey
     */
    public function isRowSelected(array $rowKey): bool
    {
        return $this->indexOfSelection($rowKey) !== null;
    }

    public function requestBulkDelete(): void
    {
        if ($this->selectedRowKeys === []) {
            return;
        }
        $threshold = (int) config('tableflip.editing.bulk_confirm_threshold', 10);
        if (count($this->selectedRowKeys) >= $threshold) {
            $this->confirmBulkDelete = true;

            return;
        }
        // Below threshold: caller will invoke deleteSelected directly via UI confirm.
        $this->confirmBulkDelete = true;
    }

    public function deleteSelected(CurrentConnection $current, DeleteRowsAction $action): void
    {
        if ($this->selectedRowKeys === []) {
            return;
        }

        $driver = $this->effectiveDriver($current);
        if ($driver === null) {
            $this->editError = 'No active connection.';

            return;
        }

        try {
            $count = $action->execute($driver, $this->currentTableIdentifier(), $this->selectedRowKeys);
            $this->editStatus = "Deleted {$count} row(s).";
            $this->clearSelection();
        } catch (Throwable $e) {
            $this->editError = $e->getMessage();
        }
    }

    public function dismissEditStatus(): void
    {
        $this->editStatus = null;
        $this->editError = null;
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * @param  list<ColumnDefinition>  $columns
     * @return list<string>
     */
    protected function pkColumnNames(array $columns): array
    {
        return array_values(array_map(
            static fn (ColumnDefinition $c) => $c->name,
            array_filter($columns, static fn (ColumnDefinition $c) => $c->isPrimaryKey),
        ));
    }

    /**
     * Extract the identifying key for a given row. Uses PK columns when the
     * table has one, otherwise falls back to ALL columns (so the WHERE clause
     * is exact). The pre-flight COUNT check in the actions catches ambiguity.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $pkColumns
     * @return array<string, mixed>
     */
    protected function rowKeyOf(array $row, array $pkColumns): array
    {
        if ($pkColumns === []) {
            return $row;
        }

        $key = [];
        foreach ($pkColumns as $col) {
            $key[$col] = $row[$col] ?? null;
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $rowKey
     */
    private function indexOfSelection(array $rowKey): ?int
    {
        foreach ($this->selectedRowKeys as $i => $candidate) {
            if ($candidate == $rowKey) { // loose equality intentional for scalar coercion
                return $i;
            }
        }

        return null;
    }

    abstract protected function currentTableIdentifier(): TableIdentifier;
}
