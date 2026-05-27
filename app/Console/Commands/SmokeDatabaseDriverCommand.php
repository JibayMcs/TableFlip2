<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Database\Exceptions\UnsupportedFeatureException;
use App\Domain\Database\ValueObjects\ConnectionConfig;
use App\Domain\Database\ValueObjects\TableIdentifier;
use App\Infrastructure\Database\DatabaseDriverFactory;
use Illuminate\Console\Command;
use Throwable;

class SmokeDatabaseDriverCommand extends Command
{
    protected $signature = 'db:driver-smoke
        {driver=mysql : Driver to test (mysql, mariadb, pgsql, sqlite, sqlsrv)}
        {--table=users : Table to introspect}';

    protected $description = 'Run a smoke test against a database driver: ping, version, list tables, introspect a table.';

    public function handle(DatabaseDriverFactory $factory): int
    {
        $driver = (string) $this->argument('driver');

        try {
            $config = $this->buildConfig($driver);
        } catch (UnsupportedFeatureException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Driver: {$driver}");
        $this->line("Database: {$config->database}");

        try {
            $instance = $factory->create($config);
        } catch (Throwable $e) {
            $this->error('Cannot instantiate driver: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $this->line('Ping: '.($instance->ping() ? '<info>ok</info>' : '<error>failed</error>'));
            $this->line('Version: '.$instance->version());

            $tables = $instance->listTables();
            $this->newLine();
            $this->info('Tables ('.count($tables).'):');
            foreach ($tables as $t) {
                $this->line('  - '.$t);
            }

            $tableName = (string) $this->option('table');
            $target = $this->resolveTarget($tables, $tableName);

            if ($target === null) {
                $this->warn("Table [{$tableName}] not found — skipping introspection.");

                return self::SUCCESS;
            }

            $this->newLine();
            $this->info("Columns of [{$target}]:");
            foreach ($instance->getColumns($target) as $c) {
                $flags = array_filter([
                    $c->nullable ? null : 'NOT NULL',
                    $c->autoIncrement ? 'AUTO_INCREMENT' : null,
                    $c->isPrimaryKey ? 'PK' : null,
                ]);
                $this->line(sprintf(
                    '  - %-30s %s [%s]%s',
                    $c->name,
                    $c->rawType,
                    $c->type->value,
                    $flags ? ' '.implode(' ', $flags) : '',
                ));
            }

            $this->newLine();
            $this->info("Indexes of [{$target}]:");
            foreach ($instance->getIndexes($target) as $i) {
                $flags = array_filter([$i->primary ? 'PRIMARY' : null, $i->unique ? 'UNIQUE' : null]);
                $this->line(sprintf(
                    '  - %-40s (%s)%s',
                    $i->name,
                    implode(', ', $i->columns),
                    $flags ? ' '.implode(' ', $flags) : '',
                ));
            }

            $this->newLine();
            $this->info("Foreign keys of [{$target}]:");
            $fks = $instance->getForeignKeys($target);
            if ($fks === []) {
                $this->line('  (none)');
            }
            foreach ($fks as $fk) {
                $this->line(sprintf(
                    '  - %s: (%s) -> %s(%s) onUpdate=%s onDelete=%s',
                    $fk->name,
                    implode(', ', $fk->columns),
                    $fk->referencedTable,
                    implode(', ', $fk->referencedColumns),
                    $fk->onUpdate ?? '-',
                    $fk->onDelete ?? '-',
                ));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e::class.': '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $instance->disconnect();
        }
    }

    private function buildConfig(string $driver): ConnectionConfig
    {
        return match ($driver) {
            'mysql', 'mariadb' => new ConnectionConfig(
                driver: $driver,
                database: (string) config('database.connections.mariadb.database', 'tableflip'),
                host: (string) config('database.connections.mariadb.host', '127.0.0.1'),
                port: (int) config('database.connections.mariadb.port', 3306),
                username: (string) config('database.connections.mariadb.username', 'root'),
                password: (string) config('database.connections.mariadb.password', ''),
            ),
            'sqlite' => new ConnectionConfig(
                driver: 'sqlite',
                database: database_path('database.sqlite'),
            ),
            'pgsql', 'sqlsrv' => throw UnsupportedFeatureException::for(
                $driver,
                'no smoke-test preset configured — provide credentials via custom command in your environment.',
            ),
            default => throw UnsupportedFeatureException::for($driver, 'unknown driver'),
        };
    }

    /**
     * @param  list<TableIdentifier>  $tables
     */
    private function resolveTarget(array $tables, string $name): ?TableIdentifier
    {
        foreach ($tables as $t) {
            if ($t->name === $name) {
                return $t;
            }
        }

        return null;
    }
}
