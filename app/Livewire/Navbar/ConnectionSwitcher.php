<?php

declare(strict_types=1);

namespace App\Livewire\Navbar;

use App\Application\Connections\ActiveConnectionResolver;
use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class ConnectionSwitcher extends Component
{
    public ?int $activeId = null;

    public function mount(ActiveConnectionResolver $resolver): void
    {
        $this->activeId = $resolver->currentId();
    }

    #[On('connection-switched')]
    public function syncActive(ActiveConnectionResolver $resolver): void
    {
        $this->activeId = $resolver->currentId();
    }

    public function activate(int $id, ActiveConnectionResolver $resolver): void
    {
        $connection = DatabaseConnection::findOrFail($id);
        Gate::authorize('activate', $connection);

        $resolver->switchTo($connection);
        $this->activeId = $connection->id;
        $this->dispatch('connection-switched');
    }

    public function deactivate(ActiveConnectionResolver $resolver): void
    {
        $resolver->clear();
        $this->activeId = null;
        $this->dispatch('connection-switched');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $connections = $user->databaseConnections()->orderBy('name')->get();
        $active = $connections->firstWhere('id', $this->activeId);

        return view('livewire.navbar.connection-switcher', compact('connections', 'active'));
    }
}
