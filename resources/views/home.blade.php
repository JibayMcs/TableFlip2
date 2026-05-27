<x-layouts.app :title="config('app.name')">
    <div class="space-y-6">
        @auth('web')
            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome back, {{ auth('web')->user()->name }}.
            </h1>
            <p class="text-zinc-600 max-w-2xl">
                Your saved database connections, the explorer, and the SQL editor are coming in the next phases.
            </p>
        @elseauth('db_session')
            @php($u = auth('db_session')->user())
            <h1 class="text-2xl font-semibold tracking-tight">
                Connected directly.
            </h1>
            <p class="text-zinc-600">
                Active connection: <code class="font-mono text-sm">{{ $u->label() }}</code>
            </p>
        @endauth
    </div>
</x-layouts.app>
