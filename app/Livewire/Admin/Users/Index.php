<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreate = false;

    #[Validate('required|string|max:255')]
    public string $newName = '';

    #[Validate('required|email')]
    public string $newEmail = '';

    public string $newRole = 'user';

    public string $newPassword = '';

    public function openCreate(): void
    {
        $this->reset('newName', 'newEmail', 'newPassword');
        $this->newRole = 'user';
        $this->showCreate = true;
    }

    public function createUser(): void
    {
        $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newEmail' => ['required', 'email', Rule::unique('users', 'email')],
            'newRole' => ['required', 'in:admin,user'],
        ]);

        $generated = $this->newPassword !== '' ? $this->newPassword : Str::random(16);
        $user = User::create([
            'name' => $this->newName,
            'email' => $this->newEmail,
            'password' => Hash::make($generated),
        ]);
        $user->assignRole($this->newRole);

        session()->flash('admin_user_status', $this->newPassword !== ''
            ? "User created."
            : "User created. Temporary password: {$generated}");

        $this->showCreate = false;
        $this->reset('newName', 'newEmail', 'newPassword');
    }

    public function toggleActive(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function resetPassword(int $userId): void
    {
        $user = User::findOrFail($userId);
        $new = Str::random(16);
        $user->update(['password' => Hash::make($new)]);
        session()->flash('admin_user_status', "Password reset for {$user->email}. New temporary password: {$new}");
    }

    public function render(): View
    {
        $users = User::query()
            // Eager-load roles to avoid an N+1 in the table render
            // ($u->roles is read inside the loop on every row).
            ->with('roles:id,name')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('id')
            ->paginate(25);

        return view('livewire.admin.users.index', ['users' => $users]);
    }
}
