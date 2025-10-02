<?php
// MongoDB initialization script using standalone PHP

require dirname(__DIR__).'/vendor/autoload.php';

// Load environment variables manually
$envFile = dirname(__DIR__).'/.env.local';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || !strpos($line, '=')) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, '"\'');
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

echo "MongoDB Initialization Script\n";
echo "===========================\n\n";

// Get MongoDB connection parameters
$mongoUri = $_ENV['MONGODB_URI'];
$mongoDb = $_ENV['MONGODB_DB'];
$keyVaultNamespace = $_ENV['MONGODB_KEY_VAULT_NAMESPACE'];

echo "Connection parameters:\n";
echo "- MongoDB URI: " . preg_replace('/mongodb:\/\/([^:]+):[^@]+@/', 'mongodb://****:****@', $mongoUri) . "\n";
echo "- Database: $mongoDb\n";
echo "- Key Vault: $keyVaultNamespace\n\n";

try {
    echo "Connecting to MongoDB...\n";
    $client = new MongoDB\Client($mongoUri);
    echo "Connection established.\n\n";
    
    // Parse key vault namespace
    list($keyVaultDb, $keyVaultColl) = explode('.', $keyVaultNamespace);
    
    // Check if key vault exists, create if not
    echo "Checking key vault collection ($keyVaultNamespace)...\n";
    try {
        $keyVaultExists = false;
        $collections = $client->selectDatabase($keyVaultDb)->listCollections(['filter' => ['name' => $keyVaultColl]]);
        foreach ($collections as $collection) {
            if ($collection->getName() === $keyVaultColl) {
                $keyVaultExists = true;
                break;
            }
        }
        
        if (!$keyVaultExists) {
            echo "Creating key vault collection...\n";
            $client->selectDatabase($keyVaultDb)->createCollection($keyVaultColl);
            echo "Key vault created.\n";
        } else {
            echo "Key vault already exists.\n";
        }
    } catch (Exception $e) {
        echo "Error checking/creating key vault: " . $e->getMessage() . "\n";
    }
    
    // Check if patients collection exists, create if not
    echo "\nChecking patients collection...\n";
    try {
        $patientsExists = false;
        $collections = $client->selectDatabase($mongoDb)->listCollections(['filter' => ['name' => 'patients']]);
        foreach ($collections as $collection) {
            if ($collection->getName() === 'patients') {
                $patientsExists = true;
                break;
            }
        }
        
        if (!$patientsExists) {
            echo "Creating patients collection...\n";
            $client->selectDatabase($mongoDb)->createCollection('patients');
            echo "Patients collection created.\n";
        } else {
            echo "Patients collection already exists.\n";
        }
    } catch (Exception $e) {
        echo "Error checking/creating patients collection: " . $e->getMessage() . "\n";
    }
    
    // Check if patients collection has data
    echo "\nChecking for patient records...\n";
    $patientsCount = $client->selectDatabase($mongoDb)->selectCollection('patients')->countDocuments();
    echo "Found $patientsCount patient records.\n";
    
    // If no patients, create sample data
    if ($patientsCount === 0) {
        echo "\nCreating sample patient records...\n";
        
        // Sample patients
        $patients = [
            [
                '_id' => new MongoDB\BSON\ObjectId(),
                'firstName' => 'John',
                'lastName' => 'Smith',
                'birthDate' => new MongoDB\BSON\UTCDateTime(strtotime('1975-05-12') * 1000),
                'email' => 'john.smith@example.com',
                'phone' => '555-123-4567',
                'ssn' => '123-45-1234',
                'address' => '123 Main St, Anytown, USA',
                'diagnosis' => ['Type 2 Diabetes', 'Hypertension'],
                'medications' => ['Metformin 500mg', 'Lisinopril 10mg'],
                'role' => 'ROLE_DOCTOR'
            ],
            [
                '_id' => new MongoDB\BSON\ObjectId(),
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'birthDate' => new MongoDB\BSON\UTCDateTime(strtotime('1982-08-24') * 1000),
                'email' => 'jane.doe@example.com',
                'phone' => '555-987-6543',
                'ssn' => '987-65-5678',
                'address' => '456 Oak Ave, Somewhere, USA',
                'diagnosis' => ['Asthma', 'Allergic Rhinitis'],
                'medications' => ['Albuterol Inhaler', 'Loratadine 10mg'],
                'role' => 'ROLE_NURSE'
            ],
            [
                '_id' => new MongoDB\BSON\ObjectId(),
                'firstName' => 'Robert',
                'lastName' => 'Johnson',
                'birthDate' => new MongoDB\BSON\UTCDateTime(strtotime('1965-03-17') * 1000),
                'email' => 'r.johnson@example.com',
                'phone' => '555-555-5555',
                'ssn' => '456-78-9012',
                'address' => '789 Pine St, Somewhere, USA',
                'diagnosis' => ['Coronary Artery Disease', 'Hyperlipidemia'],
                'medications' => ['Atorvastatin 20mg', 'Aspirin 81mg'],
                'role' => 'ROLE_RECEPTIONIST'
            ]
        ];
        
        // Insert patients
        $collection = $client->selectDatabase($mongoDb)->selectCollection('patients');
        foreach ($patients as $patient) {
            try {
                $result = $collection->insertOne($patient);
                echo "Added patient: " . $patient['firstName'] . " " . $patient['lastName'] . 
                     " (ID: " . $result->getInsertedId() . ")\n";
            } catch (Exception $e) {
                echo "Error adding patient: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Add users collection if needed
    echo "\nChecking users collection...\n";
    try {
        $usersExists = false;
        $collections = $client->selectDatabase($mongoDb)->listCollections(['filter' => ['name' => 'users']]);
        foreach ($collections as $collection) {
            if ($collection->getName() === 'users') {
                $usersExists = true;
                break;
            }
        }
        
        if (!$usersExists) {
            echo "Creating users collection...\n";
            $client->selectDatabase($mongoDb)->createCollection('users');
            echo "Users collection created.\n";
            
            // Add demo users
            $users = [
                [
                    '_id' => new MongoDB\BSON\ObjectId(),
                    'email' => 'doctor@example.com',
                    'password' => password_hash('doctor', PASSWORD_DEFAULT),
                    'roles' => ['ROLE_DOCTOR'],
                    'firstName' => 'Dr. James',
                    'lastName' => 'Wilson'
                ],
                [
                    '_id' => new MongoDB\BSON\ObjectId(),
                    'email' => 'nurse@example.com',
                    'password' => password_hash('nurse', PASSWORD_DEFAULT),
                    'roles' => ['ROLE_NURSE'],
                    'firstName' => 'Nurse',
                    'lastName' => 'Johnson'
                ],
                [
                    '_id' => new MongoDB\BSON\ObjectId(),
                    'email' => 'receptionist@example.com',
                    'password' => password_hash('receptionist', PASSWORD_DEFAULT),
                    'roles' => ['ROLE_RECEPTIONIST'],
                    'firstName' => 'Sara',
                    'lastName' => 'Davis'
                ]
            ];
            
            $usersCollection = $client->selectDatabase($mongoDb)->selectCollection('users');
            foreach ($users as $user) {
                try {
                    $result = $usersCollection->insertOne($user);
                    echo "Added user: " . $user['email'] . " with role " . implode(', ', $user['roles']) . "\n";
                } catch (Exception $e) {
                    echo "Error adding user: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "Users collection already exists.\n";
            $usersCount = $client->selectDatabase($mongoDb)->selectCollection('users')->countDocuments();
            echo "Found $usersCount user records.\n";
        }
    } catch (Exception $e) {
        echo "Error checking/creating users collection: " . $e->getMessage() . "\n";
    }
    
    echo "\nMongoDB initialization complete!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
}