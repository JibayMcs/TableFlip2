<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

final readonly class ConnectionConfig
{
    /**
     * Driver-specific admin/default databases. We need a non-empty database
     * to feed Laravel's connectors when the user logged in "server-only"
     * (no database selected). Listing databases still works fine from these.
     */
    private const ADMIN_DATABASE = [
        'mysql' => 'information_schema',
        'mariadb' => 'information_schema',
        'pgsql' => 'postgres',
        'sqlsrv' => 'master',
    ];

    /**
     * @param  array<string, mixed>  $options  driver-specific PDO options
     * @param  array<string, mixed>  $sslOptions
     */
    public function __construct(
        public string $driver,
        public string $database,
        public string $host = '',
        public int $port = 0,
        public string $username = '',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = [],
        public bool $ssl = false,
        public array $sslOptions = [],
    ) {}

    public function hasDatabase(): bool
    {
        return $this->database !== '';
    }

    /**
     * Convert this value object to the array shape expected by Laravel's
     * `config('database.connections.<name>')` slot.
     *
     * @return array<string, mixed>
     */
    public function toLaravelConfig(): array
    {
        $database = $this->resolveDatabase();

        return match ($this->driver) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'pgsql' => array_filter([
                'driver' => 'pgsql',
                'host' => $this->host,
                'port' => $this->port ?: 5432,
                'database' => $database,
                'username' => $this->username,
                'password' => $this->password,
                // Postgres only knows 'utf8' (and a few exotic encodings). The
                // mysql-style 'utf8mb4' default would crash libpq with
                // "invalid value for parameter \"client_encoding\"".
                'charset' => $this->resolveCharset('utf8'),
                'prefix' => '',
                'schema' => $this->options['schema'] ?? 'public',
                'sslmode' => $this->ssl ? ($this->sslOptions['mode'] ?? 'require') : 'prefer',
            ], static fn ($v) => $v !== null),
            'sqlsrv' => array_filter([
                'driver' => 'sqlsrv',
                'host' => $this->host,
                'port' => $this->port ?: 1433,
                'database' => $database,
                'username' => $this->username,
                'password' => $this->password,
                'charset' => $this->resolveCharset('utf8'),
                'prefix' => '',
                'encrypt' => $this->ssl ? 'yes' : ($this->options['encrypt'] ?? 'no'),
                'trust_server_certificate' => $this->options['trust_server_certificate'] ?? false,
            ], static fn ($v) => $v !== null),
            default => [
                // mysql + mariadb
                'driver' => $this->driver,
                'host' => $this->host,
                'port' => $this->port ?: 3306,
                'database' => $database,
                'username' => $this->username,
                'password' => $this->password,
                'charset' => $this->charset,
                'collation' => $this->options['collation'] ?? 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'options' => $this->ssl ? $this->sslOptions : [],
            ],
        };
    }

    public function isAzureSqlServer(): bool
    {
        return $this->driver === 'sqlsrv'
            && str_contains(strtolower($this->host), '.database.windows.net');
    }

    /**
     * Resolve which database to actually connect to. Falls back to the
     * driver's admin/default database when the user did not pick one — this
     * keeps the PDO connection valid while still letting `listDatabases()`
     * enumerate everything the user can see.
     */
    /**
     * Use the user-provided charset when they overrode the default, otherwise
     * fall back to a driver-appropriate one. Keeps utf8mb4 for MySQL/MariaDB
     * (where it's the sane default) while stopping the same value from being
     * pushed into engines that don't accept it.
     */
    private function resolveCharset(string $driverDefault): string
    {
        return $this->charset === 'utf8mb4' ? $driverDefault : $this->charset;
    }

    private function resolveDatabase(): string
    {
        if ($this->database !== '') {
            return $this->database;
        }

        return self::ADMIN_DATABASE[$this->driver] ?? '';
    }
}
