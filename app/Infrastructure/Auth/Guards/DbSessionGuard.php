<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Guards;

use App\Domain\Auth\DirectDbUser;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Infrastructure\Database\DatabaseConnectionManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Throwable;

class DbSessionGuard implements Guard
{
    use GuardHelpers;

    private const SESSION_KEY = 'tableflip.db_session';

    private bool $resolved = false;

    public function __construct(
        private readonly Session $session,
        private readonly DatabaseConnectionManager $connections,
    ) {}

    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $payload = $this->session->get(self::SESSION_KEY);

        if (! is_array($payload) || ! isset($payload['id'], $payload['config'])) {
            return $this->user = null;
        }

        try {
            $config = unserialize(decrypt($payload['config']), ['allowed_classes' => [ConnectionConfig::class]]);
        } catch (Throwable) {
            $this->logout();

            return $this->user = null;
        }

        if (! $config instanceof ConnectionConfig) {
            return $this->user = null;
        }

        $user = new DirectDbUser(id: (string) $payload['id'], config: $config);

        // Make sure the runtime pool has a driver instance for this session.
        $cid = $this->connectionId($user);
        if (! $this->connections->has($cid)) {
            $this->connections->register($cid, $config);
        }

        return $this->user = $user;
    }

    public function login(DirectDbUser $user): void
    {
        $this->session->put(self::SESSION_KEY, [
            'id' => $user->id,
            'config' => encrypt(serialize($user->config)),
        ]);
        $this->session->migrate(true);

        $this->connections->register($this->connectionId($user), $user->config);

        $this->user = $user;
        $this->resolved = true;
    }

    public function logout(): void
    {
        $user = $this->user();
        if ($user instanceof DirectDbUser) {
            $this->connections->close($this->connectionId($user));
        }

        $this->session->forget(self::SESSION_KEY);
        $this->user = null;
        $this->resolved = true;
    }

    /**
     * @param  array<int|string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function connectionIdFor(DirectDbUser $user): string
    {
        return $this->connectionId($user);
    }

    private function connectionId(DirectDbUser $user): string
    {
        return 'direct_'.$user->id;
    }
}
