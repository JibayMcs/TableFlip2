<?php

declare(strict_types=1);

namespace App\Application\Connections;

use App\Domain\Auth\DirectDbUser;
use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Infrastructure\Database\DatabaseConnectionManager;
use Illuminate\Support\Facades\Auth;

/**
 * Unified access to "the connection currently in use", regardless of which
 * auth guard the request is authenticated against:
 *  - web guard (Breeze user): the saved DatabaseConnection picked via the
 *    ActiveConnectionResolver, if any.
 *  - db_session guard (direct-DB user): the connection embedded in the
 *    session payload.
 */
class CurrentConnection
{
    public function __construct(
        private readonly ActiveConnectionResolver $resolver,
        private readonly DatabaseConnectionManager $manager,
    ) {}

    public function driver(): ?DatabaseDriverInterface
    {
        if (Auth::guard('web')->check()) {
            return $this->resolver->driver();
        }

        if (Auth::guard('db_session')->check()) {
            /** @var DirectDbUser $user */
            $user = Auth::guard('db_session')->user();
            $id = 'direct_'.$user->id;
            if (! $this->manager->has($id)) {
                $this->manager->register($id, $user->config);
            }

            return $this->manager->get($id);
        }

        return null;
    }

    /**
     * The saved DatabaseConnection id for the active session, or null when the
     * user is connected directly (db_session guard). Used by the export
     * launcher because the queue worker can only replay saved connections.
     */
    public function connectionId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return $this->resolver->currentId();
        }

        return null;
    }

    public function label(): string
    {
        if (Auth::guard('web')->check()) {
            $connection = $this->resolver->current();

            return $connection?->name ?? 'No connection';
        }

        if (Auth::guard('db_session')->check()) {
            /** @var DirectDbUser $user */
            $user = Auth::guard('db_session')->user();

            return $user->label();
        }

        return 'No connection';
    }

    /**
     * The "preferred" database for the current connection, if any. This is
     * the database the user selected at connection time (saved or direct).
     * Empty when the user is connected server-only (PMA-style).
     */
    public function defaultDatabase(): ?string
    {
        if (Auth::guard('web')->check()) {
            $connection = $this->resolver->current();

            return $connection?->database ?: null;
        }

        if (Auth::guard('db_session')->check()) {
            /** @var DirectDbUser $user */
            $user = Auth::guard('db_session')->user();

            return $user->config->hasDatabase() ? $user->config->database : null;
        }

        return null;
    }
}
