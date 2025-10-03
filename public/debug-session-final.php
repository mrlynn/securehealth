<?php
// Final debug script to test session persistence
session_start();

echo "=== Final Session Debug Test ===\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Status: " . session_status() . "\n\n";

// Set test data
$_SESSION['test'] = 'Hello World';
$_SESSION['user'] = [
    'email' => 'doctor@example.com',
    'username' => 'Dr. Smith',
    'roles' => ['ROLE_DOCTOR', 'ROLE_USER']
];

echo "Session data: " . json_encode($_SESSION) . "\n\n";

// Force session write
session_write_close();

echo "Session written. Session ID: " . session_id() . "\n\n";

// Check session file
$sessionFile = session_save_path() . '/sess_' . session_id();
echo "Session file: " . $sessionFile . "\n";
echo "Session file exists: " . (file_exists($sessionFile) ? 'YES' : 'NO') . "\n";

if (file_exists($sessionFile)) {
    echo "Session file content: " . file_get_contents($sessionFile) . "\n";
}

echo "\nSession configuration:\n";
echo "  session.save_path: " . ini_get('session.save_path') . "\n";
echo "  session.name: " . ini_get('session.name') . "\n";
echo "  session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "  session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "  session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "  session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "  session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "  session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "  session.use_cookies: " . ini_get('session.use_cookies') . "\n";
echo "  session.use_only_cookies: " . ini_get('session.use_only_cookies') . "\n";
echo "  session.auto_start: " . ini_get('session.auto_start') . "\n";
?>
