<div
    @bookmarks:fill.window="$wire.call('fillAndLogin', $event.detail)"
    class="max-w-5xl mx-auto grid gap-6 md:grid-cols-[minmax(0,1fr)_22rem]"
>
    {{-- Login form --}}
    <div data-login-form class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm">
        <div class="p-6">
            <form wire:submit="login" class="space-y-4">
                <h1 class="text-lg font-semibold">{{ __('auth.login.title') }}</h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 -mt-2">
                    {{ __('auth.login.intro') }}
                </p>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label for="driver" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.login.driver') }}</label>
                        <select id="driver" wire:model.live="driver" @disabled($driverLocked)
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ $driverLocked ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                        >
                            @foreach ($driverChoices as $choice)
                                <option value="{{ $choice }}">{{ $choice }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label for="host" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.login.host') }}</label>
                        <input id="host" type="text" wire:model="host"
                            @disabled($hostLocked || $driver === 'sqlite')
                            placeholder="{{ $driver === 'sqlite' ? __('auth.login.host_sqlite_placeholder') : '127.0.0.1' }}"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ ($hostLocked || $driver === 'sqlite') ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label for="port" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.login.port') }}</label>
                        <input id="port" type="number" wire:model="port"
                            @disabled($driver === 'sqlite')
                            placeholder="{{ __('auth.login.port_placeholder') }}"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ $driver === 'sqlite' ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                        />
                    </div>

                    <div class="col-span-2">
                        <label for="database" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                            @if ($driver === 'sqlite')
                                {{ __('auth.login.sqlite_path') }}
                            @else
                                {{ __('auth.login.database') }} {{ $databaseRequired ? '' : '('.__('common.optional').')' }}
                            @endif
                        </label>
                        <input id="database" type="text" wire:model="database"
                            @disabled($databaseLocked)
                            @if ($databaseRequired) required @endif
                            placeholder="{{ $driver !== 'sqlite' && ! $databaseRequired ? __('auth.login.database_placeholder') : '' }}"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 {{ $databaseLocked ? 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-400' : '' }}"
                        />
                        @error('database') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($driver !== 'sqlite')
                    <div>
                        <label for="username" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.login.username') }}</label>
                        <input id="username" type="text" wire:model="username" required autocomplete="username"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                        />
                        @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.login.password') }}</label>
                        <input id="password" type="password" wire:model="password" required autocomplete="current-password"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                        />
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="login">{{ __('auth.login.submit') }}</span>
                    <span wire:loading wire:target="login">{{ __('auth.login.submitting') }}</span>
                </button>
            </form>
        </div>
    </div>

    {{-- Bookmarks panel : separate Livewire component → its DOM is its own
        render boundary, the Login form's wire:model.live round-trips cannot
        morph through it. --}}
    <livewire:auth.bookmarks />
</div>
