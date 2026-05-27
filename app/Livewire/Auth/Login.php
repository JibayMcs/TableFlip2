<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Application\Auth\AllowedConnectionPolicy;
use App\Application\Auth\DirectDbAuthenticator;
use App\Application\Auth\DirectDbCredentials;
use App\Infrastructure\Database\DatabaseDriverFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Login extends Component
{
    public string $mode = 'account';

    // Account mode
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    // Direct mode
    public string $driver = 'mysql';

    public string $host = '127.0.0.1';

    public ?int $port = null;

    public string $database = '';

    public string $username = '';

    public string $directPassword = '';

    // UI state derived from config/policy
    /** @var list<string> */
    public array $driverChoices = [];

    public bool $breezeEnabled = true;

    public bool $directEnabled = true;

    public bool $hostLocked = false;

    public bool $driverLocked = false;

    public bool $databaseLocked = false;

    /** True when the user MUST provide a database (locked by policy, env, or SQLite). */
    public bool $databaseRequired = false;

    public function mount(): void
    {
        $this->breezeEnabled = (bool) config('tableflip.auth.breeze_enabled');
        $this->directEnabled = (bool) config('tableflip.auth.direct_db_enabled');

        $policy = AllowedConnectionPolicy::fromConfig();
        $factory = app(DatabaseDriverFactory::class);

        $allDrivers = $factory->supportedDrivers();
        $this->driverChoices = $policy->allowedDrivers === []
            ? $allDrivers
            : array_values(array_intersect($allDrivers, $policy->allowedDrivers));

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

        $cookie = request()->cookie('tableflip_last_direct');
        if (is_string($cookie)) {
            $data = json_decode($cookie, true) ?: [];
            if (! $this->hostLocked && isset($data['host'])) {
                $this->host = (string) $data['host'];
            }
            if (! $this->driverLocked && isset($data['driver']) && in_array($data['driver'], $this->driverChoices, true)) {
                $this->driver = (string) $data['driver'];
            }
            if (! $this->databaseLocked && isset($data['database'])) {
                $this->database = (string) $data['database'];
            }
            $this->username = (string) ($data['username'] ?? '');
            $this->port = isset($data['port']) ? (int) $data['port'] : null;
        }

        if (! $this->breezeEnabled) {
            $this->mode = 'direct';
        }
        if (! $this->directEnabled) {
            $this->mode = 'account';
        }

        $this->refreshDatabaseRequirement();
    }

    public function updatedDriver(): void
    {
        $this->refreshDatabaseRequirement();
    }

    private function refreshDatabaseRequirement(): void
    {
        $policy = AllowedConnectionPolicy::fromConfig();

        $this->databaseRequired = $policy->allowedDatabases !== []
            || $this->driver === 'sqlite'
            || (bool) config('tableflip.auth.require_db_name');
    }

    public function loginAccount(): void
    {
        if (! $this->breezeEnabled) {
            throw ValidationException::withMessages(['email' => __('Account login is disabled.')]);
        }

        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $ok = Auth::guard('web')->attempt(
            ['email' => $this->email, 'password' => $this->password, 'is_active' => true],
            $this->remember,
        );

        if (! $ok) {
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        request()->session()->regenerate();

        $this->redirect('/', navigate: true);
    }

    public function loginDirect(DirectDbAuthenticator $authenticator): void
    {
        if (! $this->directEnabled) {
            throw ValidationException::withMessages(['driver' => __('Direct database login is disabled.')]);
        }

        $rules = [
            'driver' => ['required', 'in:'.implode(',', $this->driverChoices)],
            'database' => [$this->databaseRequired ? 'required' : 'nullable', 'string'],
        ];

        if ($this->driver !== 'sqlite') {
            $rules += [
                'host' => ['required', 'string'],
                'username' => ['required', 'string'],
                'directPassword' => ['required', 'string'],
            ];
        }

        $this->validate($rules);

        $creds = new DirectDbCredentials(
            driver: $this->driver,
            host: $this->host,
            port: $this->port ?? $this->defaultPort($this->driver),
            database: $this->database,
            username: $this->username,
            password: $this->directPassword,
        );

        try {
            $user = $authenticator->authenticate($creds);
        } catch (AuthenticationException $e) {
            throw ValidationException::withMessages(['directPassword' => $e->getMessage()]);
        }

        /** @var \App\Infrastructure\Auth\Guards\DbSessionGuard $guard */
        $guard = Auth::guard('db_session');
        $guard->login($user);

        Cookie::queue('tableflip_last_direct', json_encode([
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
        ]) ?: '', 60 * 24 * 30);

        $this->redirect('/', navigate: true);
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

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
