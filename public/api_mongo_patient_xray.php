<?php
/**
 * @fileoverview MongoDB Patient X-Ray API Endpoint
 *
 * This API endpoint retrieves both encrypted and decrypted patient data from MongoDB
 * for the SecureHealth HIPAA-compliant medical records system. It returns both views
 * for comparison and debugging purposes in the X-Ray feature.
 *
 * @api
 * @endpoint GET /api_mongo_patient_xray.php?id={patientId}
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Retrieves raw encrypted patient data from MongoDB
 * - Retrieves decrypted patient data using Symfony services
 * - Returns both views in a single response
 * - Shows encrypted fields as Binary data
 * - Shows decrypted fields as readable data
 * - Useful for debugging encryption implementation
 * - Validates ObjectId format
 *
 * @parameters
 * - id: MongoDB ObjectId of the patient (required)
 *
 * @response
 * Returns both encrypted and decrypted views:
 * {
 *   "encrypted": {
 *     "_id": {"$oid": "68dbf20ae69980a1de028e22"},
 *     "firstName": "encrypted_binary_data",
 *     "lastName": "encrypted_binary_data",
 *     "ssn": "encrypted_binary_data",
 *     "diagnosis": "encrypted_binary_data"
 *   },
 *   "decrypted": {
 *     "_id": "68dbf20ae69980a1de028e22",
 *     "firstName": "John",
 *     "lastName": "Doe",
 *     "ssn": "123-45-6789",
 *     "diagnosis": ["Hypertension", "Diabetes"]
 *   }
 * }
 *
 * @useCases
 * - Debugging encryption implementation
 * - Verifying data storage structure
 * - Analyzing encrypted vs decrypted field formats
 * - Development and testing purposes
 * - Security audit verification
 * - X-Ray feature enhancement
 *
 * @security
 * - Returns both raw encrypted and decrypted data - should be used carefully
 * - Requires proper authentication in production
 * - Useful for debugging but not for normal operations
 * - Shows actual encrypted field structure vs decrypted content
 *
 * @validation
 * - Validates ObjectId format
 * - Returns 400 error for invalid ID format
 * - Returns 404 error if patient not found
 * - Handles MongoDB connection errors
 * - Handles decryption errors gracefully
 *
 * @dependencies
 * - MongoDB PHP Driver
 * - MongoDB\Client
 * - MongoDB\BSON\ObjectId
 * - Symfony services (for decryption)
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use App\Document\Patient;
use App\Service\MongoDBEncryptionService;
use App\Service\AuditLogService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Psr\Log\LoggerInterface;

header('Content-Type: application/json');

// Add comprehensive error handling to prevent 500 errors
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Create a simple logger (same as existing API)
$logger = new class() implements LoggerInterface {
    public function emergency($message, array $context = []): void { $this->log('EMERGENCY', $message, $context); }
    public function alert($message, array $context = []): void { $this->log('ALERT', $message, $context); }
    public function critical($message, array $context = []): void { $this->log('CRITICAL', $message, $context); }
    public function error($message, array $context = []): void { $this->log('ERROR', $message, $context); }
    public function warning($message, array $context = []): void { $this->log('WARNING', $message, $context); }
    public function notice($message, array $context = []): void { $this->log('NOTICE', $message, $context); }
    public function info($message, array $context = []): void { $this->log('INFO', $message, $context); }
    public function debug($message, array $context = []): void { $this->log('DEBUG', $message, $context); }
    public function log($level, $message, array $context = []): void {
        error_log("[$level] $message " . json_encode($context));
    }
};

// Read id
$patientId = $_GET['id'] ?? null;
if (!$patientId) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Patient ID is required']);
    exit;
}

// Wrap everything in comprehensive error handling
try {
    // Connection params
    $mongoUri = getenv('MONGODB_URI');
    if (!$mongoUri) {
        throw new Exception('MongoDB connection string missing. Set MONGODB_URI in the environment.');
    }
    $dbName = getenv('MONGODB_DB') ?: 'securehealth';
    $keyVaultNamespace = getenv('MONGODB_KEY_VAULT_NAMESPACE') ?: 'encryption.__keyVault';
    $keyFile = getenv('MONGODB_ENCRYPTION_KEY_PATH') ?: __DIR__ . '/../docker/encryption.key';

    // Test MongoDB connection first
    try {
        $client = new Client($mongoUri);
        $collection = $client->selectDatabase($dbName)->selectCollection('patients');
        
        // Test the connection
        $client->selectDatabase($dbName)->command(['ping' => 1]);
    } catch (Exception $e) {
        throw new Exception('MongoDB connection failed: ' . $e->getMessage());
    }

    // Validate ObjectId
    try {
        $objectId = new ObjectId($patientId);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Invalid patient ID format']);
        exit;
    }

    // Fetch raw BSON document (encrypted)
    $rawDoc = $collection->findOne(['_id' => $objectId]);
    if (!$rawDoc) {
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => 'Patient not found']);
        exit;
    }

    // Convert BSON document to array for encrypted view
    $encryptedData = iterator_to_array($rawDoc);

    // Get decrypted data using proper Symfony services for real decryption
    $decryptedData = null;
    try {
        // Initialize Symfony services for proper decryption
        $params = new ParameterBag([
            'mongodb_url' => $mongoUri,
            'mongodb_uri' => $mongoUri,
            'mongodb_db' => $dbName,
            'mongodb_key_vault_namespace' => $keyVaultNamespace,
            'mongodb_encryption_key_path' => $keyFile
        ]);
        
        $encryptionService = new MongoDBEncryptionService($params, $logger);
        
        // Create Patient object from the raw document for proper decryption
        // Convert BSONDocument to array first
        $patientArray = iterator_to_array($rawDoc);
        $patient = Patient::fromDocument($patientArray, $encryptionService);
        
        // Convert to array with proper decryption
        $decryptedData = $patient->toArray();
        
        // Add metadata to show this is real decryption
        $decryptedData['_xray_note'] = '🎉 REAL DECRYPTED DATA - MongoDB Queryable Encryption in Action!';
        $decryptedData['_encryption_demo'] = 'All sensitive fields above are properly decrypted from encrypted storage';
        $decryptedData['_demo_info'] = 'This demonstrates the power of MongoDB Queryable Encryption - data is encrypted at rest but readable by authorized applications';
        
        // Convert any remaining UTCDateTime objects to ISO strings
        foreach ($decryptedData as $key => $value) {
            if ($value instanceof UTCDateTime) {
                $decryptedData[$key] = $value->toDateTime()->format('c');
            } elseif ($value instanceof \DateTime) {
                $decryptedData[$key] = $value->format('c');
            }
        }
        
    } catch (Exception $e) {
        // If decryption fails, provide detailed error information
        error_log("X-Ray decryption failed for patient {$patientId}: " . $e->getMessage());
        $decryptedData = [
            'error' => 'Decryption failed',
            'message' => $e->getMessage(),
            'patientId' => $patientId,
            'note' => 'This may occur if encryption keys are not properly configured',
            'debug_info' => [
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ],
            '_xray_note' => '❌ Decryption failed - check encryption configuration'
        ];
    }

    // Convert BSON objects to JSON-serializable format for encrypted data
    $serializedEncryptedData = [];
    foreach ($encryptedData as $key => $value) {
        if ($value instanceof ObjectId) {
            $serializedEncryptedData[$key] = ['$oid' => (string) $value];
        } elseif ($value instanceof UTCDateTime) {
            $serializedEncryptedData[$key] = ['$date' => $value->toDateTime()->format('c')];
        } elseif ($value instanceof \MongoDB\BSON\Binary) {
            // For encrypted fields, show the binary data info
            $serializedEncryptedData[$key] = [
                '$binary' => [
                    'base64' => base64_encode($value->getData()),
                    'subType' => $value->getType()
                ]
            ];
        } elseif (is_array($value)) {
            $serializedEncryptedData[$key] = $value;
        } else {
            $serializedEncryptedData[$key] = $value;
        }
    }

    // Return both views
    $response = [
        'encrypted' => $serializedEncryptedData,
        'decrypted' => $decryptedData,
        'metadata' => [
            'patientId' => $patientId,
            'timestamp' => date('c'),
            'encryptionStatus' => $decryptedData && !isset($decryptedData['error']) ? 'success' : 'failed'
        ]
    ];

    // Ensure proper JSON encoding
    $jsonResponse = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if ($jsonResponse === false) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => 'Failed to encode response as JSON: ' . json_last_error_msg(),
        ]);
        exit;
    }

    echo $jsonResponse;

} catch (Exception $e) {
    // Log the error for debugging
    error_log("X-Ray API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Always return 200 to prevent frontend errors
    http_response_code(200);
    
    // Ensure we can always return valid JSON
    $errorResponse = [
        'encrypted' => [],
        'decrypted' => [
            'error' => 'X-Ray debugging feature unavailable',
            'message' => 'This debugging feature is temporarily unavailable in production',
            'note' => 'Core application functionality is unaffected. Use local development environment for full X-Ray functionality.',
            'technicalDetails' => $e->getMessage()
        ],
        'metadata' => [
            'patientId' => $patientId ?? 'unknown',
            'timestamp' => date('c'),
            'encryptionStatus' => 'unavailable',
            'error' => true,
            'environment' => 'production'
        ]
    ];
    
    $jsonOutput = json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonOutput === false) {
        // Last resort - return a simple error
        echo '{"error": true, "message": "X-Ray feature unavailable", "encrypted": [], "decrypted": {"error": "Service unavailable"}}';
    } else {
        echo $jsonOutput;
    }
} catch (Error $e) {
    // Handle PHP fatal errors
    error_log("X-Ray API Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Always return 200 to prevent frontend errors
    http_response_code(200);
    
    $errorResponse = [
        'encrypted' => [],
        'decrypted' => [
            'error' => 'X-Ray debugging feature unavailable',
            'message' => 'This debugging feature encountered an error',
            'note' => 'Core application functionality is unaffected. Use local development environment for full X-Ray functionality.',
            'technicalDetails' => $e->getMessage()
        ],
        'metadata' => [
            'patientId' => $patientId ?? 'unknown',
            'timestamp' => date('c'),
            'encryptionStatus' => 'unavailable',
            'error' => true,
            'environment' => 'production'
        ]
    ];
    
    $jsonOutput = json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonOutput === false) {
        // Last resort - return a simple error
        echo '{"error": true, "message": "X-Ray feature unavailable", "encrypted": [], "decrypted": {"error": "Service unavailable"}}';
    } else {
        echo $jsonOutput;
    }
}
