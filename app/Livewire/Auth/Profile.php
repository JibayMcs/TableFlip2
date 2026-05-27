<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $timezone = 'UTC';

    public string $locale = 'en';

    public string $theme = 'light';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->timezone = $user->timezone ?? 'UTC';
        $this->locale = $user->locale ?? 'en';
        $this->theme = $user->theme ?? 'light';
    }

    public function saveProfile(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'timezone' => ['required', 'string', 'max:64'],
            'locale' => ['required', 'string', 'max:10'],
            'theme' => ['required', 'in:light,dark'],
        ]);

        $user->update($data);
        session()->flash('profile_status', 'Profile updated.');
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password:web'],
            'newPassword' => ['required', 'min:8', 'confirmed:newPasswordConfirmation'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['password' => Hash::make($this->newPassword)]);

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        session()->flash('password_status', 'Password updated.');
    }

    public function render(): View
    {
        return view('livewire.auth.profile');
    }
}
