<div>
    @if ($connections->isEmpty())
        <a href="{{ route('connections.index') }}" wire:navigate class="hover:text-zinc-900 dark:text-zinc-100">Connections</a>
    @else
        <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
            <button type="button" @click="open = !open"
                class="flex items-center gap-2 rounded-md border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-sm hover:border-zinc-300 dark:border-zinc-700">
                @if ($active)
                    <span class="size-2 rounded-full" style="background-color: {{ $active->color }}"></span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $active->name }}</span>
                    <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $active->driver }}</span>
                @else
                    <span class="size-2 rounded-full bg-zinc-300"></span>
                    <span class="text-zinc-500 dark:text-zinc-400">No active connection</span>
                @endif
                <svg class="size-3 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition.opacity
                class="absolute right-0 mt-1 w-72 rounded-md border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-lg z-50">
                <div class="max-h-80 overflow-y-auto py-1">
                    @foreach ($connections as $c)
                        <button type="button"
                            wire:click="activate({{ $c->id }})"
                            wire:loading.attr="disabled"
                            @click="open = false"
                            class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:bg-zinc-950 {{ $c->id === $activeId ? 'bg-zinc-50 dark:bg-zinc-950' : '' }}">
                            <span class="size-2 rounded-full" style="background-color: {{ $c->color }}"></span>
                            <span class="flex-1 min-w-0 truncate">{{ $c->name }}</span>
                            <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $c->driver }}</span>
                            @if ($c->id === $activeId)
                                <span class="text-xs text-emerald-600">✓</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <div class="border-t border-zinc-100 dark:border-zinc-800 py-1">
                    @if ($active)
                        <button type="button" wire:click="deactivate" @click="open = false"
                            class="w-full text-left px-3 py-2 text-xs text-zinc-500 dark:text-zinc-400 hover:bg-zinc-50 dark:bg-zinc-950">
                            Disconnect current
                        </button>
                    @endif
                    <a href="{{ route('connections.index') }}" wire:navigate
                        class="block px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:bg-zinc-950">
                        Manage connections →
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
