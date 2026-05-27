<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reap expired export files once a day. Retention is configured in
// config/tableflip.php via TABLEFLIP_EXPORTS_RETENTION_DAYS.
Schedule::command('tableflip:cleanup-exports')
    ->dailyAt('03:15')
    ->withoutOverlapping();
