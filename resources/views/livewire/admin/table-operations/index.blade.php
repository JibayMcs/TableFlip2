<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('admin.table_operations.title') }}</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('admin.table_operations.subtitle') }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.300ms="search"
            placeholder="{{ __('admin.table_operations.search_placeholder') }}"
            class="flex-1 min-w-[20rem] rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm" />

        <select wire:model.live="opFilter"
            class="rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm">
            <option value="">{{ __('admin.common.filter_by_op') }} — {{ __('admin.common.filter_all') }}</option>
            @foreach ($opChoices as $op)
                <option value="{{ $op }}">{{ __('admin.table_operations.op_'.$op) }}</option>
            @endforeach
        </select>

        <select wire:model.live="kindFilter"
            class="rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm">
            <option value="">{{ __('admin.common.filter_by_kind') }} — {{ __('admin.common.filter_all') }}</option>
            @foreach ($kindChoices as $kind)
                <option value="{{ $kind }}">{{ __('admin.common.kind_'.$kind) }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                <tr>
                    <th class="px-4 py-2 w-44">{{ __('admin.table_operations.col_when') }}</th>
                    <th class="px-4 py-2">{{ __('admin.table_operations.col_user') }}</th>
                    <th class="px-4 py-2">{{ __('admin.table_operations.col_target') }}</th>
                    <th class="px-4 py-2 w-24">{{ __('admin.table_operations.col_operation') }}</th>
                    <th class="px-4 py-2 w-20 text-right">{{ __('admin.table_operations.col_rows') }}</th>
                    <th class="px-4 py-2">{{ __('admin.table_operations.col_sql') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($operations as $op)
                    <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0 align-top"
                        wire:key="op-{{ $op->id }}"
                        x-data="{ open: false }">
                        <td class="px-4 py-2 text-zinc-700 dark:text-zinc-300 whitespace-nowrap">{{ $op->performed_at?->isoFormat('YYYY-MM-DD HH:mm:ss') }}</td>
                        <td class="px-4 py-2">
                            <div class="text-zinc-700 dark:text-zinc-300 font-mono text-xs">{{ $op->user_identifier }}</div>
                            <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('admin.common.kind_'.$op->user_kind) }}</div>
                        </td>
                        <td class="px-4 py-2 text-zinc-700 dark:text-zinc-300">
                            <span class="text-zinc-400 dark:text-zinc-500">{{ $op->database_name }}</span>{{ $op->schema_name ? '.'.$op->schema_name : '' }}.<span class="font-medium">{{ $op->table_name }}</span>
                        </td>
                        <td class="px-4 py-2">
                            @php
                                $badgeClass = match ($op->operation) {
                                    'insert' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
                                    'update' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800',
                                    'delete' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
                                    'truncate', 'drop' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
                                    default => 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                {{ strtoupper($op->operation) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-zinc-700 dark:text-zinc-300 tabular-nums">{{ number_format((int) $op->affected_rows) }}</td>
                        <td class="px-4 py-2">
                            <button type="button" x-on:click="open = !open"
                                class="text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 underline-offset-2 hover:underline">
                                <span x-text="open ? '{{ __('admin.common.collapse_sql') }}' : '{{ __('admin.common.expand_sql') }}'"></span>
                            </button>
                            <div x-show="open" x-cloak class="mt-2 space-y-2">
                                <pre class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded p-3 text-xs font-mono whitespace-pre-wrap break-all text-zinc-800 dark:text-zinc-200">{{ $op->sql_text }}</pre>
                                <div class="text-xs">
                                    <div class="font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('admin.table_operations.bindings') }}</div>
                                    @if (is_array($op->bindings) && count($op->bindings) > 0)
                                        <pre class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded p-2 font-mono text-zinc-700 dark:text-zinc-300">{{ json_encode($op->bindings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @else
                                        <p class="text-zinc-400 dark:text-zinc-500 italic">{{ __('admin.table_operations.no_bindings') }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                            {{ __('admin.common.no_results') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $operations->links() }}</div>
</div>
