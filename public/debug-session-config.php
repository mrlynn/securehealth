<?php
// Debug script to check session configuration

echo "=== Session Configuration Debug ===\n\n";

echo "Session save path: " . session_save_path() . "\n";
echo "Session name: " . session_name() . "\n";
echo "Session cookie lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "Session cookie path: " . ini_get('session.cookie_path') . "\n";
echo "Session cookie domain: " . ini_get('session.cookie_domain') . "\n";
echo "Session cookie secure: " . ini_get('session.cookie_secure') . "\n";
echo "Session cookie httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "Session use cookies: " . ini_get('session.use_cookies') . "\n";
echo "Session use only cookies: " . ini_get('session.use_only_cookies') . "\n";
echo "Session auto start: " . ini_get('session.auto_start') . "\n";
echo "Session gc max lifetime: " . ini_get('session.gc_maxlifetime') . "\n";
echo "Session gc probability: " . ini_get('session.gc_probability') . "\n";
echo "Session gc divisor: " . ini_get('session.gc_divisor') . "\n\n";

echo "System temp directory: " . sys_get_temp_dir() . "\n";
echo "Session files in temp: ";
system('ls -la ' . sys_get_temp_dir() . '/sess_* 2>/dev/null | wc -l');
echo "\n";

echo "Testing session creation:\n";
session_start();
echo "Session ID: " . session_id() . "\n";
echo "Session status: " . session_status() . "\n";

$_SESSION['test'] = 'Hello World';
session_write_close();

echo "Session written. Session ID: " . session_id() . "\n";

$sessionFile = session_save_path() . '/sess_' . session_id();
echo "Session file path: " . $sessionFile . "\n";
echo "Session file exists: " . (file_exists($sessionFile) ? 'YES' : 'NO') . "\n";

if (file_exists($sessionFile)) {
    echo "Session file size: " . filesize($sessionFile) . " bytes\n";
    echo "Session file content: " . file_get_contents($sessionFile) . "\n";
}
?>
