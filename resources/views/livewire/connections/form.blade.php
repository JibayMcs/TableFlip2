<div class="max-w-2xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">
            {{ $connection ? __('connections.edit_title') : __('connections.new') }}
        </h1>
        <a href="{{ route('connections.index') }}" wire:navigate class="text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">{{ __('connections.back') }}</a>
    </div>

    @if ($testResult)
        <div class="rounded-md px-4 py-2 text-sm {{ $testResult['ok'] ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' }}">
            {{ $testResult['message'] }}
            @if (! empty($testResult['version']))
                <span class="font-mono text-xs opacity-75">({{ $testResult['version'] }})</span>
            @endif
        </div>
    @endif

    <form wire:submit="save" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-6 space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('connections.form.name') }}</label>
            <input id="name" type="text" wire:model="name" required
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label for="driver" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('connections.form.driver') }}</label>
                <select id="driver" wire:model.live="driver" @disabled($driverLocked)
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm {{ $driverLocked ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}">
                    @foreach ($driverChoices as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
            </div>

            @if ($driver !== 'sqlite')
                <div class="col-span-2">
                    <label for="host" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('connections.form.host') }}</label>
                    <input id="host" type="text" wire:model="host" @disabled($hostLocked) required
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm {{ $hostLocked ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}" />
                    @error('host') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div class="grid grid-cols-3 gap-3">
            @if ($driver !== 'sqlite')
                <div>
                    <label for="port" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('connections.form.port') }}</label>
                    <input id="port" type="number" wire:model="port" placeholder="auto"
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                </div>
            @endif
            <div class="{{ $driver === 'sqlite' ? 'col-span-3' : 'col-span-2' }}">
                <label for="database" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    @if ($driver === 'sqlite')
                        {{ __('connections.form.sqlite_path') }}
                    @else
                        {{ $databaseRequired ? __('connections.form.database') : __('connections.form.database_optional') }}
                    @endif
                </label>
                <input id="database" type="text" wire:model="database"
                    @disabled($databaseLocked) @if ($databaseRequired) required @endif
                    placeholder="{{ $driver !== 'sqlite' && ! $databaseRequired ? __('connections.form.database_placeholder') : '' }}"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm {{ $databaseLocked ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}" />
                @error('database') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($driver !== 'sqlite')
            <div>
                <label for="username" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('connections.form.username') }}</label>
                <input id="username" type="text" wire:model="username" required autocomplete="off"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                @error('username') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ $connection ? __('connections.form.password_keep') : __('connections.form.password') }}
                </label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password"
                    @if (! $connection) required @endif
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                <input type="checkbox" wire:model="ssl" class="rounded border-zinc-300 dark:border-zinc-700" />
                {{ __('connections.form.use_ssl') }}
            </label>
        @endif

        <div>
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('connections.form.color') }}</label>
            <div class="flex items-center gap-2">
                @foreach ($palette as $hex)
                    <button type="button" wire:click="$set('color', '{{ $hex }}')"
                        class="size-6 rounded-full border-2 transition-all {{ $color === $hex ? 'border-zinc-900 scale-110' : 'border-transparent hover:border-zinc-300 dark:border-zinc-700' }}"
                        style="background-color: {{ $hex }}"
                        title="{{ $hex }}"></button>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <button type="button" wire:click="test" wire:loading.attr="disabled" wire:target="test"
                class="rounded-md border border-zinc-300 dark:border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:bg-zinc-950 disabled:opacity-50">
                <span wire:loading.remove wire:target="test">{{ __('connections.form.test') }}</span>
                <span wire:loading wire:target="test">{{ __('connections.form.testing') }}</span>
            </button>

            <div class="flex items-center gap-2">
                <a href="{{ route('connections.index') }}" wire:navigate
                    class="rounded-md px-4 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">{{ __('connections.form.cancel') }}</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50">
                    {{ $connection ? __('connections.form.save') : __('connections.form.create') }}
                </button>
            </div>
        </div>
    </form>
</div>
