<x-layouts.app :title="config('app.name')">
    <div class="space-y-6">
        @auth('web')
            @php
                $resolver = app(\App\Application\Connections\ActiveConnectionResolver::class);
                $active = $resolver->current();
            @endphp

            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome back, {{ auth('web')->user()->name }}.
            </h1>

            @if ($active)
                <div class="rounded-md border border-zinc-200 bg-white p-4 flex items-center gap-3">
                    <span class="inline-block size-3 rounded-full" style="background-color: {{ $active->color }}"></span>
                    <div class="flex-1">
                        <div class="font-medium">{{ $active->name }}</div>
                        <div class="text-xs text-zinc-500 font-mono">
                            {{ $active->driver === 'sqlite'
                                ? $active->database
                                : ($active->username . '@' . $active->host . ($active->port ? ':' . $active->port : '') . ($active->database ? '/' . $active->database : '')) }}
                        </div>
                    </div>
                    <span class="text-xs text-emerald-700 inline-flex items-center gap-1">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span> Active
                    </span>
                </div>
            @else
                <p class="text-zinc-600 max-w-2xl">
                    No active connection.
                    <a href="{{ route('connections.index') }}" class="text-zinc-900 underline">Pick one</a>
                    or
                    <a href="{{ route('connections.create') }}" class="text-zinc-900 underline">create a new one</a>
                    to get started.
                </p>
            @endif
        @elseauth('db_session')
            @php($u = auth('db_session')->user())
            <h1 class="text-2xl font-semibold tracking-tight">Connected directly.</h1>
            <p class="text-zinc-600">
                Active connection: <code class="font-mono text-sm">{{ $u->label() }}</code>
            </p>
        @endauth
    </div>
</x-layouts.app>
