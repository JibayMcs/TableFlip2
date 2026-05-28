<?php

use App\Livewire\Admin\QueryHistory\Index as AdminQueryHistoryIndex;
use App\Livewire\Admin\TableOperations\Index as AdminTableOperationsIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Docs\Index as DocsIndex;
use App\Livewire\Explorer\Index as ExplorerIndex;
use App\Livewire\Exports\DatabaseExport;
use App\Livewire\Exports\Index as ExportsIndex;
use App\Livewire\Sql\Editor as SqlEditor;
use App\Livewire\Visualizer\Index as VisualizerIndex;
use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/login', Login::class)->name('login');

Route::post('/logout', function (Request $request) {
    if (Auth::guard('db_session')->check()) {
        Auth::guard('db_session')->logout();
    }
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // The Switch-connection flow forwards a bookmark id : the login page
    // will pre-fill from it once the user unlocks the encrypted store.
    $bookmark = (string) $request->input('bookmark', '');
    $target = route('login');
    if ($bookmark !== '' && preg_match('/^[a-f0-9-]+$/i', $bookmark) === 1) {
        $target .= '?bookmark='.urlencode($bookmark);
    }

    return redirect($target);
})->name('logout');

Route::middleware('auth:db_session')->group(function () {
    Route::view('/', 'home')->name('home');
    Route::get('/explorer', ExplorerIndex::class)->name('explorer');
    Route::get('/sql', SqlEditor::class)->name('sql');
    Route::get('/visualizer', VisualizerIndex::class)->name('visualizer');

    // Markdown docs. `slug` is everything after /docs/, including
    // slashes — page hierarchies like `connections/mysql` resolve to
    // `documentation/<locale>/connections/mysql.md`.
    Route::get('/docs', DocsIndex::class)->name('docs');
    Route::get('/docs/{slug}', DocsIndex::class)
        ->where('slug', '[a-z0-9\-/]+')
        ->name('docs.show');

    // Exports — the async pipeline still ships here in CP1 ; CP5 will
    // collapse it to synchronous streaming downloads.
    Route::get('/exports', ExportsIndex::class)->name('exports.index');
    Route::get('/exports/database', DatabaseExport::class)->name('exports.database');
    Route::get('/exports/{export}/download', function (Export $export, Request $request) {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($export->user_identifier === (string) Auth::id(), 404);
        abort_unless($export->isCompleted() && $export->file_path !== null, 404);

        $disk = Storage::disk((string) config('tableflip.exports.disk', 'local'));
        abort_unless($disk->exists($export->file_path), 404);

        return $disk->download($export->file_path, $export->file_name);
    })->name('exports.download');

    // Admin browsers — every authenticated user can see them in CP1 ;
    // CP2 introduces server-side privilege detection to scope them.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit', AdminTableOperationsIndex::class)->name('audit');
        Route::get('/history', AdminQueryHistoryIndex::class)->name('history');
    });
});
