<div class="flex gap-8">
    {{-- Sidebar : navigation arbre généré depuis docs/user/<locale>/ --}}
    <aside class="w-64 flex-shrink-0 border-r border-zinc-200 dark:border-zinc-800 pr-6">
        <h2 class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400 mb-3">{{ __('docs.sidebar_title') }}</h2>
        <nav class="text-sm space-y-1">
            @include('livewire.docs.partials.tree', ['nodes' => $tree, 'current' => $currentSlug])
        </nav>
    </aside>

    {{-- Main content --}}
    <main class="flex-1 min-w-0">
        @if ($doc === null)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                {{ __('docs.not_found') }}
            </div>
        @else
            <article class="tf-docs-prose max-w-3xl">
                {!! $doc->html !!}
            </article>
        @endif
    </main>

    {{-- ToC right rail --}}
    @if ($doc !== null && trim($doc->toc) !== '')
        <aside class="w-56 flex-shrink-0 hidden xl:block">
            <div class="sticky top-6">
                <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400 mb-3">{{ __('docs.toc') }}</div>
                <div class="tf-docs-toc text-sm">{!! $doc->toc !!}</div>
            </div>
        </aside>
    @endif
</div>
