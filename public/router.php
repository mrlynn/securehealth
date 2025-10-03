<?php

// Router script for PHP built-in server
// This ensures index.html is served by default instead of index.php

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
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

// If the request is for a static file that exists, serve it
if (file_exists(__DIR__ . $uri)) {
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
