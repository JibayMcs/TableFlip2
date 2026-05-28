@foreach ($nodes as $node)
    @if ($node['slug'] !== '')
        <a href="{{ route('docs.show', $node['slug']) }}" wire:navigate
            class="block px-2 py-1 rounded-md {{ $node['slug'] === $current ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/60' }}">
            {{ $node['title'] }}
        </a>
    @else
        <div class="px-2 py-1 text-xs uppercase text-zinc-400 dark:text-zinc-500 mt-3">{{ $node['title'] }}</div>
    @endif

    @if (! empty($node['children']))
        <div class="ml-3 border-l border-zinc-200 dark:border-zinc-800 pl-2 mt-1 mb-2 space-y-1">
            @include('livewire.docs.partials.tree', ['nodes' => $node['children'], 'current' => $current])
        </div>
    @endif
@endforeach
