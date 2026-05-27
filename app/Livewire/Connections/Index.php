<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Application\Connections\ActiveConnectionResolver;
use App\Application\Connections\DeleteConnectionAction;
use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Index extends Component
{
    public ?int $activeId = null;

    public function mount(ActiveConnectionResolver $resolver): void
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

        session()->flash('connections_status', "Switched to “{$connection->name}”.");
    }

    public function deactivate(ActiveConnectionResolver $resolver): void
    {
        $resolver->clear();
        $this->activeId = null;
        $this->dispatch('connection-switched');
        session()->flash('connections_status', 'Connection cleared.');
    }

    public function delete(int $id, DeleteConnectionAction $action, ActiveConnectionResolver $resolver): void
    {
        $connection = DatabaseConnection::findOrFail($id);
        Gate::authorize('delete', $connection);

        if ($this->activeId === $connection->id) {
            $resolver->clear();
            $this->activeId = null;
        }

        $action->execute($connection);
        $this->dispatch('connection-switched');
        session()->flash('connections_status', 'Connection deleted.');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $own = DatabaseConnection::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_used_at')
            ->orderBy('name')
            ->get();

        $shared = collect();
        if ($user->hasRole('admin')) {
            $shared = DatabaseConnection::query()
                ->where('user_id', '!=', $user->id)
                ->with('user:id,name,email')
                ->orderBy('name')
                ->get();
        }

        return view('livewire.connections.index', [
            'own' => $own,
            'shared' => $shared,
        ]);
    }
}
