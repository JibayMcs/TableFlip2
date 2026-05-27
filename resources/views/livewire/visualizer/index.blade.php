<div class="h-full flex flex-col"
    x-data="{
        search: '',
        selected: null,
        fit() {
            const cy = window.tfCytoscape?.instance($refs.diagram);
            cy?.fit(undefined, 30);
        },
        downloadPng() {
            const cy = window.tfCytoscape?.instance($refs.diagram);
            if (!cy) return;
            const blob = cy.png({ output: 'blob', scale: 2, bg: '#ffffff', full: true });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = ($wire.database || 'schema') + '-erd.png';
            a.click();
            URL.revokeObjectURL(url);
        }
    }"
    @cy:node-selected.window="selected = $event.detail"
    @cy:node-deselected.window="selected = null">

    {{-- Toolbar --}}
    <div class="shrink-0 px-4 py-2 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex items-center gap-3 text-xs text-zinc-600 dark:text-zinc-400 flex-wrap">
        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $currentLabel }}</span>
        <span class="text-zinc-300 dark:text-zinc-700">/</span>

        <label class="flex items-center gap-1">
            <span class="text-zinc-500 dark:text-zinc-400">Database</span>
            <select wire:model="database" class="text-xs border border-zinc-300 dark:border-zinc-700 rounded px-1.5 py-1">
                <option value="">— pick —</option>
                @foreach ($databases as $db)
                    <option value="{{ $db }}">{{ $db }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
            <input type="checkbox" wire:model="compact" class="rounded border-zinc-300 dark:border-zinc-700" />
            Compact (PK + FK only)
        </label>

        <label class="flex items-center gap-1">
            <span class="text-zinc-500 dark:text-zinc-400">Layout</span>
            <select wire:model.live="layout" class="text-xs border border-zinc-300 dark:border-zinc-700 rounded px-1.5 py-1">
                <option value="dagre">dagre (hierarchical)</option>
                <option value="fcose">fcose (force-directed, fast)</option>
                <option value="cose">cose-bilkent (organic)</option>
            </select>
        </label>

        <button type="button" wire:click="generate"
            wire:loading.attr="disabled" wire:target="generate"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded bg-zinc-900 text-white hover:bg-zinc-800 disabled:opacity-50 text-xs font-medium">
            <svg wire:loading.remove wire:target="generate" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            <svg wire:loading wire:target="generate" class="size-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span wire:loading.remove wire:target="generate">Generate diagram</span>
            <span wire:loading wire:target="generate">Generating…</span>
        </button>

        @if ($tableCount > 0)
            <div class="ml-auto flex items-center gap-2">
                <input type="search" x-model.debounce.200ms="search" placeholder="Highlight table…"
                    class="w-40 rounded border border-zinc-300 dark:border-zinc-700 px-2 py-1 text-xs" />
                <span class="text-zinc-500 dark:text-zinc-400">
                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $tableCount }}</span> entities ·
                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $relationshipCount }}</span> relationships
                    @if (count($skippedTables) > 0)
                        · <span class="text-amber-700" x-tooltip.bottom="{{ implode(', ', $skippedTables) }}">{{ count($skippedTables) }} skipped</span>
                    @endif
                </span>
                <button type="button" @click="fit()"
                    x-tooltip.bottom="Fit diagram to viewport"
                    class="inline-flex items-center justify-center size-7 rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:bg-zinc-950">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 3 21 3 21 9"/>
                        <polyline points="9 21 3 21 3 15"/>
                        <line x1="21" y1="3" x2="14" y2="10"/>
                        <line x1="3" y1="21" x2="10" y2="14"/>
                    </svg>
                </button>
                <button type="button" @click="downloadPng()"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:bg-zinc-950 text-xs">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    PNG
                </button>
            </div>
        @endif
    </div>

    {{-- Body --}}
    <div class="flex-1 min-h-0 flex bg-zinc-50 dark:bg-zinc-950">
        <div class="flex-1 min-w-0 min-h-0 relative">
            @if ($error)
                <div class="absolute inset-0 p-4">
                    <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 font-mono whitespace-pre-wrap">
                        {{ $error }}
                    </div>
                </div>
            @elseif ($tableCount === 0)
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center text-zinc-500 dark:text-zinc-400 text-sm">
                    <svg class="size-12 text-zinc-300 dark:text-zinc-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="6" cy="6" r="2" stroke-width="1.5"/>
                        <circle cx="18" cy="6" r="2" stroke-width="1.5"/>
                        <circle cx="6" cy="18" r="2" stroke-width="1.5"/>
                        <circle cx="18" cy="18" r="2" stroke-width="1.5"/>
                        <line x1="8" y1="6" x2="16" y2="6" stroke-width="1.5"/>
                        <line x1="6" y1="8" x2="6" y2="16" stroke-width="1.5"/>
                        <line x1="18" y1="8" x2="18" y2="16" stroke-width="1.5"/>
                        <line x1="8" y1="18" x2="16" y2="18" stroke-width="1.5"/>
                    </svg>
                    Pick a database and click <span class="font-medium text-zinc-700 dark:text-zinc-300 mx-1">Generate</span> to render the schema diagram.
                    <p class="mt-2 text-xs text-zinc-400 dark:text-zinc-500 max-w-md">Tip : enable <em>Compact</em> on huge schemas (200+ tables) to keep PK + FK columns only. Click a node to highlight its relationships, drag nodes to rearrange.</p>
                </div>
            @else
                <div x-ref="diagram"
                    x-cytoscape="{ nodes: $wire.nodes, edges: $wire.edges, layout: $wire.layout, highlight: search }"
                    wire:ignore
                    class="absolute inset-0 h-full"></div>
            @endif
        </div>

        {{-- Side panel : details for the selected node --}}
        <aside x-show="selected" x-cloak x-transition
            class="w-80 shrink-0 border-l border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-y-auto">
            <template x-if="selected">
                <div class="p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" x-text="selected.label"></h3>
                        <button type="button" @click="selected = null"
                            class="text-zinc-400 dark:text-zinc-500 hover:text-zinc-700 dark:text-zinc-300 text-lg leading-none">&times;</button>
                    </div>
                    <p class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400" x-text="selected.qualified"></p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        <span x-text="selected.visibleColumnCount"></span> /
                        <span x-text="selected.columnCount"></span> columns
                    </p>
                    <ul class="space-y-px text-xs font-mono">
                        <template x-for="col in (selected.columns || [])" :key="col.name">
                            <li class="flex items-center gap-1.5 px-1.5 py-1 rounded hover:bg-zinc-50 dark:bg-zinc-950">
                                <span class="truncate flex-1" x-text="col.name"></span>
                                <span class="text-zinc-400 dark:text-zinc-500" x-text="col.type"></span>
                                <template x-if="col.pk">
                                    <span class="text-[10px] text-amber-700 bg-amber-50 border border-amber-200 rounded px-1">PK</span>
                                </template>
                                <template x-if="col.fk">
                                    <span class="text-[10px] text-blue-700 bg-blue-50 border border-blue-200 rounded px-1">FK</span>
                                </template>
                                <template x-if="!col.nullable">
                                    <span class="text-[10px] text-rose-500">*</span>
                                </template>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </aside>
    </div>
</div>
