<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\DirectDbUser;
use App\Domain\Database\Exceptions\ConnectionException;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Infrastructure\Database\DatabaseDriverFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

class DirectDbAuthenticator
{
    public function __construct(
        private readonly DatabaseDriverFactory $factory,
        private readonly AllowedConnectionPolicy $policy,
    ) {}

    /**
     * @throws AuthenticationException when the credentials cannot reach the
     *         requested database OR the requested target is out of the allowed
     *         scope defined by the deployment.
     */
    public function authenticate(DirectDbCredentials $creds): DirectDbUser
    {
        if (! $this->policy->isAllowed($creds->host, $creds->driver, $creds->database)) {
            throw new AuthenticationException(
                'This database target is not allowed by the current deployment.',
            );
        }

        $config = new ConnectionConfig(
            driver: $creds->driver,
            database: $creds->database,
            host: $creds->host,
            port: $creds->port,
            username: $creds->username,
            password: $creds->password,
        );

        $driver = $this->factory->create($config);

        try {
            if (! $driver->ping()) {
                throw new AuthenticationException('Unable to reach the database with these credentials.');
            }
        } catch (ConnectionException $e) {
            throw new AuthenticationException($e->getMessage(), 0, $e);
        } finally {
            $driver->disconnect();
        }

        return new DirectDbUser(id: (string) Str::uuid(), config: $config);
    }
}
