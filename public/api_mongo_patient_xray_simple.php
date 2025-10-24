<?php
/**
 * Simplified X-Ray API for Production
 * This version is designed to never return a 500 error
 */

header('Content-Type: application/json');

// Always return 200 status to prevent frontend errors
http_response_code(200);

// Get patient ID
$patientId = $_GET['id'] ?? null;

if (!$patientId) {
    echo json_encode([
        'encrypted' => [],
        'decrypted' => [
            'error' => 'Patient ID required',
            'message' => 'Please provide a valid patient ID'
        ],
        'metadata' => [
            'patientId' => 'unknown',
            'timestamp' => date('c'),
            'encryptionStatus' => 'error'
        ]
    ]);
    exit;
}

// Simple error response function
function returnError($message, $patientId = null) {
    echo json_encode([
        'encrypted' => [],
        'decrypted' => [
            'error' => 'X-Ray feature unavailable',
            'message' => $message,
            'note' => 'This is a debugging feature that may not be available in production'
        ],
        'metadata' => [
            'patientId' => $patientId ?? 'unknown',
            'timestamp' => date('c'),
            'encryptionStatus' => 'unavailable'
        ]
    ]);
    exit;
}

try {
    // Check if MongoDB extension is available
    if (!class_exists('MongoDB\Client')) {
        returnError('MongoDB extension not available');
    }
    
    // Get environment variables
    $mongoUri = getenv('MONGODB_URI');
    if (!$mongoUri) {
        returnError('MongoDB connection not configured');
    }
    
    $dbName = getenv('MONGODB_DB') ?: 'securehealth';
    
    // Create MongoDB client
    $client = new MongoDB\Client($mongoUri);
    $collection = $client->selectDatabase($dbName)->selectCollection('patients');
    
    // Validate ObjectId
    try {
        $objectId = new MongoDB\BSON\ObjectId($patientId);
    } catch (Exception $e) {
        returnError('Invalid patient ID format', $patientId);
    }
    
    // Find the patient
    $patient = $collection->findOne(['_id' => $objectId]);
    if (!$patient) {
        returnError('Patient not found', $patientId);
    }
    
    // Convert to array for JSON serialization
    $encryptedData = [];
    foreach ($patient as $key => $value) {
        if ($value instanceof MongoDB\BSON\ObjectId) {
            $encryptedData[$key] = ['$oid' => (string) $value];
        } elseif ($value instanceof MongoDB\BSON\UTCDateTime) {
            $encryptedData[$key] = ['$date' => $value->toDateTime()->format('c')];
        } elseif ($value instanceof MongoDB\BSON\Binary) {
            $encryptedData[$key] = [
                '$binary' => [
                    'base64' => base64_encode($value->getData()),
                    'subType' => $value->getType()
                ]
            ];
        } else {
            $encryptedData[$key] = $value;
        }
    }
    
    // Create a simple decrypted view
    $decryptedData = [
        '_id' => (string) $objectId,
        'firstName' => '[Encrypted Field]',
        'lastName' => '[Encrypted Field]',
        'dateOfBirth' => '[Encrypted Field]',
        'ssn' => '[Encrypted Field]',
        'phoneNumber' => '[Encrypted Field]',
        'email' => '[Encrypted Field]',
        'address' => [
            'street' => '[Encrypted Field]',
            'city' => '[Encrypted Field]',
            'state' => '[Encrypted Field]',
            'zipCode' => '[Encrypted Field]'
        ],
        'diagnosis' => '[Encrypted Field]',
        'medications' => '[Encrypted Field]',
        'notes' => '[Encrypted Field]',
        'createdAt' => isset($patient['createdAt']) ? $patient['createdAt']->toDateTime()->format('c') : null,
        'updatedAt' => isset($patient['updatedAt']) ? $patient['updatedAt']->toDateTime()->format('c') : null,
        'note' => 'This shows the encrypted data structure. Full decryption requires local development environment.'
    ];
    
    // Return success response
    echo json_encode([
        'encrypted' => $encryptedData,
        'decrypted' => $decryptedData,
        'metadata' => [
            'patientId' => $patientId,
            'timestamp' => date('c'),
            'encryptionStatus' => 'success'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Log the error
    error_log("X-Ray API Error: " . $e->getMessage());
    returnError('X-Ray feature encountered an error: ' . $e->getMessage(), $patientId);
} catch (Error $e) {
    // Log fatal errors
    error_log("X-Ray API Fatal Error: " . $e->getMessage());
    returnError('X-Ray feature encountered a fatal error', $patientId);
}

// Final safety net
if (!headers_sent()) {
    returnError('X-Ray feature unavailable');
}
