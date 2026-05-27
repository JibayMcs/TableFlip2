<div class="max-w-2xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">
            {{ $connection ? 'Edit connection' : 'New connection' }}
        </h1>
        <a href="{{ route('connections.index') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-900">← Back</a>
    </div>

    @if ($testResult)
        <div class="rounded-md px-4 py-2 text-sm {{ $testResult['ok'] ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' }}">
            {{ $testResult['message'] }}
            @if (! empty($testResult['version']))
                <span class="font-mono text-xs opacity-75">({{ $testResult['version'] }})</span>
            @endif
        </div>
    @endif

    <form wire:submit="save" class="bg-white border border-zinc-200 rounded-lg p-6 space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-zinc-700 mb-1">Name</label>
            <input id="name" type="text" wire:model="name" required
                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" />
            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label for="driver" class="block text-sm font-medium text-zinc-700 mb-1">Driver</label>
                <select id="driver" wire:model.live="driver" @disabled($driverLocked)
                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm {{ $driverLocked ? 'bg-zinc-50 text-zinc-500' : '' }}">
                    @foreach ($driverChoices as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
            </div>

            @if ($driver !== 'sqlite')
                <div class="col-span-2">
                    <label for="host" class="block text-sm font-medium text-zinc-700 mb-1">Host</label>
                    <input id="host" type="text" wire:model="host" @disabled($hostLocked) required
                        class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm {{ $hostLocked ? 'bg-zinc-50 text-zinc-500' : '' }}" />
                    @error('host') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div class="grid grid-cols-3 gap-3">
            @if ($driver !== 'sqlite')
                <div>
                    <label for="port" class="block text-sm font-medium text-zinc-700 mb-1">Port</label>
                    <input id="port" type="number" wire:model="port" placeholder="auto"
                        class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" />
                </div>
            @endif
            <div class="{{ $driver === 'sqlite' ? 'col-span-3' : 'col-span-2' }}">
                <label for="database" class="block text-sm font-medium text-zinc-700 mb-1">
                    @if ($driver === 'sqlite')
                        SQLite file path
                    @else
                        Database {{ $databaseRequired ? '' : '(optional)' }}
                    @endif
                </label>
                <input id="database" type="text" wire:model="database"
                    @disabled($databaseLocked) @if ($databaseRequired) required @endif
                    placeholder="{{ $driver !== 'sqlite' && ! $databaseRequired ? 'leave empty to list all databases' : '' }}"
                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm {{ $databaseLocked ? 'bg-zinc-50 text-zinc-500' : '' }}" />
                @error('database') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($driver !== 'sqlite')
            <div>
                <label for="username" class="block text-sm font-medium text-zinc-700 mb-1">Username</label>
                <input id="username" type="text" wire:model="username" required autocomplete="off"
                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" />
                @error('username') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-zinc-700 mb-1">
                    Password {{ $connection ? '(leave empty to keep the stored one)' : '' }}
                </label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password"
                    @if (! $connection) required @endif
                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" />
                @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-700">
                <input type="checkbox" wire:model="ssl" class="rounded border-zinc-300" />
                Use SSL/TLS
            </label>
        @endif

        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-2">Color</label>
            <div class="flex items-center gap-2">
                @foreach ($palette as $hex)
                    <button type="button" wire:click="$set('color', '{{ $hex }}')"
                        class="size-6 rounded-full border-2 transition-all {{ $color === $hex ? 'border-zinc-900 scale-110' : 'border-transparent hover:border-zinc-300' }}"
                        style="background-color: {{ $hex }}"
                        title="{{ $hex }}"></button>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-zinc-100">
            <button type="button" wire:click="test" wire:loading.attr="disabled" wire:target="test"
                class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-50">
                <span wire:loading.remove wire:target="test">Test connection</span>
                <span wire:loading wire:target="test">Testing…</span>
            </button>

            <div class="flex items-center gap-2">
                <a href="{{ route('connections.index') }}" wire:navigate
                    class="rounded-md px-4 py-2 text-sm text-zinc-600 hover:text-zinc-900">Cancel</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50">
                    {{ $connection ? 'Save changes' : 'Create connection' }}
                </button>
            </div>
        </div>
    </form>
</div>
