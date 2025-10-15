<?php
// Simple authentication test
session_start();

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'message' => 'Authentication test completed'
]);
?>
