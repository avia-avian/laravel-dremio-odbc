<?php

return [
    // Pilih transport: odbc | api
    'connection' => env('DREMIO_CONNECTION', 'odbc'),

    'driver' => 'dremio',
    'dsn' => env('DB_ODBC_DRIVER', env('DREMIO_DRIVER', 'Arrow Flight SQL ODBC Driver')),
    'host' => env('DB_ODBC_HOST', env('DREMIO_HOST', 'localhost')),
    'port' => env('DB_ODBC_PORT', env('DREMIO_PORT', 32010)),
    'database' => env('DB_ODBC_DATABASE', env('DREMIO_DATABASE', 'AVIAN')),
    'username' => env('DB_ODBC_USERNAME', env('DREMIO_USERNAME', '')),
    'password' => env('DB_ODBC_PASSWORD', env('DREMIO_PASSWORD', '')),
    'case' => env('DB_ODBC_CASE', env('DREMIO_CASE', 'original')),

    // Opsi tambahan
    'encryption' => env('DB_ODBC_ENCRYPTION', env('DREMIO_ENCRYPTION', 1)),
    'disable_cert_verification' => env('DB_ODBC_DISABLE_CERT', env('DREMIO_DISABLE_CERTIFICATE_VERIFICATION', 1)),

    // API options (dipakai jika connection=api)
    'api_base_url' => env('DREMIO_API_BASE_URL', ''),
    'api_token' => env('DREMIO_API_TOKEN', ''),
    'api_username' => env('DREMIO_API_USERNAME', ''),
    'api_password' => env('DREMIO_API_PASSWORD', ''),
    'api_login_endpoint' => env('DREMIO_API_LOGIN_ENDPOINT', '/apiv2/login'),
    'api_sql_endpoint' => env('DREMIO_API_SQL_ENDPOINT', '/api/v3/sql'),
    'api_timeout' => env('DREMIO_API_TIMEOUT', 30),
    'api_poll_interval' => env('DREMIO_API_POLL_INTERVAL', 500), // ms
    'api_results_limit' => env('DREMIO_API_RESULTS_LIMIT', 500),
    'api_verify_ssl' => env('DREMIO_API_VERIFY_SSL', true),
    // Gunakan format JSON array di .env, contoh: ["mySpace","myFolder"]
    'api_context' => env('DREMIO_API_CONTEXT', null),
];
