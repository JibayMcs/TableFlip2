<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Database\ValueObjects\ConnectionConfig;
use Illuminate\Contracts\Auth\Authenticatable;

final class DirectDbUser implements Authenticatable
{
    public function __construct(
        public readonly string $id,
        public readonly ConnectionConfig $config,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function label(): string
    {
        $cfg = $this->config;

        if ($cfg->driver === 'sqlite') {
            return "sqlite:{$cfg->database}";
        }

        $base = "{$cfg->username}@{$cfg->host}:{$cfg->port}";

        return $cfg->hasDatabase() ? "{$base}/{$cfg->database}" : $base;
    }

    /**
     * Compact label for tight spaces (navbar) : drops the port and, for
     * SQLite, keeps only the file basename. The full {@see label()} is shown
     * as a tooltip alongside it.
     */
    public function shortLabel(): string
    {
        $cfg = $this->config;

        if ($cfg->driver === 'sqlite') {
            return 'sqlite:'.basename($cfg->database);
        }

        $base = "{$cfg->username}@{$cfg->host}";

        return $cfg->hasDatabase() ? "{$base}/{$cfg->database}" : $base;
    }
}
