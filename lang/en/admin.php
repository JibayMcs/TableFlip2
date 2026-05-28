<?php

return [
    'navbar' => [
        'audit' => 'Audit',
        'history' => 'History',
    ],

    'common' => [
        'no_results' => 'No matching entries.',
        'search_placeholder' => 'Search…',
        'expand_sql' => 'Show SQL',
        'collapse_sql' => 'Hide SQL',
        'filter_all' => 'All',
        'filter_by_op' => 'Operation',
        'filter_by_status' => 'Status',
        'filter_by_kind' => 'User type',
        'kind_web' => 'Account',
        'kind_direct_db' => 'Direct DB',
    ],

    'table_operations' => [
        'title' => 'Write audit log',
        'subtitle' => 'Every insert / update / delete performed through the explorer.',
        'search_placeholder' => 'Search by table, database, user or SQL…',
        'col_when' => 'When',
        'col_user' => 'User',
        'col_target' => 'Target',
        'col_operation' => 'Op',
        'col_rows' => 'Rows',
        'col_sql' => 'SQL',
        'op_insert' => 'INSERT',
        'op_update' => 'UPDATE',
        'op_delete' => 'DELETE',
        'op_truncate' => 'TRUNCATE',
        'op_drop' => 'DROP',
        'bindings' => 'Bindings',
        'no_bindings' => 'No bindings.',
    ],

    'query_history' => [
        'title' => 'Query history',
        'subtitle' => 'Every SQL statement run through the editor or the scratch pad.',
        'search_placeholder' => 'Search by SQL, database or user…',
        'col_when' => 'When',
        'col_user' => 'User',
        'col_db' => 'Database',
        'col_status' => 'Status',
        'col_duration' => 'Duration',
        'col_rows' => 'Rows',
        'col_sql' => 'SQL',
        'status_success' => 'OK',
        'status_error' => 'Error',
        'duration_ms' => ':ms ms',
        'error_message' => 'Error',
    ],
];
