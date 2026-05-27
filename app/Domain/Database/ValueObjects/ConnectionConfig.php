<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

final readonly class ConnectionConfig
{
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

    /**
     * Convert this value object to the array shape expected by Laravel's
     * `config('database.connections.<name>')` slot.
     *
     * @return array<string, mixed>
     */
    public function toLaravelConfig(): array
    {
        return match ($this->driver) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $this->database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'pgsql' => array_filter([
                'driver' => 'pgsql',
                'host' => $this->host,
                'port' => $this->port ?: 5432,
                'database' => $this->database,
                'username' => $this->username,
                'password' => $this->password,
                'charset' => $this->charset,
                'prefix' => '',
                'schema' => $this->options['schema'] ?? 'public',
                'sslmode' => $this->ssl ? ($this->sslOptions['mode'] ?? 'require') : 'prefer',
            ], static fn ($v) => $v !== null),
            'sqlsrv' => array_filter([
                'driver' => 'sqlsrv',
                'host' => $this->host,
                'port' => $this->port ?: 1433,
                'database' => $this->database,
                'username' => $this->username,
                'password' => $this->password,
                'charset' => 'utf8',
                'prefix' => '',
                'encrypt' => $this->ssl ? 'yes' : ($this->options['encrypt'] ?? 'no'),
                'trust_server_certificate' => $this->options['trust_server_certificate'] ?? false,
            ], static fn ($v) => $v !== null),
            default => [
                // mysql + mariadb
                'driver' => $this->driver,
                'host' => $this->host,
                'port' => $this->port ?: 3306,
                'database' => $this->database,
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
}
