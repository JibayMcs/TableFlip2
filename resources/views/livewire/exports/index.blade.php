<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('exports.title') }}</h1>
        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('exports.expire_notice', ['days' => config('tableflip.exports.retention_days', 7)]) }}</span>
    </div>

    @if (session('export_queued'))
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            {{ session('export_queued') }}
        </div>
    @endif

    @if ($exports->isEmpty())
        <div class="rounded-md border border-dashed border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('exports.empty') }}
        </div>
    @else
        <div class="overflow-x-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-4 py-2">{{ __('exports.columns.file') }}</th>
                        <th class="px-4 py-2">{{ __('exports.columns.format') }}</th>
                        <th class="px-4 py-2">{{ __('exports.columns.source') }}</th>
                        <th class="px-4 py-2">{{ __('exports.columns.status') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('exports.columns.rows_size') }}</th>
                        <th class="px-4 py-2">{{ __('exports.columns.created') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('exports.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exports as $export)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0" wire:key="export-{{ $export->id }}">
                            <td class="px-4 py-2 font-mono text-xs">{{ $export->file_name }}</td>
                            <td class="px-4 py-2">
                                <span class="font-mono text-xs uppercase rounded bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5">{{ $export->format }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-zinc-600 dark:text-zinc-400">
                                @if ($export->source_kind === 'raw_sql')
                                    <span class="font-mono">SQL · {{ \Illuminate\Support\Str::limit($export->source_payload['sql'] ?? '', 60) }}</span>
                                @else
                                    <span class="font-mono">{{ $export->database_name }}.{{ $export->source_payload['name'] ?? '?' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($export->status === 'completed')
                                    <span class="inline-flex items-center gap-1 text-xs text-emerald-700">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span> {{ __('exports.status.ready') }}
                                    </span>
                                @elseif ($export->status === 'failed')
                                    <span class="inline-flex items-center gap-1 text-xs text-rose-700" title="{{ $export->error_message }}">
                                        <span class="size-1.5 rounded-full bg-rose-500"></span> {{ __('exports.status.failed') }}
                                    </span>
                                @elseif ($export->status === 'running')
                                    <span class="inline-flex items-center gap-1 text-xs text-blue-700">
                                        <span class="size-1.5 rounded-full bg-blue-500 animate-pulse"></span> {{ __('exports.status.running') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span class="size-1.5 rounded-full bg-zinc-400"></span> {{ __('exports.status.queued') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                @if ($export->isCompleted())
                                    {{ number_format($export->row_count) }} · {{ number_format($export->byte_size / 1024, 1) }} KB
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $export->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                @if ($export->isCompleted() && $export->download_url && ! $export->isExpired())
                                    <a href="{{ $export->download_url }}"
                                        class="text-xs text-zinc-900 dark:text-zinc-100 hover:underline font-medium">{{ __('exports.download') }}</a>
                                @endif
                                <button type="button" wire:click="deleteExport({{ $export->id }})"
                                    wire:confirm="{{ __('exports.delete_confirm') }}"
                                    class="text-xs text-rose-600 hover:text-rose-700">{{ __('exports.delete') }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>{{ $exports->links() }}</div>

        <button type="button" wire:click="$refresh"
            class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100 underline">
            {{ __('exports.refresh') }}
        </button>
    @endif
</div>
