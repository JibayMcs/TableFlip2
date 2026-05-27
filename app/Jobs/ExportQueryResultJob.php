<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Export\ExportAction;
use App\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportQueryResultJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;   // 10 minutes — generous for big SELECT exports
    public int $tries = 1;       // Don't retry on failure; the user will re-trigger if needed

    public function __construct(public readonly int $exportId) {}

    public function handle(ExportAction $action): void
    {
        $export = Export::find($this->exportId);
        if ($export === null) {
            return;
        }
        $action->run($export);
    }

    public function failed(\Throwable $e): void
    {
        Export::query()
            ->whereKey($this->exportId)
            ->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
    }
}
