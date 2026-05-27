@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="h-full bg-zinc-50 text-zinc-900 antialiased">
        <div class="min-h-full flex flex-col">
            <header class="bg-white border-b border-zinc-200">
                <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
                    <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-zinc-900">
                        <h1 class="table-flip-logo"></h1>
                    </a>

                    <div class="flex items-center gap-4 text-sm text-zinc-600">
                        @auth('web')
                            <livewire:navbar.connection-switcher />

                            <a href="{{ route('profile') }}" class="hover:text-zinc-900">
                                {{ auth('web')->user()->name }}
                            </a>
                            @role('admin')
                                <a href="{{ route('admin.users') }}" class="hover:text-zinc-900">Users</a>
                            @endrole
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:text-zinc-900">Sign out</button>
                            </form>
                        @elseauth('db_session')
                            <span class="text-zinc-500 font-mono text-xs">
                                {{ auth('db_session')->user()->label() }}
                            </span>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:text-zinc-900">Disconnect</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="hover:text-zinc-900">Sign in</a>
                        @endauth
                    </div>
                </nav>
            </header>

            <main class="flex-1">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-zinc-200 bg-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-xs text-zinc-500">
                    {{ config('app.name') }} — self-hosted database studio.
                </div>
            </footer>
        </div>

        @livewireScripts
    </body>
</html>
