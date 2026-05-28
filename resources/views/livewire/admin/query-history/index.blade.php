<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('admin.query_history.title') }}</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('admin.query_history.subtitle') }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.300ms="search"
            placeholder="{{ __('admin.query_history.search_placeholder') }}"
            class="flex-1 min-w-[20rem] rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm" />

        <select wire:model.live="statusFilter"
            class="rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm">
            <option value="">{{ __('admin.common.filter_by_status') }} — {{ __('admin.common.filter_all') }}</option>
            @foreach ($statusChoices as $st)
                <option value="{{ $st }}">{{ __('admin.query_history.status_'.$st) }}</option>
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
                    <th class="px-4 py-2 w-44">{{ __('admin.query_history.col_when') }}</th>
                    <th class="px-4 py-2">{{ __('admin.query_history.col_user') }}</th>
                    <th class="px-4 py-2">{{ __('admin.query_history.col_db') }}</th>
                    <th class="px-4 py-2 w-24">{{ __('admin.query_history.col_status') }}</th>
                    <th class="px-4 py-2 w-24 text-right">{{ __('admin.query_history.col_duration') }}</th>
                    <th class="px-4 py-2 w-20 text-right">{{ __('admin.query_history.col_rows') }}</th>
                    <th class="px-4 py-2">{{ __('admin.query_history.col_sql') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0 align-top"
                        wire:key="qh-{{ $entry->id }}"
                        x-data="{ open: false }">
                        <td class="px-4 py-2 text-zinc-700 dark:text-zinc-300 whitespace-nowrap">{{ $entry->executed_at?->isoFormat('YYYY-MM-DD HH:mm:ss') }}</td>
                        <td class="px-4 py-2">
                            <div class="text-zinc-700 dark:text-zinc-300 font-mono text-xs">{{ $entry->user_identifier }}</div>
                            <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('admin.common.kind_'.$entry->user_kind) }}</div>
                        </td>
                        <td class="px-4 py-2 text-zinc-700 dark:text-zinc-300">{{ $entry->database_name ?: '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($entry->status === 'success')
                                <span class="inline-flex items-center gap-1 rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span> {{ __('admin.query_history.status_success') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-300">
                                    <span class="size-1.5 rounded-full bg-rose-500"></span> {{ __('admin.query_history.status_error') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right text-zinc-700 dark:text-zinc-300 tabular-nums">{{ __('admin.query_history.duration_ms', ['ms' => number_format((int) $entry->duration_ms)]) }}</td>
                        <td class="px-4 py-2 text-right text-zinc-700 dark:text-zinc-300 tabular-nums">{{ number_format((int) $entry->affected_rows) }}</td>
                        <td class="px-4 py-2">
                            <button type="button" x-on:click="open = !open"
                                class="text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 underline-offset-2 hover:underline">
                                <span x-text="open ? '{{ __('admin.common.collapse_sql') }}' : '{{ __('admin.common.expand_sql') }}'"></span>
                            </button>
                            <div x-show="open" x-cloak class="mt-2 space-y-2">
                                <pre class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded p-3 text-xs font-mono whitespace-pre-wrap break-all text-zinc-800 dark:text-zinc-200">{{ $entry->sql_text }}</pre>
                                @if ($entry->status === 'error' && $entry->error_message)
                                    <div class="text-xs">
                                        <div class="font-medium text-rose-500 dark:text-rose-400 mb-1">{{ __('admin.query_history.error_message') }}</div>
                                        <pre class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded p-2 font-mono text-rose-700 dark:text-rose-300 whitespace-pre-wrap break-all">{{ $entry->error_message }}</pre>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                            {{ __('admin.common.no_results') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $entries->links() }}</div>
</div>
