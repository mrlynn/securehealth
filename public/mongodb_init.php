<?php
// MongoDB initialization and debugging script

// Output content type
header('Content-Type: text/plain');

// Get MongoDB connection parameters
$mongoUri = getenv('MONGODB_URI');
$mongoDb = getenv('MONGODB_DB');
$keyVaultNamespace = getenv('MONGODB_KEY_VAULT_NAMESPACE');
$encryptionKeyPath = getenv('MONGODB_ENCRYPTION_KEY_PATH');

echo "MongoDB Debug Script\n";
echo "===================\n\n";

echo "Connection parameters:\n";
echo "- MongoDB URI: " . preg_replace('/mongodb:\/\/([^:]+):[^@]+@/', 'mongodb://****:****@', $mongoUri) . "\n";
echo "- Database: $mongoDb\n";
echo "- Key Vault: $keyVaultNamespace\n";
echo "- Encryption Key Path: $encryptionKeyPath\n\n";

// Load encryption key
if (file_exists($encryptionKeyPath)) {
    echo "Encryption key file exists\n";
    $masterKey = file_get_contents($encryptionKeyPath);
    echo "Key size: " . strlen($masterKey) . " bytes\n\n";
} else {
    echo "ERROR: Encryption key file not found at: $encryptionKeyPath\n";
    echo "Will attempt to generate a new key\n\n";
    try {
        $masterKey = random_bytes(96);
        file_put_contents($encryptionKeyPath, $masterKey);
        echo "Generated new encryption key\n\n";
    } catch (Exception $e) {
        echo "ERROR: Failed to generate key: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

try {
    // Create MongoDB manager
    echo "Connecting to MongoDB...\n";
    $manager = new MongoDB\Driver\Manager($mongoUri);
    echo "Connection established.\n\n";
    
    // Parse key vault namespace
    list($keyVaultDb, $keyVaultColl) = explode('.', $keyVaultNamespace);
    
    // Check if key vault collection exists
    echo "Checking key vault collection ($keyVaultNamespace)...\n";
    $command = new MongoDB\Driver\Command(['listCollections' => 1, 'filter' => ['name' => $keyVaultColl]]);
    $result = $manager->executeCommand($keyVaultDb, $command);
    $cursor = $result->toArray();
    
    if (count($cursor) === 0 || !isset($cursor[0]->cursor) || count($cursor[0]->cursor->firstBatch) === 0) {
        echo "Key vault collection not found. Creating...\n";
        $command = new MongoDB\Driver\Command(['create' => $keyVaultColl]);
        $result = $manager->executeCommand($keyVaultDb, $command);
        echo "Key vault collection created.\n";
    } else {
        echo "Key vault collection already exists.\n";
    }
    
    // Setup encryption client
    echo "\nSetting up client-side encryption...\n";
    
    // Create KMS providers configuration
    $kmsProviders = [
        'local' => ['key' => new MongoDB\BSON\Binary($masterKey, MongoDB\BSON\Binary::TYPE_GENERIC)]
    ];
    
    // Create ClientEncryption instance with options
    $encryptionOpts = [
        'keyVaultNamespace' => $keyVaultNamespace,
        'kmsProviders' => $kmsProviders
    ];
    
    $clientEncryption = new MongoDB\Driver\ClientEncryption($encryptionOpts);
    
    // Check if data encryption key exists, create if not
    echo "Checking for data encryption key...\n";
    $keyFilter = ['keyAltNames' => 'hipaa_encryption_key'];
    $keyQuery = new MongoDB\Driver\Query($keyFilter);
    $keyResults = $manager->executeQuery($keyVaultNamespace, $keyQuery);
    $dataKeyExists = false;
    $dataKeyId = null;
    
    foreach ($keyResults as $keyDoc) {
        $dataKeyExists = true;
        $dataKeyId = $keyDoc->_id;
        break;
    }
    
    if (!$dataKeyExists) {
        echo "Data encryption key not found. Creating...\n";
        try {
            $dataKeyId = $clientEncryption->createDataKey('local', [
                'keyAltNames' => ['hipaa_encryption_key']
            ]);
            echo "Data encryption key created with ID: " . bin2hex($dataKeyId->getData()) . "\n";
        } catch (Exception $e) {
            echo "ERROR creating data key: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Found existing data encryption key: " . bin2hex($dataKeyId->getData()) . "\n";
    }
    
    // Check if patients collection exists
    echo "\nChecking patients collection...\n";
    $command = new MongoDB\Driver\Command(['listCollections' => 1, 'filter' => ['name' => 'patients']]);
    $result = $manager->executeCommand($mongoDb, $command);
    $cursor = $result->toArray();
    
    $patientsExists = false;
    if (count($cursor) > 0 && isset($cursor[0]->cursor) && count($cursor[0]->cursor->firstBatch) > 0) {
        $patientsExists = true;
    }
    
    if (!$patientsExists) {
        echo "Patients collection not found. Creating...\n";
        $command = new MongoDB\Driver\Command(['create' => 'patients']);
        $result = $manager->executeCommand($mongoDb, $command);
        echo "Patients collection created.\n";
    } else {
        echo "Patients collection exists.\n";
    }
    
    // Check if patients collection has data
    $query = new MongoDB\Driver\Query([]);
    $results = $manager->executeQuery("$mongoDb.patients", $query);
    $patientCount = count(iterator_to_array($results));
    
    echo "Found $patientCount patients in the collection.\n";
    
    // Encrypt and insert demo patients if none exist
    if ($patientCount === 0 && $dataKeyId) {
        echo "\nNo patients found. Creating demo patients...\n";
        
        // Demo patients
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
            ]
        ];
        
        // Encrypt sensitive fields
        foreach ($patients as &$patient) {
            try {
                // Deterministic encryption for searchable fields
                $patient['lastName'] = $clientEncryption->encrypt(
                    $patient['lastName'],
                    ['algorithm' => 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic', 'keyId' => $dataKeyId]
                );
                $patient['firstName'] = $clientEncryption->encrypt(
                    $patient['firstName'],
                    ['algorithm' => 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic', 'keyId' => $dataKeyId]
                );
                $patient['email'] = $clientEncryption->encrypt(
                    $patient['email'],
                    ['algorithm' => 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic', 'keyId' => $dataKeyId]
                );
                
                // Random encryption for sensitive data
                $patient['ssn'] = $clientEncryption->encrypt(
                    $patient['ssn'],
                    ['algorithm' => 'AEAD_AES_256_CBC_HMAC_SHA_512-Random', 'keyId' => $dataKeyId]
                );
                $patient['diagnosis'] = $clientEncryption->encrypt(
                    $patient['diagnosis'],
                    ['algorithm' => 'AEAD_AES_256_CBC_HMAC_SHA_512-Random', 'keyId' => $dataKeyId]
                );
                $patient['medications'] = $clientEncryption->encrypt(
                    $patient['medications'],
                    ['algorithm' => 'AEAD_AES_256_CBC_HMAC_SHA_512-Random', 'keyId' => $dataKeyId]
                );
                
                echo "Encrypted patient data for: " . $patient['_id'] . "\n";
            } catch (Exception $e) {
                echo "ERROR encrypting patient data: " . $e->getMessage() . "\n";
            }
        }
        
        // Insert patients
        foreach ($patients as $patient) {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert($patient);
            
            try {
                $result = $manager->executeBulkWrite("$mongoDb.patients", $bulk);
                echo "Inserted patient: " . $patient['_id'] . "\n";
            } catch (Exception $e) {
                echo "ERROR inserting patient: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nDebug operation complete.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}