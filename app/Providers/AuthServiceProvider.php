<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Auth\AllowedConnectionPolicy;
use App\Infrastructure\Auth\Guards\DbSessionGuard;
use App\Infrastructure\Database\DatabaseConnectionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AllowedConnectionPolicy::class, fn () => AllowedConnectionPolicy::fromConfig());
    }

    public function boot(): void
    {
        Auth::extend('db_session', fn ($app) => new DbSessionGuard(
            session: $app['session.store'],
            connections: $app->make(DatabaseConnectionManager::class),
        ));
    }
}
