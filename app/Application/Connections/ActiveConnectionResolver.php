<?php

declare(strict_types=1);

namespace App\Application\Connections;

use App\Application\Schema\SchemaIndexService;
use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Infrastructure\Database\DatabaseConnectionManager;
use App\Models\DatabaseConnection;
use Illuminate\Contracts\Session\Session;
use Throwable;

class ActiveConnectionResolver
{
    private const SESSION_KEY = 'tableflip.active_connection_id';

    public function __construct(
        private readonly Session $session,
        private readonly DatabaseConnectionManager $manager,
        private readonly SchemaIndexService $schemaIndex,
    ) {}

    public function currentId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    public function current(): ?DatabaseConnection
    {
        $id = $this->currentId();

        return $id === null ? null : DatabaseConnection::find($id);
    }

    public function switchTo(DatabaseConnection $connection): void
    {
        // Drop any cached pool entry under the previous active id so the
        // next request rebuilds it from the source of truth.
        if ($previous = $this->current()) {
            $this->manager->close($previous->poolId());
        }

        $this->session->put(self::SESSION_KEY, $connection->id);
        $connection->forceFill(['last_used_at' => now()])->save();

        // Prime the sidebar search index eagerly so the first interaction
        // with the search field is instant. Best-effort — a failure here
        // shouldn't block the connection switch.
        try {
            $driver = $this->driver();
            if ($driver !== null) {
                $this->schemaIndex->refresh($driver, $connection->poolId());
            }
        } catch (Throwable) {
            // ignored — index will be lazy-built on first sidebar render.
        }
    }

    public function clear(): void
    {
        if ($current = $this->current()) {
            $this->manager->close($current->poolId());
        }
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Hydrate the runtime pool for the current saved connection and return
     * its driver instance. Returns null if no connection is selected.
     */
    public function driver(): ?DatabaseDriverInterface
    {
        $connection = $this->current();
        if ($connection === null) {
            return null;
        }

        $id = $connection->poolId();
        if (! $this->manager->has($id)) {
            $this->manager->register($id, $connection->toConnectionConfig());
        }

        return $this->manager->get($id);
    }
}
