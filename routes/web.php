<?php

use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->name('login');

Route::post('/logout', function (Request $request) {
    foreach (['web', 'db_session'] as $guard) {
        if (Auth::guard($guard)->check()) {
            Auth::guard($guard)->logout();
        }
    }
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware('auth.tableflip')->group(function () {
    Route::view('/', 'home')->name('home');
});

Route::middleware('auth:web')->group(function () {
    Route::get('/profile', Profile::class)->name('profile');
});

Route::middleware(['auth:web', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', AdminUsersIndex::class)->name('users');
});
