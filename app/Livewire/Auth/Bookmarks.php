<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Bookmarks side panel — rendered on the login page next to the form.
 *
 * The component is intentionally state-less : everything happens in
 * localStorage through the `x-bookmarks` Alpine directive. We expose this
 * as a Livewire component for a single reason : it gives Alpine a render
 * boundary that the parent Login component cannot morph through.
 * Without it, every `wire:model.live` round-trip on the form would erase
 * the bookmarks DOM and trip the Alpine reactive state.
 */
class Bookmarks extends Component
{
    public function render(): View
    {
        return view('livewire.auth.bookmarks');
    }
}
