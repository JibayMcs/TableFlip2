<div class="max-w-3xl mx-auto space-y-6">
    {{-- Breadcrumb --}}
    <nav class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1">
        <span>{{ $currentLabel }}</span>
        <span class="text-zinc-300 dark:text-zinc-700">/</span>
        <a href="{{ route('explorer', ['db' => $database]) }}" wire:navigate
            class="hover:text-zinc-900 dark:hover:text-zinc-100 hover:underline">{{ $database }}</a>
        <span class="text-zinc-300 dark:text-zinc-700">/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('exports.database.title_segment') }}</span>
    </nav>

    <header>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
            {{ __('exports.database.title', ['database' => $database ?? '?']) }}
        </h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('exports.database.subtitle') }}
        </p>
    </header>

    @if ($error)
        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800">
            {{ $error }}
        </div>
    @endif

    <form wire:submit="start" class="space-y-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-6">
        {{-- Méthode d'exportation --}}
        <fieldset class="border border-zinc-200 dark:border-zinc-800 rounded-md p-3">
            <legend class="px-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('exports.database.method') }}</legend>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" checked disabled class="text-zinc-900" />
                <span class="text-zinc-900 dark:text-zinc-100">{{ __('exports.database.method_quick') }}</span>
            </label>
            <label class="flex items-center gap-2 text-sm text-zinc-400 dark:text-zinc-500 mt-1">
                <input type="radio" disabled />
                <span>{{ __('exports.database.method_custom') }} <span class="text-[10px] uppercase">{{ __('common.coming_soon') }}</span></span>
            </label>
        </fieldset>

        {{-- Database picker --}}
        <fieldset class="border border-zinc-200 dark:border-zinc-800 rounded-md p-3">
            <legend class="px-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('exports.database.source') }}</legend>
            <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">{{ __('visualizer.database') }}</label>
            <select wire:model="database"
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm">
                <option value="">{{ __('visualizer.pick') }}</option>
                @foreach ($databases as $db)
                    <option value="{{ $db }}">{{ $db }}</option>
                @endforeach
            </select>
        </fieldset>

        {{-- Format --}}
        <fieldset class="border border-zinc-200 dark:border-zinc-800 rounded-md p-3">
            <legend class="px-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('exports.database.format') }}</legend>
            <select wire:model="format" disabled
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400 cursor-not-allowed">
                <option value="sql_dump">{{ __('exports.database.format_sql_dump') }}</option>
            </select>
            <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('exports.database.format_sql_dump_hint') }}</p>
        </fieldset>

        {{-- Sortie --}}
        <fieldset class="border border-zinc-200 dark:border-zinc-800 rounded-md p-3 space-y-3">
            <legend class="px-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('exports.database.output') }}</legend>

            <div>
                <label for="filename-template" class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                    {{ __('exports.database.filename_template') }}
                </label>
                <input id="filename-template" type="text" wire:model="filenameTemplate"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm font-mono" />
                <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('exports.database.filename_template_hint') }}</p>
            </div>

            <div>
                <label for="compression" class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">{{ __('exports.database.compression') }}</label>
                <select id="compression" wire:model="compression"
                    class="w-full sm:w-48 rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm">
                    <option value="none">{{ __('exports.database.compression_none') }}</option>
                    <option value="gzip">{{ __('exports.database.compression_gzip') }}</option>
                    <option value="zip">{{ __('exports.database.compression_zip') }}</option>
                </select>
            </div>
        </fieldset>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('explorer', ['db' => $database]) }}" wire:navigate
                class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="start"
                class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50">
                <span wire:loading.remove wire:target="start">{{ __('exports.database.start') }}</span>
                <span wire:loading wire:target="start">{{ __('exports.database.queueing') }}</span>
            </button>
        </div>
    </form>
</div>
