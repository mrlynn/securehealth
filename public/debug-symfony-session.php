<?php
// Debug script to test Symfony session handling
require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
if (method_exists(Dotenv::class, 'bootEnv') && file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('dev', true);
$kernel->boot();

// Create a mock request for login
$request = Request::create('/api/login', 'POST', [], [], [], [], json_encode([
    '_username' => 'doctor@example.com',
    '_password' => 'doctor'
]));
$request->headers->set('Content-Type', 'application/json');

echo "=== Symfony Session Debug Test ===\n\n";

echo "Before session - Session ID: " . session_id() . "\n";
echo "Before session - Session data: " . json_encode($_SESSION ?? []) . "\n\n";

// Start the session first
session_start();

// Get the session
$session = $request->getSession();

echo "Session object: " . get_class($session) . "\n";
echo "Session started: " . ($session->isStarted() ? 'YES' : 'NO') . "\n";
echo "Session ID: " . $session->getId() . "\n\n";

echo "After session start - Session ID: " . $session->getId() . "\n";
echo "After session start - Session data: " . json_encode($_SESSION ?? []) . "\n\n";

// Set user in session
$session->set('user', [
    'email' => 'doctor@example.com',
    'username' => 'Dr. Smith',
    'roles' => ['ROLE_DOCTOR', 'ROLE_USER']
]);

echo "After setting session - Session ID: " . $session->getId() . "\n";
echo "After setting session - Session data: " . json_encode($_SESSION ?? []) . "\n\n";

// Get user from session
$userData = $session->get('user');
echo "User data from session: " . json_encode($userData) . "\n\n";

// Save the session
$session->save();

echo "Session saved. Session ID: " . $session->getId() . "\n\n";

// Check session file
$sessionFile = session_save_path() . '/sess_' . $session->getId();
echo "Session file path: " . $sessionFile . "\n";
echo "Session file exists: " . (file_exists($sessionFile) ? 'YES' : 'NO') . "\n";

if (file_exists($sessionFile)) {
    echo "Session file content: " . file_get_contents($sessionFile) . "\n";
}
?>
