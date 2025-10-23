<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use App\Service\PatientVerificationService;
use App\Security\Voter\PatientVoter;
use App\Service\MongoDBEncryptionService;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/patients')]
class PatientController extends AbstractController
{
    private PatientRepository $patientRepository;
    private AuditLogService $auditLogService;
    private MongoDBEncryptionService $encryptionService;
    private ValidatorInterface $validator;
    private PatientVerificationService $verificationService;

    public function __construct(
        PatientRepository $patientRepository,
        AuditLogService $auditLogService,
        MongoDBEncryptionService $encryptionService,
        ValidatorInterface $validator,
        PatientVerificationService $verificationService
    ) {
        $this->patientRepository = $patientRepository;
        $this->auditLogService = $auditLogService;
        $this->encryptionService = $encryptionService;
        $this->validator = $validator;
        $this->verificationService = $verificationService;
    }

    #[Route('', name: 'patient_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        // Check if user has permission to view patients (no specific patient needed)
        if (!$this->isGranted(PatientVoter::VIEW)) {
            return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Get query parameters for filtering
        $lastName = $request->query->get('lastName');
        $firstName = $request->query->get('firstName');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, $request->query->getInt('limit', 20));

        // Build query criteria
        $criteria = [];
        if ($lastName) {
            $criteria['lastName'] = $this->encryptionService->encrypt('patient', 'lastName', $lastName);
        }
        if ($firstName) {
            $criteria['firstName'] = $this->encryptionService->encrypt('patient', 'firstName', $firstName);
        }

        // Get patients from repository
        $patients = $this->patientRepository->findByCriteria($criteria, $page, $limit);
        $totalPatients = $this->patientRepository->countByCriteria($criteria);

        // Convert to array representation with proper role-based filtering
        $user = $this->getUser();
        $userRole = 'ROLE_DOCTOR'; // Default role
        
        if ($user) {
            $userRoles = $user->getRoles();
            if (in_array('ROLE_DOCTOR', $userRoles)) {
                $userRole = 'ROLE_DOCTOR';
            } elseif (in_array('ROLE_NURSE', $userRoles)) {
                $userRole = 'ROLE_NURSE';
            } elseif (in_array('ROLE_ADMIN', $userRoles)) {
                $userRole = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_RECEPTIONIST', $userRoles)) {
                $userRole = 'ROLE_RECEPTIONIST';
            } elseif (in_array('ROLE_PATIENT', $userRoles)) {
                $userRole = 'ROLE_PATIENT';
            }
        }
        
        $patientsArray = array_map(function(Patient $patient) use ($userRole) {
            return $this->serializePatient($patient, $userRole);
        }, $patients);

        // Log the action
        $this->auditLogService->log(
            $this->getUser(),
            'PATIENT_LIST',
            [
                'description' => 'Listed patients',
                'filters' => ['lastName' => $lastName, 'firstName' => $firstName],
                'count' => count($patients)
            ]
        );

        return $this->json([
            'total' => $totalPatients,
            'page' => $page,
            'limit' => $limit,
            'patients' => $patientsArray,
        ]);
    }
    
    /**
     * Serialize patient data safely for JSON output
     */
    private function serializePatient(Patient $patient, ?string $userRole = null): array
    {
        // Start with basic data - now showing actual values since data is unencrypted
        $data = [
            'id' => (string)$patient->getId(),
            'firstName' => $this->safeGetString($patient->getFirstName()),
            'lastName' => $this->safeGetString($patient->getLastName()),
            'email' => $this->safeGetString($patient->getEmail()),
            'phoneNumber' => $this->safeGetString($patient->getPhoneNumber()),
            'birthDate' => $this->safeGetDate($patient->getBirthDate()),
            'createdAt' => $this->safeGetDate($patient->getCreatedAt())
        ];
        
        return $data;
    }
    
    /**
     * Safely get string value, handling Binary objects
     */
    private function safeGetString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if (is_object($value) && !method_exists($value, '__toString')) {
            return '[Encrypted]';
        }
        
        return (string)$value;
    }
    
    /**
     * Safely get date value, handling Binary objects
     */
    private function safeGetDate($value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format('Y-m-d');
        }
        
        if (is_object($value) && !method_exists($value, '__toString')) {
            return '[Encrypted]';
        }
        
        return (string)$value;
    }
    
    /**
     * Safely get array value, handling Binary objects
     */
    private function safeGetArray($value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        if (is_object($value) && !method_exists($value, '__toString')) {
            return ['[Encrypted]'];
        }
        
        return [(string)$value];
    }

    #[Route('/{id}', name: 'patient_show', methods: ['GET'])]
    public function show(string $id, Request $request): JsonResponse
    {
        $patient = $this->getPatientById($id);

        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::VIEW, $patient);

        // Check if verification is required
        $user = $this->getUser();
        if ($this->verificationService->isVerificationRequired($user)) {
            $verificationResult = $this->checkVerification($request, $user, $id);
            if (!$verificationResult['success']) {
                return $this->json([
                    'message' => 'Patient identity verification required',
                    'verificationRequired' => true,
                    'requirements' => $this->verificationService->getVerificationRequirements()
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Log the access
        $this->auditLogService->logPatientAccess(
            $user,
            'VIEW',
            (string)$patient->getId(),
            [
                'description' => 'Viewed patient details',
                'verificationRequired' => $this->verificationService->isVerificationRequired($user)
            ]
        );

        // Get user role for serialization
        $userRoles = $user->getRoles();
        $userRole = 'ROLE_DOCTOR'; // Default
        if (in_array('ROLE_DOCTOR', $userRoles)) {
            $userRole = 'ROLE_DOCTOR';
        } elseif (in_array('ROLE_NURSE', $userRoles)) {
            $userRole = 'ROLE_NURSE';
        } elseif (in_array('ROLE_ADMIN', $userRoles)) {
            $userRole = 'ROLE_ADMIN';
        } elseif (in_array('ROLE_RECEPTIONIST', $userRoles)) {
            $userRole = 'ROLE_RECEPTIONIST';
        } elseif (in_array('ROLE_PATIENT', $userRoles)) {
            $userRole = 'ROLE_PATIENT';
        }
        
        return $this->json($patient->toArray($userRole));
    }

    #[Route('', name: 'patient_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PatientVoter::CREATE);

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Create new patient
        $patient = Patient::fromArray($data, $this->encryptionService);

        // Validate patient
        $errors = $this->validator->validate($patient);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Save patient
        $this->patientRepository->save($patient);

        // Log the creation
        $this->auditLogService->logPatientAccess(
            $this->getUser(),
            'CREATE',
            (string)$patient->getId(),
            [
                'description' => 'Created new patient record'
            ]
        );

        // Get user role for serialization
        $user = $this->getUser();
        $userRoles = $user->getRoles();
        $userRole = 'ROLE_DOCTOR'; // Default
        if (in_array('ROLE_DOCTOR', $userRoles)) {
            $userRole = 'ROLE_DOCTOR';
        } elseif (in_array('ROLE_NURSE', $userRoles)) {
            $userRole = 'ROLE_NURSE';
        } elseif (in_array('ROLE_ADMIN', $userRoles)) {
            $userRole = 'ROLE_ADMIN';
        } elseif (in_array('ROLE_RECEPTIONIST', $userRoles)) {
            $userRole = 'ROLE_RECEPTIONIST';
        } elseif (in_array('ROLE_PATIENT', $userRoles)) {
            $userRole = 'ROLE_PATIENT';
        }
        
        return $this->json(
            $patient->toArray($userRole),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'patient_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $patient = $this->getPatientById($id);

        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::EDIT, $patient);

        // Check if verification is required for updates
        $user = $this->getUser();
        if ($this->verificationService->isVerificationRequired($user)) {
            $verificationResult = $this->checkVerification($request, $user, $id);
            if (!$verificationResult['success']) {
                return $this->json([
                    'message' => 'Patient identity verification required for updates',
                    'verificationRequired' => true,
                    'requirements' => $this->verificationService->getVerificationRequirements()
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Track which fields are updated
        $updatedFields = [];

        // Update basic fields
        if (isset($data['firstName'])) {
            $patient->setFirstName($data['firstName']);
            $updatedFields[] = 'firstName';
        }

        if (isset($data['lastName'])) {
            $patient->setLastName($data['lastName']);
            $updatedFields[] = 'lastName';
        }

        if (isset($data['email'])) {
            $patient->setEmail($data['email']);
            $updatedFields[] = 'email';
        }

        if (isset($data['phoneNumber'])) {
            $patient->setPhoneNumber($data['phoneNumber']);
            $updatedFields[] = 'phoneNumber';
        }

        if (isset($data['birthDate'])) {
            $dateTime = new \DateTime($data['birthDate']);
            $patient->setBirthDate(new UTCDateTime($dateTime));
            $updatedFields[] = 'birthDate';
        }

        // Handle restricted fields based on permissions
        if (isset($data['ssn']) && $this->isGranted(PatientVoter::VIEW_SSN, $patient)) {
            $patient->setSsn($data['ssn']);
            $updatedFields[] = 'ssn';
        }

        if (isset($data['diagnosis']) && $this->isGranted(PatientVoter::EDIT_DIAGNOSIS, $patient)) {
            $patient->setDiagnosis($data['diagnosis']);
            $updatedFields[] = 'diagnosis';
        }

        if (isset($data['medications']) && $this->isGranted(PatientVoter::EDIT_MEDICATIONS, $patient)) {
            $patient->setMedications($data['medications']);
            $updatedFields[] = 'medications';
        }

        if (isset($data['insuranceDetails']) && $this->isGranted(PatientVoter::EDIT_INSURANCE, $patient)) {
            $patient->setInsuranceDetails($data['insuranceDetails']);
            $updatedFields[] = 'insuranceDetails';
        }

        if (isset($data['notes']) && $this->isGranted(PatientVoter::EDIT_DIAGNOSIS, $patient)) {
            $patient->setNotes($data['notes']);
            $updatedFields[] = 'notes';
        }

        if (isset($data['notesHistory']) && $this->isGranted(PatientVoter::EDIT_NOTES, $patient)) {
            $patient->setNotesHistory($data['notesHistory']);
            $updatedFields[] = 'notesHistory';
        }

        if (isset($data['primaryDoctorId']) && $this->isGranted(PatientVoter::EDIT, $patient)) {
            $patient->setPrimaryDoctorId(new ObjectId($data['primaryDoctorId']));
            $updatedFields[] = 'primaryDoctorId';
        }

        // Update timestamp
        $patient->setUpdatedAt(new UTCDateTime());

        // Validate patient
        $errors = $this->validator->validate($patient);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Save patient
        $this->patientRepository->save($patient);

        // Log the update
        $this->auditLogService->logPatientAccess(
            $this->getUser(),
            'UPDATE',
            (string)$patient->getId(),
            [
                'description' => 'Updated patient record',
                'updatedFields' => $updatedFields
            ]
        );

        // Get user role for serialization
        $user = $this->getUser();
        $userRoles = $user->getRoles();
        $userRole = 'ROLE_DOCTOR'; // Default
        if (in_array('ROLE_DOCTOR', $userRoles)) {
            $userRole = 'ROLE_DOCTOR';
        } elseif (in_array('ROLE_NURSE', $userRoles)) {
            $userRole = 'ROLE_NURSE';
        } elseif (in_array('ROLE_ADMIN', $userRoles)) {
            $userRole = 'ROLE_ADMIN';
        } elseif (in_array('ROLE_RECEPTIONIST', $userRoles)) {
            $userRole = 'ROLE_RECEPTIONIST';
        } elseif (in_array('ROLE_PATIENT', $userRoles)) {
            $userRole = 'ROLE_PATIENT';
        }
        
        return $this->json($patient->toArray($userRole));
    }

    #[Route('/{id}', name: 'patient_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $patient = $this->getPatientById($id);

        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::DELETE, $patient);

        // Get patient info for logging before deletion
        $patientInfo = [
            'id' => (string)$patient->getId(),
            'firstName' => $patient->getFirstName(),
            'lastName' => $patient->getLastName()
        ];

        // Delete patient
        $this->patientRepository->delete($patient);

        // Log the deletion
        $this->auditLogService->log(
            $this->getUser(),
            'PATIENT_DELETE',
            [
                'description' => 'Deleted patient record',
                'entityId' => $patientInfo['id'],
                'entityType' => 'Patient',
                'patientInfo' => $patientInfo
            ]
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get patient diagnosis records
     */
    #[Route('/{id}/diagnosis', name: 'patient_diagnosis', methods: ['GET'])]
    public function getDiagnosis(string $id): JsonResponse
    {
        $patient = $this->getPatientById($id);

        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::VIEW_DIAGNOSIS, $patient);

        // Log the access
        $this->auditLogService->logPatientAccess(
            $this->getUser(),
            'VIEW_DIAGNOSIS',
            (string)$patient->getId(),
            [
                'description' => 'Viewed patient diagnosis information'
            ]
        );

        return $this->json([
            'id' => (string)$patient->getId(),
            'firstName' => $patient->getFirstName(),
            'lastName' => $patient->getLastName(),
            'diagnosis' => $patient->getDiagnosis()
        ]);
    }

    /**
     * Get patient medications
     */
    #[Route('/{id}/medications', name: 'patient_medications', methods: ['GET'])]
    public function getMedications(string $id): JsonResponse
    {
        $patient = $this->getPatientById($id);

        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::VIEW_MEDICATIONS, $patient);

        // Log the access
        $this->auditLogService->logPatientAccess(
            $this->getUser(),
            'VIEW_MEDICATIONS',
            (string)$patient->getId(),
            [
                'description' => 'Viewed patient medication information'
            ]
        );

        return $this->json([
            'id' => (string)$patient->getId(),
            'firstName' => $patient->getFirstName(),
            'lastName' => $patient->getLastName(),
            'medications' => $patient->getMedications()
        ]);
    }

    /**
     * Helper method to get a patient by ID
     */
    private function getPatientById(string $id): ?Patient
    {
        try {
            $objectId = new ObjectId($id);
            return $this->patientRepository->findById($objectId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check patient verification from request headers or body
     */
    private function checkVerification(Request $request, UserInterface $user, string $patientId): array
    {
        // Check for verification data in request body first
        $data = json_decode($request->getContent(), true);
        if (isset($data['verification'])) {
            $verification = $data['verification'];
            if (isset($verification['birthDate'])) {
                return $this->verificationService->verifyPatientIdentity(
                    $patientId,
                    $verification['birthDate'],
                    $user
                );
            }
        }

        // Check for verification data in headers
        $birthDate = $request->headers->get('X-Patient-Birth-Date');

        if ($birthDate) {
            return $this->verificationService->verifyPatientIdentity(
                $patientId,
                $birthDate,
                $user
            );
        }

        return [
            'success' => false,
            'message' => 'Verification data not provided'
        ];
    }
}