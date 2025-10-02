<?php
header('Content-Type: application/json');

// Simple health check endpoint that doesn't require authentication
echo json_encode([
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'api_version' => '1.0.0'
]);