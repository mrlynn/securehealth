<?php
session_start();

echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";

// Test if we can access Symfony's authentication
try {
    // Simple test - check if we have any session data
    if (isset($_SESSION['_security_main'])) {
        echo "Security token found in session\n";
    } else {
        echo "No security token in session\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
