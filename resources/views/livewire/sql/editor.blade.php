<div class="h-full flex">
    {{-- Main column : tabs + editor + result --}}
    <section class="flex-1 min-w-0 flex flex-col">

        {{-- Top bar : connection + database picker --}}
        <div class="shrink-0 px-4 py-2 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex items-center gap-3 text-xs text-zinc-600 dark:text-zinc-400">
            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $currentLabel }}</span>
            <span class="text-zinc-300 dark:text-zinc-700">/</span>
            <label class="flex items-center gap-1">
                <span class="text-zinc-500 dark:text-zinc-400">Database</span>
                <select wire:model.live="currentDatabase" wire:change="changeDatabase($event.target.value)"
                    class="text-xs border border-zinc-300 dark:border-zinc-700 rounded px-1.5 py-1">
                    <option value="">— pick —</option>
                    @foreach ($databases as $db)
                        <option value="{{ $db }}">{{ $db }}</option>
                    @endforeach
                </select>
            </label>
            <span class="text-zinc-300 dark:text-zinc-700">·</span>
            <span class="text-zinc-500 dark:text-zinc-400">dialect: <code class="text-zinc-700 dark:text-zinc-300">{{ $dialect }}</code></span>

            <div class="ml-auto flex items-center gap-2">
                <kbd class="px-1.5 py-0.5 text-[10px] bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded">⌘/Ctrl + Enter</kbd>
                <span class="text-zinc-400 dark:text-zinc-500">to execute</span>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="shrink-0 flex items-center gap-px bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-800 overflow-x-auto">
            @foreach ($tabs as $tab)
                <div class="group flex items-center gap-1 px-3 py-1.5 text-xs cursor-pointer whitespace-nowrap {{ $tab['id'] === $activeTabId ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 font-medium border-t-2 border-zinc-900 -mb-px' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:bg-zinc-950 border-t-2 border-transparent' }}"
                    wire:click="activateTab('{{ $tab['id'] }}')" wire:key="tab-{{ $tab['id'] }}">
                    <span class="truncate max-w-[200px]">{{ $tab['title'] ?: 'Query' }}</span>
                    <button type="button" wire:click.stop="closeTab('{{ $tab['id'] }}')"
                        class="opacity-0 group-hover:opacity-100 text-zinc-400 dark:text-zinc-500 hover:text-rose-600 leading-none px-1">×</button>
                </div>
            @endforeach
            <button type="button" wire:click="newTab"
                class="px-3 py-1.5 text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:bg-zinc-950">+ New tab</button>

            <div class="ml-auto pr-3 flex items-center gap-2">
                <button type="button" wire:click="openExportLauncher"
                    x-tooltip.bottom="Export current query"
                    class="size-8 inline-flex items-center justify-center rounded-md border border-zinc-300 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:bg-zinc-950 transition-colors">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                </button>
                <button type="button" wire:click="executeActive(null)"
                    wire:loading.attr="disabled" wire:target="executeActive"
                    x-tooltip.left="Run query (⌘/Ctrl+↵)"
                    class="size-8 inline-flex items-center justify-center rounded-md bg-zinc-900 text-white hover:bg-zinc-800 disabled:opacity-50 transition-colors">
                    <svg wire:loading.remove wire:target="executeActive" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    <svg wire:loading wire:target="executeActive" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                        <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <livewire:exports.launcher
            wire:key="sql-export-launcher"
            source-kind="raw_sql"
            :source-payload="['sql' => $currentSql]"
            default-file-name="query"
            :database-name="$currentDatabase"
            :external="true" />

        {{-- Editor --}}
        @php
            $editorConfig = json_encode([
                'dialect' => $dialect,
                'schema' => $schema,
                'initialContent' => $currentSql,
                'changeEvent' => 'sql-change',
                'executeEvent' => 'sql-execute',
            ]);
        @endphp
        <div class="flex-1 min-h-0 bg-white dark:bg-zinc-900"
            @sql-change="$wire.set('currentSql', $event.detail.sql, false)"
            @sql-execute="$wire.executeActive($event.detail.sql)">
            <div x-sql-editor='{!! $editorConfig !!}'
                wire:ignore
                class="h-full"></div>
        </div>

        {{-- Result --}}
        <div class="shrink-0 max-h-[40vh] overflow-auto border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            @if ($lastResult === null)
                <div class="px-4 py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                    Run a query to see results.
                </div>
            @elseif (! empty($lastResult['error']))
                <div class="m-3 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 font-mono whitespace-pre-wrap">
                    {{ $lastResult['error'] }}
                </div>
            @elseif (! empty($lastResult['isWrite']))
                <div class="m-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    Affected rows: <span class="font-mono font-medium">{{ number_format($lastResult['affectedRows']) }}</span>
                    <span class="text-emerald-600 text-xs ml-2">({{ number_format($lastResult['durationMs'], 1) }} ms)</span>
                </div>
            @else
                <div class="px-3 py-1.5 text-xs text-zinc-500 dark:text-zinc-400 border-b border-zinc-100 dark:border-zinc-800 flex items-center gap-3 bg-zinc-50 dark:bg-zinc-950">
                    <span><span class="font-mono font-medium text-zinc-700 dark:text-zinc-300">{{ number_format(count($lastResult['rows'])) }}</span> rows</span>
                    <span class="text-zinc-300 dark:text-zinc-700">·</span>
                    <span>{{ number_format($lastResult['durationMs'], 1) }} ms</span>
                </div>
                @if (count($lastResult['rows']) === 0)
                    <div class="px-4 py-6 text-center text-xs text-zinc-400 dark:text-zinc-500">Empty result set.</div>
                @else
                    <table class="w-full text-xs border-collapse">
                        <thead class="text-left text-[10px] uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950">
                            <tr>
                                @foreach ($lastResult['columns'] as $col)
                                    <th class="px-3 py-1.5 whitespace-nowrap">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lastResult['rows'] as $row)
                                <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0 hover:bg-zinc-50 dark:bg-zinc-950">
                                    @foreach ($lastResult['columns'] as $col)
                                        <td class="px-3 py-1 font-mono align-top">
                                            @if ($row[$col] === null)
                                                <span class="text-zinc-400 dark:text-zinc-500 italic">null</span>
                                            @else
                                                <span class="block max-w-md truncate" title="{{ (string) $row[$col] }}">{{ (string) $row[$col] }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </div>
    </section>

    {{-- Right sidebar : history --}}
    <aside class="w-72 shrink-0 border-l border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-y-auto flex flex-col">
        <div class="sticky top-0 z-10 bg-white dark:bg-zinc-900 border-b border-zinc-100 dark:border-zinc-800 p-3">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400 font-semibold">History</div>
                @if ($history->isNotEmpty())
                    <button type="button" wire:click="clearAllHistory"
                        wire:confirm="Delete all your query history?"
                        x-tooltip.left="Clear all history"
                        class="text-zinc-400 dark:text-zinc-500 hover:text-rose-600 p-1 -m-1 rounded">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        </svg>
                    </button>
                @endif
            </div>
            <input type="search" wire:model.live.debounce.300ms="historySearch" placeholder="Search…"
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-2.5 py-1.5 text-xs" />
        </div>

        <div class="flex-1 divide-y divide-zinc-100">
            @forelse ($history as $entry)
                <div class="group p-2.5 hover:bg-zinc-50 dark:bg-zinc-950/60" wire:key="hist-{{ $entry->id }}">
                    <div class="flex items-center gap-2 text-[10px] mb-1">
                        @if ($entry->status === 'success')
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        @else
                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                        @endif
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $entry->executed_at->diffForHumans() }}</span>
                        <span class="ml-auto text-zinc-400 dark:text-zinc-500 font-mono">{{ $entry->duration_ms }}ms</span>
                        <button type="button" wire:click="deleteHistoryEntry({{ $entry->id }})"
                            x-tooltip.left="Delete this entry"
                            class="inline-flex items-center justify-center size-4 rounded text-zinc-400 dark:text-zinc-500 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <button type="button" wire:click="loadFromHistory({{ $entry->id }})"
                        class="block w-full text-left cursor-pointer">
                        <div class="font-mono text-xs text-zinc-700 dark:text-zinc-300 line-clamp-3 break-all">{{ $entry->sql_text }}</div>
                        @if ($entry->database_name)
                            <div class="mt-1 text-[10px] text-zinc-400 dark:text-zinc-500 font-mono">{{ $entry->database_name }}</div>
                        @endif
                    </button>
                </div>
            @empty
                <div class="p-4 text-center text-xs text-zinc-400 dark:text-zinc-500 italic">
                    @if ($historySearch !== '')
                        No queries matching "{{ $historySearch }}".
                    @else
                        No queries yet.
                    @endif
                </div>
            @endforelse
        </div>
    </aside>

    {{-- Destructive confirmation modal --}}
    @if ($pendingDestructive)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-4"
            wire:click.self="cancelDestructive">
            <div x-data="{ confirmText: '' }" class="bg-white dark:bg-zinc-900 rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center gap-2 text-amber-700">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Destructive query detected</h2>
                </div>

                <div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                    {{ $pendingDestructive['reason'] }}
                </div>

                <details class="text-xs">
                    <summary class="cursor-pointer text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300">Show SQL</summary>
                    <pre class="mt-2 rounded bg-zinc-900 text-zinc-100 p-3 overflow-x-auto whitespace-pre-wrap break-all">{{ $pendingDestructive['sql'] }}</pre>
                </details>

                <div>
                    <label class="block text-sm text-zinc-700 dark:text-zinc-300 mb-1">
                        Type <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded font-mono">CONFIRM</code> to proceed:
                    </label>
                    <input type="text" x-model="confirmText" autofocus
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm font-mono" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="cancelDestructive"
                        class="px-3 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">Cancel</button>
                    <button type="button" wire:click="confirmDestructive"
                        :disabled="confirmText !== 'CONFIRM'"
                        :class="confirmText === 'CONFIRM' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-zinc-300 cursor-not-allowed'"
                        class="rounded-md px-4 py-2 text-sm font-medium text-white">
                        Run anyway
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
