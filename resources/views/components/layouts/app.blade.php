@props(['title' => null, 'flush' => false])
@php
    // Server-rendered theme class avoids any FOUC. Falls back to light for
    // anonymous visitors (login screen). The user picks light/dark in the
    // profile page.
    $themePref = auth('web')->user()?->theme ?? 'light';
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

                    <div class="flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400 dark:text-zinc-400">
                        @auth('web')
                            @if (session('tableflip.active_connection_id'))
                                <a href="{{ route('explorer') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">Explorer</a>
                                <a href="{{ route('sql') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">SQL</a>
                                <a href="{{ route('visualizer') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">Diagram</a>
                            @endif

                            <a href="{{ route('exports.index') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">Exports</a>

                            <livewire:navbar.connection-switcher />

                            <a href="{{ route('profile') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">
                                {{ auth('web')->user()->name }}
                            </a>
                            @role('admin')
                                <a href="{{ route('admin.users') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">Users</a>
                            @endrole
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:text-zinc-900 dark:hover:text-zinc-100">Sign out</button>
                            </form>
                        @elseauth('db_session')
                            <a href="{{ route('explorer') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">Explorer</a>
                            <a href="{{ route('sql') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">SQL</a>
                            <a href="{{ route('visualizer') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100 data-current:text-zinc-900 dark:data-current:text-zinc-100 data-current:font-medium">Diagram</a>
                            <span class="text-zinc-500 dark:text-zinc-400 dark:text-zinc-400 font-mono text-xs">
                                {{ auth('db_session')->user()->label() }}
                            </span>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:text-zinc-900 dark:hover:text-zinc-100">Disconnect</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" wire:navigate class="hover:text-zinc-900 dark:hover:text-zinc-100">Sign in</a>
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
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-xs text-zinc-500 dark:text-zinc-400 dark:text-zinc-400">
                        {{ config('app.name') }} — self-hosted database studio.
                    </div>
                </footer>
            @endunless
        </div>

        @livewireScriptConfig
    </body>
</html>
