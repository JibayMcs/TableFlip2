<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Database\Contracts\DatabaseDriverInterface;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use RuntimeException;

class DatabaseConnectionManager
{
    /** @var array<string, DatabaseDriverInterface> */
    private array $pool = [];

    public function __construct(private readonly DatabaseDriverFactory $factory) {}

    public function register(string $id, ConnectionConfig $config): DatabaseDriverInterface
    {
        if (isset($this->pool[$id])) {
            $this->pool[$id]->disconnect();
        }

        return $this->pool[$id] = $this->factory->create($config);
    }

    public function get(string $id): DatabaseDriverInterface
    {
        return $this->pool[$id]
            ?? throw new RuntimeException("No database connection registered with id [{$id}].");
    }

    public function has(string $id): bool
    {
        return isset($this->pool[$id]);
    }

    public function close(string $id): void
    {
        if (! isset($this->pool[$id])) {
            return;
        }

        $this->pool[$id]->disconnect();
        unset($this->pool[$id]);
    }

    public function closeAll(): void
    {
        foreach ($this->pool as $id => $driver) {
            $driver->disconnect();
            unset($this->pool[$id]);
        }
    }

    /**
     * @return array<string, DatabaseDriverInterface>
     */
    public function all(): array
    {
        return $this->pool;
    }
}
