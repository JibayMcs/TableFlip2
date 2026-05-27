<div class="space-y-4">
    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2">
            <button type="button" wire:click="toggleFilters"
                class="text-xs px-2.5 py-1.5 rounded border border-zinc-300 hover:bg-zinc-50 {{ $showFilters ? 'bg-zinc-100 border-zinc-400' : '' }}">
                Filters {{ count($filters) > 0 ? '('.count($filters).')' : '' }}
            </button>
            @if (count($filters) > 0)
                <button type="button" wire:click="clearFilters"
                    class="text-xs text-zinc-500 hover:text-zinc-700 underline">
                    Clear all
                </button>
            @endif
            @if (count($sort) > 0)
                <button type="button" wire:click="$set('sort', [])"
                    class="text-xs text-zinc-500 hover:text-zinc-700 underline">
                    Reset sort
                </button>
            @endif
        </div>
        <div class="flex items-center gap-3 text-xs text-zinc-500">
            <span><span class="font-mono font-medium text-zinc-700">{{ number_format($total) }}</span> rows</span>
            <label class="flex items-center gap-1">
                Per page
                <select wire:model.live="perPage"
                    class="text-xs border border-zinc-300 rounded px-1.5 py-1">
                    @foreach ([25, 50, 100, 250] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    {{-- Filter builder --}}
    @if ($showFilters)
        <div class="bg-white border border-zinc-200 rounded-md p-3">
            <div class="space-y-2">
                @foreach ($filters as $i => $f)
                    <div class="flex items-center gap-2" wire:key="filter-{{ $i }}">
                        <select wire:model="filters.{{ $i }}.column"
                            class="text-xs border border-zinc-300 rounded px-2 py-1 max-w-xs">
                            <option value="">— column —</option>
                            @foreach ($columns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="filters.{{ $i }}.operator"
                            class="text-xs border border-zinc-300 rounded px-2 py-1">
                            @foreach ($this->operatorChoices() as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @if (! in_array($f['operator'], ['is_null', 'is_not_null']))
                            <input type="text" wire:model="filters.{{ $i }}.value"
                                placeholder="value"
                                class="text-xs border border-zinc-300 rounded px-2 py-1 flex-1 min-w-0" />
                        @endif
                        <button type="button" wire:click="removeFilter({{ $i }})"
                            class="text-zinc-400 hover:text-rose-600 text-base leading-none px-1"
                            title="Remove">×</button>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-2 pt-2 border-t border-zinc-100">
                <button type="button" wire:click="addFilter"
                    class="text-xs px-2.5 py-1 border border-dashed border-zinc-300 rounded hover:bg-zinc-50">
                    + Add filter
                </button>
                <button type="button" wire:click="applyFilters"
                    class="text-xs px-3 py-1 bg-zinc-900 text-white rounded hover:bg-zinc-800">
                    Apply
                </button>
            </div>
        </div>
    @endif

    {{-- Data table --}}
    @if ($error)
        <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 font-mono">
            {{ $error }}
        </div>
    @else
        <div class="overflow-x-auto bg-white border border-zinc-200 rounded-md">
            <table class="w-full text-sm border-collapse">
                <thead class="text-left text-xs uppercase text-zinc-500 border-b border-zinc-200 bg-zinc-50">
                    <tr>
                        @foreach ($columns as $col)
                            @php
                                $sortDir = collect($sort)->firstWhere('column', $col)['direction'] ?? null;
                            @endphp
                            <th class="px-3 py-2 cursor-pointer select-none hover:bg-zinc-100 whitespace-nowrap"
                                @click="$wire.toggleSort('{{ $col }}', $event.shiftKey)">
                                <div class="flex items-center gap-1">
                                    <span>{{ $col }}</span>
                                    @if ($sortDir === 'asc')
                                        <span class="text-zinc-900">↑</span>
                                    @elseif ($sortDir === 'desc')
                                        <span class="text-zinc-900">↓</span>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $row)
                        <tr class="border-b border-zinc-100 last:border-0 hover:bg-zinc-50/60" wire:key="row-{{ $i }}">
                            @foreach ($columns as $col)
                                <td class="px-3 py-1.5 font-mono text-xs align-top">
                                    @if ($row[$col] === null)
                                        <span class="text-zinc-400 italic">null</span>
                                    @else
                                        <span class="block max-w-md truncate" title="{{ (string) $row[$col] }}">{{ (string) $row[$col] }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(1, count($columns)) }}" class="text-center py-12 text-zinc-400 text-sm">
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
                <button wire:click="setPage(1)" @disabled($page <= 1)
                    class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">‹‹</button>
                <button wire:click="setPage({{ $page - 1 }})" @disabled($page <= 1)
                    class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">‹</button>
                <button wire:click="setPage({{ $page + 1 }})" @disabled($page >= $totalPages)
                    class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">›</button>
                <button wire:click="setPage({{ $totalPages }})" @disabled($page >= $totalPages)
                    class="px-2 py-1 rounded border border-zinc-300 disabled:opacity-30 hover:bg-zinc-50">››</button>
            </div>
        </div>

        <p class="text-xs text-zinc-400">
            Tip: click a column header to sort, shift-click to add to multi-sort.
        </p>
    @endif
</div>
