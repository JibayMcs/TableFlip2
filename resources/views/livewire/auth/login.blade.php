<div class="max-w-md mx-auto">
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm">
        @if ($breezeEnabled && $directEnabled)
            <div class="flex border-b border-zinc-200 dark:border-zinc-800" role="tablist">
                <button
                    type="button"
                    wire:click="$set('mode', 'account')"
                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors {{ $mode === 'account' ? 'text-zinc-900 dark:text-zinc-100 border-b-2 border-zinc-900 -mb-px' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300' }}"
                >
                    Account
                </button>
                <button
                    type="button"
                    wire:click="$set('mode', 'direct')"
                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors {{ $mode === 'direct' ? 'text-zinc-900 dark:text-zinc-100 border-b-2 border-zinc-900 -mb-px' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:text-zinc-300' }}"
                >
                    Direct database
                </button>
            </div>
        @endif

        <div class="p-6">
            @if ($mode === 'account' && $breezeEnabled)
                <form wire:submit="loginAccount" class="space-y-4">
                    <h1 class="text-lg font-semibold">Sign in to your account</h1>

                    <div>
                        <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Email</label>
                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            required
                            autofocus
                            autocomplete="email"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                        />
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Password</label>
                        <input
                            id="password"
                            type="password"
                            wire:model="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                        />
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <input type="checkbox" wire:model="remember" class="rounded border-zinc-300 dark:border-zinc-700" />
                        Remember me
                    </label>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    >
                        Sign in
                    </button>
                </form>
            @endif

            @if ($mode === 'direct' && $directEnabled)
                <form wire:submit="loginDirect" class="space-y-4">
                    <h1 class="text-lg font-semibold">Connect directly to a database</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 -mt-2">
                        Use the database server's own credentials. The session lives only as long as you stay logged in.
                    </p>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-1">
                            <label for="driver" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Driver</label>
                            <select
                                id="driver"
                                wire:model.live="driver"
                                @disabled($driverLocked)
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ $driverLocked ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                            >
                                @foreach ($driverChoices as $choice)
                                    <option value="{{ $choice }}">{{ $choice }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-2">
                            <label for="host" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Host</label>
                            <input
                                id="host"
                                type="text"
                                wire:model="host"
                                @disabled($hostLocked || $driver === 'sqlite')
                                placeholder="{{ $driver === 'sqlite' ? 'n/a — sqlite uses a file path' : '127.0.0.1' }}"
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ ($hostLocked || $driver === 'sqlite') ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-1">
                            <label for="port" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Port</label>
                            <input
                                id="port"
                                type="number"
                                wire:model="port"
                                @disabled($driver === 'sqlite')
                                placeholder="auto"
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ $driver === 'sqlite' ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                            />
                        </div>

                        <div class="col-span-2">
                            <label for="database" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                @if ($driver === 'sqlite')
                                    SQLite file path
                                @else
                                    Database {{ $databaseRequired ? '' : '(optional)' }}
                                @endif
                            </label>
                            <input
                                id="database"
                                type="text"
                                wire:model="database"
                                @disabled($databaseLocked)
                                @if ($databaseRequired) required @endif
                                placeholder="{{ $driver !== 'sqlite' && ! $databaseRequired ? 'leave empty to list all databases' : '' }}"
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ $databaseLocked ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                            />
                            @error('database') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($driver !== 'sqlite')
                        <div>
                            <label for="username" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Username</label>
                            <input
                                id="username"
                                type="text"
                                wire:model="username"
                                required
                                autocomplete="username"
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                            />
                            @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="directPassword" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Password</label>
                            <input
                                id="directPassword"
                                type="password"
                                wire:model="directPassword"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                            />
                            @error('directPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="loginDirect">Connect</span>
                        <span wire:loading wire:target="loginDirect">Connecting…</span>
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
