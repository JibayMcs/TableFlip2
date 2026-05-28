<div class="h-full flex">
    {{-- Sidebar tree (independent scroll, flush left) --}}
    <aside class="w-72 shrink-0 border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-y-auto">
        <div x-data="{
                q: '',
                /** Pre-built lowercase index : { dbName: [tableName, ...] } — covers ALL databases, not just expanded ones. */
                searchIndex: @js(array_map(static fn ($names) => array_map('strtolower', $names), $searchIndex)),
                ql() { return this.q.toLowerCase(); },
                /**
                 * A DB row is visible when no query, OR its name matches,
                 * OR ANY of its tables (indexed, not just loaded) matches.
                 */
                dbVisible(dbName) {
                    if (this.q === '') return true;
                    if (dbName.toLowerCase().includes(this.ql())) return true;
                    const tables = this.searchIndex[dbName] || [];
                    return tables.some(t => t.includes(this.ql()));
                },
                /** Child visible when no query, OR DB matches (show all its tables), OR own name matches. */
                childVisible(dbName, childName) {
                    if (this.q === '') return true;
                    if (dbName.toLowerCase().includes(this.ql())) return true;
                    return childName.toLowerCase().includes(this.ql());
                },
                /** Database has a matching table but is NOT expanded — used to hint the user to expand it. */
                hasUnloadedMatch(dbName, expanded) {
                    if (this.q === '' || expanded) return false;
                    if (dbName.toLowerCase().includes(this.ql())) return false;
                    const tables = this.searchIndex[dbName] || [];
                    return tables.some(t => t.includes(this.ql()));
                },
                /** True when there's an active query AND the given name matches it. Used to paint matching rows in amber. */
                matchesQuery(name) {
                    return this.q !== '' && name.toLowerCase().includes(this.ql());
                }
             }"
             @keydown.escape="q = ''">
            <div class="sticky top-0 z-10 bg-white dark:bg-zinc-900 border-b border-zinc-100 dark:border-zinc-800 p-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400 font-semibold truncate">
                        {{ $currentLabel }}
                    </div>
                    <button type="button" wire:click="reindexSchema"
                        wire:loading.attr="disabled" wire:target="reindexSchema"
                        x-tooltip.bottom="{{ __('explorer.sidebar.reindex_tooltip') }}"
                        class="shrink-0 inline-flex items-center justify-center size-5 rounded text-zinc-400 dark:text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                        <svg wire:loading.remove wire:target="reindexSchema" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10"/>
                            <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
                        </svg>
                        <svg wire:loading wire:target="reindexSchema" class="size-3 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                            <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <input x-model="q" type="search" placeholder="{{ __('explorer.sidebar.filter_placeholder') }}"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-2.5 py-1.5 text-sm" />
            </div>

            <div class="p-3 space-y-0.5">
                @forelse ($databases as $db)
                    <div x-show="dbVisible(@js($db))">
                        <button type="button" wire:click="toggleDatabase('{{ $db }}')"
                            class="cursor-pointer w-full flex items-center gap-1.5 px-2 py-1 text-left text-sm rounded hover:bg-zinc-50 dark:bg-zinc-950 {{ $selectedDatabase === $db ? 'text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-700 dark:text-zinc-300' }}">
                            <svg class="size-3 text-zinc-400 dark:text-zinc-500 transition-transform {{ in_array($db, $expanded) ? 'rotate-90' : '' }}"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span class="truncate">{{ $db }}</span>
                            <template x-if="hasUnloadedMatch(@js($db), {{ in_array($db, $expanded) ? 'true' : 'false' }})">
                                <span class="ml-auto text-[9px] font-mono text-amber-700 dark:text-amber-500" x-tooltip.right="{{ __('explorer.sidebar.unloaded_match_tooltip') }}">●</span>
                            </template>
                        </button>

                        @if (in_array($db, $expanded))
                            <div class="ml-5 mt-1 border-l border-zinc-100 dark:border-zinc-800 pl-2 space-y-1">
                                @if (count($tablesByDb[$db] ?? []) === 0 && count($viewsByDb[$db] ?? []) === 0)
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500 px-2 py-1 italic">{{ __('explorer.sidebar.empty') }}</div>
                                @endif

                                @foreach ($tablesByDb[$db] ?? [] as $t)
                                    <button type="button"
                                        wire:click="selectTable('{{ $db }}', '{{ $t->name }}', {{ $t->schema ? "'".$t->schema."'" : 'null' }})"
                                        x-show="childVisible(@js($db), @js($t->name))"
                                        :class="matchesQuery(@js($t->name)) ? 'ring-1 ring-amber-400 dark:ring-amber-500 bg-amber-50/60 dark:bg-amber-900/20' : ''"
                                        class="cursor-pointer w-full flex items-center gap-1.5 px-2 py-0.5 text-left text-xs rounded hover:bg-zinc-50 dark:bg-zinc-950 {{ $selectedDatabase === $db && $selectedTable === $t->name ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        <svg class="size-3 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                        </svg>
                                        <span class="truncate">{{ $t->name }}</span>
                                    </button>
                                @endforeach

                                @foreach ($viewsByDb[$db] ?? [] as $v)
                                    <button type="button"
                                        wire:click="selectTable('{{ $db }}', '{{ $v->name }}', {{ $v->schema ? "'".$v->schema."'" : 'null' }})"
                                        x-show="childVisible(@js($db), @js($v->name))"
                                        :class="matchesQuery(@js($v->name)) ? 'ring-1 ring-amber-400 dark:ring-amber-500 bg-amber-50/60 dark:bg-amber-900/20' : ''"
                                        class="cursor-pointer w-full flex items-center gap-1.5 px-2 py-0.5 text-left text-xs rounded hover:bg-zinc-50 dark:bg-zinc-950 {{ $selectedDatabase === $db && $selectedTable === $v->name ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        <svg class="size-3 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span class="truncate italic">{{ $v->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-xs text-zinc-400 dark:text-zinc-500 px-2 py-4 text-center">
                        {{ __('explorer.sidebar.no_databases') }}
                    </div>
                @endforelse
            </div>
        </div>
    </aside>

    {{-- Main panel (independent scroll) --}}
    <section class="flex-1 min-w-0 overflow-y-auto">
        <div class="max-w-full mx-auto px-6 py-6">
            @if (! $selectedTable)
                <div class="flex flex-col items-center justify-center text-center py-24 text-zinc-500 dark:text-zinc-400 text-sm">
                    <svg class="size-12 text-zinc-300 dark:text-zinc-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                    </svg>
                    {{ __('explorer.pick_database') }}
                </div>
            @else
                {{-- Breadcrumbs --}}
                <nav class="text-xs text-zinc-500 dark:text-zinc-400 mb-4 flex items-center gap-1">
                    <span>{{ $currentLabel }}</span>
                    <span class="text-zinc-300 dark:text-zinc-700">/</span>
                    <span>{{ $selectedDatabase }}</span>
                    @if ($selectedSchema)
                        <span class="text-zinc-300 dark:text-zinc-700">/</span>
                        <span>{{ $selectedSchema }}</span>
                    @endif
                    <span class="text-zinc-300 dark:text-zinc-700">/</span>
                    <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $selectedTable }}</span>
                </nav>

                <h1 class="text-xl font-semibold mb-4">{{ $selectedTable }}</h1>

                {{-- Tabs --}}
                <div class="border-b border-zinc-200 dark:border-zinc-800 mb-6">
                    <nav class="flex gap-1 -mb-px" aria-label="Tabs">
                        <button type="button" wire:click="setTab('schema')"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'schema' ? 'border-zinc-900 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300' }}">
                            {{ __('explorer.tabs.schema') }}
                        </button>
                        <button type="button" wire:click="setTab('data')"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'data' ? 'border-zinc-900 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300' }}">
                            {{ __('explorer.tabs.data') }}
                        </button>
                    </nav>
                </div>

                @if ($tab === 'data')
                    <livewire:explorer.table-data
                        :database="$selectedDatabase"
                        :schema="$selectedSchema"
                        :table="$selectedTable"
                        :key="'tdv-'.$selectedDatabase.'-'.($selectedSchema ?? '').'-'.$selectedTable" />
                @elseif (isset($detail['error']))
                    <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                        {{ $detail['error'] }}
                    </div>
                @elseif ($detail)
                    {{-- Row count --}}
                    <div class="mb-6 inline-flex items-center gap-3 rounded-md border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-3 py-2 text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('explorer.rows_label') }}</span>
                        @if ($rowCount !== null)
                            <span class="font-mono font-medium">{{ number_format($rowCount) }}</span>
                        @elseif ($rowCountFailed)
                            <span class="text-xs text-rose-600"
                                @if ($rowCountError) x-tooltip.bottom="{{ $rowCountError }}" @endif>{{ __('explorer.unable_to_count') }}</span>
                        @else
                            <button wire:click="loadRowCount" wire:loading.attr="disabled" wire:target="loadRowCount"
                                class="text-xs text-zinc-700 dark:text-zinc-300 underline hover:text-zinc-900 dark:text-zinc-100">
                                <span wire:loading.remove wire:target="loadRowCount">{{ __('explorer.count_rows') }}</span>
                                <span wire:loading wire:target="loadRowCount">{{ __('explorer.counting') }}</span>
                            </button>
                        @endif
                    </div>

                    {{-- Columns --}}
                    <section class="mb-8">
                        <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('explorer.columns_section') }} ({{ count($detail['columns']) }})</h2>
                        <div class="overflow-x-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2">{{ __('explorer.table_headers.name') }}</th>
                                        <th class="px-3 py-2">{{ __('explorer.table_headers.type') }}</th>
                                        <th class="px-3 py-2">{{ __('explorer.table_headers.nullable') }}</th>
                                        <th class="px-3 py-2">{{ __('explorer.table_headers.default') }}</th>
                                        <th class="px-3 py-2">{{ __('explorer.table_headers.notes') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($detail['columns'] as $c)
                                        <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                            <td class="px-3 py-1.5 font-mono">{{ $c->name }}</td>
                                            <td class="px-3 py-1.5 text-zinc-600 dark:text-zinc-400">
                                                <span class="font-mono text-xs">{{ $c->rawType }}</span>
                                                <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $c->type->value }}</span>
                                            </td>
                                            <td class="px-3 py-1.5 text-xs">
                                                @if ($c->nullable) <span class="text-zinc-400 dark:text-zinc-500">{{ __('common.yes') }}</span>
                                                @else <span class="text-zinc-700 dark:text-zinc-300">{{ __('common.no') }}</span> @endif
                                            </td>
                                            <td class="px-3 py-1.5 text-xs font-mono text-zinc-500 dark:text-zinc-400">
                                                {{ $c->default === null ? '—' : (string) $c->default }}
                                            </td>
                                            <td class="px-3 py-1.5 text-xs space-x-1">
                                                @if ($c->isPrimaryKey)
                                                    <span class="inline-block rounded bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5">PK</span>
                                                @endif
                                                @if ($c->autoIncrement)
                                                    <span class="inline-block rounded bg-blue-50 text-blue-700 border border-blue-200 px-1.5 py-0.5">AI</span>
                                                @endif
                                                @if ($c->enumValues)
                                                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('explorer.enum_prefix') }} {{ implode(', ', $c->enumValues) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- Indexes --}}
                    <section class="mb-8">
                        <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('explorer.indexes_section') }} ({{ count($detail['indexes']) }})</h2>
                        @if (count($detail['indexes']) === 0)
                            <p class="text-sm text-zinc-400 dark:text-zinc-500 italic">{{ __('explorer.no_indexes') }}</p>
                        @else
                            <div class="overflow-x-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                                        <tr>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.name') }}</th>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.columns') }}</th>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.type') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detail['indexes'] as $i)
                                            <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                                <td class="px-3 py-1.5 font-mono">{{ $i->name }}</td>
                                                <td class="px-3 py-1.5 font-mono text-zinc-600 dark:text-zinc-400">{{ implode(', ', $i->columns) }}</td>
                                                <td class="px-3 py-1.5 text-xs space-x-1">
                                                    @if ($i->primary)
                                                        <span class="inline-block rounded bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5">PRIMARY</span>
                                                    @endif
                                                    @if ($i->unique && ! $i->primary)
                                                        <span class="inline-block rounded bg-emerald-50 text-emerald-700 border border-emerald-200 px-1.5 py-0.5">UNIQUE</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    {{-- Foreign keys --}}
                    <section class="mb-8">
                        <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">{{ __('explorer.foreign_keys_section') }} ({{ count($detail['foreignKeys']) }})</h2>
                        @if (count($detail['foreignKeys']) === 0)
                            <p class="text-sm text-zinc-400 dark:text-zinc-500 italic">{{ __('explorer.no_foreign_keys') }}</p>
                        @else
                            <div class="overflow-x-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                                        <tr>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.name') }}</th>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.columns') }}</th>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.references') }}</th>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.on_update') }}</th>
                                            <th class="px-3 py-2">{{ __('explorer.table_headers.on_delete') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detail['foreignKeys'] as $fk)
                                            <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                                <td class="px-3 py-1.5 font-mono">{{ $fk->name }}</td>
                                                <td class="px-3 py-1.5 font-mono text-zinc-600 dark:text-zinc-400">{{ implode(', ', $fk->columns) }}</td>
                                                <td class="px-3 py-1.5 font-mono text-zinc-600 dark:text-zinc-400">
                                                    {{ $fk->referencedTable }}({{ implode(', ', $fk->referencedColumns) }})
                                                </td>
                                                <td class="px-3 py-1.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $fk->onUpdate ?? '—' }}</td>
                                                <td class="px-3 py-1.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $fk->onDelete ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                @endif
            @endif
        </div>
    </section>
</div>
