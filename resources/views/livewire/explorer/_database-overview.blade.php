@php
    /**
     * Local helpers : human-readable byte size + estimate row count.
     * Kept inline so this partial stays self-contained.
     */
    $fmtSize = function (?int $bytes): string {
        if ($bytes === null) { return '—'; }
        if ($bytes < 1024) { return $bytes.' B'; }
        if ($bytes < 1024 * 1024) { return number_format($bytes / 1024, 1).' KB'; }
        if ($bytes < 1024 * 1024 * 1024) { return number_format($bytes / (1024 * 1024), 1).' MB'; }
        return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
    };
    $tables = $overview['tables'] ?? [];
    $totals = $overview['totals'] ?? ['count' => 0, 'rows' => 0, 'size' => 0];
@endphp

<div x-data="{
        overviewView: @js($overviewView),
        tableFilter: '',
        tableNames: @js(array_values(array_map(static fn ($t) => strtolower((string) $t['name']), $tables))),
        setView(v) {
            this.overviewView = v;
            // Persist in the URL client-side so a reload / deeplink keeps
            // the choice, without a Livewire round-trip ('grid' is the
            // default and dropped from the query string).
            const u = new URL(location);
            if (v === 'grid') { u.searchParams.delete('view'); }
            else { u.searchParams.set('view', v); }
            history.replaceState({}, '', u);
        },
        matchesFilter(name) {
            const q = this.tableFilter.trim().toLowerCase();
            return q === '' || String(name).toLowerCase().includes(q);
        },
        get filteredCount() {
            const q = this.tableFilter.trim().toLowerCase();
            return q === '' ? this.tableNames.length : this.tableNames.filter(n => n.includes(q)).length;
        },
    }">
    {{-- Breadcrumb --}}
    <nav class="text-xs text-zinc-500 dark:text-zinc-400 mb-4 flex items-center gap-1">
        <span>{{ $currentLabel }}</span>
        <span class="text-zinc-300 dark:text-zinc-700">/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $selectedDatabase }}</span>
    </nav>

    {{-- Header : title + totals + view toggle --}}
    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedDatabase }}</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ number_format($totals['count']) }}</span> {{ __('explorer.overview.tables') }} ·
                <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ number_format($totals['rows']) }}</span> {{ __('common.rows') }} ·
                <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $fmtSize($totals['size']) }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            {{-- Dynamic table filter : pure Alpine, instant, no server round-trip. --}}
            <div class="relative">
                <svg class="size-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-zinc-400 dark:text-zinc-500 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="search" x-model="tableFilter"
                    placeholder="{{ __('explorer.overview.filter_placeholder') }}"
                    x-on:keydown.escape="tableFilter = ''"
                    class="w-44 rounded border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 pl-7 pr-2 py-1.5 text-xs focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900" />
            </div>

            <a href="{{ route('exports.database', ['db' => $selectedDatabase]) }}" wire:navigate
                x-tooltip.bottom="{{ __('exports.database.title_segment') }}"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded border border-zinc-300 dark:border-zinc-700 text-xs text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                {{ __('exports.database.title_segment') }}
            </a>

            {{-- View mode toggle : pure Alpine, no server round-trip. --}}
            <div class="inline-flex border border-zinc-300 dark:border-zinc-700 rounded-md overflow-hidden">
                <button type="button" @click="setView('grid')"
                    x-tooltip.bottom="{{ __('explorer.overview.grid_view') }}"
                    class="px-2 py-1 text-xs"
                    :class="overviewView === 'grid' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800'">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </button>
                <button type="button" @click="setView('list')"
                    x-tooltip.bottom="{{ __('explorer.overview.list_view') }}"
                    class="px-2 py-1 text-xs"
                    :class="overviewView === 'list' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800'">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Error / status flash --}}
    @if ($overviewError)
        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 font-mono whitespace-pre-wrap mb-4 flex items-start justify-between gap-2">
            <span class="flex-1">{{ $overviewError }}</span>
            <button wire:click="dismissOverviewStatus" class="text-rose-500 hover:text-rose-700 text-base leading-none">×</button>
        </div>
    @elseif ($overviewStatus)
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-4 flex items-start justify-between gap-2">
            <span class="flex-1">{{ $overviewStatus }}</span>
            <button wire:click="dismissOverviewStatus" class="text-emerald-500 hover:text-emerald-700 text-base leading-none">×</button>
        </div>
    @endif

    {{-- Bulk action bar (visible when selection is non-empty) --}}
    @if (count($bulkSelected) > 0)
        <div class="rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm mb-4 flex items-center gap-3">
            <span class="text-zinc-700 dark:text-zinc-300">
                {{ count($bulkSelected) }} {{ __('explorer.overview.selected') }}
            </span>
            <button type="button" wire:click="requestBulkAction('truncate')"
                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h12M6 12c0-3.3 2.7-6 6-6s6 2.7 6 6M6 12v6a2 2 0 002 2h8a2 2 0 002-2v-6"/></svg>
                {{ __('explorer.overview.truncate_selected') }}
            </button>
            <button type="button" wire:click="requestBulkAction('drop')"
                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded border border-rose-300 dark:border-rose-700 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/30">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6"/></svg>
                {{ __('explorer.overview.drop_selected') }}
            </button>
            <button type="button" wire:click="clearBulk"
                class="ml-auto text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 underline">
                {{ __('explorer.overview.clear_selection') }}
            </button>
        </div>
    @endif

    @if (isset($overview['error']))
        <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 font-mono">{{ $overview['error'] }}</div>
    @elseif (count($tables) === 0)
        <div class="rounded-md border border-dashed border-zinc-300 dark:border-zinc-700 p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('explorer.overview.no_tables') }}
        </div>
    @else
        {{-- Empty state when the live filter matches nothing. --}}
        <div x-show="filteredCount === 0" x-cloak
            class="rounded-md border border-dashed border-zinc-300 dark:border-zinc-700 p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('explorer.overview.no_filter_match') }} <span class="font-mono" x-text="tableFilter"></span>
        </div>

        {{-- Grid view : cards. Both layouts are rendered and toggled by
            Alpine x-show so switching is instant and the inner wire: actions
            (selectTable / toggleBulk) stay bound. --}}
        <div x-show="overviewView === 'grid' && filteredCount > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach ($tables as $t)
                @php $checked = in_array($t['name'], $bulkSelected, true); @endphp
                <div wire:key="grid-{{ $t['name'] }}"
                    x-show="matchesFilter(@js($t['name']))"
                    class="group relative bg-white dark:bg-zinc-900 border rounded-md transition-colors {{ $checked ? 'border-amber-400 dark:border-amber-500 ring-1 ring-amber-400 dark:ring-amber-500' : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                    {{-- Click overlay for navigation (excludes the checkbox + action buttons) --}}
                    <button type="button" wire:click="selectTable('{{ $selectedDatabase }}', {{ \Illuminate\Support\Js::from($t['name']) }}, {{ $t['schema'] ? "'".$t['schema']."'" : 'null' }})"
                        class="absolute inset-0 z-0 cursor-pointer rounded-md focus:outline-none focus:ring-2 focus:ring-zinc-900 dark:focus:ring-zinc-300"
                        aria-label="Open {{ $t['name'] }}"></button>

                    <div class="relative p-3 z-10 pointer-events-none">
                        <div class="flex items-start gap-2">
                            <input type="checkbox" @checked($checked)
                                wire:click.stop="toggleBulk({{ \Illuminate\Support\Js::from($t['name']) }})"
                                class="pointer-events-auto mt-0.5 rounded border-zinc-300 dark:border-zinc-700" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-mono text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $t['name'] }}</span>
                                    @if ($t['type'] === 'view')
                                        <span class="shrink-0 text-[9px] uppercase font-medium tracking-wide rounded px-1 py-0.5 bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">view</span>
                                    @endif
                                </div>
                                <div class="mt-2 flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                                        @if ($t['rows'] !== null)
                                            {{ number_format($t['rows']) }}
                                        @else
                                            —
                                        @endif
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                        {{ $fmtSize($t['size']) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- List view : compact table --}}
        <div x-show="overviewView === 'list' && filteredCount > 0" x-cloak class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-3 py-2 w-8"></th>
                        <th class="px-3 py-2">{{ __('explorer.table_headers.name') }}</th>
                        <th class="px-3 py-2">{{ __('explorer.table_headers.type') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('common.rows') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('explorer.overview.size') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tables as $t)
                        @php $checked = in_array($t['name'], $bulkSelected, true); @endphp
                        <tr wire:key="list-{{ $t['name'] }}"
                            x-show="matchesFilter(@js($t['name']))"
                            class="border-b border-zinc-100 dark:border-zinc-800 last:border-0 cursor-pointer {{ $checked ? 'bg-amber-50/60 dark:bg-amber-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                            wire:click="selectTable('{{ $selectedDatabase }}', {{ \Illuminate\Support\Js::from($t['name']) }}, {{ $t['schema'] ? "'".$t['schema']."'" : 'null' }})">
                            <td class="px-3 py-2" wire:click.stop>
                                <input type="checkbox" @checked($checked)
                                    wire:click.stop="toggleBulk({{ \Illuminate\Support\Js::from($t['name']) }})"
                                    class="rounded border-zinc-300 dark:border-zinc-700" />
                            </td>
                            <td class="px-3 py-2 font-mono text-zinc-900 dark:text-zinc-100">{{ $t['name'] }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if ($t['type'] === 'view')
                                    <span class="rounded px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">view</span>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">table</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                @if ($t['rows'] !== null) {{ number_format($t['rows']) }} @else — @endif
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $fmtSize($t['size']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Type-to-confirm modal for bulk truncate/drop --}}
    @if ($pendingBulkAction)
        @php
            $action = $pendingBulkAction['action'] ?? 'truncate';
            $count = $pendingBulkAction['count'] ?? 0;
            $isDrop = $action === 'drop';
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-4" wire:click.self="cancelBulkAction">
            <div x-data="{ confirmText: '' }" class="bg-white dark:bg-zinc-900 rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center gap-2 {{ $isDrop ? 'text-rose-700' : 'text-amber-700' }}">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $isDrop ? __('explorer.overview.confirm_drop_title', ['count' => $count]) : __('explorer.overview.confirm_truncate_title', ['count' => $count]) }}
                    </h2>
                </div>

                <div class="rounded-md {{ $isDrop ? 'bg-rose-50 border-rose-200' : 'bg-amber-50 border-amber-200' }} border p-3 text-sm {{ $isDrop ? 'text-rose-900' : 'text-amber-900' }}">
                    {{ $isDrop ? __('explorer.overview.confirm_drop_warning') : __('explorer.overview.confirm_truncate_warning') }}
                </div>

                <ul class="max-h-32 overflow-y-auto text-xs font-mono text-zinc-600 dark:text-zinc-400 space-y-0.5 border border-zinc-200 dark:border-zinc-800 rounded p-2 bg-zinc-50 dark:bg-zinc-950">
                    @foreach (($pendingBulkAction['tables'] ?? []) as $tbl)
                        <li>{{ $tbl }}</li>
                    @endforeach
                </ul>

                {{-- FK enforcement toggle (phpMyAdmin-style) --}}
                <label class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300 cursor-pointer">
                    <input type="checkbox" wire:model="enforceForeignKeys"
                        class="mt-0.5 rounded border-zinc-300 dark:border-zinc-700" />
                    <span>
                        {{ __('explorer.overview.fk_checks_label') }}
                        <span class="block text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('explorer.overview.fk_checks_hint') }}</span>
                    </span>
                </label>

                <div>
                    <label class="block text-sm text-zinc-700 dark:text-zinc-300 mb-1">
                        {{ __('explorer.overview.confirm_input_label') }}
                    </label>
                    <input type="text" x-model="confirmText" autofocus
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm font-mono" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="cancelBulkAction"
                        class="px-3 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="button" wire:click="confirmBulkAction"
                        :disabled="confirmText !== 'CONFIRM'"
                        :class="confirmText === 'CONFIRM' ? '{{ $isDrop ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700' }}' : 'bg-zinc-300 cursor-not-allowed'"
                        class="rounded-md px-4 py-2 text-sm font-medium text-white">
                        {{ $isDrop ? __('explorer.overview.confirm_drop_button') : __('explorer.overview.confirm_truncate_button') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
