<?php
return [
    'driver'     => env('MAIL_DRIVER', 'smtp'),
    'host'       => env('MAIL_HOST', ''),
    'port'       => (int) env('MAIL_PORT', 465),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
    'from'       => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'YdAdmin SaaS'),
    ],
];
