<div class="h-full flex">
    {{-- Sidebar tree (independent scroll, flush left) --}}
    <aside class="w-72 shrink-0 border-r border-zinc-200 bg-white overflow-y-auto">
        <div x-data="{ q: '' }" @keydown.escape="q = ''">
            <div class="sticky top-0 z-10 bg-white border-b border-zinc-100 p-3">
                <div class="text-xs uppercase tracking-wide text-zinc-500 mb-2 font-semibold">
                    {{ $currentLabel }}
                </div>
                <input x-model="q" type="search" placeholder="Filter databases & tables…"
                    class="w-full rounded-md border border-zinc-300 px-2.5 py-1.5 text-sm" />
            </div>

            <div class="p-3 space-y-0.5">
                @forelse ($databases as $db)
                    <div data-name="{{ strtolower($db) }}"
                        x-show="q === '' || '{{ strtolower($db) }}'.includes(q.toLowerCase())">
                        <button type="button" wire:click="toggleDatabase('{{ $db }}')"
                            class="cursor-pointer w-full flex items-center gap-1.5 px-2 py-1 text-left text-sm rounded hover:bg-zinc-50 {{ $selectedDatabase === $db ? 'text-zinc-900 font-medium' : 'text-zinc-700' }}">
                            <svg class="size-3 text-zinc-400 transition-transform {{ in_array($db, $expanded) ? 'rotate-90' : '' }}"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span class="truncate">{{ $db }}</span>
                        </button>

                        @if (in_array($db, $expanded))
                            <div class="ml-5 mt-0.5 border-l border-zinc-100 pl-2 space-y-px">
                                @if (count($tablesByDb[$db] ?? []) === 0 && count($viewsByDb[$db] ?? []) === 0)
                                    <div class="text-xs text-zinc-400 px-2 py-1 italic">empty</div>
                                @endif

                                @foreach ($tablesByDb[$db] ?? [] as $t)
                                    <button type="button"
                                        wire:click="selectTable('{{ $db }}', '{{ $t->name }}', {{ $t->schema ? "'".$t->schema."'" : 'null' }})"
                                        x-show="q === '' || '{{ strtolower($t->name) }}'.includes(q.toLowerCase())"
                                        class="cursor-pointer w-full flex items-center gap-1.5 px-2 py-0.5 text-left text-xs rounded hover:bg-zinc-50 {{ $selectedDatabase === $db && $selectedTable === $t->name ? 'bg-zinc-100 text-zinc-900 font-medium' : 'text-zinc-600' }}">
                                        <svg class="size-3 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                        </svg>
                                        <span class="truncate">{{ $t->name }}</span>
                                    </button>
                                @endforeach

                                @foreach ($viewsByDb[$db] ?? [] as $v)
                                    <button type="button"
                                        wire:click="selectTable('{{ $db }}', '{{ $v->name }}', {{ $v->schema ? "'".$v->schema."'" : 'null' }})"
                                        x-show="q === '' || '{{ strtolower($v->name) }}'.includes(q.toLowerCase())"
                                        class="cursor-pointer w-full flex items-center gap-1.5 px-2 py-0.5 text-left text-xs rounded hover:bg-zinc-50 {{ $selectedDatabase === $db && $selectedTable === $v->name ? 'bg-zinc-100 text-zinc-900 font-medium' : 'text-zinc-600' }}">
                                        <svg class="size-3 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <div class="text-xs text-zinc-400 px-2 py-4 text-center">
                        No databases visible.
                    </div>
                @endforelse
            </div>
        </div>
    </aside>

    {{-- Main panel (independent scroll) --}}
    <section class="flex-1 min-w-0 overflow-y-auto">
        <div class="max-w-full mx-auto px-6 py-6">
            @if (! $selectedTable)
                <div class="flex flex-col items-center justify-center text-center py-24 text-zinc-500 text-sm">
                    <svg class="size-12 text-zinc-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                    </svg>
                    Pick a database in the sidebar to start exploring.
                </div>
            @else
                {{-- Breadcrumbs --}}
                <nav class="text-xs text-zinc-500 mb-4 flex items-center gap-1">
                    <span>{{ $currentLabel }}</span>
                    <span class="text-zinc-300">/</span>
                    <span>{{ $selectedDatabase }}</span>
                    @if ($selectedSchema)
                        <span class="text-zinc-300">/</span>
                        <span>{{ $selectedSchema }}</span>
                    @endif
                    <span class="text-zinc-300">/</span>
                    <span class="text-zinc-900 font-medium">{{ $selectedTable }}</span>
                </nav>

                <h1 class="text-xl font-semibold mb-4">{{ $selectedTable }}</h1>

                {{-- Tabs --}}
                <div class="border-b border-zinc-200 mb-6">
                    <nav class="flex gap-1 -mb-px" aria-label="Tabs">
                        <button type="button" wire:click="setTab('schema')"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'schema' ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
                            Schema
                        </button>
                        <button type="button" wire:click="setTab('data')"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'data' ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
                            Data
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
                    <div class="mb-6 inline-flex items-center gap-3 rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm">
                        <span class="text-zinc-500">Rows:</span>
                        @if ($rowCount !== null)
                            <span class="font-mono font-medium">{{ number_format($rowCount) }}</span>
                        @elseif ($rowCountFailed)
                            <span class="text-xs text-rose-600">unable to count</span>
                        @else
                            <button wire:click="loadRowCount" wire:loading.attr="disabled" wire:target="loadRowCount"
                                class="text-xs text-zinc-700 underline hover:text-zinc-900">
                                <span wire:loading.remove wire:target="loadRowCount">Count rows</span>
                                <span wire:loading wire:target="loadRowCount">Counting…</span>
                            </button>
                        @endif
                    </div>

                    {{-- Columns --}}
                    <section class="mb-8">
                        <h2 class="text-sm font-semibold text-zinc-700 mb-2">Columns ({{ count($detail['columns']) }})</h2>
                        <div class="overflow-x-auto bg-white border border-zinc-200 rounded-md">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase text-zinc-500 border-b border-zinc-200">
                                    <tr>
                                        <th class="px-3 py-2">Name</th>
                                        <th class="px-3 py-2">Type</th>
                                        <th class="px-3 py-2">Nullable</th>
                                        <th class="px-3 py-2">Default</th>
                                        <th class="px-3 py-2">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($detail['columns'] as $c)
                                        <tr class="border-b border-zinc-100 last:border-0">
                                            <td class="px-3 py-1.5 font-mono">{{ $c->name }}</td>
                                            <td class="px-3 py-1.5 text-zinc-600">
                                                <span class="font-mono text-xs">{{ $c->rawType }}</span>
                                                <span class="text-xs text-zinc-400">{{ $c->type->value }}</span>
                                            </td>
                                            <td class="px-3 py-1.5 text-xs">
                                                @if ($c->nullable) <span class="text-zinc-400">yes</span>
                                                @else <span class="text-zinc-700">no</span> @endif
                                            </td>
                                            <td class="px-3 py-1.5 text-xs font-mono text-zinc-500">
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
                                                    <span class="text-zinc-500">enum: {{ implode(', ', $c->enumValues) }}</span>
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
                        <h2 class="text-sm font-semibold text-zinc-700 mb-2">Indexes ({{ count($detail['indexes']) }})</h2>
                        @if (count($detail['indexes']) === 0)
                            <p class="text-sm text-zinc-400 italic">No indexes.</p>
                        @else
                            <div class="overflow-x-auto bg-white border border-zinc-200 rounded-md">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-xs uppercase text-zinc-500 border-b border-zinc-200">
                                        <tr>
                                            <th class="px-3 py-2">Name</th>
                                            <th class="px-3 py-2">Columns</th>
                                            <th class="px-3 py-2">Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detail['indexes'] as $i)
                                            <tr class="border-b border-zinc-100 last:border-0">
                                                <td class="px-3 py-1.5 font-mono">{{ $i->name }}</td>
                                                <td class="px-3 py-1.5 font-mono text-zinc-600">{{ implode(', ', $i->columns) }}</td>
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
                        <h2 class="text-sm font-semibold text-zinc-700 mb-2">Foreign keys ({{ count($detail['foreignKeys']) }})</h2>
                        @if (count($detail['foreignKeys']) === 0)
                            <p class="text-sm text-zinc-400 italic">No foreign keys.</p>
                        @else
                            <div class="overflow-x-auto bg-white border border-zinc-200 rounded-md">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-xs uppercase text-zinc-500 border-b border-zinc-200">
                                        <tr>
                                            <th class="px-3 py-2">Name</th>
                                            <th class="px-3 py-2">Columns</th>
                                            <th class="px-3 py-2">References</th>
                                            <th class="px-3 py-2">On update</th>
                                            <th class="px-3 py-2">On delete</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detail['foreignKeys'] as $fk)
                                            <tr class="border-b border-zinc-100 last:border-0">
                                                <td class="px-3 py-1.5 font-mono">{{ $fk->name }}</td>
                                                <td class="px-3 py-1.5 font-mono text-zinc-600">{{ implode(', ', $fk->columns) }}</td>
                                                <td class="px-3 py-1.5 font-mono text-zinc-600">
                                                    {{ $fk->referencedTable }}({{ implode(', ', $fk->referencedColumns) }})
                                                </td>
                                                <td class="px-3 py-1.5 text-xs text-zinc-500">{{ $fk->onUpdate ?? '—' }}</td>
                                                <td class="px-3 py-1.5 text-xs text-zinc-500">{{ $fk->onDelete ?? '—' }}</td>
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
