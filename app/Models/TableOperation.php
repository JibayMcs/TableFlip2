<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_kind', 'user_identifier', 'connection_label',
    'database_name', 'schema_name', 'table_name',
    'operation', 'sql_text', 'bindings', 'affected_rows', 'performed_at',
])]
class TableOperation extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bindings' => 'array',
            'affected_rows' => 'integer',
            'performed_at' => 'datetime',
        ];
    }
}
