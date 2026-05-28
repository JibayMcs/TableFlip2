<?php

declare(strict_types=1);

namespace App\Application\Sql;

use App\Application\Connections\CurrentConnection;
use App\Domain\Auth\DirectDbUser;
use App\Domain\Sql\SqlExecutionResult;
use App\Models\QueryHistory;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class QueryHistoryService
{
    public function __construct(private readonly CurrentConnection $current) {}

    public function record(string $sql, ?string $database, SqlExecutionResult $result): QueryHistory
    {
        [$kind, $identifier] = $this->resolveUser();

        $payload = [
            'duration_ms' => (int) round($result->durationMs),
            'status' => $result->succeeded() ? 'success' : 'error',
            'error_message' => $result->error,
            'affected_rows' => $result->affectedRows,
            'executed_at' => now(),
        ];

        // Dedupe : if the user's most recent entry has the exact same SQL +
        // database, just refresh its timestamp/duration so the sidebar stays
        // useful without piling up identical rows when someone hits "run" twice.
        $latest = QueryHistory::query()
            ->where('user_kind', $kind)
            ->where('user_identifier', $identifier)
            ->orderByDesc('id')
            ->first();

        if ($latest !== null && $latest->sql_text === $sql && $latest->database_name === $database) {
            $latest->update($payload);

            return $latest;
        }

        return QueryHistory::create([
            'user_kind' => $kind,
            'user_identifier' => $identifier,
            'connection_label' => $this->current->label(),
            'database_name' => $database,
            'sql_text' => $sql,
        ] + $payload);
    }

    public function deleteEntry(int $id): bool
    {
        [$kind, $identifier] = $this->resolveUser();

        return (bool) QueryHistory::query()
            ->where('id', $id)
            ->where('user_kind', $kind)
            ->where('user_identifier', $identifier)
            ->delete();
    }

    public function clearAll(): int
    {
        [$kind, $identifier] = $this->resolveUser();

        return QueryHistory::query()
            ->where('user_kind', $kind)
            ->where('user_identifier', $identifier)
            ->delete();
    }

    /**
     * Recent queries by the current user, optionally filtered by text.
     */
    public function recent(string $search = '', int $perPage = 25): Paginator
    {
        [$kind, $identifier] = $this->resolveUser();

        return QueryHistory::query()
            ->where('user_kind', $kind)
            ->where('user_identifier', $identifier)
            ->when($search !== '', fn ($q) => $q->where('sql_text', 'like', "%{$search}%"))
            ->orderByDesc('executed_at')
            ->simplePaginate($perPage);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUser(): array
    {
        if (Auth::guard('db_session')->check()) {
            /** @var DirectDbUser $u */
            $u = Auth::guard('db_session')->user();

            return ['direct_db', $u->id];
        }

        return ['system', '0'];
    }
}
