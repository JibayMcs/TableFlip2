<?php

declare(strict_types=1);

namespace App\Application\Connections;

use App\Domain\Database\Exceptions\ConnectionException;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Infrastructure\Database\DatabaseDriverFactory;
use Throwable;

class TestConnectionAction
{
    public function __construct(private readonly DatabaseDriverFactory $factory) {}

    /**
     * Live ping using a freshly created driver. Always disconnects.
     *
     * @return array{ok: bool, message: string, version?: string}
     */
    public function execute(ConnectionConfig $config): array
    {
        try {
            $driver = $this->factory->create($config);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        try {
            if (! $driver->ping()) {
                return ['ok' => false, 'message' => 'The server did not respond to the ping.'];
            }

            return ['ok' => true, 'message' => 'Connection successful.', 'version' => $driver->version()];
        } catch (ConnectionException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            $driver->disconnect();
        }
    }
}
