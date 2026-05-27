<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_kind', 'user_identifier', 'connection_label',
    'database_name', 'sql_text', 'duration_ms',
    'status', 'error_message', 'affected_rows', 'executed_at',
])]
class QueryHistory extends Model
{
    protected $table = 'query_history';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'affected_rows' => 'integer',
            'executed_at' => 'datetime',
        ];
    }
}
