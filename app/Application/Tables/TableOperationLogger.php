<?php

declare(strict_types=1);

namespace App\Application\Tables;

use App\Application\Connections\CurrentConnection;
use App\Domain\Auth\DirectDbUser;
use App\Domain\Database\ValueObjects\TableIdentifier;
use App\Models\TableOperation;
use Illuminate\Support\Facades\Auth;

class TableOperationLogger
{
    public function __construct(private readonly CurrentConnection $current) {}

    /**
     * @param  array<int, mixed>  $bindings
     */
    public function log(
        string $operation,
        TableIdentifier $table,
        string $sql,
        array $bindings,
        int $affected,
    ): void {
        if (! (bool) config('tableflip.audit.enabled', true)) {
            return;
        }

        [$kind, $identifier] = $this->resolveUser();

        TableOperation::create([
            'user_kind' => $kind,
            'user_identifier' => $identifier,
            'connection_label' => $this->current->label(),
            'database_name' => (string) ($table->database ?? ''),
            'schema_name' => $table->schema,
            'table_name' => $table->name,
            'operation' => $operation,
            'sql_text' => $sql,
            'bindings' => $bindings,
            'affected_rows' => $affected,
            'performed_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUser(): array
    {
        if (Auth::guard('web')->check()) {
            return ['web', (string) Auth::guard('web')->id()];
        }

        if (Auth::guard('db_session')->check()) {
            /** @var DirectDbUser $u */
            $u = Auth::guard('db_session')->user();

            return ['direct_db', $u->id];
        }

        return ['system', '0'];
    }
}
