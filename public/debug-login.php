<?php
// Debug script to test session functionality
echo "=== Session Debug Test ===\n\n";

// Test basic session functionality
session_start();

echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Status: " . session_status() . "\n\n";

// Set some test data
$_SESSION['test'] = 'Hello World';
$_SESSION['user'] = [
    'email' => 'doctor@example.com',
    'username' => 'Dr. Smith',
    'roles' => ['ROLE_DOCTOR', 'ROLE_USER']
];

echo "Session data after setting: " . json_encode($_SESSION) . "\n\n";

// Test session persistence
session_write_close();
session_start();

echo "Session data after restart: " . json_encode($_SESSION) . "\n\n";

// Test session configuration
echo "Session configuration:\n";
echo "  session.save_path: " . ini_get('session.save_path') . "\n";
echo "  session.name: " . ini_get('session.name') . "\n";
echo "  session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "  session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "  session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "  session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "  session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "  session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
?>
