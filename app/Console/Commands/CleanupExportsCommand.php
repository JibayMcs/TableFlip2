<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('tableflip:cleanup-exports {--dry-run : Report what would be deleted without touching anything}')]
#[Description('Delete expired exports (rows + files on the exports disk).')]
class CleanupExportsCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk((string) config('tableflip.exports.disk', 'local'));

        $expired = Export::query()
            ->where('expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired exports.');

            return self::SUCCESS;
        }

        $bytes = 0;
        foreach ($expired as $export) {
            $bytes += (int) ($export->byte_size ?? 0);

            $this->line(sprintf(
                '%s #%d  %s  (%s)',
                $dryRun ? '[dry-run] would delete' : 'deleting',
                $export->id,
                $export->file_name ?? '(no file)',
                $export->expires_at?->diffForHumans() ?? 'unknown expiry',
            ));

            if ($dryRun) {
                continue;
            }

            if ($export->file_path !== null && $disk->exists($export->file_path)) {
                $disk->delete($export->file_path);
            }
            $export->delete();
        }

        $this->info(sprintf(
            '%s %d export(s) (~%.1f KB).',
            $dryRun ? 'Would remove' : 'Removed',
            $expired->count(),
            $bytes / 1024,
        ));

        return self::SUCCESS;
    }
}
