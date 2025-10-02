<?php

namespace App\Service;

use App\Document\Patient;
use App\Repository\PatientRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service to handle integration with external healthcare systems
 */
class ExternalSystemIntegrationService
{
    private MongoDBEncryptionService $encryptionService;
    private PatientRepository $patientRepository;
    private AuditLogService $auditLogService;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private Security $security;
    private ValidatorInterface $validator;
    private array $systemConfigurations;

    public function __construct(
        MongoDBEncryptionService $encryptionService,
        PatientRepository $patientRepository,
        AuditLogService $auditLogService,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        Security $security,
        ValidatorInterface $validator,
        array $systemConfigurations = []
    ) {
        $this->encryptionService = $encryptionService;
        $this->patientRepository = $patientRepository;
        $this->auditLogService = $auditLogService;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->security = $security;
        $this->validator = $validator;
        $this->systemConfigurations = $systemConfigurations;
    }

    /**
     * Add a new external system configuration
     * 
     * @param string $systemId Unique identifier for the external system
     * @param array $config Configuration for the external system
     */
    public function addSystemConfiguration(string $systemId, array $config): void
    {
        $this->systemConfigurations[$systemId] = $config;
    }

    /**
     * Import patient data from an external system
     * 
     * @param string $systemId Identifier for the external system
     * @param string $externalPatientId External system's patient ID
     * @return Patient|null The imported patient or null on failure
     */
    public function importPatient(string $systemId, string $externalPatientId): ?Patient
    {
        if (!isset($this->systemConfigurations[$systemId])) {
            $this->logger->error("Unknown external system: {$systemId}");
            return null;
        }

        $systemConfig = $this->systemConfigurations[$systemId];
        $externalData = $this->fetchPatientFromExternalSystem($systemId, $externalPatientId);

        if (!$externalData) {
            return null;
        }

        try {
            // Create or update patient
            $patient = $this->createPatientFromExternalData($externalData, $systemId, $externalPatientId);
            
            // Validate the patient
            $errors = $this->validator->validate($patient);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                
                $this->logger->error("Validation failed for patient import", [
                    'systemId' => $systemId,
                    'externalPatientId' => $externalPatientId,
                    'errors' => $errorMessages
                ]);
                
                return null;
            }

            // Save the patient
            $this->patientRepository->save($patient);
            
            // Log the import
            $this->auditLogService->log(
                $this->security->getUser(),
                'PATIENT_IMPORT',
                [
                    'description' => "Imported patient from external system",
                    'systemId' => $systemId,
                    'externalPatientId' => $externalPatientId,
                    'entityId' => (string)$patient->getId(),
                    'entityType' => 'Patient',
                ]
            );

            return $patient;

        } catch (\Exception $e) {
            $this->logger->error("Error importing patient from external system", [
                'systemId' => $systemId,
                'externalPatientId' => $externalPatientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Fetch patient data from an external system
     * 
     * @param string $systemId The external system identifier
     * @param string $externalPatientId The patient ID in the external system
     * @return array|null The patient data or null on failure
     */
    private function fetchPatientFromExternalSystem(string $systemId, string $externalPatientId): ?array
    {
        if (!isset($this->systemConfigurations[$systemId])) {
            $this->logger->error("Unknown external system: {$systemId}");
            return null;
        }

        $config = $this->systemConfigurations[$systemId];
        
        try {
            // Different implementations based on the system type
            if ($config['type'] === 'api') {
                return $this->fetchPatientFromApi($config, $externalPatientId);
            } elseif ($config['type'] === 'file') {
                return $this->fetchPatientFromFile($config, $externalPatientId);
            } else {
                $this->logger->error("Unsupported external system type: {$config['type']}");
                return null;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to fetch patient data from external system", [
                'systemId' => $systemId,
                'externalPatientId' => $externalPatientId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Fetch patient data from an external API
     */
    private function fetchPatientFromApi(array $config, string $externalPatientId): ?array
    {
        $url = sprintf($config['url'], $externalPatientId);
        
        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'timeout' => $config['timeout'] ?? 30,
        ];

        // Add authentication if configured
        if (isset($config['auth'])) {
            if ($config['auth']['type'] === 'basic') {
                $options['auth_basic'] = [$config['auth']['username'], $config['auth']['password']];
            } elseif ($config['auth']['type'] === 'bearer') {
                $options['headers']['Authorization'] = "Bearer {$config['auth']['token']}";
            }
        }

        $response = $this->httpClient->request('GET', $url, $options);
        
        if ($response->getStatusCode() === Response::HTTP_OK) {
            $data = $response->toArray();
            
            // Log the successful fetch without sensitive data
            $this->logger->info("Successfully fetched patient data from external API", [
                'externalPatientId' => $externalPatientId,
                'dataSize' => strlen($response->getContent()),
            ]);
            
            return $data;
        }
        
        $this->logger->error("Failed to fetch patient from external API", [
            'externalPatientId' => $externalPatientId,
            'statusCode' => $response->getStatusCode(),
            'error' => $response->getContent(false),
        ]);
        
        return null;
    }

    /**
     * Fetch patient data from a file source
     */
    private function fetchPatientFromFile(array $config, string $externalPatientId): ?array
    {
        $filePath = sprintf($config['path'], $externalPatientId);
        
        if (!file_exists($filePath)) {
            $this->logger->error("Patient file not found", [
                'externalPatientId' => $externalPatientId,
                'filePath' => $filePath
            ]);
            return null;
        }
        
        $fileContents = file_get_contents($filePath);
        $data = json_decode($fileContents, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Invalid JSON in patient file", [
                'externalPatientId' => $externalPatientId,
                'filePath' => $filePath,
                'jsonError' => json_last_error_msg()
            ]);
            return null;
        }
        
        return $data;
    }

    /**
     * Create a Patient object from external system data
     */
    private function createPatientFromExternalData(array $data, string $systemId, string $externalPatientId): Patient
    {
        $config = $this->systemConfigurations[$systemId];
        $fieldMapping = $config['fieldMapping'] ?? [];
        
        // Check if this patient already exists by external ID
        $existingPatient = $this->patientRepository->findByCriteria([
            'metadata.externalSystem' => $systemId,
            'metadata.externalPatientId' => $externalPatientId
        ]);
        
        $patient = count($existingPatient) > 0 ? $existingPatient[0] : new Patient();
        
        // Map fields from external system to our patient model
        foreach ($fieldMapping as $ourField => $externalField) {
            $value = $this->getNestedValue($data, $externalField);
            
            if ($value !== null) {
                switch ($ourField) {
                    case 'firstName':
                        $patient->setFirstName($value);
                        break;
                    case 'lastName':
                        $patient->setLastName($value);
                        break;
                    case 'email':
                        $patient->setEmail($value);
                        break;
                    case 'phoneNumber':
                        $patient->setPhoneNumber($value);
                        break;
                    case 'birthDate':
                        if (is_string($value)) {
                            $dateTime = new \DateTime($value);
                            $patient->setBirthDate(new UTCDateTime($dateTime));
                        }
                        break;
                    case 'ssn':
                        $patient->setSsn($value);
                        break;
                    case 'diagnosis':
                        if (is_array($value)) {
                            $patient->setDiagnosis($value);
                        }
                        break;
                    case 'medications':
                        if (is_array($value)) {
                            $patient->setMedications($value);
                        }
                        break;
                    case 'insuranceDetails':
                        if (is_array($value)) {
                            $patient->setInsuranceDetails($value);
                        }
                        break;
                    case 'notes':
                        $patient->setNotes($value);
                        break;
                    case 'primaryDoctorId':
                        if (is_string($value) && !empty($value)) {
                            try {
                                $patient->setPrimaryDoctorId(new ObjectId($value));
                            } catch (\Exception $e) {
                                $this->logger->warning("Invalid primary doctor ID format", [
                                    'value' => $value,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        break;
                }
            }
        }
        
        // Store metadata about the external system
        $metadata = $patient->getMetadata() ?? [];
        $metadata['externalSystem'] = $systemId;
        $metadata['externalPatientId'] = $externalPatientId;
        $metadata['lastImported'] = (new \DateTime())->format('Y-m-d H:i:s');
        $patient->setMetadata($metadata);
        
        // Update timestamp
        $patient->setUpdatedAt(new UTCDateTime());
        
        return $patient;
    }

    /**
     * Get a nested value from an array using dot notation
     * Example: getNestedValue($data, 'patient.demographics.firstName')
     */
    private function getNestedValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }

    /**
     * Export patient data to an external system
     * 
     * @param Patient $patient The patient to export
     * @param string $systemId Identifier for the external system
     * @return bool Success or failure
     */
    public function exportPatient(Patient $patient, string $systemId): bool
    {
        if (!isset($this->systemConfigurations[$systemId])) {
            $this->logger->error("Unknown external system: {$systemId}");
            return false;
        }

        $systemConfig = $this->systemConfigurations[$systemId];
        
        try {
            // Convert patient to array with full access
            $patientData = $patient->toArray('ROLE_DOCTOR'); 
            
            // Map our fields to external system fields
            $exportData = [];
            $reverseFieldMapping = array_flip($systemConfig['fieldMapping'] ?? []);
            
            foreach ($patientData as $ourField => $value) {
                if (isset($reverseFieldMapping[$ourField])) {
                    $externalField = $reverseFieldMapping[$ourField];
                    $this->setNestedValue($exportData, $externalField, $value);
                }
            }
            
            // Add metadata
            $metadata = $patient->getMetadata() ?? [];
            $exportData['metadata'] = [
                'exportedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
                'sourceSystem' => 'SecureHealth',
                'patientId' => (string)$patient->getId()
            ];
            
            if (isset($metadata['externalPatientId'])) {
                $exportData['metadata']['previousId'] = $metadata['externalPatientId'];
            }
            
            // Send to external system
            $success = false;
            if ($systemConfig['type'] === 'api') {
                $success = $this->exportPatientToApi($exportData, $systemConfig);
            } elseif ($systemConfig['type'] === 'file') {
                $success = $this->exportPatientToFile($exportData, $systemConfig, (string)$patient->getId());
            }
            
            // Log the export
            if ($success) {
                $this->auditLogService->log(
                    $this->security->getUser(),
                    'PATIENT_EXPORT',
                    [
                        'description' => "Exported patient to external system",
                        'systemId' => $systemId,
                        'patientId' => (string)$patient->getId(),
                        'entityId' => (string)$patient->getId(),
                        'entityType' => 'Patient',
                        'status' => 'success'
                    ]
                );
            } else {
                $this->auditLogService->log(
                    $this->security->getUser(),
                    'PATIENT_EXPORT',
                    [
                        'description' => "Failed to export patient to external system",
                        'systemId' => $systemId,
                        'patientId' => (string)$patient->getId(),
                        'entityId' => (string)$patient->getId(),
                        'entityType' => 'Patient',
                        'status' => 'failed'
                    ]
                );
            }
            
            return $success;
            
        } catch (\Exception $e) {
            $this->logger->error("Error exporting patient to external system", [
                'systemId' => $systemId,
                'patientId' => (string)$patient->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Export patient data to an external API
     */
    private function exportPatientToApi(array $patientData, array $config): bool
    {
        $url = $config['exportUrl'] ?? $config['url'];
        
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $patientData,
            'timeout' => $config['timeout'] ?? 30,
        ];
        
        // Add authentication if configured
        if (isset($config['auth'])) {
            if ($config['auth']['type'] === 'basic') {
                $options['auth_basic'] = [$config['auth']['username'], $config['auth']['password']];
            } elseif ($config['auth']['type'] === 'bearer') {
                $options['headers']['Authorization'] = "Bearer {$config['auth']['token']}";
            }
        }
        
        $response = $this->httpClient->request('POST', $url, $options);
        
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->logger->info("Successfully exported patient to external API", [
                'statusCode' => $statusCode
            ]);
            return true;
        }
        
        $this->logger->error("Failed to export patient to external API", [
            'statusCode' => $statusCode,
            'error' => $response->getContent(false)
        ]);
        
        return false;
    }

    /**
     * Export patient data to a file
     */
    private function exportPatientToFile(array $patientData, array $config, string $patientId): bool
    {
        $exportPath = $config['exportPath'] ?? $config['path'];
        $filePath = sprintf($exportPath, $patientId);
        
        // Create directory if it doesn't exist
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $this->logger->error("Failed to create export directory", [
                    'directory' => $directory
                ]);
                return false;
            }
        }
        
        // Write patient data to file
        $jsonData = json_encode($patientData, JSON_PRETTY_PRINT);
        if (file_put_contents($filePath, $jsonData) === false) {
            $this->logger->error("Failed to write patient data to file", [
                'filePath' => $filePath
            ]);
            return false;
        }
        
        $this->logger->info("Successfully exported patient to file", [
            'filePath' => $filePath
        ]);
        
        return true;
    }

    /**
     * Set a nested value in an array using dot notation
     * Example: setNestedValue($data, 'patient.demographics.firstName', 'John')
     */
    private function setNestedValue(array &$data, string $path, $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                // Last key, set the value
                $current[$key] = $value;
            } else {
                // Create nested array if needed
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Get metadata for a patient related to an external system
     */
    public function getPatientExternalSystemInfo(Patient $patient, string $systemId = null): array
    {
        $metadata = $patient->getMetadata() ?? [];
        
        if ($systemId !== null) {
            if ($metadata['externalSystem'] === $systemId) {
                return [
                    'systemId' => $metadata['externalSystem'],
                    'externalPatientId' => $metadata['externalPatientId'] ?? null,
                    'lastImported' => $metadata['lastImported'] ?? null,
                ];
            }
            return [];
        }
        
        if (isset($metadata['externalSystem'])) {
            return [
                'systemId' => $metadata['externalSystem'],
                'externalPatientId' => $metadata['externalPatientId'] ?? null,
                'lastImported' => $metadata['lastImported'] ?? null,
            ];
        }
        
        return [];
    }

    /**
     * Get the list of available external systems
     */
    public function getAvailableExternalSystems(): array
    {
        $result = [];
        
        foreach ($this->systemConfigurations as $systemId => $config) {
            $result[] = [
                'id' => $systemId,
                'name' => $config['name'] ?? $systemId,
                'type' => $config['type'] ?? 'unknown',
                'description' => $config['description'] ?? '',
            ];
        }
        
        return $result;
    }
}