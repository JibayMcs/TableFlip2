<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DatabaseDriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 1: bind DatabaseDriverInterface implementations,
        // DatabaseDriverFactory and DatabaseConnectionManager here.
    }

    public function boot(): void
    {
        //
    }
}
