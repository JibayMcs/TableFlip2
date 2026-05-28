<x-layouts.app :title="config('app.name')">
    <div class="space-y-6">
        @auth('db_session')
            @php($u = auth('db_session')->user())
            <h1 class="text-2xl font-semibold tracking-tight">Connected.</h1>
            <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                Active connection: <code class="font-mono text-sm">{{ $u->label() }}</code>
            </p>
            <a href="{{ route('explorer') }}" wire:navigate
                class="inline-block rounded-md bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-800">
                Open Explorer
            </a>
        @endauth
    </div>
</x-layouts.app>
