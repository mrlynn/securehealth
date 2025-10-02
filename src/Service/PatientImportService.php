<?php

namespace App\Service;

use App\Document\Patient;
use App\Repository\PatientRepository;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service to handle bulk patient data imports
 */
class PatientImportService
{
    private PatientRepository $patientRepository;
    private MongoDBEncryptionService $encryptionService;
    private AuditLogService $auditLogService;
    private LoggerInterface $logger;
    private Security $security;
    private ValidatorInterface $validator;

    public function __construct(
        PatientRepository $patientRepository,
        MongoDBEncryptionService $encryptionService,
        AuditLogService $auditLogService,
        LoggerInterface $logger,
        Security $security,
        ValidatorInterface $validator
    ) {
        $this->patientRepository = $patientRepository;
        $this->encryptionService = $encryptionService;
        $this->auditLogService = $auditLogService;
        $this->logger = $logger;
        $this->security = $security;
        $this->validator = $validator;
    }

    /**
     * Import patients from a CSV file
     * 
     * @param UploadedFile|string $file CSV file to import (either an UploadedFile or path to file)
     * @param array $options Import options
     * @return array Import results with counts and errors
     */
    public function importFromCsv($file, array $options = []): array
    {
        // Default options
        $options = array_merge([
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'headerRow' => true,
            'skipDuplicates' => true,
            'batchSize' => 100,
            'fieldMapping' => [
                'firstName' => 'FirstName',
                'lastName' => 'LastName',
                'email' => 'Email',
                'phoneNumber' => 'Phone',
                'birthDate' => 'DOB',
                'ssn' => 'SSN',
                'diagnosis' => 'Diagnosis',
                'medications' => 'Medications',
                'notes' => 'Notes'
            ]
        ], $options);

        // Prepare result tracking
        $result = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        try {
            // Open the file
            $filepath = $file instanceof UploadedFile ? $file->getPathname() : $file;
            $handle = fopen($filepath, 'r');
            if ($handle === false) {
                throw new \RuntimeException("Could not open file: {$filepath}");
            }

            // Skip header row if needed
            if ($options['headerRow']) {
                $headers = fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'], $options['escape']);
                if ($headers === false) {
                    throw new \RuntimeException("Could not read header row from CSV file");
                }
                
                // Create field index mapping
                $fieldIndexes = [];
                foreach ($options['fieldMapping'] as $ourField => $csvField) {
                    $index = array_search($csvField, $headers);
                    if ($index !== false) {
                        $fieldIndexes[$ourField] = $index;
                    }
                }
            }

            // Process each row
            $batch = [];
            $batchCount = 0;
            
            while (($row = fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'], $options['escape'])) !== false) {
                $result['total']++;
                
                try {
                    $patientData = [];
                    
                    // Map CSV fields to our fields
                    foreach ($fieldIndexes as $ourField => $index) {
                        if (isset($row[$index]) && $row[$index] !== '') {
                            $patientData[$ourField] = $row[$index];
                        }
                    }
                    
                    // Skip if we don't have minimum required fields
                    if (!isset($patientData['firstName']) || !isset($patientData['lastName']) || !isset($patientData['email'])) {
                        $result['skipped']++;
                        $result['errors'][] = [
                            'row' => $result['total'],
                            'message' => 'Missing required fields (firstName, lastName, or email)',
                            'data' => json_encode($patientData)
                        ];
                        continue;
                    }
                    
                    // Check for duplicates if needed
                    if ($options['skipDuplicates']) {
                        $emailEncrypted = $this->encryptionService->encrypt('patient', 'email', $patientData['email']);
                        $existingPatients = $this->patientRepository->findByCriteria(['email' => $emailEncrypted]);
                        
                        if (count($existingPatients) > 0) {
                            $result['skipped']++;
                            continue;
                        }
                    }
                    
                    // Create patient object
                    $patient = $this->createPatientFromData($patientData);
                    
                    // Validate patient
                    $errors = $this->validator->validate($patient);
                    if (count($errors) > 0) {
                        $errorMessages = [];
                        foreach ($errors as $error) {
                            $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                        }
                        
                        $result['skipped']++;
                        $result['errors'][] = [
                            'row' => $result['total'],
                            'message' => 'Validation failed',
                            'errors' => $errorMessages,
                            'data' => json_encode($patientData)
                        ];
                        continue;
                    }
                    
                    // Add to batch
                    $batch[] = $patient;
                    $batchCount++;
                    
                    // Process batch if reached batch size
                    if ($batchCount >= $options['batchSize']) {
                        $this->saveBatch($batch);
                        $result['imported'] += count($batch);
                        $batch = [];
                        $batchCount = 0;
                    }
                    
                } catch (\Exception $e) {
                    $result['skipped']++;
                    $result['errors'][] = [
                        'row' => $result['total'],
                        'message' => 'Error processing row: ' . $e->getMessage(),
                        'data' => isset($row) ? json_encode($row) : ''
                    ];
                }
            }
            
            // Save any remaining batch
            if (count($batch) > 0) {
                $this->saveBatch($batch);
                $result['imported'] += count($batch);
            }
            
            fclose($handle);
            
            // Log the import
            $this->auditLogService->log(
                $this->security->getUser(),
                'PATIENT_IMPORT_CSV',
                [
                    'description' => 'Imported patients from CSV file',
                    'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filepath),
                    'total' => $result['total'],
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'errors' => count($result['errors'])
                ]
            );
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Error importing patients from CSV', [
                'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filepath),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Import patients from a JSON file
     * 
     * @param UploadedFile|string $file JSON file to import (either an UploadedFile or path to file)
     * @param array $options Import options
     * @return array Import results with counts and errors
     */
    public function importFromJson($file, array $options = []): array
    {
        // Default options
        $options = array_merge([
            'skipDuplicates' => true,
            'batchSize' => 100,
            'rootPath' => 'patients',
            'fieldMapping' => [
                'firstName' => 'firstName',
                'lastName' => 'lastName',
                'email' => 'email',
                'phoneNumber' => 'phoneNumber',
                'birthDate' => 'birthDate',
                'ssn' => 'ssn',
                'diagnosis' => 'diagnosis',
                'medications' => 'medications',
                'notes' => 'notes'
            ]
        ], $options);

        // Prepare result tracking
        $result = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        try {
            // Read JSON file
            $filepath = $file instanceof UploadedFile ? $file->getPathname() : $file;
            $jsonContent = file_get_contents($filepath);
            if ($jsonContent === false) {
                throw new \RuntimeException("Could not read file: {$filepath}");
            }
            
            $data = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON file: " . json_last_error_msg());
            }
            
            // Get patients array from JSON
            $patients = $data;
            if (!empty($options['rootPath'])) {
                $rootPath = explode('.', $options['rootPath']);
                foreach ($rootPath as $key) {
                    if (!isset($patients[$key]) || !is_array($patients[$key])) {
                        throw new \RuntimeException("Invalid JSON structure: '{$options['rootPath']}' path not found");
                    }
                    $patients = $patients[$key];
                }
            }
            
            if (!is_array($patients)) {
                throw new \RuntimeException("Invalid JSON structure: patients data is not an array");
            }
            
            // Process each patient
            $batch = [];
            $batchCount = 0;
            
            foreach ($patients as $patientJson) {
                $result['total']++;
                
                try {
                    $patientData = [];
                    
                    // Map JSON fields to our fields
                    foreach ($options['fieldMapping'] as $ourField => $jsonField) {
                        $jsonPath = explode('.', $jsonField);
                        $value = $patientJson;
                        
                        foreach ($jsonPath as $key) {
                            if (!isset($value[$key])) {
                                $value = null;
                                break;
                            }
                            $value = $value[$key];
                        }
                        
                        if ($value !== null) {
                            $patientData[$ourField] = $value;
                        }
                    }
                    
                    // Skip if we don't have minimum required fields
                    if (!isset($patientData['firstName']) || !isset($patientData['lastName']) || !isset($patientData['email'])) {
                        $result['skipped']++;
                        $result['errors'][] = [
                            'index' => $result['total'],
                            'message' => 'Missing required fields (firstName, lastName, or email)',
                            'data' => json_encode($patientData)
                        ];
                        continue;
                    }
                    
                    // Check for duplicates if needed
                    if ($options['skipDuplicates']) {
                        $emailEncrypted = $this->encryptionService->encrypt('patient', 'email', $patientData['email']);
                        $existingPatients = $this->patientRepository->findByCriteria(['email' => $emailEncrypted]);
                        
                        if (count($existingPatients) > 0) {
                            $result['skipped']++;
                            continue;
                        }
                    }
                    
                    // Create patient object
                    $patient = $this->createPatientFromData($patientData);
                    
                    // Validate patient
                    $errors = $this->validator->validate($patient);
                    if (count($errors) > 0) {
                        $errorMessages = [];
                        foreach ($errors as $error) {
                            $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                        }
                        
                        $result['skipped']++;
                        $result['errors'][] = [
                            'index' => $result['total'],
                            'message' => 'Validation failed',
                            'errors' => $errorMessages,
                            'data' => json_encode($patientData)
                        ];
                        continue;
                    }
                    
                    // Add to batch
                    $batch[] = $patient;
                    $batchCount++;
                    
                    // Process batch if reached batch size
                    if ($batchCount >= $options['batchSize']) {
                        $this->saveBatch($batch);
                        $result['imported'] += count($batch);
                        $batch = [];
                        $batchCount = 0;
                    }
                    
                } catch (\Exception $e) {
                    $result['skipped']++;
                    $result['errors'][] = [
                        'index' => $result['total'],
                        'message' => 'Error processing patient: ' . $e->getMessage(),
                        'data' => isset($patientJson) ? json_encode($patientJson) : ''
                    ];
                }
            }
            
            // Save any remaining batch
            if (count($batch) > 0) {
                $this->saveBatch($batch);
                $result['imported'] += count($batch);
            }
            
            // Log the import
            $this->auditLogService->log(
                $this->security->getUser(),
                'PATIENT_IMPORT_JSON',
                [
                    'description' => 'Imported patients from JSON file',
                    'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filepath),
                    'total' => $result['total'],
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'errors' => count($result['errors'])
                ]
            );
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Error importing patients from JSON', [
                'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filepath),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Create a Patient object from an array of data
     */
    private function createPatientFromData(array $data): Patient
    {
        $patient = new Patient();
        
        // Set basic fields
        if (isset($data['firstName'])) {
            $patient->setFirstName($data['firstName']);
        }
        
        if (isset($data['lastName'])) {
            $patient->setLastName($data['lastName']);
        }
        
        if (isset($data['email'])) {
            $patient->setEmail($data['email']);
        }
        
        if (isset($data['phoneNumber'])) {
            $patient->setPhoneNumber($data['phoneNumber']);
        }
        
        // Handle birth date
        if (isset($data['birthDate'])) {
            try {
                $dateTime = new \DateTime($data['birthDate']);
                $patient->setBirthDate(new UTCDateTime($dateTime));
            } catch (\Exception $e) {
                $this->logger->warning('Invalid birthDate format', [
                    'birthDate' => $data['birthDate'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Handle sensitive fields
        if (isset($data['ssn'])) {
            $patient->setSsn($data['ssn']);
        }
        
        if (isset($data['diagnosis'])) {
            if (is_string($data['diagnosis'])) {
                // Split comma-separated values
                $diagnosisList = array_map('trim', explode(',', $data['diagnosis']));
                $patient->setDiagnosis($diagnosisList);
            } elseif (is_array($data['diagnosis'])) {
                $patient->setDiagnosis($data['diagnosis']);
            }
        }
        
        if (isset($data['medications'])) {
            if (is_string($data['medications'])) {
                // Split comma-separated values
                $medicationsList = array_map('trim', explode(',', $data['medications']));
                $patient->setMedications($medicationsList);
            } elseif (is_array($data['medications'])) {
                $patient->setMedications($data['medications']);
            }
        }
        
        if (isset($data['notes'])) {
            $patient->setNotes($data['notes']);
        }
        
        if (isset($data['insuranceDetails'])) {
            if (is_string($data['insuranceDetails'])) {
                // Try to parse as JSON
                try {
                    $insuranceDetails = json_decode($data['insuranceDetails'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($insuranceDetails)) {
                        $patient->setInsuranceDetails($insuranceDetails);
                    }
                } catch (\Exception $e) {
                    // Just store as a simple array with provider info
                    $patient->setInsuranceDetails(['provider' => $data['insuranceDetails']]);
                }
            } elseif (is_array($data['insuranceDetails'])) {
                $patient->setInsuranceDetails($data['insuranceDetails']);
            }
        }
        
        // Set metadata
        $metadata = [
            'importSource' => 'bulk_import',
            'importTimestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'importedBy' => $this->security->getUser()->getUserIdentifier()
        ];
        $patient->setMetadata($metadata);
        
        return $patient;
    }

    /**
     * Save a batch of patients
     */
    private function saveBatch(array $patients): void
    {
        foreach ($patients as $patient) {
            $this->patientRepository->save($patient);
        }
    }
}