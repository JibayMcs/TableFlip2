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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Login extends Component
{
    public string $driver = 'mysql';

    public string $host = '127.0.0.1';

    public ?int $port = null;

    public string $database = '';

    public string $username = '';

    public string $password = '';

    /** @var list<string> */
    public array $driverChoices = [];

    public bool $hostLocked = false;

    public bool $driverLocked = false;

    public bool $databaseLocked = false;

    /** True when the user must provide a database (locked by policy, env, or SQLite). */
    public bool $databaseRequired = false;

    public function mount(): void
    {
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

        // Pre-fill from the last successful login on this browser. Username
        // and host save a keystroke ; the password is never persisted.
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

    /**
     * Fill the form with a saved bookmark's decrypted credentials and,
     * when `autoSubmit` is true, immediately attempt the login. Saves the
     * caller a roundtrip + a click compared to set-then-submit.
     *
     * @param  array{driver?: string, host?: string, port?: int|null, database?: string, username?: string, password?: string, autoSubmit?: bool}  $data
     */
    public function fillAndLogin(array $data, DirectDbAuthenticator $authenticator): void
    {
        if (isset($data['driver']) && in_array($data['driver'], $this->driverChoices, true)) {
            $this->driver = (string) $data['driver'];
        }
        if (isset($data['host'])) {
            $this->host = (string) $data['host'];
        }
        $this->port = isset($data['port']) && $data['port'] !== null ? (int) $data['port'] : null;
        if (isset($data['database'])) {
            $this->database = (string) $data['database'];
        }
        if (isset($data['username'])) {
            $this->username = (string) $data['username'];
        }
        if (isset($data['password'])) {
            $this->password = (string) $data['password'];
        }

        $this->refreshDatabaseRequirement();

        if (! ($data['autoSubmit'] ?? false)) {
            return;
        }

        $this->login($authenticator);
    }

    public function login(DirectDbAuthenticator $authenticator): void
    {
        $rules = [
            'driver' => ['required', 'in:'.implode(',', $this->driverChoices)],
            'database' => [$this->databaseRequired ? 'required' : 'nullable', 'string'],
        ];

        if ($this->driver !== 'sqlite') {
            $rules += [
                'host' => ['required', 'string'],
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
            ];
        }

        $this->validate($rules);

        // Throttle attempts the same way the back-end will, with a smaller
        // window. Five failures per minute on the (IP, host, username)
        // triple is enough to catch a brute force without affecting a user
        // mistyping their password.
        $rateKey = 'login|'.request()->ip().'|'.$this->host.'|'.$this->username;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'password' => __('Too many login attempts. Try again in :sec seconds.', ['sec' => $seconds]),
            ]);
        }

        $creds = new DirectDbCredentials(
            driver: $this->driver,
            host: $this->host,
            port: $this->port ?? $this->defaultPort($this->driver),
            database: $this->database,
            username: $this->username,
            password: $this->password,
        );

        try {
            $user = $authenticator->authenticate($creds);
        } catch (AuthenticationException $e) {
            RateLimiter::hit($rateKey, 60);
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        RateLimiter::clear($rateKey);

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
