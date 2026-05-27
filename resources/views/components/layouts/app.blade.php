@props(['title' => null, 'flush' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])

        @livewireStyles
    </head>
    <body class="h-full bg-zinc-50 text-zinc-900 antialiased">
        <div @class([
            'flex flex-col',
            'h-screen' => $flush,
            'min-h-full' => ! $flush,
        ])>
            <header class="shrink-0 bg-white border-b border-zinc-200">
                <nav class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
                    <a href="{{ url('/') }}" wire:navigate class="flex items-center gap-2 font-semibold text-zinc-900">
                        <h1 class="table-flip-logo"></h1>
                    </a>

                    <div class="flex items-center gap-4 text-sm text-zinc-600">
                        @auth('web')
                            @if (session('tableflip.active_connection_id'))
                                <a href="{{ route('explorer') }}" wire:navigate class="hover:text-zinc-900 data-current:text-zinc-900 data-current:font-medium">Explorer</a>
                            @endif

                            <livewire:navbar.connection-switcher />

                            <a href="{{ route('profile') }}" wire:navigate class="hover:text-zinc-900 data-current:text-zinc-900 data-current:font-medium">
                                {{ auth('web')->user()->name }}
                            </a>
                            @role('admin')
                                <a href="{{ route('admin.users') }}" wire:navigate class="hover:text-zinc-900 data-current:text-zinc-900 data-current:font-medium">Users</a>
                            @endrole
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:text-zinc-900">Sign out</button>
                            </form>
                        @elseauth('db_session')
                            <a href="{{ route('explorer') }}" wire:navigate class="hover:text-zinc-900 data-current:text-zinc-900 data-current:font-medium">Explorer</a>
                            <span class="text-zinc-500 font-mono text-xs">
                                {{ auth('db_session')->user()->label() }}
                            </span>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:text-zinc-900">Disconnect</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" wire:navigate class="hover:text-zinc-900">Sign in</a>
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
                <footer class="border-t border-zinc-200 bg-white shrink-0">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-xs text-zinc-500">
                        {{ config('app.name') }} — self-hosted database studio.
                    </div>
                </footer>
            @endunless
        </div>

        @livewireScriptConfig
    </body>
</html>
