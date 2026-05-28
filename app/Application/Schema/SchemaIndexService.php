<?php

declare(strict_types=1);

namespace App\Application\Schema;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Persistent index of every database + its table/view names for a given
 * connection. Powers the sidebar's cross-database search without paying the
 * introspection cost on every render.
 *
 * Caching strategy :
 *   - Built once at connection activation (eager) or on first sidebar load.
 *   - Cached forever, keyed by the pool id of the connection.
 *   - A cheap "signature" query is run on each load (1 round-trip vs N for
 *     a full re-walk) and compared to the cached signature. If they differ
 *     (new DB added, table count changed) the index is rebuilt transparently.
 *   - Drivers that can't cheaply compute a signature return null and the
 *     cached index is served until the user triggers a manual refresh.
 */
class SchemaIndexService
{
    /**
     * Local memo inside a single request — avoids hitting the cache backend
     * multiple times when the sidebar reads the index and then the same
     * render passes it down to children.
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $requestMemo = [];

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly SchemaIntrospectionService $introspection,
    ) {}

    /**
     * Resolve the index for the given connection. Reads from cache,
     * compares signatures, rebuilds when stale.
     *
     * @return array<string, list<string>>  database name → list of table/view names
     */
    public function index(DatabaseDriverInterface $driver, string $poolId): array
    {
        if (isset($this->requestMemo[$poolId])) {
            return $this->requestMemo[$poolId];
        }

        $cachedIndex = $this->cache->get($this->indexKey($poolId));
        $cachedSig = $this->cache->get($this->sigKey($poolId));
        $currentSig = $this->safeSignature($driver);

        // Cache hit + signature unchanged (or driver can't compute one and
        // we already have an index) → serve the cache.
        if (is_array($cachedIndex) && $cachedSig !== null && $currentSig !== null && $cachedSig === $currentSig) {
            return $this->requestMemo[$poolId] = $cachedIndex;
        }
        if (is_array($cachedIndex) && $currentSig === null) {
            return $this->requestMemo[$poolId] = $cachedIndex;
        }

        // Cache miss or stale → rebuild.
        return $this->requestMemo[$poolId] = $this->rebuild($driver, $poolId, $currentSig);
    }

    /**
     * Force a full rebuild and replace the cached index. Used by the
     * "Reindex" button in the sidebar.
     *
     * @return array<string, list<string>>
     */
    public function refresh(DatabaseDriverInterface $driver, string $poolId): array
    {
        $sig = $this->safeSignature($driver);

        return $this->requestMemo[$poolId] = $this->rebuild($driver, $poolId, $sig);
    }

    /**
     * Drop the cache for a connection. Called when a connection is deleted
     * or when the user logs out.
     */
    public function invalidate(string $poolId): void
    {
        $this->cache->forget($this->indexKey($poolId));
        $this->cache->forget($this->sigKey($poolId));
        unset($this->requestMemo[$poolId]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function rebuild(DatabaseDriverInterface $driver, string $poolId, ?string $signature): array
    {
        $databases = $this->introspection->databases($driver);
        $index = [];

        foreach ($databases as $db) {
            $names = [];
            try {
                foreach ($this->introspection->tables($driver, $db) as $t) {
                    $names[] = $t->name;
                }
                foreach ($this->introspection->views($driver, $db) as $v) {
                    $names[] = $v->name;
                }
            } catch (Throwable) {
                // Skip DBs we can't introspect (perms, cross-DB FKs, etc.) —
                // they'll just not be searchable.
            }
            $index[$db] = $names;
        }

        $this->cache->forever($this->indexKey($poolId), $index);
        if ($signature !== null) {
            $this->cache->forever($this->sigKey($poolId), $signature);
        } else {
            $this->cache->forget($this->sigKey($poolId));
        }

        return $index;
    }

    private function safeSignature(DatabaseDriverInterface $driver): ?string
    {
        try {
            return $driver->schemaSignature();
        } catch (Throwable) {
            return null;
        }
    }

    private function indexKey(string $poolId): string
    {
        return "tableflip:schema_index:{$poolId}";
    }

    private function sigKey(string $poolId): string
    {
        return "tableflip:schema_sig:{$poolId}";
    }
}
