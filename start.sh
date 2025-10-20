#!/bin/bash

# Force PHP built-in server instead of FrankenPHP
echo "Starting PHP built-in server on port 9000..."

# Start PHP built-in server
exec php -S 0.0.0.0:9000 -t public public/router.php
