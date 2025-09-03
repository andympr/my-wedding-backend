<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'my_wedding_db'),
            'username' => env('DB_USERNAME', 'wedding_user'),
            'password' => env('DB_PASSWORD', 'Myp@ssw0rd!'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ],
    ],

    'migrations' => 'migrations',
];
