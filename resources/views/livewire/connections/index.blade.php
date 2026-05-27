<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Connections</h1>
        <a href="{{ route('connections.create') }}"
            class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
            New connection
        </a>
    </div>

    @if (session('connections_status'))
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            {{ session('connections_status') }}
        </div>
    @endif

    @if ($own->isEmpty())
        <div class="rounded-md border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500">
            You don't have any saved connection yet.
            <a href="{{ route('connections.create') }}" class="text-zinc-900 underline">Create your first one</a>.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($own as $c)
                <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden flex flex-col">
                    <div class="h-1.5" style="background-color: {{ $c->color }}"></div>
                    <div class="p-4 flex-1 flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-semibold text-zinc-900 truncate">{{ $c->name }}</div>
                                <div class="text-xs text-zinc-500 font-mono truncate">
                                    {{ $c->driver === 'sqlite' ? $c->database : ($c->username . '@' . $c->host . ($c->port ? ':' . $c->port : '') . ($c->database ? '/' . $c->database : '')) }}
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-mono rounded bg-zinc-100 px-1.5 py-0.5 text-zinc-700">{{ $c->driver }}</span>
                        </div>

                        <div class="text-xs text-zinc-500">
                            @if ($c->last_used_at)
                                Last used {{ $c->last_used_at->diffForHumans() }}
                            @else
                                Never used yet
                            @endif
                            @if ($c->ssl) · SSL @endif
                        </div>

                        <div class="mt-auto flex items-center gap-2 pt-2 border-t border-zinc-100">
                            @if ($activeId === $c->id)
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                                <button wire:click="deactivate"
                                    class="text-xs text-zinc-500 hover:text-zinc-900">Disconnect</button>
                            @else
                                <button wire:click="activate({{ $c->id }})"
                                    class="text-xs font-medium text-zinc-900 hover:underline">Use this</button>
                            @endif
                            <span class="ml-auto"></span>
                            <a href="{{ route('connections.edit', $c) }}" class="text-xs text-zinc-500 hover:text-zinc-900">Edit</a>
                            <button wire:click="delete({{ $c->id }})"
                                wire:confirm="Delete connection “{{ $c->name }}”?"
                                class="text-xs text-rose-600 hover:text-rose-700">Delete</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($shared->isNotEmpty())
        <div class="space-y-3 pt-4">
            <h2 class="text-sm font-semibold text-zinc-600 uppercase tracking-wide">Other users' connections <span class="font-normal normal-case text-zinc-400">(read-only)</span></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($shared as $c)
                    <div class="bg-zinc-50 border border-zinc-200 rounded-lg overflow-hidden">
                        <div class="h-1.5" style="background-color: {{ $c->color }}"></div>
                        <div class="p-4 space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-medium text-zinc-700 truncate">{{ $c->name }}</div>
                                <span class="text-xs font-mono rounded bg-white px-1.5 py-0.5 text-zinc-600 border border-zinc-200">{{ $c->driver }}</span>
                            </div>
                            <div class="text-xs text-zinc-500 truncate">owned by {{ $c->user->email }}</div>
                            <div class="text-xs text-zinc-500 font-mono truncate">
                                {{ $c->driver === 'sqlite' ? $c->database : ($c->username . '@' . $c->host . ($c->port ? ':' . $c->port : '') . ($c->database ? '/' . $c->database : '')) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
