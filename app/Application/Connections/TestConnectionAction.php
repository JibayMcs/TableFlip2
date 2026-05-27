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
            return ['ok' => false, 'message' => $this->humanize($config, $e)];
        }

        try {
            // version() goes through the same connection() bootstrap as ping()
            // but lets ConnectionException propagate with the original PDO
            // message ("Connection refused", "FATAL: password authentication
            // failed for user X", "could not translate host name…", etc.).
            $version = $driver->version();

            return ['ok' => true, 'message' => 'Connection successful.', 'version' => $version];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->humanize($config, $e)];
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Map common PDO failure modes to actionable messages without losing the
     * raw driver error (kept as a hint for the curious).
     */
    private function humanize(ConnectionConfig $config, Throwable $e): string
    {
        $raw = $e instanceof ConnectionException ? $e->getMessage() : $e->getMessage();
        $target = $config->host !== '' ? "{$config->host}:".($config->port ?: '?') : '(no host)';

        $lower = strtolower($raw);

        return match (true) {
            // libpq translates "Connection refused" into the system locale —
            // accept the English form and the common French rendering, plus
            // the stable English suffix it always appends.
            str_contains($lower, 'connection refused')
            || str_contains($lower, 'connexion refusée')
            || str_contains($lower, 'is the server running on that host')
                => "Connection refused by {$target}. Check that the server is running and reachable, and that the port is correct. (raw: {$raw})",

            str_contains($lower, 'no route to host')
                => "No network route to {$target}. Check firewall / VPN / docker network. (raw: {$raw})",

            str_contains($lower, 'could not translate host name')
            || str_contains($lower, 'name or service not known')
            || str_contains($lower, 'getaddrinfo')
                => "Host {$config->host} could not be resolved. Check the hostname / DNS. (raw: {$raw})",

            str_contains($lower, 'timeout') || str_contains($lower, 'timed out')
                => "Network timeout while reaching {$target}. The server may be unreachable or blocked by a firewall. (raw: {$raw})",

            str_contains($lower, 'authentication failed') || str_contains($lower, 'access denied') || str_contains($lower, 'password authentication')
                => "Authentication failed for user '{$config->username}'. Check the username/password. (raw: {$raw})",

            str_contains($lower, 'database') && (str_contains($lower, 'does not exist') || str_contains($lower, 'unknown database'))
                => "Database '{$config->database}' does not exist on {$target}. (raw: {$raw})",

            str_contains($lower, 'sslmode') || str_contains($lower, 'ssl ') || str_contains($lower, 'tls')
                => "TLS/SSL negotiation failed. The server may require SSL (or reject it). Try toggling the SSL option. (raw: {$raw})",

            str_contains($lower, 'no pg_hba.conf entry')
                => "PostgreSQL refused the connection (no pg_hba.conf entry for this host/user). Check the server's pg_hba.conf. (raw: {$raw})",

            default => $raw,
        };
    }
}
