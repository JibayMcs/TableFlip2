<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Database\DatabaseConnectionManager;
use App\Infrastructure\Database\DatabaseDriverFactory;
use Illuminate\Support\ServiceProvider;

class DatabaseDriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseDriverFactory::class);

        $this->app->singleton(DatabaseConnectionManager::class, fn ($app) => new DatabaseConnectionManager(
            $app->make(DatabaseDriverFactory::class),
        ));
    }

    public function boot(): void
    {
        //
    }
}
