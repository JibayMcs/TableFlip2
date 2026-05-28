<?php

declare(strict_types=1);

namespace App\Livewire\Exports;

use App\Models\Export;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function deleteExport(int $id): void
    {
        $export = Export::find($id);
        if ($export === null) {
            return;
        }
        if ($export->user_identifier !== (string) Auth::guard('db_session')->id()) {
            return;
        }

        if ($export->file_path !== null) {
            Storage::disk((string) config('tableflip.exports.disk', 'local'))
                ->delete($export->file_path);
        }

        $export->delete();
    }

    public function render(): View
    {
        $userId = (string) Auth::guard('db_session')->id();
        $ttl = (int) config('tableflip.exports.download_url_ttl_minutes', 30);

        $exports = Export::query()
            ->where('user_kind', 'direct_db')
            ->where('user_identifier', $userId)
            ->orderByDesc('created_at')
            ->paginate(25);

        $exports->each(function (Export $export) use ($ttl): void {
            $export->setAttribute(
                'download_url',
                $export->isCompleted()
                    ? URL::temporarySignedRoute('exports.download', now()->addMinutes($ttl), ['export' => $export->id])
                    : null,
            );
        });

        return view('livewire.exports.index', ['exports' => $exports]);
    }
}
