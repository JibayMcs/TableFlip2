<div class="max-w-2xl mx-auto space-y-8">
    <h1 class="text-2xl font-semibold">{{ __('auth.profile.title') }}</h1>

    @if (session('profile_status'))
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            {{ session('profile_status') }}
        </div>
    @endif

    <section class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-6">
        <form wire:submit="saveProfile" class="space-y-4">
            <h2 class="text-base font-semibold">{{ __('auth.profile.account') }}</h2>

            <div>
                <label for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.profile.name') }}</label>
                <input id="name" type="text" wire:model="name" required
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900" />
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.email') }}</label>
                <input id="email" type="email" wire:model="email" required
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900" />
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label for="timezone" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.profile.timezone') }}</label>
                    <input id="timezone" type="text" wire:model="timezone"
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label for="locale" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.profile.locale') }}</label>
                    <select id="locale" wire:model="locale"
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm">
                        <option value="fr">{{ __('auth.profile.locale_fr') }}</option>
                        <option value="en">{{ __('auth.profile.locale_en') }}</option>
                    </select>
                </div>
                <div>
                    <label for="theme" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.profile.theme') }}</label>
                    <select id="theme" wire:model="theme"
                        class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm">
                        <option value="light">{{ __('auth.profile.theme_light') }}</option>
                        <option value="dark">{{ __('auth.profile.theme_dark') }}</option>
                    </select>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
                    {{ __('auth.profile.save_button') }}
                </button>
            </div>
        </form>
    </section>

    @if (session('password_status'))
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            {{ session('password_status') }}
        </div>
    @endif

    <section class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-6">
        <form wire:submit="changePassword" class="space-y-4">
            <h2 class="text-base font-semibold">{{ __('auth.profile.change_password') }}</h2>

            <div>
                <label for="currentPassword" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.profile.current_password') }}</label>
                <input id="currentPassword" type="password" wire:model="currentPassword" required
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                @error('currentPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="newPassword" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.profile.new_password') }}</label>
                <input id="newPassword" type="password" wire:model="newPassword" required minlength="8"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                @error('newPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="newPasswordConfirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('auth.profile.confirm_password') }}</label>
                <input id="newPasswordConfirmation" type="password" wire:model="newPasswordConfirmation" required
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
                    {{ __('auth.profile.update_password_button') }}
                </button>
            </div>
        </form>
    </section>
</div>
