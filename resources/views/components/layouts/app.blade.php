@props(['title' => null, 'flush' => false])
@php
    // Server-rendered theme class avoids any FOUC. Reads from a cookie set
    // by the theme toggle. Falls back to light when the cookie is missing
    // (anonymous visitors, first visit).
    $themePref = $_COOKIE['tableflip_theme'] ?? 'light';
    if (! in_array($themePref, ['light', 'dark'], true)) {
        $themePref = 'light';
    }
    $isDark = $themePref === 'dark';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full {{ $isDark ? 'dark' : '' }}"
    data-theme="{{ $themePref }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])

        @livewireStyles
    </head>
    <body class="h-full bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased dark:bg-zinc-950">
        <div @class([
            'flex flex-col',
            'h-screen' => $flush,
            'min-h-full' => ! $flush,
        ])>
            <header class="shrink-0 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:bg-zinc-900">
                <nav class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
                    <a href="{{ url('/') }}" wire:navigate class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-zinc-100">
                        <h1 class="table-flip-logo"></h1>
                    </a>

                    <div class="flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                        @auth('db_session')
                            <a href="{{ route('explorer') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">{{ __('common.navbar.explorer') }}</a>
                            <a href="{{ route('sql') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">{{ __('common.navbar.sql') }}</a>
                            <a href="{{ route('visualizer') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">{{ __('common.navbar.diagram') }}</a>
                            <a href="{{ route('exports.index') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">{{ __('common.navbar.exports') }}</a>
                            <a href="{{ route('docs') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">{{ __('common.navbar.docs') }}</a>
                            <a href="{{ route('admin.audit') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">{{ __('admin.navbar.audit') }}</a>
                            <a href="{{ route('admin.history') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">{{ __('admin.navbar.history') }}</a>

                            {{-- Switch-connection dropdown : reads the public list from
                                localStorage. The label and accent colour stay in clear so we
                                can display them while the store is locked ; everything else
                                (driver / host / username / password) is encrypted and only
                                visible after the master password has been entered on /login. --}}
                            <div x-data="{
                                    open: false,
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
                                }" x-bookmarks class="relative">
                                <button type="button" @click="open = ! open" :disabled="bookmarks.publicList.length === 0"
                                    class="hover:text-zinc-900 dark:hover:text-zinc-100 disabled:opacity-40 disabled:cursor-not-allowed">
                                    {{ __('bookmarks.navbar.switch') }}
                                </button>
                                <div x-show="open" x-cloak @click.away="open = false"
                                    class="absolute right-0 mt-2 w-72 rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-lg z-50 py-1">
                                    <template x-if="bookmarks.publicList.length === 0">
                                        <p class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400 italic">{{ __('bookmarks.navbar.no_saved') }}</p>
                                    </template>
                                    <template x-for="b in bookmarks.publicList" :key="b.id">
                                        <form method="POST" action="{{ route('logout') }}" class="block">
                                            @csrf
                                            <input type="hidden" name="bookmark" :value="b.id">
                                            <button type="submit"
                                                class="w-full flex items-center gap-2 text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                                <span class="size-2.5 rounded-full shrink-0" :class="colorClasses[b.color] || colorClasses.zinc"></span>
                                                <span class="text-sm text-zinc-900 dark:text-zinc-100 truncate" x-text="b.label"></span>
                                            </button>
                                        </form>
                                    </template>
                                </div>
                            </div>

                            <span x-tooltip.bottom="{{ auth('db_session')->user()->label() }}"
                                class="hidden lg:inline-block max-w-[16rem] truncate align-middle text-zinc-500 dark:text-zinc-400 font-mono text-xs cursor-default">
                                {{ auth('db_session')->user()->shortLabel() }}
                            </span>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:text-zinc-900 dark:hover:text-zinc-100">{{ __('common.disconnect') }}</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100">{{ __('common.sign_in') }}</a>
                        @endauth
                    </div>
                </nav>
            </header>

            <main @class([
                'flex-1',
                'min-h-0 overflow-hidden' => $flush,
            ])>
                @if ($flush)
                    {{ $slot }}
                @else
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        {{ $slot }}
                    </div>
                @endif
            </main>

            @unless ($flush)
                <footer class="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shrink-0 dark:border-zinc-800">
                    <div class="flex items-center justify-between mx-auto px-4 sm:px-6 lg:px-8 py-4 text-xs text-zinc-500 dark:text-zinc-400 dark:text-zinc-400">
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-bold">
                            <span class="text-black dark:text-white">(╯°□°)╯</span><span class="text-gray-500 dark:text-gray-400">彡</span> <span class="dark:text-amber-600 text-amber-900">┻━┻</span>
                        </p>
                        <div>
                            <a href="https://github.com/JibayMcs/TableFlip2" target="_blank" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M208,104v8a48,48,0,0,1-48,48H136a32,32,0,0,1,32,32v40H104V192a32,32,0,0,1,32-32H112a48,48,0,0,1-48-48v-8a49.28,49.28,0,0,1,8.51-27.3A51.92,51.92,0,0,1,76,32a52,52,0,0,1,43.83,24h32.34A52,52,0,0,1,196,32a51.92,51.92,0,0,1,3.49,44.7A49.28,49.28,0,0,1,208,104Z" opacity="0.2"></path><path d="M208.3,75.68A59.74,59.74,0,0,0,202.93,28,8,8,0,0,0,196,24a59.75,59.75,0,0,0-48,24H124A59.75,59.75,0,0,0,76,24a8,8,0,0,0-6.93,4,59.78,59.78,0,0,0-5.38,47.68A58.14,58.14,0,0,0,56,104v8a56.06,56.06,0,0,0,48.44,55.47A39.8,39.8,0,0,0,96,192v8H72a24,24,0,0,1-24-24A40,40,0,0,0,8,136a8,8,0,0,0,0,16,24,24,0,0,1,24,24,40,40,0,0,0,40,40H96v16a8,8,0,0,0,16,0V192a24,24,0,0,1,48,0v40a8,8,0,0,0,16,0V192a39.8,39.8,0,0,0-8.44-24.53A56.06,56.06,0,0,0,216,112v-8A58,58,0,0,0,208.3,75.68ZM200,112a40,40,0,0,1-40,40H112a40,40,0,0,1-40-40v-8a41.74,41.74,0,0,1,6.9-22.48A8,8,0,0,0,80,73.83a43.81,43.81,0,0,1,.79-33.58,43.88,43.88,0,0,1,32.32,20.06A8,8,0,0,0,119.82,64h32.35a8,8,0,0,0,6.74-3.69,43.87,43.87,0,0,1,32.32-20.06A43.81,43.81,0,0,1,192,73.83a8.09,8.09,0,0,0,1,7.65A41.76,41.76,0,0,1,200,104Z"></path></svg>                            </a>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-bold">
                            Made with 💖 by <a href="https://github.com/JibayMcs" target="_blank" class="font-bold hover:underline hover:text-emerald-400 text-emerald-600">JibayMcs</a>
                        </p>
                    </div>
                </footer>
            @endunless
        </div>

        @livewireScriptConfig
    </body>
</html>
