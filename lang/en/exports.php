<?php

return [
    'title' => 'Exports',
    'expire_notice' => 'Files expire after :days days.',
    'empty' => 'No exports yet. Trigger one from the Explorer or the SQL editor.',
    'columns' => [
        'file' => 'File',
        'format' => 'Format',
        'source' => 'Source',
        'status' => 'Status',
        'rows_size' => 'Rows / Size',
        'created' => 'Created',
        'actions' => 'Actions',
    ],
    'status' => [
        'ready' => 'ready',
        'failed' => 'failed',
        'running' => 'running',
        'queued' => 'queued',
    ],
    'download' => 'Download',
    'delete' => 'Delete',
    'delete_confirm' => 'Delete this export?',
    'refresh' => 'Refresh',
];
