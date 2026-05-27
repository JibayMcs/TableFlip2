<?php

declare(strict_types=1);

return [

    'auth' => [
        'breeze_enabled' => (bool) env('AUTH_BREEZE_ENABLED', true),
        'direct_db_enabled' => (bool) env('AUTH_DIRECT_DB_ENABLED', true),
        'registration_enabled' => (bool) env('AUTH_REGISTRATION_ENABLED', false),

        // When false (default), the direct-DB login lets you authenticate
        // against a server WITHOUT picking a database — PMA-style. Switch
        // to true to force users to provide a database at login.
        'require_db_name' => (bool) env('TABLEFLIP_REQUIRE_DB_NAME', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed direct-DB connection scope
    |--------------------------------------------------------------------------
    |
    | These lists restrict what a "direct-DB" user can reach. Each list is a
    | comma-separated set of patterns (fnmatch syntax, case-insensitive).
    | Leave a list empty to allow anything. When a list contains exactly
    | one value, the corresponding form field is pre-filled and locked.
    |
    | Useful for deployments where TableFlip should be scoped to a single
    | database service (e.g. a Dokploy stack with one Postgres service).
    |
    */

    'allowed_db' => [
        'hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TABLEFLIP_ALLOWED_DB_HOSTS', '')),
        ))),
        'drivers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TABLEFLIP_ALLOWED_DB_DRIVERS', '')),
        ))),
        'databases' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TABLEFLIP_ALLOWED_DB_NAMES', '')),
        ))),
    ],

];
