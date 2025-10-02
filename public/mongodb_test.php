<?php
// Test MongoDB connectivity directly using the MongoDB driver

// Output content type
header('Content-Type: text/plain');

// MongoDB URI from environment
$mongoUri = getenv('MONGODB_URI');
$mongoDb = getenv('MONGODB_DB');

echo "Testing MongoDB connection to: " . preg_replace('/mongodb:\/\/([^:]+):[^@]+@/', 'mongodb://****:****@', $mongoUri) . "\n\n";

try {
    // Create MongoDB manager
    echo "Creating MongoDB manager...\n";
    $manager = new MongoDB\Driver\Manager($mongoUri, [
        'serverSelectionTimeoutMS' => 5000,
        'connectTimeoutMS' => 5000,
    ]);
    
    echo "Manager created, testing connection...\n";
    
    // Execute a ping command
    $command = new MongoDB\Driver\Command(['ping' => 1]);
    $result = $manager->executeCommand('admin', $command);
    $response = current($result->toArray());
    
    echo "Connection successful!\n";
    echo "Server response: " . json_encode($response) . "\n\n";
    
    // List databases
    echo "Listing databases...\n";
    $command = new MongoDB\Driver\Command(['listDatabases' => 1]);
    $result = $manager->executeCommand('admin', $command);
    $response = current($result->toArray());
    
    if (isset($response->databases)) {
        echo "Available databases:\n";
        foreach ($response->databases as $db) {
            echo "- " . $db->name . "\n";
        }
    }
    
    // Test if target database exists
    echo "\nChecking for target database ($mongoDb)...\n";
    $databaseFound = false;
    if (isset($response->databases)) {
        foreach ($response->databases as $db) {
            if ($db->name === $mongoDb) {
                $databaseFound = true;
                break;
            }
        }
    }
    
    if ($databaseFound) {
        echo "Target database '$mongoDb' found.\n";
        
        // Try to list collections
        echo "Listing collections in $mongoDb...\n";
        $command = new MongoDB\Driver\Command(['listCollections' => 1]);
        $result = $manager->executeCommand($mongoDb, $command);
        $response = current($result->toArray());
        
        if (isset($response->cursor->firstBatch)) {
            foreach ($response->cursor->firstBatch as $collection) {
                echo "- " . $collection->name . "\n";
            }
        } else {
            echo "No collections found or unable to list collections.\n";
        }
    } else {
        echo "Target database '$mongoDb' not found in the server.\n";
    }
    
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    echo "Connection timeout: " . $e->getMessage() . "\n";
    echo "This usually means the MongoDB server is unreachable or blocked by firewall.\n";
    echo "Check if your IP is whitelisted in MongoDB Atlas.\n";
    
} catch (MongoDB\Driver\Exception\AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
    echo "Check your username and password in the MongoDB URI.\n";
    
} catch (MongoDB\Driver\Exception\SSLConnectionException $e) {
    echo "SSL Connection error: " . $e->getMessage() . "\n";
    echo "This usually means SSL/TLS issues with the connection.\n";
    
} catch (Exception $e) {
    echo "Error: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    
    // Print trace for detailed debugging
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}