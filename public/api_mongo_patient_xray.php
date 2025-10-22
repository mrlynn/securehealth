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

try {
    // Connection params
    $mongoUri = getenv('MONGODB_URI');
    if (!$mongoUri) {
        throw new Exception('MongoDB connection string missing. Set MONGODB_URI in the environment.');
    }
    $dbName = getenv('MONGODB_DB') ?: 'securehealth';

    $client = new Client($mongoUri);
    $collection = $client->selectDatabase($dbName)->selectCollection('patients');

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

    // Get decrypted data using the same approach as the existing API
    $decryptedData = null;
    try {
        // Initialize encryption service (same as existing API)
        $keyVaultNamespace = getenv('MONGODB_KEY_VAULT_NAMESPACE') ?: 'encryption.__keyVault';
        $keyFile = getenv('MONGODB_ENCRYPTION_KEY_PATH') ?: __DIR__ . '/../docker/encryption.key';
        
        // Create parameter bag (same as existing API)
        $params = new ParameterBag([
            'mongodb_url' => $mongoUri,  // Changed from mongodb_uri to mongodb_url
            'mongodb_uri' => $mongoUri,  // Keep both for compatibility
            'mongodb_db' => $dbName,
            'mongodb_key_vault_namespace' => $keyVaultNamespace,
            'mongodb_encryption_key_path' => $keyFile
        ]);
        
        $encryptionService = new MongoDBEncryptionService($params, $logger);
        
        // Convert raw document to Patient object (this decrypts the data)
        $patient = Patient::fromDocument((array) $rawDoc, $encryptionService);
        
        if ($patient) {
            // Convert to array for JSON response (same as existing API)
            $decryptedData = $patient->toArray('DOCTOR'); // Use DOCTOR role to see all fields
            
            // Convert ObjectId to string for JSON serialization
            if (isset($decryptedData['_id']) && $decryptedData['_id'] instanceof ObjectId) {
                $decryptedData['_id'] = (string) $decryptedData['_id'];
            }
            
            // Convert UTCDateTime objects to ISO strings
            foreach ($decryptedData as $key => $value) {
                if ($value instanceof UTCDateTime) {
                    $decryptedData[$key] = $value->toDateTime()->format('c');
                } elseif ($value instanceof \DateTime) {
                    $decryptedData[$key] = $value->format('c');
                }
            }
        }
    } catch (Exception $e) {
        // If decryption fails, we'll still return the encrypted data
        error_log("Decryption failed for patient {$patientId}: " . $e->getMessage());
        $decryptedData = [
            'error' => 'Decryption failed',
            'message' => $e->getMessage(),
            'patientId' => $patientId
        ];
    }

    // Return both views
    $response = [
        'encrypted' => $encryptedData,
        'decrypted' => $decryptedData,
        'metadata' => [
            'patientId' => $patientId,
            'timestamp' => date('c'),
            'encryptionStatus' => $decryptedData && !isset($decryptedData['error']) ? 'success' : 'failed'
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
