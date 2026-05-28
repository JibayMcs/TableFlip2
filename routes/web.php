<?php

use App\Livewire\Admin\QueryHistory\Index as AdminQueryHistoryIndex;
use App\Livewire\Admin\TableOperations\Index as AdminTableOperationsIndex;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Profile;
use App\Livewire\Connections\Form as ConnectionForm;
use App\Livewire\Connections\Index as ConnectionsIndex;
use App\Livewire\Docs\Index as DocsIndex;
use App\Livewire\Explorer\Index as ExplorerIndex;
use App\Livewire\Exports\DatabaseExport;
use App\Livewire\Exports\Index as ExportsIndex;
use App\Livewire\Sql\Editor as SqlEditor;
use App\Livewire\Visualizer\Index as VisualizerIndex;
use App\Models\Export;
use Illuminate\Support\Facades\Storage;
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
    Route::get('/explorer', ExplorerIndex::class)->name('explorer');
    Route::get('/sql', SqlEditor::class)->name('sql');
    Route::get('/visualizer', VisualizerIndex::class)->name('visualizer');

    // Markdown docs. `slug` is everything after /docs/, including
    // slashes — page hierarchies like `connections/mysql` resolve to
    // `docs/user/<locale>/connections/mysql.md`.
    Route::get('/docs', DocsIndex::class)->name('docs');
    Route::get('/docs/{slug}', DocsIndex::class)
        ->where('slug', '[a-z0-9\-/]+')
        ->name('docs.show');
});

Route::middleware('auth:web')->group(function () {
    Route::get('/profile', Profile::class)->name('profile');

    Route::prefix('connections')->name('connections.')->group(function () {
        Route::get('/', ConnectionsIndex::class)->name('index');
        Route::get('/new', ConnectionForm::class)->name('create');
        Route::get('/{connection}/edit', ConnectionForm::class)->name('edit');
    });

    Route::get('/exports', ExportsIndex::class)->name('exports.index');
    Route::get('/exports/database', DatabaseExport::class)->name('exports.database');
    Route::get('/exports/{export}/download', function (Export $export, \Illuminate\Http\Request $request) {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($export->user_identifier === (string) Auth::id(), 404);
        abort_unless($export->isCompleted() && $export->file_path !== null, 404);

        $disk = Storage::disk((string) config('tableflip.exports.disk', 'local'));
        abort_unless($disk->exists($export->file_path), 404);

        return $disk->download($export->file_path, $export->file_name);
    })->name('exports.download');
});

Route::middleware(['auth:web', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', AdminUsersIndex::class)->name('users');
    Route::get('/audit', AdminTableOperationsIndex::class)->name('audit');
    Route::get('/history', AdminQueryHistoryIndex::class)->name('history');
});
