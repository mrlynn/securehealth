<?php

require dirname(__DIR__).'/vendor/autoload.php';

echo "<h1>MongoDB Connection Debug</h1>";

// Network connectivity test
echo "<h2>Network Connectivity Test</h2>";
$servers = [
    'performance-shard-00-00.zbcul.mongodb.net' => 27017,
    'performance-shard-00-01.zbcul.mongodb.net' => 27017,
    'performance-shard-00-02.zbcul.mongodb.net' => 27017
];

foreach ($servers as $host => $port) {
    echo "Testing connection to $host:$port... ";
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($socket) {
        echo "<span style='color:green'>Connected</span><br>";
        fclose($socket);
    } else {
        echo "<span style='color:red'>Failed ($errstr)</span><br>";
    }
}

// Display environment variables
echo "<h2>Environment Variables</h2>";
echo "MONGODB_URI: " . getenv('MONGODB_URI') . "<br>";
echo "MONGODB_DB: " . getenv('MONGODB_DB') . "<br>";
echo "MONGODB_KEY_VAULT_NAMESPACE: " . getenv('MONGODB_KEY_VAULT_NAMESPACE') . "<br>";

try {
    // Try to establish connection with different options
    echo "<h2>Connection Test</h2>";
    
    echo "<h3>Attempt 1 - Standard Options</h3>";
    try {
        $options = [
            'ssl' => true,
            'authSource' => 'admin',
            'retryWrites' => true,
            'serverSelectionTimeoutMS' => 5000,
        ];
        
        echo "Creating MongoDB client...<br>";
        $client = new MongoDB\Client(getenv('MONGODB_URI'), $options);
        
        echo "Listing databases...<br>";
        $dbs = $client->listDatabases();
        
        echo "Connection successful!<br>";
    } catch (Exception $e) {
        echo "<span style='color:red'>Error: " . $e->getMessage() . "</span><br>";
    }
    
    echo "<h3>Attempt 2 - TLS Options</h3>";
    try {
        $options = [
            'tls' => true,
            'authSource' => 'admin',
            'retryWrites' => true,
            'serverSelectionTimeoutMS' => 5000,
            'tlsAllowInvalidCertificates' => true,
        ];
        
        echo "Creating MongoDB client with TLS options...<br>";
        $client = new MongoDB\Client(getenv('MONGODB_URI'), $options);
        
        echo "Listing databases...<br>";
        $dbs = $client->listDatabases();
        
        echo "Connection successful!<br>";
    } catch (Exception $e) {
        echo "<span style='color:red'>Error: " . $e->getMessage() . "</span><br>";
    }
    
    echo "<h3>Attempt 3 - Modified URI</h3>";
    try {
        // Try with a more basic URI
        $simpleUri = "mongodb://mike:xxx%21@performance-shard-00-00.zbcul.mongodb.net:27017/?authSource=admin&ssl=true";
        $options = [
            'retryWrites' => true,
            'serverSelectionTimeoutMS' => 5000,
        ];
        
        echo "Creating MongoDB client with simple URI...<br>";
        echo "URI: $simpleUri<br>";
        $client = new MongoDB\Client($simpleUri, $options);
        
        echo "Listing databases...<br>";
        $dbs = $client->listDatabases();
        
        echo "Connection successful!<br>";
    } catch (Exception $e) {
        echo "<span style='color:red'>Error: " . $e->getMessage() . "</span><br>";
    }
    
    // PHP info for debugging
    echo "<h2>PHP Info</h2>";
    echo "<h3>PHP Version</h3>";
    echo phpversion() . "<br>";
    
    echo "<h3>MongoDB Extension</h3>";
    echo "MongoDB Extension Version: " . phpversion('mongodb') . "<br>";
    
    echo "<h3>OpenSSL</h3>";
    echo "OpenSSL Version: " . OPENSSL_VERSION_TEXT . "<br>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Fatal Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
