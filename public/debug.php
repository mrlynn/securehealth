<?php
// Simple debug script to test Railway environment

echo "=== Railway Debug Info ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "PORT: " . ($_SERVER['PORT'] ?? 'NOT SET') . "\n";
echo "APP_ENV: " . ($_SERVER['APP_ENV'] ?? 'NOT SET') . "\n";
echo "MONGODB_URI: " . (isset($_SERVER['MONGODB_URI']) ? 'SET' : 'NOT SET') . "\n";

echo "\n=== File Check ===\n";
echo "vendor/autoload.php exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo "index.html exists: " . (file_exists(__DIR__ . '/index.html') ? 'YES' : 'NO') . "\n";
echo "index.php exists: " . (file_exists(__DIR__ . '/index.php') ? 'YES' : 'NO') . "\n";

echo "\n=== Environment Variables ===\n";
$envVars = ['APP_ENV', 'APP_DEBUG', 'MONGODB_URI', 'MONGODB_DB', 'APP_SECRET'];
foreach ($envVars as $var) {
    echo "$var: " . (getenv($var) ? 'SET' : 'NOT SET') . "\n";
}

echo "\n=== Test Complete ===\n";
?>