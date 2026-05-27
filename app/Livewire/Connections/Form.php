<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Application\Auth\AllowedConnectionPolicy;
use App\Application\Connections\ConnectionColors;
use App\Application\Connections\StoreConnectionAction;
use App\Application\Connections\TestConnectionAction;
use App\Application\Connections\UpdateConnectionAction;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Infrastructure\Database\DatabaseDriverFactory;
use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?DatabaseConnection $connection = null;

    public string $name = '';

    public string $driver = 'mysql';

    public string $host = '';

    public ?int $port = null;

    public string $database = '';

    public string $username = '';

    public string $password = '';

    public bool $ssl = false;

    public string $color = ConnectionColors::DEFAULT;

    // UI state
    /** @var list<string> */
    public array $driverChoices = [];

    public bool $hostLocked = false;

    public bool $driverLocked = false;

    public bool $databaseLocked = false;

    public bool $databaseRequired = false;

    /** @var array{ok: bool, message: string, version?: string}|null */
    public ?array $testResult = null;

    public bool $passwordTouched = false;

    public function mount(?int $connection = null): void
    {
        $factory = app(DatabaseDriverFactory::class);
        $policy = AllowedConnectionPolicy::fromConfig();

        $this->driverChoices = $policy->allowedDrivers === []
            ? $factory->supportedDrivers()
            : array_values(array_intersect($factory->supportedDrivers(), $policy->allowedDrivers));

        $this->hostLocked = $policy->hostLocked();
        $this->driverLocked = $policy->driverLocked();
        $this->databaseLocked = $policy->databaseLocked();

        if ($this->hostLocked) {
            $this->host = $policy->allowedHosts[0];
        }
        if ($this->driverLocked) {
            $this->driver = $policy->allowedDrivers[0];
        }
        if ($this->databaseLocked) {
            $this->database = $policy->allowedDatabases[0];
        }

        if ($connection !== null) {
            $model = DatabaseConnection::findOrFail($connection);
            Gate::authorize('update', $model);

            $this->connection = $model;
            $this->name = $model->name;
            $this->driver = $model->driver;
            $this->host = (string) ($model->host ?? '');
            $this->port = $model->port;
            $this->database = (string) ($model->database ?? '');
            $this->username = (string) ($model->username ?? '');
            $this->ssl = $model->ssl;
            $this->color = $model->color;
            // password is intentionally NOT preloaded — empty means "keep existing"
        }

        $this->refreshDatabaseRequirement();
    }

    public function updatedDriver(): void
    {
        $this->refreshDatabaseRequirement();
        $this->testResult = null;
    }

    public function updatedPassword(): void
    {
        $this->passwordTouched = true;
    }

    public function test(TestConnectionAction $action): void
    {
        $this->validate($this->rules());

        $this->testResult = $action->execute($this->buildConfig());
    }

    public function save(StoreConnectionAction $store, UpdateConnectionAction $update): void
    {
        $data = $this->validate($this->rules());

        $payload = [
            'name' => $data['name'],
            'driver' => $data['driver'],
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'database' => ($data['database'] ?? '') !== '' ? $data['database'] : null,
            'username' => $data['username'] ?? null,
            'ssl' => $data['ssl'] ?? false,
            'color' => $data['color'] ?? ConnectionColors::DEFAULT,
        ];

        if ($this->connection === null) {
            $payload['password'] = $data['password'] ?? '';
            /** @var User $user */
            $user = Auth::user();
            $store->execute($user, $payload);
            session()->flash('connections_status', 'Connection created.');
        } else {
            // Only include password when the user actually typed a new one.
            if ($this->passwordTouched && $this->password !== '') {
                $payload['password'] = $this->password;
            }
            $update->execute($this->connection, $payload);
            session()->flash('connections_status', 'Connection updated.');
        }

        $this->redirectRoute('connections.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.connections.form', [
            'palette' => ConnectionColors::PRESETS,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:'.implode(',', $this->driverChoices)],
            'database' => [$this->databaseRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'color' => ['required', 'in:'.implode(',', ConnectionColors::PRESETS)],
            'ssl' => ['boolean'],
        ];

        if ($this->driver !== 'sqlite') {
            $rules += [
                'host' => ['required', 'string', 'max:255'],
                'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'username' => ['required', 'string', 'max:255'],
                'password' => [$this->connection === null ? 'required' : 'nullable', 'string'],
            ];
        }

        return $rules;
    }

    private function refreshDatabaseRequirement(): void
    {
        $policy = AllowedConnectionPolicy::fromConfig();
        $this->databaseRequired = $policy->allowedDatabases !== []
            || $this->driver === 'sqlite'
            || (bool) config('tableflip.auth.require_db_name');
    }

    private function buildConfig(): ConnectionConfig
    {
        // For testing during edit when password was not retyped, fall back to
        // the stored encrypted password.
        $password = $this->password;
        if ($password === '' && $this->connection !== null) {
            $password = (string) $this->connection->password;
        }

        return new ConnectionConfig(
            driver: $this->driver,
            database: $this->database,
            host: $this->host,
            port: $this->port ?? $this->defaultPort($this->driver),
            username: $this->username,
            password: $password,
            ssl: $this->ssl,
        );
    }

    private function defaultPort(string $driver): int
    {
        return match ($driver) {
            'pgsql' => 5432,
            'sqlsrv' => 1433,
            'sqlite' => 0,
            default => 3306,
        };
    }
}
