<?php
/**
 * @fileoverview MongoDB Patient Creation API Endpoint
 *
 * This API endpoint handles the creation of new patient records in the SecureHealth
 * HIPAA-compliant medical records system. It integrates with MongoDB using field-level
 * encryption to ensure sensitive patient data is protected at rest.
 *
 * @api
 * @endpoint POST /api_mongo_patient_create.php
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Creates new patient records in MongoDB
 * - Field-level encryption for sensitive data
 * - Audit logging for compliance
 * - Role-based access control
 * - CORS support for cross-origin requests
 * - Comprehensive error handling
 *
 * @encryption
 * - Uses MongoDBEncryptionService for field-level encryption
 * - Supports deterministic, range, and random encryption types
 * - Encrypts sensitive fields like SSN, diagnosis, medications
 * - Maintains searchability for non-sensitive fields
 *
 * @request
 * POST with JSON body containing patient data:
 * {
 *   "firstName": "John",
 *   "lastName": "Doe",
 *   "birthDate": "1980-05-15T00:00:00.000Z",
 *   "email": "john.doe@example.com",
 *   "phoneNumber": "555-123-4567",
 *   "ssn": "123-45-6789",
 *   "insuranceDetails": {...},
 *   "diagnosis": [...],
 *   "medications": [...],
 *   "notes": "Clinical notes"
 * }
 *
 * @response
 * {
 *   "success": true,
 *   "message": "Patient created successfully",
 *   "patient": {
 *     "id": "generated_object_id",
 *     "firstName": "John",
 *     "lastName": "Doe",
 *     // ... other patient fields based on user role
 *   }
 * }
 *
 * @auditLogging
 * - Logs patient creation events
 * - Records user ID, IP address, and action details
 * - Stores timestamp and entity information
 * - Enables compliance tracking
 *
 * @security
 * - Requires proper authentication in production
 * - Uses role-based access control
 * - Encrypts sensitive data at rest
 * - Implements audit logging for compliance
 * - Validates input data
 *
 * @dependencies
 * - MongoDB PHP Driver
 * - App\Document\Patient
 * - App\Service\MongoDBEncryptionService
 * - App\Service\AuditLogService
 * - Symfony\Component\DependencyInjection\ParameterBag\ParameterBag
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Document\Patient;
use App\Service\MongoDBEncryptionService;
use App\Service\AuditLogService;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Psr\Log\LoggerInterface;
use RuntimeException;

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Invalid JSON data']);
    exit;
}

// Create a simple logger
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

try {
    // Get MongoDB connection details from environment
    $mongoUri = getenv('MONGODB_URI');
    if (!$mongoUri) {
        throw new RuntimeException('MongoDB connection string missing. Set MONGODB_URI in the environment.');
    }
    $dbName = getenv('MONGODB_DB') ?: 'securehealth';
    $keyVaultNamespace = getenv('MONGODB_KEY_VAULT_NAMESPACE') ?: 'encryption.__keyVault';
    $keyFile = getenv('MONGODB_ENCRYPTION_KEY_PATH') ?: __DIR__ . '/../docker/encryption.key';

    // Create parameter bag
    $params = new ParameterBag([
        'mongodb_url' => $mongoUri,  // Changed from mongodb_uri to mongodb_url
        'mongodb_uri' => $mongoUri,  // Keep both for compatibility
        'mongodb_db' => $dbName,
        'mongodb_key_vault_namespace' => $keyVaultNamespace,
        'mongodb_encryption_key_path' => $keyFile
    ]);

    // Create encryption service
    $encryptionService = new MongoDBEncryptionService($params, $logger);

    // Create MongoDB client
    $client = new Client($mongoUri);

    // Get user role from request (would normally come from authentication)
    $userRole = 'ROLE_RECEPTIONIST'; // Default to receptionist role for patient creation

    // Basic implementation of audit log service
    $auditLogService = new class($client, $dbName) extends AuditLogService {
        private $client;
        private $dbName;
        
        public function __construct($client, $dbName) {
            $this->client = $client;
            $this->dbName = $dbName;
        }
        
        public function logEvent(string $action, string $entityType, $entityId, $userId = null, array $details = []): void {
            $collection = $this->client->selectCollection($this->dbName, 'audit_log');
            $collection->insertOne([
                'timestamp' => new UTCDateTime(),
                'action' => $action,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'userId' => $userId,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'details' => $details
            ]);
        }
    };

    // Get the patients collection
    $patientsCollection = $client->selectCollection($dbName, 'patients');

    // Create new patient
    $patient = Patient::fromArray($data, $encryptionService);
    
    // Convert to document for storage
    $document = $patient->toDocument($encryptionService);
    
    // Insert into MongoDB
    $result = $patientsCollection->insertOne($document);
    
    if (!$result->getInsertedId()) {
        throw new Exception('Failed to insert patient document');
    }
    
    // Set ID on patient object
    $patient->setId($result->getInsertedId());
    
    // Log the creation
    $auditLogService->logEvent(
        'create',
        'patient',
        (string)$patient->getId(),
        'api_user',
        ['action' => 'create_patient']
    );

    // Return success with patient data
    echo json_encode([
        'success' => true,
        'message' => 'Patient created successfully',
        'patient' => $patient->toArray($userRole)
    ]);

} catch (Exception $e) {
    // Return error
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
