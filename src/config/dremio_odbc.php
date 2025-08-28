<?php

return [
    'driver' => 'odbc',
    'dsn' => env('DB_ODBC_DRIVER', 'Arrow Flight SQL ODBC Driver'),
    'host' => env('DB_ODBC_HOST', 'localhost'),
    'port' => env('DB_ODBC_PORT', 32010),
    'database' => env('DB_ODBC_DATABASE', 'AVIAN'),
    'username' => env('DB_ODBC_USERNAME', ''),
    'password' => env('DB_ODBC_PASSWORD', ''),
    'case' => env('DB_ODBC_CASE', 'original'),

    // Opsi tambahan
    'encryption' => env('DB_ODBC_ENCRYPTION', 1),
    'disable_cert_verification' => env('DB_ODBC_DISABLE_CERT', 1),
];
