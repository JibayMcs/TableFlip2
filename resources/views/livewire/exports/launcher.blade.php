<div class="inline-block">
    @if ($available && $showTrigger)
        <button type="button" wire:click="show"
            x-tooltip.bottom="Export result"
            class="inline-flex items-center justify-center size-8 rounded-md border border-zinc-300 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:bg-zinc-950 transition-colors">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
            </svg>
        </button>
    @endif

    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-4" wire:click.self="hide">
            <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Export result</h2>
                    <button type="button" wire:click="hide" class="text-zinc-400 dark:text-zinc-500 hover:text-zinc-700 dark:text-zinc-300 text-xl leading-none">×</button>
                </div>

                @if ($error)
                    <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                        {{ $error }}
                    </div>
                @endif

                {{-- Format selector --}}
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Format</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['csv' => 'CSV', 'json' => 'JSON', 'sql' => 'SQL'] as $value => $label)
                            <button type="button" wire:click="$set('format', '{{ $value }}')"
                                class="px-3 py-2 text-sm rounded border-2 transition-colors {{ $format === $value ? 'border-zinc-900 bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 font-medium' : 'border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 hover:border-zinc-300 dark:border-zinc-700' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- File name --}}
                <div>
                    <label for="export-filename" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">File name</label>
                    <div class="flex items-center gap-2">
                        <input id="export-filename" type="text" wire:model="fileName"
                            class="flex-1 rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">.{{ $format }}</span>
                    </div>
                </div>

                {{-- Format-specific options --}}
                @if ($format === 'csv')
                    <div class="space-y-2 border-t border-zinc-100 dark:border-zinc-800 pt-4">
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                                <input type="checkbox" wire:model="csvIncludeHeader" class="rounded border-zinc-300 dark:border-zinc-700" />
                                Include header row
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm text-zinc-700 dark:text-zinc-300 mb-1">Delimiter</label>
                            <select wire:model="csvDelimiter" class="text-sm border border-zinc-300 dark:border-zinc-700 rounded px-2 py-1">
                                <option value=",">comma ( , )</option>
                                <option value=";">semicolon ( ; )</option>
                                <option value="	">tab</option>
                                <option value="|">pipe ( | )</option>
                            </select>
                        </div>
                    </div>
                @elseif ($format === 'json')
                    <div class="space-y-2 border-t border-zinc-100 dark:border-zinc-800 pt-4">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Layout</label>
                        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="radio" wire:model="jsonLayout" value="lines" class="border-zinc-300 dark:border-zinc-700" />
                            <span>JSON Lines (one object per line, streaming-friendly)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="radio" wire:model="jsonLayout" value="array" class="border-zinc-300 dark:border-zinc-700" />
                            <span>JSON array</span>
                        </label>
                    </div>
                @elseif ($format === 'sql')
                    <div class="space-y-2 border-t border-zinc-100 dark:border-zinc-800 pt-4">
                        <label class="flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" wire:model="sqlIncludeDrop" class="rounded border-zinc-300 dark:border-zinc-700" />
                            Include <code class="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-1 rounded">DROP TABLE IF EXISTS</code>
                        </label>
                        <label class="flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" wire:model="sqlIncludeCreate" class="rounded border-zinc-300 dark:border-zinc-700" />
                            Include <code class="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-1 rounded">CREATE TABLE</code> <span class="text-zinc-400 dark:text-zinc-500 text-xs">(minimal — columns, defaults, PK)</span>
                        </label>
                        <label class="flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" wire:model="sqlMultiRowInsert" class="rounded border-zinc-300 dark:border-zinc-700" />
                            Multi-row <code class="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-1 rounded">INSERT</code> (batches of 100)
                        </label>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button type="button" wire:click="hide"
                        class="px-3 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">Cancel</button>
                    <button type="button" wire:click="startExport"
                        wire:loading.attr="disabled" wire:target="startExport"
                        class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50">
                        <span wire:loading.remove wire:target="startExport">Start export</span>
                        <span wire:loading wire:target="startExport">Queueing…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
