<?php
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

header('Content-Type: application/json');

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
    $encryptionService = new MongoDBEncryptionService($params, $logger);

    // Create MongoDB client
    $client = new Client($mongoUri);

    // Get user role from request (would normally come from authentication)
    $userRole = 'ROLE_DOCTOR'; // Default to doctor role for demo

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
    $document = $patientsCollection->findOne(['_id' => $objectId]);

    if (!$document) {
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => 'Patient not found']);
        exit;
    }

    // Convert to Patient object
    $patient = Patient::fromDocument((array) $document, $encryptionService);

    // Log the access
    $auditLogService->logEvent(
        'read',
        'patient',
        (string)$patient->getId(),
        'api_user',
        ['action' => 'get_patient']
    );

    // Return patient data with role-based access control
    echo json_encode($patient->toArray($userRole));

} catch (Exception $e) {
    // Return error
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
