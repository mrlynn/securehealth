<?php
header('Content-Type: application/json');

$debug = [
    'APP_ENV' => $_SERVER['APP_ENV'] ?? 'NOT SET',
    'APP_DEBUG' => $_SERVER['APP_DEBUG'] ?? 'NOT SET',
    'MONGODB_DB' => $_SERVER['MONGODB_DB'] ?? 'NOT SET',
    'MONGODB_URI' => isset($_SERVER['MONGODB_URI']) ? 'SET (hidden)' : 'NOT SET',
    'PHP_SAPI' => php_sapi_name(),
    'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'NOT SET',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
    'getenv_MONGODB_DB' => getenv('MONGODB_DB') ?: 'NOT SET',
    'getenv_APP_ENV' => getenv('APP_ENV') ?: 'NOT SET',
    'all_env_vars' => array_keys($_ENV ?? [])
];

echo json_encode($debug, JSON_PRETTY_PRINT);
