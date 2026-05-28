<div
    x-data="{
        wipeOpen: false,
        masterPwd: '',
        masterConfirm: '',
        unlockPwd: '',
        label: '',
        color: 'zinc',
        // Tailwind-friendly dot classes per palette key. Defined here so the
        // markup stays declarative — no JIT pitfalls with concatenated names.
        colorClasses: {
            zinc: 'bg-zinc-400',
            blue: 'bg-blue-500',
            emerald: 'bg-emerald-500',
            amber: 'bg-amber-500',
            rose: 'bg-rose-500',
            violet: 'bg-violet-500',
            cyan: 'bg-cyan-500',
            pink: 'bg-pink-500',
        },
        pendingBookmarkId: new URLSearchParams(window.location.search).get('bookmark') || null,
        async tryAutoFill() {
            if (! this.bookmarks || ! this.bookmarks.unlocked || ! this.pendingBookmarkId) return;
            // URL-driven fills (navbar Switch flow) always auto-submit ; the
            // user already clicked their intent, they don't want a second click.
            await this.bookmarks.fill(this.pendingBookmarkId, true);
            this.pendingBookmarkId = null;
            history.replaceState({}, '', window.location.pathname);
        },
        readForm() {
            // Read the login form inputs via the DOM. Both the form and this
            // panel sit on the same page but in distinct Livewire components,
            // so we can't reach $wire of the other side.
            const root = document.querySelector('[data-login-form]');
            if (! root) return null;
            return {
                driver: root.querySelector('[name=driver], #driver')?.value ?? '',
                host: root.querySelector('#host')?.value ?? '',
                port: (() => {
                    const v = root.querySelector('#port')?.value;
                    return v && v !== '' ? Number(v) : null;
                })(),
                database: root.querySelector('#database')?.value ?? '',
                username: root.querySelector('#username')?.value ?? '',
                password: root.querySelector('#password')?.value ?? '',
            };
        },
    }"
    x-bookmarks
    x-init="$nextTick(() => tryAutoFill())"
    class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm p-5 space-y-4"
>
    <div>
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('bookmarks.panel_title') }}</h2>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ __('bookmarks.panel_subtitle') }}</p>
    </div>

    {{-- State A : no master password set yet --}}
    <template x-if="! bookmarks.initialised">
        <form @submit.prevent="if (await bookmarks.initialise(masterPwd, masterConfirm)) { masterPwd = ''; masterConfirm = ''; }" class="space-y-3">
            <h3 class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('bookmarks.setup.title') }}</h3>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('bookmarks.setup.intro') }}</p>
            <input type="password" x-model="masterPwd" placeholder="{{ __('bookmarks.setup.master_password') }}" minlength="8" required
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm" />
            <input type="password" x-model="masterConfirm" placeholder="{{ __('bookmarks.setup.confirm') }}" minlength="8" required
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm" />
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('bookmarks.setup.minimum') }}</p>
            <p x-show="bookmarks.error" x-text="bookmarks.error" class="text-xs text-rose-600"></p>
            <button type="submit"
                class="w-full rounded-md bg-zinc-900 px-3 py-2 text-xs font-medium text-white hover:bg-zinc-800">
                {{ __('bookmarks.setup.submit') }}
            </button>
        </form>
    </template>

    {{-- State B : master password set but locked --}}
    <template x-if="bookmarks.initialised && ! bookmarks.unlocked">
        <form @submit.prevent="if (await bookmarks.unlock(unlockPwd)) { unlockPwd = ''; await tryAutoFill(); }" class="space-y-3">
            <h3 class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('bookmarks.unlock.title') }}</h3>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                <span x-text="bookmarks.publicList.length"></span>
                {{ __('bookmarks.unlock.locked_summary_suffix') }}
            </p>
            <input type="password" x-model="unlockPwd" placeholder="{{ __('bookmarks.unlock.master_password') }}" required
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm" />
            <p x-show="bookmarks.error" x-text="bookmarks.error" class="text-xs text-rose-600"></p>
            <div class="flex items-center justify-between gap-2">
                <button type="submit"
                    class="flex-1 rounded-md bg-zinc-900 px-3 py-2 text-xs font-medium text-white hover:bg-zinc-800">
                    {{ __('bookmarks.unlock.submit') }}
                </button>
                <button type="button" @click="wipeOpen = true"
                    class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 underline-offset-2 hover:underline">
                    {{ __('bookmarks.unlock.forgot') }}
                </button>
            </div>
        </form>
    </template>

    {{-- State C : unlocked --}}
    <template x-if="bookmarks.unlocked">
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('bookmarks.list.unlocked_summary') }}</p>
                <button type="button" @click="bookmarks.lock()" class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                    {{ __('bookmarks.list.lock') }}
                </button>
            </div>

            <template x-if="bookmarks.list.length === 0">
                <p class="text-xs italic text-zinc-500 dark:text-zinc-400 py-3 text-center border border-dashed border-zinc-300 dark:border-zinc-700 rounded">
                    {{ __('bookmarks.list.empty') }}
                </p>
            </template>

            <template x-if="bookmarks.list.length > 0">
                <ul class="space-y-1.5">
                    <template x-for="b in bookmarks.list" :key="b.id">
                        <li class="flex items-center gap-2 group">
                            <button type="button" @click="bookmarks.fill(b.id, true)"
                                class="flex-1 flex items-center gap-2 text-left rounded-md border border-zinc-200 dark:border-zinc-800 px-3 py-2 hover:border-zinc-400 dark:hover:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                <span class="size-2.5 rounded-full shrink-0" :class="colorClasses[b.color] || colorClasses.zinc"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="b.label || (b.username + '@' + b.host)"></div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono truncate" x-text="b.driver + ' · ' + b.host + (b.port ? ':' + b.port : '') + (b.database ? ' / ' + b.database : '')"></div>
                                </div>
                            </button>
                            <button type="button"
                                data-confirm-msg="{{ __('bookmarks.list.remove_confirm') }}"
                                @click="if (confirm($event.currentTarget.dataset.confirmMsg)) bookmarks.remove(b.id)"
                                class="opacity-0 group-hover:opacity-100 text-xs text-zinc-400 dark:text-zinc-500 hover:text-rose-600 dark:hover:text-rose-400 px-2"
                                aria-label="{{ __('bookmarks.list.remove') }}">
                                &times;
                            </button>
                        </li>
                    </template>
                </ul>
            </template>

            {{-- Save current form values --}}
            <div class="border-t border-zinc-200 dark:border-zinc-800 pt-3 space-y-2">
                <h3 class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('bookmarks.save_current.title') }}</h3>
                <input type="text" x-model="label" placeholder="{{ __('bookmarks.save_current.label_placeholder') }}"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm" />

                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1.5">{{ __('bookmarks.save_current.color') }}</p>
                    <div class="flex items-center gap-1.5">
                        <template x-for="c in bookmarks.palette" :key="c">
                            <button type="button" @click="color = c"
                                :class="[colorClasses[c], color === c ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-zinc-100 ring-offset-white dark:ring-offset-zinc-900' : '']"
                                class="size-5 rounded-full transition-shadow"
                                :aria-label="c">
                            </button>
                        </template>
                    </div>
                </div>

                <button type="button"
                    data-missing-msg="{{ __('bookmarks.save_current.missing_credentials') }}"
                    @click="
                        const form = readForm();
                        if (! form || ! form.username || ! form.password) {
                            bookmarks.error = $event.currentTarget.dataset.missingMsg;
                            return;
                        }
                        await bookmarks.save({ ...form, label: label || (form.username + '@' + form.host), color });
                        label = '';
                        color = bookmarks.defaultColor;
                    "
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                    {{ __('bookmarks.save_current.button') }}
                </button>
                <p x-show="bookmarks.error" x-text="bookmarks.error" class="text-xs text-rose-600"></p>
            </div>
        </div>
    </template>

    {{-- Wipe confirmation modal --}}
    <div x-show="wipeOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/40 p-4"
        @click.self="wipeOpen = false">
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-lg max-w-sm w-full p-6 space-y-4">
            <h3 class="text-base font-semibold">{{ __('bookmarks.wipe.title') }}</h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('bookmarks.wipe.intro') }}</p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="wipeOpen = false"
                    class="px-3 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                    {{ __('bookmarks.wipe.cancel') }}
                </button>
                <button type="button" @click="bookmarks.wipe(); wipeOpen = false"
                    class="rounded-md bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700">
                    {{ __('bookmarks.wipe.confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
