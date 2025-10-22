<?php

// Router script for PHP built-in server
// This ensures index.html is served by default instead of index.php

// Set session save path
$sessionPath = __DIR__ . '/../var/cache/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}
session_save_path($sessionPath);

// Load environment variables from .env file only in development
// In production (Railway), environment variables are already set
if (($_SERVER['APP_ENV'] ?? 'dev') === 'dev' && file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Ensure critical environment variables are available in $_SERVER for Symfony
// Railway sets them via getenv() but Symfony reads from $_SERVER
$criticalVars = ['APP_ENV', 'APP_DEBUG', 'MONGODB_DB', 'MONGODB_URI', 'APP_SECRET'];
foreach ($criticalVars as $var) {
    $value = getenv($var);
    if ($value !== false && !isset($_SERVER[$var])) {
        $_SERVER[$var] = $value;
        $_ENV[$var] = $value;
    }
}

// Set defaults if still not set
if (!isset($_SERVER['APP_ENV'])) {
    $_SERVER['APP_ENV'] = 'prod';
    $_ENV['APP_ENV'] = 'prod';
}
if (!isset($_SERVER['MONGODB_DB'])) {
    $_SERVER['MONGODB_DB'] = 'securehealth';
    $_ENV['MONGODB_DB'] = 'securehealth';
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If the request is for the root path, serve index.html
if ($uri === '/' || $uri === '') {
    if (file_exists(__DIR__ . '/index.html')) {
        return false; // Let PHP serve the static file
    }
}

// If the request is for a PHP file, serve it
if (preg_match('/\.php$/', $uri)) {
    return false; // Let PHP serve the PHP file
}

// If the request is for the API, route to index.php
if (preg_match('/^\/api\//', $uri)) {
    $_SERVER['REQUEST_URI'] = $uri;
    include __DIR__ . '/index.php';
    return true;
}

// If the request is for staff routes, route to index.php
if (preg_match('/^\/staff\//', $uri)) {
    $_SERVER['REQUEST_URI'] = $uri;
    include __DIR__ . '/index.php';
    return true;
}

// If the request is for admin routes, route to index.php
if (preg_match('/^\/admin\//', $uri)) {
    $_SERVER['REQUEST_URI'] = $uri;
    include __DIR__ . '/index.php';
    return true;
}

// If the request is for a static file that exists, serve it
if (file_exists(__DIR__ . $uri)) {
    // Set proper content type for JSON files
    if (preg_match('/\.json$/', $uri)) {
        header('Content-Type: application/json');
    }
    return false; // Let PHP serve the static file
}

// For all other requests, serve index.html (SPA behavior)
if (file_exists(__DIR__ . '/index.html')) {
    include __DIR__ . '/index.html';
    return true;
}

// If index.html doesn't exist, return 404
http_response_code(404);
echo "File not found";
return true;
