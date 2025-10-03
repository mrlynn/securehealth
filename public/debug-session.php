<?php
// Debug script to check session data
session_start();

echo "=== Session Debug Information ===\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Status: " . session_status() . "\n\n";

echo "Session Data:\n";
if (empty($_SESSION)) {
    echo "No session data found\n";
} else {
    foreach ($_SESSION as $key => $value) {
        echo "  $key: " . json_encode($value) . "\n";
    }
}

echo "\nCookies:\n";
if (empty($_COOKIE)) {
    echo "No cookies found\n";
} else {
    foreach ($_COOKIE as $key => $value) {
        echo "  $key: $value\n";
    }
}

echo "\nRequest Headers:\n";
foreach (getallheaders() as $name => $value) {
    if (strtolower($name) === 'cookie') {
        echo "  $name: $value\n";
    }
}
?>