<?php
// Debug session information
// This script is for debugging session data in local and deployed environments.

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionData = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'session_name' => session_name(),
    'session_cookie_params' => session_get_cookie_params(),
    'request_cookies' => $_COOKIE,
    'server_vars' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
    ]
];

echo json_encode($sessionData, JSON_PRETTY_PRINT);
?>
