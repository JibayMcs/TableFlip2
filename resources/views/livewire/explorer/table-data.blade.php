<div class="space-y-4">
    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" wire:click="toggleFilters"
                class="text-xs px-2.5 py-1.5 rounded border border-zinc-300 hover:bg-zinc-50 {{ $showFilters ? 'bg-zinc-100 border-zinc-400' : '' }}">
                Filters {{ count($filters) > 0 ? '('.count($filters).')' : '' }}
            </button>
            @if (count($filters) > 0)
                <button type="button" wire:click="clearFilters"
                    class="text-xs text-zinc-500 hover:text-zinc-700 underline">Clear all</button>
            @endif
            @if (count($sort) > 0)
                <button type="button" wire:click="$set('sort', [])"
                    class="text-xs text-zinc-500 hover:text-zinc-700 underline">Reset sort</button>
            @endif

            @if (! $error)
                <span class="mx-1 h-4 w-px bg-zinc-200"></span>
                <button type="button" wire:click="startInsert"
                    class="text-xs px-2.5 py-1.5 rounded bg-zinc-900 text-white hover:bg-zinc-800">
                    + Insert row
                </button>

                @if (count($selectedRowKeys) > 0)
                    <button type="button" wire:click="requestBulkDelete"
                        class="text-xs px-2.5 py-1.5 rounded bg-rose-600 text-white hover:bg-rose-700">
                        Delete {{ count($selectedRowKeys) }} selected
                    </button>
                    <button type="button" wire:click="clearSelection"
                        class="text-xs text-zinc-500 hover:text-zinc-700 underline">clear selection</button>
                @endif
            @endif
        </div>
        <div class="flex items-center gap-3 text-xs text-zinc-500">
            <span><span class="font-mono font-medium text-zinc-700">{{ number_format($total) }}</span> rows</span>
            <label class="flex items-center gap-1">
                Per page
                <select wire:model.live="perPage" class="text-xs border border-zinc-300 rounded px-1.5 py-1">
                    @foreach ([25, 50, 100, 250] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    {{-- Status / error flash --}}
    @if ($editError)
        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 flex items-center justify-between">
            <span>{{ $editError }}</span>
            <button wire:click="dismissEditStatus" class="text-rose-500 hover:text-rose-700 text-base leading-none">×</button>
        </div>
    @elseif ($editStatus)
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 flex items-center justify-between">
            <span>{{ $editStatus }}</span>
            <button wire:click="dismissEditStatus" class="text-emerald-500 hover:text-emerald-700 text-base leading-none">×</button>
        </div>
    @endif

    {{-- No-PK warning --}}
    @if (! $error && ! $hasPrimaryKey)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            ⚠ This table has no primary key. Updates and deletes will identify rows by <strong>all column values</strong>.
            Operations refuse to run if the WHERE clause doesn't match exactly one row.
        </div>
    @endif

    {{-- Filter builder --}}
    @if ($showFilters)
        <div class="bg-white border border-zinc-200 rounded-md p-3">
            <div class="space-y-2">
                @foreach ($filters as $i => $f)
                    <div class="flex items-center gap-2" wire:key="filter-{{ $i }}">
                        <select wire:model="filters.{{ $i }}.column" class="text-xs border border-zinc-300 rounded px-2 py-1 max-w-xs">
                            <option value="">— column —</option>
                            @foreach ($columns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="filters.{{ $i }}.operator" class="text-xs border border-zinc-300 rounded px-2 py-1">
                            @foreach ($this->operatorChoices() as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @if (! in_array($f['operator'], ['is_null', 'is_not_null']))
                            <input type="text" wire:model="filters.{{ $i }}.value" placeholder="value"
                                class="text-xs border border-zinc-300 rounded px-2 py-1 flex-1 min-w-0" />
                        @endif
                        <button type="button" wire:click="removeFilter({{ $i }})"
                            class="text-zinc-400 hover:text-rose-600 text-base leading-none px-1" title="Remove">×</button>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-2 pt-2 border-t border-zinc-100">
                <button type="button" wire:click="addFilter"
                    class="text-xs px-2.5 py-1 border border-dashed border-zinc-300 rounded hover:bg-zinc-50">+ Add filter</button>
                <button type="button" wire:click="applyFilters"
                    class="text-xs px-3 py-1 bg-zinc-900 text-white rounded hover:bg-zinc-800">Apply</button>
            </div>
        </div>
    @endif

    {{-- Data table --}}
    @if ($error)
        <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 font-mono">{{ $error }}</div>
    @else
        <div class="overflow-x-auto bg-white border border-zinc-200 rounded-md">
            <table class="w-full text-sm border-collapse">
                <thead class="text-left text-xs uppercase text-zinc-500 border-b border-zinc-200 bg-zinc-50">
                    <tr>
                        <th class="px-3 py-2 w-8 sticky left-0 z-20 bg-zinc-50 shadow-[1px_0_0_rgb(228_228_231)]"></th>
                        @foreach ($columns as $col)
                            @php
                                $sortDir = collect($sort)->firstWhere('column', $col)['direction'] ?? null;
                                $def = $columnDefs[$col] ?? null;
                                $required = $def && ! $def->nullable && $def->default === null && ! $def->autoIncrement;

                                $tooltipParts = [];
                                if ($def) {
                                    $tooltipParts[] = $def->rawType;
                                    $tooltipParts[] = $def->nullable ? 'NULL' : 'NOT NULL';
                                    if ($def->isPrimaryKey) {
                                        $tooltipParts[] = 'PRIMARY KEY';
                                    }
                                    if ($def->autoIncrement) {
                                        $tooltipParts[] = 'auto_increment';
                                    }
                                    if ($def->default !== null) {
                                        $tooltipParts[] = 'default ' . $def->default;
                                    }
                                    if ($def->comment) {
                                        $tooltipParts[] = '— ' . $def->comment;
                                    }
                                }
                                $tooltip = implode(' · ', $tooltipParts);
                            @endphp
                            <th class="px-3 py-2 cursor-pointer select-none hover:bg-zinc-100 whitespace-nowrap"
                                @if ($tooltip) x-tooltip.bottom="{{ $tooltip }}" @endif
                                @click="$wire.toggleSort('{{ $col }}', $event.shiftKey)">
                                <div class="flex items-center gap-1">
                                    <span>{{ $col }}</span>
                                    @if ($required)
                                        <span class="text-rose-500">*</span>
                                    @endif
                                    @if ($def?->isPrimaryKey)
                                        <span class="text-amber-700 text-[10px]">PK</span>
                                    @endif
                                    @if ($sortDir === 'asc') <span class="text-zinc-900">↑</span>
                                    @elseif ($sortDir === 'desc') <span class="text-zinc-900">↓</span> @endif
                                </div>
                            </th>
                        @endforeach
                        <th class="px-3 py-2 w-12 sticky right-0 z-20 bg-zinc-50 shadow-[-1px_0_0_rgb(228_228_231)]"></th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Insert draft row --}}
                    @if ($insertDraft !== null)
                        <tr class="border-b border-zinc-200 bg-blue-50" wire:key="insert-draft">
                            <td class="px-3 py-1.5 text-center text-xs text-blue-600 sticky left-0 z-10 bg-blue-50 shadow-[1px_0_0_rgb(228_228_231)]">+</td>
                            @foreach ($columns as $col)
                                <td class="px-3 py-1 align-top">
                                    @if (! array_key_exists($col, $insertDraft))
                                        <span class="text-xs text-zinc-400 italic">auto</span>
                                    @else
                                        @include('livewire.explorer._cell-input', [
                                            'column' => $columnDefs[$col] ?? null,
                                            'wireModel' => "insertDraft.{$col}",
                                            'inlineEdit' => false,
                                        ])
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-2 py-1 text-right whitespace-nowrap sticky right-0 z-10 bg-blue-50 shadow-[-1px_0_0_rgb(228_228_231)]">
                                <button wire:click="saveInsert" wire:loading.attr="disabled" wire:target="saveInsert"
                                    class="text-xs px-2 py-1 bg-emerald-600 text-white rounded hover:bg-emerald-700">Save</button>
                                <button wire:click="cancelInsert"
                                    class="text-xs px-2 py-1 text-zinc-500 hover:text-zinc-700">Cancel</button>
                            </td>
                        </tr>
                    @endif

                    @forelse ($rows as $i => $row)
                        @php
                            $rowKey = $this->rowKeyOf($row, $pkColumns);
                            $isSelected = $this->isRowSelected($rowKey);
                        @endphp
                        <tr class="group border-b border-zinc-100 last:border-0 {{ $isSelected ? 'bg-amber-50' : 'bg-white hover:bg-zinc-50' }}" wire:key="row-{{ $i }}">
                            <td class="px-3 py-1.5 sticky left-0 z-10 shadow-[1px_0_0_rgb(228_228_231)] {{ $isSelected ? 'bg-amber-50' : 'bg-white group-hover:bg-zinc-50' }}">
                                <input type="checkbox" @checked($isSelected)
                                    wire:click="toggleSelectRow({{ Js::from($rowKey) }})"
                                    class="rounded border-zinc-300" />
                            </td>
                            @foreach ($columns as $col)
                                @php
                                    $isEditing = $editingRowIndex === $i && $editingColumn === $col;
                                    $def = $columnDefs[$col] ?? null;
                                    $isPk = $def?->isPrimaryKey ?? false;
                                    $typeValue = $def?->type->value;
                                @endphp
                                <td class="px-3 py-1 font-mono text-xs align-top">
                                    @if ($isEditing)
                                        @include('livewire.explorer._cell-input', [
                                            'column' => $def,
                                            'wireModel' => 'editingValue',
                                            'inlineEdit' => true,
                                        ])
                                    @else
                                        <span
                                            @if (! $isPk || $hasPrimaryKey === false)
                                                @click="$wire.startEdit({{ $i }}, {{ Js::from($col) }}, {{ Js::from($row[$col] ?? null) }}, {{ Js::from($rowKey) }}, {{ Js::from($typeValue) }})"
                                                class="block max-w-md truncate cursor-text hover:bg-yellow-50 rounded px-1 -mx-1"
                                            @else
                                                class="block max-w-md truncate text-zinc-500"
                                                title="Primary key — read-only"
                                            @endif
                                            @if ($row[$col] !== null) title="{{ (string) $row[$col] }}" @endif >
                                            @if ($row[$col] === null)
                                                <span class="text-zinc-400 italic">null</span>
                                            @else
                                                {{ (string) $row[$col] }}
                                            @endif
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-2 py-1 text-right sticky right-0 z-10 shadow-[-1px_0_0_rgb(228_228_231)] {{ $isSelected ? 'bg-amber-50' : 'bg-white group-hover:bg-zinc-50' }}">
                                <button wire:click="deleteRow({{ Js::from($rowKey) }})"
                                    wire:confirm="Delete this row?"
                                    class="text-zinc-300 hover:text-rose-600 text-base leading-none"
                                    title="Delete row">×</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 2 }}" class="text-center py-12 text-zinc-400 text-sm">
                                No rows match the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex items-center justify-between text-sm">
            <span class="text-zinc-500">
                Page <span class="font-medium text-zinc-700">{{ $page }}</span>
                of <span class="font-medium text-zinc-700">{{ number_format($totalPages) }}</span>
            </span>
            <div class="flex gap-1">
                <button wire:click="setPage(1)" @disabled($page <= 1) class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">‹‹</button>
                <button wire:click="setPage({{ $page - 1 }})" @disabled($page <= 1) class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">‹</button>
                <button wire:click="setPage({{ $page + 1 }})" @disabled($page >= $totalPages) class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">›</button>
                <button wire:click="setPage({{ $totalPages }})" @disabled($page >= $totalPages) class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">››</button>
            </div>
        </div>

        <p class="text-xs text-zinc-400">
            Tip: click a cell to edit, Enter to save, Esc to cancel. Click a column header to sort, shift-click for multi-sort.
        </p>
    @endif

    {{-- Bulk delete confirmation modal --}}
    @if ($confirmBulkDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/40 p-4" wire:click.self="$set('confirmBulkDelete', false)">
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 space-y-4">
                <h2 class="text-lg font-semibold">Delete {{ count($selectedRowKeys) }} row(s) ?</h2>
                <p class="text-sm text-zinc-600">
                    This will permanently delete the selected rows from
                    <code class="font-mono">{{ $database }}.{{ $table }}</code>. Each row is
                    pre-checked for unique match before deletion.
                </p>
                <div class="flex justify-end gap-2 pt-2">
                    <button wire:click="$set('confirmBulkDelete', false)"
                        class="px-3 py-2 text-sm text-zinc-600 hover:text-zinc-900">Cancel</button>
                    <button wire:click="deleteSelected"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                        Delete {{ count($selectedRowKeys) }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
