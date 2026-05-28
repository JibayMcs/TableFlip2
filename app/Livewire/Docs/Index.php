<?php

declare(strict_types=1);

namespace App\Livewire\Docs;

use App\Application\Docs\DocsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Index extends Component
{
    public ?string $slug = null;

    public function mount(?string $slug = null): void
    {
        $this->slug = $slug;
    }

    public function render(DocsService $docs): View
    {
        $locale = app()->getLocale();
        $available = $docs->locales();

        // Fall back to the first available locale that has docs if the user's
        // locale doesn't have its own folder (avoid an empty page when only
        // EN docs ship but the user runs FR).
        if (! in_array($locale, $available, true) && $available !== []) {
            $locale = in_array('en', $available, true) ? 'en' : $available[0];
        }

        $slug = $this->slug ?: $docs->defaultSlug($locale);
        $tree = $docs->tree($locale);
        $doc = $docs->render($slug, $locale);

        return view('livewire.docs.index', [
            'tree' => $tree,
            'doc' => $doc,
            'currentSlug' => $slug,
            'locale' => $locale,
        ]);
    }
}
