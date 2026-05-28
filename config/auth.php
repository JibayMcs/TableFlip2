<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'db_session'),
    ],

    'guards' => [
        'db_session' => [
            'driver' => 'db_session',
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
