<?php
/**
 * @fileoverview MongoDB Patient Retrieval API Endpoint
 *
 * This API endpoint retrieves individual patient records from MongoDB with decryption
 * for the SecureHealth HIPAA-compliant medical records system. It uses field-level
 * encryption to securely access and return patient data based on user roles.
 *
 * @api
 * @endpoint GET /api_mongo_patient.php?id={patientId}
 * @version 1.0.0
 * @since 2024
 * @author Michael Lynn https://github.com/mrlynn
 * @license MIT
 *
 * @features
 * - Retrieves encrypted patient data from MongoDB
 * - Decrypts sensitive fields based on user role
 * - Role-based access control
 * - Audit logging for compliance
 * - Comprehensive error handling
 * - ObjectId validation
 *
 * @encryption
 * - Uses MongoDBEncryptionService for field-level decryption
 * - Supports deterministic, range, and random encryption types
 * - Decrypts fields based on user role permissions
 * - Maintains data security while enabling access
 *
 * @parameters
 * - id: MongoDB ObjectId of the patient (required)
 *
 * @response
 * Returns decrypted patient data based on user role:
 * {
 *   "id": "68dbf20ae69980a1de028e22",
 *   "firstName": "John",
 *   "lastName": "Doe",
 *   "birthDate": "1980-05-15T00:00:00.000Z",
 *   "email": "john.doe@example.com",
 *   "phoneNumber": "555-123-4567",
 *   "ssn": "***-**-6789", // Masked based on role
 *   "diagnosis": [...], // Available based on role
 *   "medications": [...], // Available based on role
 *   // ... other fields based on user permissions
 * }
 *
 * @roleBasedAccess
 * - ROLE_DOCTOR: Full access to all patient data
 * - ROLE_NURSE: Limited access to medical information
 * - ROLE_RECEPTIONIST: Basic demographic information only
 * - Field visibility determined by user role
 *
 * @auditLogging
 * - Logs patient access events
 * - Records user ID, IP address, and action details
 * - Stores timestamp and entity information
 * - Enables compliance tracking
 *
 * @security
 * - Requires proper authentication in production
 * - Uses role-based access control
 * - Decrypts data based on user permissions
 * - Implements audit logging for compliance
 * - Validates ObjectId format
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

// Check if Patient class exists
if (!class_exists('App\Document\Patient')) {
    error_log("Patient class not found - this might be a Symfony autoloading issue");
}

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

// Get patient ID from the request
$patientId = $_GET['id'] ?? null;

if (!$patientId) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Patient ID is required']);
    exit;
}

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
    try {
        $encryptionService = new MongoDBEncryptionService($params, $logger);
        error_log("Encryption service created successfully");
    } catch (Exception $e) {
        error_log("Error creating encryption service: " . $e->getMessage());
        // Continue without encryption service for now
        $encryptionService = null;
    }

    // Create MongoDB client
    try {
        $client = new Client($mongoUri);
        error_log("MongoDB client created successfully");
    } catch (Exception $e) {
        error_log("Error creating MongoDB client: " . $e->getMessage());
        throw $e;
    }

    // Get user role from request (would normally come from authentication)
    // For now, we'll determine role based on session or default to doctor
    $userRole = 'ROLE_DOCTOR'; // Default to doctor role for demo
    
    // Try to get user role from session or headers
    if (isset($_SESSION['user_role'])) {
        $userRole = $_SESSION['user_role'];
    } elseif (isset($_SERVER['HTTP_X_USER_ROLE'])) {
        $userRole = $_SERVER['HTTP_X_USER_ROLE'];
    }
    
    // Validate role
    $validRoles = ['ROLE_DOCTOR', 'ROLE_NURSE', 'ROLE_RECEPTIONIST', 'ROLE_ADMIN', 'ROLE_PATIENT'];
    if (!in_array($userRole, $validRoles)) {
        $userRole = 'ROLE_DOCTOR'; // Fallback to doctor
    }
    
    // Log the role being used for debugging
    error_log("API using user role: " . $userRole);

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

    // Try to convert ID to ObjectId
    try {
        $objectId = new ObjectId($patientId);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Invalid patient ID format']);
        exit;
    }

    // Find patient by ID
    error_log("Looking for patient with ID: " . $patientId);
    $document = $patientsCollection->findOne(['_id' => $objectId]);
    error_log("Document found: " . ($document ? "yes" : "no"));

    if (!$document) {
        error_log("Patient not found in database");
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => 'Patient not found']);
        exit;
    }
    
    // Log document structure for debugging
    error_log("Document structure: " . json_encode(array_keys(iterator_to_array($document))));

    // Convert to Patient object
    error_log("Encryption service available: " . ($encryptionService ? "yes" : "no"));
    if ($encryptionService) {
        try {
            error_log("Calling Patient::fromDocument");
            $patient = Patient::fromDocument((array) $document, $encryptionService);
            error_log("Patient::fromDocument completed successfully");
        } catch (Exception $e) {
            error_log("Error creating Patient object: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Fallback: return raw document data
            error_log("Using fallback: returning raw document data");
            $patientData = [];
            foreach ($document as $key => $value) {
                if ($value instanceof ObjectId) {
                    $patientData[$key] = (string) $value;
                } elseif ($value instanceof UTCDateTime) {
                    $patientData[$key] = $value->toDateTime()->format('c');
                } elseif ($value instanceof \MongoDB\BSON\Binary) {
                    $patientData[$key] = '[Encrypted Field]';
                } else {
                    $patientData[$key] = $value;
                }
            }
            $patientData['id'] = $patientData['_id'] ?? $patientId;
            echo json_encode($patientData);
            exit;
        }
    } else {
        // No encryption service - return raw document data
        error_log("No encryption service available, returning raw document data");
        error_log("Document keys: " . json_encode(array_keys(iterator_to_array($document))));
        $patientData = [];
        foreach ($document as $key => $value) {
            if ($value instanceof ObjectId) {
                $patientData[$key] = (string) $value;
            } elseif ($value instanceof UTCDateTime) {
                $patientData[$key] = $value->toDateTime()->format('c');
            } elseif ($value instanceof \MongoDB\BSON\Binary) {
                $patientData[$key] = '[Encrypted Field]';
            } else {
                $patientData[$key] = $value;
            }
        }
        $patientData['id'] = $patientData['_id'] ?? $patientId;
        error_log("Returning patient data: " . json_encode($patientData));
        echo json_encode($patientData);
        exit;
    }

    // Log the access
    try {
        $auditLogService->logEvent(
            'read',
            'patient',
            (string)$patient->getId(),
            'api_user',
            ['action' => 'get_patient']
        );
    } catch (Exception $e) {
        error_log("Error logging audit event: " . $e->getMessage());
    }

    // Return patient data with role-based access control
    error_log("Final return section - patient object available: " . (isset($patient) ? "yes" : "no"));
    if (isset($patient)) {
        try {
            error_log("Calling patient->toArray with role: " . $userRole);
            $patientArray = $patient->toArray($userRole);
            error_log("Patient array generated successfully");
            echo json_encode($patientArray);
        } catch (Exception $e) {
            error_log("Error serializing patient data: " . $e->getMessage());
            // Fallback: return basic data
            echo json_encode([
                'id' => (string)$patient->getId(),
                'firstName' => '[Error loading data]',
                'lastName' => '[Error loading data]',
                'error' => 'Data serialization error'
            ]);
        }
    } else {
        error_log("No patient object available");
        echo json_encode(['error' => true, 'message' => 'Failed to process patient data']);
    }

} catch (Exception $e) {
    // Return error
    error_log("Final catch block - Exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
