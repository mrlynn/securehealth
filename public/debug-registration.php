<?php
// Debug script for patient registration issues
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get the email from POST data
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';

if (empty($email)) {
    echo json_encode([
        'error' => 'No email provided',
        'input' => $input
    ]);
    exit;
}

echo json_encode([
    'email' => $email,
    'timestamp' => date('Y-m-d H:i:s'),
    'message' => 'Debug info for registration conflict'
]);
?>
