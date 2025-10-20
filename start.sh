#!/bin/bash

# Force PHP built-in server instead of FrankenPHP
echo "Starting PHP built-in server on port 8080..."

# Start PHP built-in server
exec php -S 0.0.0.0:8080 -t public public/router.php
