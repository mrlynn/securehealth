<?php
// Debug script to test session persistence across requests
session_start();

echo "=== Session Persistence Test ===\n\n";

echo "Current Session ID: " . session_id() . "\n";
echo "Current Session Data: " . json_encode($_SESSION) . "\n\n";

// Check if we have a session ID from the previous request
if (isset($_GET['session_id'])) {
    echo "Previous Session ID: " . $_GET['session_id'] . "\n";
    echo "Current Session ID: " . session_id() . "\n";
    echo "Session IDs match: " . ($_GET['session_id'] === session_id() ? 'YES' : 'NO') . "\n\n";
}

// Set some test data
$_SESSION['test'] = 'Hello World';
$_SESSION['timestamp'] = time();
$_SESSION['user'] = [
    'email' => 'doctor@example.com',
    'username' => 'Dr. Smith',
    'roles' => ['ROLE_DOCTOR', 'ROLE_USER']
];

echo "Session data after setting: " . json_encode($_SESSION) . "\n\n";

// Force session write
session_write_close();

echo "Session written. Current Session ID: " . session_id() . "\n\n";

// Check session file
$sessionFile = session_save_path() . '/sess_' . session_id();
echo "Session file path: " . $sessionFile . "\n";
echo "Session file exists: " . (file_exists($sessionFile) ? 'YES' : 'NO') . "\n";

if (file_exists($sessionFile)) {
    echo "Session file content: " . file_get_contents($sessionFile) . "\n";
}

echo "\nNext request URL: http://localhost:8080/debug-session-persistence.php?session_id=" . session_id() . "\n";
?>
