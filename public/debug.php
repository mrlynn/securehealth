<?php
header('Content-Type: application/json');

// Log all requests for debugging
file_put_contents('php://stderr', "Request received at " . date('Y-m-d H:i:s') . "\n");
file_put_contents('php://stderr', "Method: " . $_SERVER['REQUEST_METHOD'] . "\n");
file_put_contents('php://stderr', "URI: " . $_SERVER['REQUEST_URI'] . "\n");

// For POST requests, log the body
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    file_put_contents('php://stderr', "Raw POST data: " . $rawData . "\n");
    
    // Try to parse JSON
    $jsonData = json_decode($rawData, true);
    if ($jsonData !== null) {
        file_put_contents('php://stderr', "Parsed JSON: " . print_r($jsonData, true) . "\n");
    }
}

// For debugging authentication specifically
if (strpos($_SERVER['REQUEST_URI'], '/api/login') !== false) {
    file_put_contents('php://stderr', "Login attempt detected!\n");
    
    // Demo login logic
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    
    if ($data && (isset($data['email']) || isset($data['_username'])) && (isset($data['password']) || isset($data['_password']))) {
        $email = $data['email'] ?? $data['_username'];
        $password = $data['password'] ?? $data['_password'];
        
        file_put_contents('php://stderr', "Login attempt: $email / $password\n");
        
        // Demo users
        $users = [
            'doctor@example.com' => [
                'password' => 'doctor',
                'username' => 'Dr. Smith',
                'roles' => ['ROLE_DOCTOR']
            ],
            'nurse@example.com' => [
                'password' => 'nurse',
                'username' => 'Nurse Johnson',
                'roles' => ['ROLE_NURSE']
            ],
            'receptionist@example.com' => [
                'password' => 'receptionist',
                'username' => 'Receptionist Davis',
                'roles' => ['ROLE_RECEPTIONIST']
            ],
            'admin@securehealth.com' => [
                'password' => 'admin123',
                'username' => 'System Administrator',
                'roles' => ['ROLE_ADMIN', 'ROLE_DOCTOR'],
                'isAdmin' => true
            ]
        ];
        
        // Check credentials
        if (isset($users[$email]) && $users[$email]['password'] === $password) {
            file_put_contents('php://stderr', "Login successful for $email\n");
            // Format response to match what React app expects
            echo json_encode([
                'success' => true,
                'user' => [
                    'email' => $email,
                    'username' => $users[$email]['username'],
                    'roles' => $users[$email]['roles'],
                    'isAdmin' => $users[$email]['isAdmin'] ?? false
                ]
            ]);
            exit;
        } else {
            file_put_contents('php://stderr', "Login failed for $email\n");
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
            exit;
        }
    } else {
        file_put_contents('php://stderr', "Missing username or password in request\n");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required'
        ]);
        exit;
    }
}

// Return debug info
echo json_encode([
    'debug' => 'This is a debug endpoint',
    'time' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    'php_version' => phpversion()
]);