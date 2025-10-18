<?php

namespace App\Controller\Api;

use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/api/debug')]
class DebugController extends AbstractController
{
    private DocumentManager $documentManager;
    private MongoDBEncryptionService $encryptionService;
    private ParameterBagInterface $params;

    public function __construct(
        DocumentManager $documentManager,
        MongoDBEncryptionService $encryptionService,
        ParameterBagInterface $params
    ) {
        $this->documentManager = $documentManager;
        $this->encryptionService = $encryptionService;
        $this->params = $params;
    }

    /**
     * Comprehensive Railway deployment debug endpoint
     */
    #[Route('/railway', name: 'debug_railway', methods: ['GET'])]
    public function railwayDebug(): JsonResponse
    {
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => [
                'APP_ENV' => $_SERVER['APP_ENV'] ?? 'not_set',
                'SYMFONY_ENV' => $_SERVER['SYMFONY_ENV'] ?? 'not_set',
                'RAILWAY_ENVIRONMENT' => $_SERVER['RAILWAY_ENVIRONMENT'] ?? 'not_set',
                'PORT' => $_SERVER['PORT'] ?? 'not_set',
            ],
            'mongodb' => [
                'MONGODB_URI_set' => !empty($_SERVER['MONGODB_URI'] ?? ''),
                'MONGODB_URL_set' => !empty($_SERVER['MONGODB_URL'] ?? ''),
                'MONGODB_DB' => $_SERVER['MONGODB_DB'] ?? 'not_set',
                'MONGODB_DISABLED' => $_SERVER['MONGODB_DISABLED'] ?? 'not_set',
                'MONGODB_KEY_VAULT_NAMESPACE' => $_SERVER['MONGODB_KEY_VAULT_NAMESPACE'] ?? 'not_set',
            ],
            'security' => [
                'APP_SECRET_set' => !empty($_SERVER['APP_SECRET'] ?? ''),
                'JWT_SECRET_KEY_set' => !empty($_SERVER['JWT_SECRET_KEY'] ?? ''),
                'JWT_PASSPHRASE_set' => !empty($_SERVER['JWT_PASSPHRASE'] ?? ''),
            ],
            'encryption' => [
                'MONGODB_ENCRYPTION_KEY_PATH' => $_SERVER['MONGODB_ENCRYPTION_KEY_PATH'] ?? 'not_set',
                'key_file_exists' => false,
                'encryption_service_status' => 'unknown',
            ],
            'database' => [
                'connection_status' => 'unknown',
                'patient_count' => 'unknown',
                'user_count' => 'unknown',
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'session_save_path' => session_save_path(),
                'session_status' => session_status(),
            ]
        ];

        // Test MongoDB connection
        try {
            $this->documentManager->getConnection()->getDatabase();
            $debug['database']['connection_status'] = 'connected';
            
            // Test patient collection
            $patientRepo = $this->documentManager->getRepository(\App\Document\Patient::class);
            $debug['database']['patient_count'] = $patientRepo->count([]);
            
            // Test user collection
            $userRepo = $this->documentManager->getRepository(\App\Document\User::class);
            $debug['database']['user_count'] = $userRepo->count([]);
            
        } catch (\Exception $e) {
            $debug['database']['connection_status'] = 'failed';
            $debug['database']['error'] = $e->getMessage();
        }

        // Test encryption service
        try {
            $this->encryptionService->isEnabled();
            $debug['encryption']['encryption_service_status'] = 'enabled';
        } catch (\Exception $e) {
            $debug['encryption']['encryption_service_status'] = 'disabled';
            $debug['encryption']['error'] = $e->getMessage();
        }

        // Check encryption key file
        $keyPath = $_SERVER['MONGODB_ENCRYPTION_KEY_PATH'] ?? '/app/docker/encryption.key';
        $debug['encryption']['key_file_exists'] = file_exists($keyPath);
        if (file_exists($keyPath)) {
            $debug['encryption']['key_file_size'] = filesize($keyPath);
            $debug['encryption']['key_file_readable'] = is_readable($keyPath);
        }

        return $this->json($debug);
    }

    /**
     * Test patient API access
     */
    #[Route('/patient-access', name: 'debug_patient_access', methods: ['GET'])]
    public function testPatientAccess(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'No authenticated user',
                'debug' => [
                    'session_id' => session_id(),
                    'session_status' => session_status(),
                    'session_data' => $_SESSION ?? [],
                ]
            ]);
        }

        try {
            $patientRepo = $this->documentManager->getRepository(\App\Document\Patient::class);
            $patients = $patientRepo->findBy([], null, 5);
            
            $patientData = [];
            foreach ($patients as $patient) {
                $patientData[] = [
                    'id' => (string)$patient->getId(),
                    'firstName' => $patient->getFirstName(),
                    'lastName' => $patient->getLastName(),
                    'email' => $patient->getEmail(),
                ];
            }

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->getUserIdentifier(),
                    'roles' => $user->getRoles(),
                ],
                'patients' => $patientData,
                'total_patients' => count($patientData),
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error accessing patient data',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Test MongoDB connection specifically
     */
    #[Route('/mongodb', name: 'debug_mongodb', methods: ['GET'])]
    public function testMongoDB(): JsonResponse
    {
        try {
            $connection = $this->documentManager->getConnection();
            $database = $connection->getDatabase();
            
            // Test basic operations
            $collections = $database->listCollections();
            $collectionNames = [];
            foreach ($collections as $collection) {
                $collectionNames[] = $collection->getName();
            }

            // Test patient collection specifically
            $patientCollection = $database->selectCollection('Patient');
            $patientCount = $patientCollection->countDocuments([]);

            return $this->json([
                'success' => true,
                'database_name' => $database->getDatabaseName(),
                'collections' => $collectionNames,
                'patient_count' => $patientCount,
                'connection_string' => $_SERVER['MONGODB_URI'] ?? 'not_set',
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'MongoDB connection failed',
                'error' => $e->getMessage(),
                'connection_string' => $_SERVER['MONGODB_URI'] ?? 'not_set',
            ]);
        }
    }

    /**
     * Test authentication system
     */
    #[Route('/auth', name: 'debug_auth', methods: ['GET'])]
    public function testAuth(): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'authenticated' => $user !== null,
            'user' => $user ? [
                'id' => $user->getUserIdentifier(),
                'username' => method_exists($user, 'getUsername') ? $user->getUsername() : 'N/A',
                'email' => method_exists($user, 'getEmail') ? $user->getEmail() : 'N/A',
                'roles' => $user->getRoles(),
            ] : null,
            'session' => [
                'id' => session_id(),
                'status' => session_status(),
                'data' => $_SESSION ?? [],
            ],
            'security_context' => [
                'is_granted_admin' => $this->isGranted('ROLE_ADMIN'),
                'is_granted_doctor' => $this->isGranted('ROLE_DOCTOR'),
                'is_granted_nurse' => $this->isGranted('ROLE_NURSE'),
                'is_granted_patient' => $this->isGranted('ROLE_PATIENT'),
            ]
        ]);
    }
}