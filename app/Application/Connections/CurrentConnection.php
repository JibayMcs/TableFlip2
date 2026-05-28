<?php

declare(strict_types=1);

namespace App\Application\Connections;

use App\Domain\Auth\DirectDbUser;
use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Infrastructure\Database\DatabaseConnectionManager;
use Illuminate\Support\Facades\Auth;

/**
 * Wraps the connection bound to the current direct-DB session.
 * Resolves the active driver from the credentials stored in the
 * `db_session` guard.
 */
class CurrentConnection
{
    public function __construct(
        private readonly DatabaseConnectionManager $manager,
    ) {}

    public function driver(): ?DatabaseDriverInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return null;
        }

        $id = 'direct_'.$user->id;
        if (! $this->manager->has($id)) {
            $this->manager->register($id, $user->config);
        }

        return $this->manager->get($id);
    }

    public function label(): string
    {
        return $this->currentUser()?->label() ?? 'No connection';
    }

    /**
     * Pool identifier for the active session. Stable for the lifetime
     * of the session — used as a cache key by the schema index.
     */
    public function poolId(): ?string
    {
        $user = $this->currentUser();

        return $user === null ? null : 'direct_'.$user->id;
    }

    /**
     * The database the user pointed at when logging in. Empty when the
     * session is server-only (PMA-style).
     */
    public function defaultDatabase(): ?string
    {
        $user = $this->currentUser();
        if ($user === null) {
            return null;
        }

        return $user->config->hasDatabase() ? $user->config->database : null;
    }

    private function currentUser(): ?DirectDbUser
    {
        if (! Auth::guard('db_session')->check()) {
            return null;
        }

        /** @var DirectDbUser $user */
        $user = Auth::guard('db_session')->user();

        return $user;
    }
}
