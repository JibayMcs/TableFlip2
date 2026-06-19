<div class="space-y-4">
    {{-- SQL scratch pad : drives the table below when set --}}
    @php
        $sqlEditorConfig = json_encode([
            'dialect' => $dialect,
            'schema' => $autocompleteSchema,
            'initialContent' => $customSql !== '' ? $customSql : $this->scratchPadSeedSql(),
            'defaultTable' => $table,
            'changeEvent' => 'table-data-sql-change',
            'executeEvent' => 'table-data-sql-execute',
        ]);
    @endphp
    {{-- Collapsible state is pure client UI : Alpine keeps it off the server.
        Seeded open when a custom query is already active so the editor that
        drives the table is visible. The panel stays mounted (x-show, not
        x-if) so the embedded CodeMirror inits once with correct dimensions
        and the inner wire: actions bind normally. --}}
    <div x-data="{ sqlPadOpen: @js($customSql !== '') }"
        class="border border-zinc-200 dark:border-zinc-800 rounded-md bg-white dark:bg-zinc-900 overflow-hidden">
        <button type="button" @click="sqlPadOpen = ! sqlPadOpen"
            class="w-full px-3 py-2 flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:bg-zinc-950">
            <svg class="size-3 text-zinc-400 dark:text-zinc-500 transition-transform" :class="sqlPadOpen ? 'rotate-90' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="font-medium text-zinc-700 dark:text-zinc-300">SQL</span>
            @if ($mode === 'custom')
                <span class="text-emerald-700 text-[10px] bg-emerald-50 border border-emerald-200 rounded px-1.5 py-0.5 font-semibold">ACTIVE</span>
            @endif
            @if ($customSqlMeta)
                <span class="ml-auto text-[10px] font-mono text-zinc-400 dark:text-zinc-500">last: {{ number_format($customSqlMeta['durationMs'], 1) }}ms</span>
            @endif
        </button>

        <div x-show="sqlPadOpen" x-cloak>
            <div class="border-t border-zinc-200 dark:border-zinc-800">
                <div class="flex gap-px items-stretch bg-zinc-200">
                    <div class="flex-1 min-w-0 h-40 bg-white dark:bg-zinc-900"
                        @table-data-sql-change="$wire.set('customSql', $event.detail.sql, false)"
                        @table-data-sql-execute="$wire.executeCustomSql($event.detail.sql)">
                        <div x-sql-editor='{!! $sqlEditorConfig !!}' wire:ignore class="h-full"></div>
                    </div>
                    <div class="shrink-0 flex flex-col items-center gap-1 p-2 bg-zinc-50 dark:bg-zinc-950">
                        <button type="button" wire:click="executeCustomSql(null)"
                            wire:loading.attr="disabled" wire:target="executeCustomSql"
                            x-tooltip.left="Run query (⌘/Ctrl+↵)"
                            class="size-9 inline-flex items-center justify-center rounded-md bg-zinc-900 text-white hover:bg-zinc-800 disabled:opacity-50 transition-colors">
                            <svg wire:loading.remove wire:target="executeCustomSql" class="size-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            <svg wire:loading wire:target="executeCustomSql" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                                <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                        </button>
                        @if ($mode === 'custom')
                            <button type="button" wire:click="clearCustomSql"
                                x-tooltip.left="Reset to filtered view"
                                class="size-9 inline-flex items-center justify-center rounded-md border border-zinc-300 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:bg-zinc-800 transition-colors">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10"/>
                                    <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                @if ($customSqlError)
                    <div class="border-t border-zinc-200 dark:border-zinc-800 m-2 p-2 bg-rose-50 border-rose-200 border rounded text-xs text-rose-800 font-mono whitespace-pre-wrap">
                        {{ $customSqlError }}
                    </div>
                @endif

                @if ($customSqlMeta && $customSqlMeta['isWrite'])
                    <div class="border-t border-zinc-200 dark:border-zinc-800 m-2 p-2 bg-emerald-50 border border-emerald-200 rounded text-xs text-emerald-800">
                        Affected rows : <span class="font-mono font-medium">{{ number_format($customSqlMeta['affectedRows']) }}</span>
                        <span class="text-emerald-600 ml-2">({{ number_format($customSqlMeta['durationMs'], 1) }} ms)</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Custom mode banner --}}
    @if ($mode === 'custom')
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 flex items-center gap-2">
            <span class="font-medium">Custom SQL mode</span>
            <span class="text-emerald-700">— the table below reflects your query's result. Filters & pagination are paused.</span>
            <button type="button" wire:click="clearCustomSql" class="ml-auto underline hover:text-emerald-900">Back to filtered view</button>
        </div>
    @endif

    {{-- Toolbar + Filter builder (Alpine local state so opening/typing doesn't ping the server) --}}
    @php
        $emptyFilterRow = ['column' => '', 'operator' => '=', 'value' => null];
        $initialFilters = count($filters) > 0 ? $filters : [$emptyFilterRow];
    @endphp
    <div x-data="{
            showFilters: @js($showFilters || count($filters) > 0),
            filters: @js($initialFilters),
            operators: @js($this->operatorChoices()),
            addRow() { this.filters.push({ column: '', operator: '=', value: null }); },
            removeRow(i) { this.filters.splice(i, 1); if (this.filters.length === 0) this.addRow(); },
            clearAll() { this.filters = [{ column: '', operator: '=', value: null }]; $wire.set('filters', [], false); $wire.clearFilters(); },
            apply() {
                const cleaned = this.filters.filter(f => f.column !== '');
                $wire.set('filters', cleaned, false);
                $wire.applyFilters();
            },
            valuelessOps: ['is_null', 'is_not_null'],
            isValueless(op) { return this.valuelessOps.includes(op); },
            activeCount() { return this.filters.filter(f => f.column !== '').length; },
        }" class="space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
            @if ($mode === 'natural')
                <button type="button" @click="showFilters = !showFilters"
                    :class="showFilters ? 'bg-zinc-100 dark:bg-zinc-800 border-zinc-400' : ''"
                    class="text-xs px-2.5 py-1.5 rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:bg-zinc-950">
                    Filters <template x-if="activeCount() > 0"><span x-text="'(' + activeCount() + ')'"></span></template>
                </button>
                <template x-if="activeCount() > 0">
                    <button type="button" @click="clearAll()"
                        class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300 underline">Clear all</button>
                </template>
                @if (count($sort) > 0)
                    <button type="button" wire:click="$set('sort', [])"
                        class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300 underline">Reset sort</button>
                @endif
            @endif

            @if (! $error)
                @if ($mode === 'natural')
                    <span class="mx-1 h-4 w-px bg-zinc-200"></span>
                @endif
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
                        class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300 underline">clear selection</button>
                @endif
            @endif
        </div>
        <div class="flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
            {{-- Column visibility picker — Alpine-driven so opening the dropdown / typing in the search costs zero roundtrips. --}}
            @php
                $hiddenCount = count($hiddenColumns);
                $autoHidden = $autoHideEmpty ? count($emptyColumns ?? []) : 0;
            @endphp
            <div x-data="{
                    open: false,
                    q: '',
                    hidden: @js($hiddenColumns),
                    isHidden(c) { return this.hidden.includes(c); },
                    toggle(c) {
                        const i = this.hidden.indexOf(c);
                        if (i >= 0) this.hidden.splice(i, 1); else this.hidden.push(c);
                        $wire.set('hiddenColumns', this.hidden, false);
                    },
                    commit() { $wire.$commit(); },
                    showAll() { this.hidden = []; $wire.showAllColumns(); this.open = false; },
                }" @click.outside="if (open) { open = false; commit(); }" class="relative">
                <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:bg-zinc-950">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="9" y1="3" x2="9" y2="21"/>
                        <line x1="15" y1="3" x2="15" y2="21"/>
                    </svg>
                    <span>Columns ({{ count($visibleColumns) }}/{{ count($columns) }})</span>
                </button>
                <div x-show="open" x-transition.opacity x-cloak
                    class="absolute right-0 mt-1 w-72 max-h-[70vh] z-40 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md shadow-lg flex flex-col">
                    <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 space-y-2">
                        <input type="search" x-model="q" placeholder="Filter columns…" autofocus
                            class="w-full rounded border border-zinc-300 dark:border-zinc-700 px-2 py-1 text-xs" />
                        <label class="flex items-center gap-1.5 text-xs text-zinc-700 dark:text-zinc-300 cursor-pointer">
                            <input type="checkbox" @checked($autoHideEmpty)
                                wire:click="toggleAutoHideEmpty"
                                class="rounded border-zinc-300 dark:border-zinc-700" />
                            Auto-hide empty columns
                            @if ($autoHideEmpty && $autoHidden > 0)
                                <span class="text-[10px] text-zinc-400 dark:text-zinc-500">({{ $autoHidden }} hidden)</span>
                            @endif
                        </label>
                    </div>
                    <div class="overflow-y-auto flex-1 p-1">
                        @foreach ($columns as $col)
                            @php $isPk = ($columnDefs[$col] ?? null)?->isPrimaryKey ?? false; @endphp
                            <label x-show="q === '' || '{{ strtolower($col) }}'.includes(q.toLowerCase())"
                                class="flex items-center gap-2 px-2 py-1 text-xs rounded hover:bg-zinc-50 dark:bg-zinc-950 cursor-pointer">
                                <input type="checkbox"
                                    :checked="!isHidden('{{ $col }}')"
                                    @if ($isPk) disabled @endif
                                    @change="toggle('{{ $col }}')"
                                    class="rounded border-zinc-300 dark:border-zinc-700 disabled:opacity-50" />
                                <span class="font-mono truncate flex-1 {{ $isPk ? 'text-amber-700' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $col }}</span>
                                @if ($isPk) <span class="text-[10px] text-amber-700">PK</span> @endif
                            </label>
                        @endforeach
                    </div>
                    <div class="p-2 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        <button type="button" @click="showAll()"
                            class="text-[10px] text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100 underline">Show all</button>
                        <button type="button" @click="open = false; commit();"
                            class="text-[10px] px-2 py-1 bg-zinc-900 text-white rounded hover:bg-zinc-800">Done</button>
                    </div>
                </div>
            </div>

            @if (! $error)
                <livewire:exports.launcher
                    wire:key="export-launcher-{{ $mode }}-{{ $table }}"
                    :source-kind="$exportSourceKind"
                    :source-payload="$exportSourcePayload"
                    :default-file-name="$table"
                    :database-name="$database" />
            @endif
            <span class="inline-flex items-center gap-1">
                @if ($totalIsEstimate ?? false)
                    <span x-tooltip.bottom="Approximate count from engine stats — click to compute exact value">≈</span>
                    <span class="font-mono font-medium text-zinc-700 dark:text-zinc-300">{{ number_format($total) }}</span> rows
                    <button type="button" wire:click="refreshExactCount"
                        wire:loading.attr="disabled" wire:target="refreshExactCount"
                        x-tooltip.bottom="Run exact COUNT(*) (may be slow on huge tables)"
                        class="ml-1 text-zinc-400 dark:text-zinc-500 hover:text-zinc-900 dark:text-zinc-100">
                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10"/>
                            <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
                        </svg>
                    </button>
                @else
                    <span class="font-mono font-medium text-zinc-700 dark:text-zinc-300">{{ number_format($total) }}</span> rows
                @endif
            </span>
            @if ($mode === 'natural')
                <label class="flex items-center gap-1">
                    Per page
                    <select wire:model.live="perPage" class="text-xs border border-zinc-300 dark:border-zinc-700 rounded px-1.5 py-1">
                        @foreach ([25, 50, 100, 250] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
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

    {{-- Filter builder (Alpine-driven) --}}
    @if ($mode === 'natural')
        <div x-show="showFilters" x-transition.opacity class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md p-3">
            <div class="space-y-2">
                <template x-for="(f, i) in filters" :key="i">
                    <div class="flex items-center gap-2">
                        <select x-model="f.column" class="text-xs border border-zinc-300 dark:border-zinc-700 rounded px-2 py-1 max-w-xs">
                            <option value="">— column —</option>
                            @foreach ($columns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                        <select x-model="f.operator" class="text-xs border border-zinc-300 dark:border-zinc-700 rounded px-2 py-1">
                            <template x-for="opt in operators" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                        <input type="text" x-model="f.value" placeholder="value"
                            x-show="!isValueless(f.operator)"
                            @keydown.enter.prevent="apply()"
                            class="text-xs border border-zinc-300 dark:border-zinc-700 rounded px-2 py-1 flex-1 min-w-0" />
                        <button type="button" @click="removeRow(i)"
                            class="text-zinc-400 dark:text-zinc-500 hover:text-rose-600 text-base leading-none px-1" title="Remove">×</button>
                    </div>
                </template>
            </div>
            <div class="mt-3 flex items-center gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <button type="button" @click="addRow()"
                    class="text-xs px-2.5 py-1 border border-dashed border-zinc-300 dark:border-zinc-700 rounded hover:bg-zinc-50 dark:bg-zinc-950">+ Add filter</button>
                <button type="button" @click="apply()"
                    class="text-xs px-3 py-1 bg-zinc-900 text-white rounded hover:bg-zinc-800">Apply</button>
                <span class="text-[10px] text-zinc-400 dark:text-zinc-500 ml-2">Enter to apply · panel is local until Apply</span>
            </div>
        </div>
    @endif
    </div>{{-- /Alpine filter wrapper --}}

    {{-- Data table --}}
    @if ($error)
        <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 font-mono">{{ $error }}</div>
    @else
        <div class="overflow-x-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md relative">
            {{-- Loading veil shown during sort / pagination / filter apply --}}
            <div wire:loading.flex wire:target="toggleSort,setPage,applyFilters,clearFilters,perPage,updatedPerPage,$set,executeCustomSql,clearCustomSql"
                class="absolute inset-0 z-30 bg-white dark:bg-zinc-900/70 backdrop-blur-[1px] items-center justify-center pointer-events-none">
                <div class="flex items-center gap-2 text-xs text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md px-3 py-2 shadow-sm">
                    <svg class="size-4 animate-spin text-zinc-700 dark:text-zinc-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                        <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span>Loading…</span>
                </div>
            </div>
            <table class="w-full text-sm border-collapse">
                <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950">
                    <tr>
                        <th class="px-3 py-2 w-8 sticky left-0 z-20 bg-zinc-50 dark:bg-zinc-950 shadow-[1px_0_0_rgb(228_228_231)]"></th>
                        @foreach ($visibleColumns as $col)
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
                            <th class="px-3 py-2 cursor-pointer select-none hover:bg-zinc-100 dark:bg-zinc-800 whitespace-nowrap"
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
                                    @if ($sortDir === 'asc') <span class="text-zinc-900 dark:text-zinc-100">↑</span>
                                    @elseif ($sortDir === 'desc') <span class="text-zinc-900 dark:text-zinc-100">↓</span> @endif
                                </div>
                            </th>
                        @endforeach
                        <th class="px-3 py-2 w-12 sticky right-0 z-20 bg-zinc-50 dark:bg-zinc-950 shadow-[-1px_0_0_rgb(228_228_231)]"></th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Insert draft row --}}
                    @if ($insertDraft !== null)
                        <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-blue-50" wire:key="insert-draft">
                            <td class="px-3 py-1.5 text-center text-xs text-blue-600 sticky left-0 z-10 bg-blue-50 shadow-[1px_0_0_rgb(228_228_231)]">+</td>
                            @foreach ($visibleColumns as $col)
                                <td class="px-3 py-1 align-top">
                                    @if (! array_key_exists($col, $insertDraft))
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500 italic">auto</span>
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
                                    class="text-xs px-2 py-1 text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300">Cancel</button>
                            </td>
                        </tr>
                    @endif

                    @forelse ($rows as $i => $row)
                        @php
                            $rowKey = $this->rowKeyOf($row, $pkColumns);
                            $isSelected = $this->isRowSelected($rowKey);
                            // Hoisted ONCE per row instead of N times per cell — saves megabytes of
                            // duplicated JSON when the row has lots of visible columns.
                            $rowKeyJson = \Illuminate\Support\Js::from($rowKey);
                        @endphp
                        <tr class="group border-b border-zinc-100 dark:border-zinc-800 last:border-0 {{ $isSelected ? 'bg-amber-50' : 'bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:bg-zinc-950' }}" wire:key="row-{{ $i }}">
                            <td class="px-3 py-1.5 sticky left-0 z-10 shadow-[1px_0_0_rgb(228_228_231)] {{ $isSelected ? 'bg-amber-50' : 'bg-white dark:bg-zinc-900 group-hover:bg-zinc-50 dark:bg-zinc-950' }}">
                                <input type="checkbox" @checked($isSelected)
                                    wire:click="toggleSelectRow({!! $rowKeyJson !!})"
                                    class="rounded border-zinc-300 dark:border-zinc-700" />
                            </td>
                            @foreach ($visibleColumns as $col)
                                @php
                                    $isEditing = $editingRowIndex === $i && $editingColumn === $col;
                                    $def = $columnDefs[$col] ?? null;
                                    $isPk = $def?->isPrimaryKey ?? false;
                                    $typeValue = $def?->type->value;
                                    $cellValue = $row[$col] ?? null;
                                    $wasTruncated = isset(($truncatedCells ?? [])[$i][$col]);
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
                                                @if ($wasTruncated)
                                                    @click="$wire.startEditFetch({{ $i }}, {{ Js::from($col) }}, {!! $rowKeyJson !!}, {{ Js::from($typeValue) }})"
                                                @else
                                                    @click="$wire.startEdit({{ $i }}, {{ Js::from($col) }}, {{ Js::from($cellValue) }}, {!! $rowKeyJson !!}, {{ Js::from($typeValue) }})"
                                                @endif
                                                class="block max-w-md truncate cursor-text hover:bg-yellow-50 rounded px-1 -mx-1"
                                            @else
                                                class="block max-w-md truncate text-zinc-500 dark:text-zinc-400"
                                                title="Primary key — read-only"
                                            @endif
                                            @if ($cellValue !== null && ! $wasTruncated) title="{{ (string) $cellValue }}" @endif
                                            @if ($wasTruncated) title="value truncated for display — click to load full content" @endif >
                                            @if ($cellValue === null)
                                                <span class="text-zinc-400 dark:text-zinc-500 italic">null</span>
                                            @else
                                                {{ (string) $cellValue }}
                                                @if ($wasTruncated)
                                                    <span class="ml-1 text-[10px] text-zinc-400 dark:text-zinc-500 italic">(truncated)</span>
                                                @endif
                                            @endif
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-2 py-1 text-right sticky right-0 z-10 shadow-[-1px_0_0_rgb(228_228_231)] {{ $isSelected ? 'bg-amber-50' : 'bg-white dark:bg-zinc-900 group-hover:bg-zinc-50 dark:bg-zinc-950' }}">
                                <button wire:click="deleteRow({!! $rowKeyJson !!})"
                                    wire:confirm="Delete this row?"
                                    class="text-zinc-300 dark:text-zinc-700 hover:text-rose-600 text-base leading-none"
                                    title="Delete row">×</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($visibleColumns) + 2 }}" class="text-center py-12 text-zinc-400 dark:text-zinc-500 text-sm">
                                No rows match the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($mode === 'natural')
            {{-- Pagination --}}
            <div class="flex items-center justify-between text-sm">
                <span class="text-zinc-500 dark:text-zinc-400">
                    Page <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $page }}</span>
                    of <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ number_format($totalPages) }}</span>
                </span>
                <div class="flex gap-1">
                    <button wire:click="setPage(1)" @disabled($page <= 1) class="px-2 py-1 rounded border border-zinc-300 dark:border-zinc-700 disabled:opacity-30 hover:bg-zinc-50 dark:bg-zinc-950">‹‹</button>
                    <button wire:click="setPage({{ $page - 1 }})" @disabled($page <= 1) class="px-2 py-1 rounded border border-zinc-300 dark:border-zinc-700 disabled:opacity-30 hover:bg-zinc-50 dark:bg-zinc-950">‹</button>
                    <button wire:click="setPage({{ $page + 1 }})" @disabled($page >= $totalPages) class="px-2 py-1 rounded border border-zinc-300 dark:border-zinc-700 disabled:opacity-30 hover:bg-zinc-50 dark:bg-zinc-950">›</button>
                    <button wire:click="setPage({{ $totalPages }})" @disabled($page >= $totalPages) class="px-2 py-1 rounded border border-zinc-300 dark:border-zinc-700 disabled:opacity-30 hover:bg-zinc-50 dark:bg-zinc-950">››</button>
                </div>
            </div>
        @endif

        <p class="text-xs text-zinc-400 dark:text-zinc-500">
            Tip: click a cell to edit, Enter to save, Esc to cancel. Click a column header to sort, shift-click for multi-sort.
        </p>
    @endif

    {{-- Destructive SQL confirmation modal (custom scratch pad) --}}
    @if ($pendingSqlDestructive)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-4"
            wire:click.self="cancelCustomDestructive">
            <div x-data="{ confirmText: '' }" class="bg-white dark:bg-zinc-900 rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Destructive query detected</h2>
                <div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                    {{ $pendingSqlDestructive['reason'] }}
                </div>
                <details class="text-xs">
                    <summary class="cursor-pointer text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300">Show SQL</summary>
                    <pre class="mt-2 rounded bg-zinc-900 text-zinc-100 p-3 overflow-x-auto whitespace-pre-wrap break-all">{{ $pendingSqlDestructive['sql'] }}</pre>
                </details>
                <div>
                    <label class="block text-sm text-zinc-700 dark:text-zinc-300 mb-1">
                        Type <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded font-mono">CONFIRM</code> to proceed:
                    </label>
                    <input type="text" x-model="confirmText" autofocus
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm font-mono" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="cancelCustomDestructive"
                        class="px-3 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">Cancel</button>
                    <button type="button" wire:click="confirmCustomDestructive"
                        :disabled="confirmText !== 'CONFIRM'"
                        :class="confirmText === 'CONFIRM' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-zinc-300 cursor-not-allowed'"
                        class="rounded-md px-4 py-2 text-sm font-medium text-white">
                        Run anyway
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Bulk delete confirmation modal --}}
    @if ($confirmBulkDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/40 p-4" wire:click.self="$set('confirmBulkDelete', false)">
            <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-lg max-w-md w-full p-6 space-y-4">
                <h2 class="text-lg font-semibold">Delete {{ count($selectedRowKeys) }} row(s) ?</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    This will permanently delete the selected rows from
                    <code class="font-mono">{{ $database }}.{{ $table }}</code>. Each row is
                    pre-checked for unique match before deletion.
                </p>
                <div class="flex justify-end gap-2 pt-2">
                    <button wire:click="$set('confirmBulkDelete', false)"
                        class="px-3 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">Cancel</button>
                    <button wire:click="deleteSelected"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                        Delete {{ count($selectedRowKeys) }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
