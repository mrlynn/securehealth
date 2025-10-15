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

    // Get the database
    $db = $client->selectDatabase($dbName);

    // Get or create patients collection
    $patientsCollection = $db->selectCollection('patients');

    // Check if we need to create sample data
    $patientCount = $patientsCollection->countDocuments([]);
    if ($patientCount === 0) {
        echo json_encode(['message' => 'Creating sample patient data...']);

        // Create sample patients
        $samplePatients = [
            [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'birthDate' => new UTCDateTime(new DateTime('1980-05-15')),
                'email' => 'john.doe@example.com',
                'phoneNumber' => '555-123-4567',
                'ssn' => '123-45-6789',
                'insuranceDetails' => [
                    'provider' => 'Blue Cross',
                    'policyNumber' => 'BC98765432',
                    'groupNumber' => '12345'
                ],
                'diagnosis' => ['Hypertension', 'Type 2 Diabetes'],
                'medications' => ['Metformin 500mg', 'Lisinopril 10mg'],
                'notes' => 'Patient is managing conditions well with current medications.',
                'createdAt' => new UTCDateTime()
            ],
            [
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'birthDate' => new UTCDateTime(new DateTime('1985-10-22')),
                'email' => 'jane.smith@example.com',
                'phoneNumber' => '555-987-6543',
                'ssn' => '234-56-7890',
                'insuranceDetails' => [
                    'provider' => 'Aetna',
                    'policyNumber' => 'AE12345678',
                    'groupNumber' => '67890'
                ],
                'diagnosis' => ['Asthma'],
                'medications' => ['Albuterol inhaler'],
                'notes' => 'Patient experiences occasional asthma attacks, especially during pollen season.',
                'createdAt' => new UTCDateTime()
            ],
            [
                'firstName' => 'Robert',
                'lastName' => 'Johnson',
                'birthDate' => new UTCDateTime(new DateTime('1975-03-08')),
                'email' => 'robert.j@example.com',
                'phoneNumber' => '555-567-8901',
                'ssn' => '345-67-8901',
                'insuranceDetails' => [
                    'provider' => 'Medicare',
                    'policyNumber' => 'MC55566777',
                    'groupNumber' => '54321'
                ],
                'diagnosis' => ['Arthritis', 'GERD'],
                'medications' => ['Omeprazole 20mg', 'Ibuprofen 400mg'],
                'notes' => 'Patient reports increasing joint pain in knees and shoulders.',
                'createdAt' => new UTCDateTime()
            ]
        ];

        foreach ($samplePatients as $patientData) {
            // Create Patient object
            $patient = new Patient();
            $patient->setFirstName($patientData['firstName']);
            $patient->setLastName($patientData['lastName']);
            $patient->setBirthDate($patientData['birthDate']);
            $patient->setEmail($patientData['email']);
            $patient->setPhoneNumber($patientData['phoneNumber']);
            $patient->setSsn($patientData['ssn']);
            $patient->setInsuranceDetails($patientData['insuranceDetails']);
            $patient->setDiagnosis($patientData['diagnosis']);
            $patient->setMedications($patientData['medications']);
            $patient->setNotes($patientData['notes']);
            
            // Convert to encrypted document
            $document = $patient->toDocument($encryptionService);
            
            // Insert into collection
            $result = $patientsCollection->insertOne($document);
            
            // Set ID on patient object
            if ($result->getInsertedId()) {
                $patient->setId($result->getInsertedId());
            }
            
            // Log the creation
            $auditLogService->logEvent(
                'create',
                'patient',
                (string)$patient->getId(),
                'system',
                ['action' => 'create_sample_data']
            );
        }
    }

    // Fetch all patients
    $cursor = $patientsCollection->find([], ['sort' => ['lastName' => 1]]);

    // Convert to Patient objects and then to arrays for output
    $patients = [];
    foreach ($cursor as $document) {
        $patient = Patient::fromDocument((array) $document, $encryptionService);
        $patients[] = $patient->toArray($userRole);
    }

    // Log the read access
    $auditLogService->logEvent(
        'read',
        'patient',
        'all',
        'api_user',
        ['action' => 'list_patients', 'count' => count($patients)]
    );

    // Return patients as JSON
    echo json_encode($patients);

} catch (Exception $e) {
    // Return error
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
