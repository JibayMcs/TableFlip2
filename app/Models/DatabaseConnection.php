<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Database\ValueObjects\ConnectionConfig;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'name', 'driver', 'host', 'port', 'database',
    'username', 'password', 'options', 'ssl', 'color', 'last_used_at',
])]
#[Hidden(['password'])]
class DatabaseConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'options' => 'array',
            'ssl' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toConnectionConfig(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: $this->driver,
            database: (string) ($this->database ?? ''),
            host: (string) ($this->host ?? ''),
            port: (int) ($this->port ?? 0),
            username: (string) ($this->username ?? ''),
            password: (string) ($this->password ?? ''),
            options: $this->options ?? [],
            ssl: $this->ssl,
        );
    }

    public function poolId(): string
    {
        return 'saved_'.$this->id;
    }

    public function label(): string
    {
        if ($this->driver === 'sqlite') {
            return $this->name.' — sqlite:'.$this->database;
        }

        $base = ($this->username ? $this->username.'@' : '').($this->host ?? '').($this->port ? ':'.$this->port : '');

        return $this->database ? "{$this->name} — {$base}/{$this->database}" : "{$this->name} — {$base}";
    }
}
