<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Document\User;
use App\Repository\PatientRepository;
use App\Repository\UserRepository;
use App\Repository\AppointmentRepository;
use App\Repository\MessageRepository;
use App\Service\AuditLogService;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/patient-portal')]
class PatientPortalController extends AbstractController
{
    private PatientRepository $patientRepository;
    private UserRepository $userRepository;
    private AppointmentRepository $appointmentRepository;
    private DocumentManager $documentManager;
    private AuditLogService $auditLogService;
    private AuthorizationCheckerInterface $authorizationChecker;
    private ValidatorInterface $validator;
    private MessageRepository $messageRepository;

    public function __construct(
        PatientRepository $patientRepository,
        UserRepository $userRepository,
        AppointmentRepository $appointmentRepository,
        DocumentManager $documentManager,
        AuditLogService $auditLogService,
        AuthorizationCheckerInterface $authorizationChecker,
        ValidatorInterface $validator,
        MessageRepository $messageRepository
    ) {
        $this->patientRepository = $patientRepository;
        $this->userRepository = $userRepository;
        $this->appointmentRepository = $appointmentRepository;
        $this->documentManager = $documentManager;
        $this->auditLogService = $auditLogService;
        $this->authorizationChecker = $authorizationChecker;
        $this->validator = $validator;
        $this->messageRepository = $messageRepository;
    }

    /**
     * Get patient's own medical records
     */
    #[Route('/my-records', name: 'patient_portal_my_records', methods: ['GET'])]
    public function getMyRecords(UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied. Patient access required.'
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        $patient = $this->patientRepository->find($patientId);
        if (!$patient) {
            return $this->json([
                'success' => false,
                'message' => 'Patient record not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Log the access
        $this->auditLogService->log(
            $user,
            'patient_portal_access',
            [
                'action' => 'view_own_records',
                'patientId' => (string)$patientId
            ]
        );

        // Return patient data with patient-appropriate filtering
        $patientData = $patient->toArray('ROLE_PATIENT');
        
        return $this->json([
            'success' => true,
            'data' => $patientData
        ]);
    }

    /**
     * Update patient's own basic information
     */
    #[Route('/my-records', name: 'patient_portal_update_my_records', methods: ['PUT'])]
    public function updateMyRecords(Request $request, UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied. Patient access required.'
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        $patient = $this->patientRepository->find($patientId);
        if (!$patient) {
            return $this->json([
                'success' => false,
                'message' => 'Patient record not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        // Patients can only update certain fields
        $allowedFields = ['phoneNumber', 'email'];
        $updatedFields = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $setter = 'set' . ucfirst($field);
                if (method_exists($patient, $setter)) {
                    $patient->$setter($data[$field]);
                    $updatedFields[] = $field;
                }
            }
        }

        if (empty($updatedFields)) {
            return $this->json([
                'success' => false,
                'message' => 'No valid fields provided for update.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate the updated patient
        $errors = $this->validator->validate($patient);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update timestamp
        $patient->setUpdatedAt(new \MongoDB\BSON\UTCDateTime());

        try {
            $this->documentManager->flush();

            // Log the update
            $this->auditLogService->log(
                $user,
                'patient_portal_update',
                [
                    'action' => 'update_own_records',
                    'patientId' => (string)$patientId,
                    'updatedFields' => $updatedFields
                ]
            );

            return $this->json([
                'success' => true,
                'message' => 'Patient information updated successfully',
                'data' => $patient->toArray('ROLE_PATIENT')
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to update patient information'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Register a new patient account
     */
    #[Route('/register', name: 'patient_portal_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['firstName', 'lastName', 'email', 'password', 'birthDate'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Field '$field' is required."
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findOneByEmail($data['email']);
        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'An account with this email already exists.',
                'debug' => [
                    'email' => $data['email'],
                    'existingUserId' => (string)$existingUser->getId(),
                    'existingUserRoles' => $existingUser->getRoles()
                ]
            ], Response::HTTP_CONFLICT);
        }

        // Check if patient already exists
        try {
            $existingPatient = $this->patientRepository->findOneByEmail($data['email']);
            if ($existingPatient) {
                return $this->json([
                    'success' => false,
                    'message' => 'A patient record with this email already exists.',
                    'debug' => [
                        'email' => $data['email'],
                        'existingPatientId' => (string)$existingPatient->getId()
                    ]
                ], Response::HTTP_CONFLICT);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error checking for existing patient: ' . $e->getMessage(),
                'debug' => [
                    'email' => $data['email'],
                    'error' => $e->getMessage()
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            // Create patient record
            $patient = new Patient();
            $patient->setFirstName($data['firstName']);
            $patient->setLastName($data['lastName']);
            $patient->setEmail($data['email']);
            $patient->setBirthDate(new \MongoDB\BSON\UTCDateTime(new \DateTime($data['birthDate'])));
            
            if (isset($data['phoneNumber'])) {
                $patient->setPhoneNumber($data['phoneNumber']);
            }

            $this->documentManager->persist($patient);
            $this->documentManager->flush();

            // Create user account linked to patient
            $user = new User();
            $user->setEmail($data['email']);
            $user->setUsername($data['firstName'] . ' ' . $data['lastName']);
            $user->setPassword($data['password']); // TODO: Hash password properly
            $user->setRoles(['ROLE_PATIENT']);
            $user->setIsPatient(true);
            $user->setPatientId($patient->getId());

            $this->documentManager->persist($user);
            $this->documentManager->flush();

            // Log the registration
            $this->auditLogService->log(
                $user,
                'patient_portal_registration',
                [
                    'action' => 'register',
                    'patientId' => (string)$patient->getId(),
                    'email' => $data['email']
                ]
            );

            return $this->json([
                'success' => true,
                'message' => 'Patient account created successfully',
                'data' => [
                    'userId' => (string)$user->getId(),
                    'patientId' => (string)$patient->getId(),
                    'email' => $user->getEmail()
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create patient account: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get patient dashboard information
     */
    #[Route('/dashboard', name: 'patient_portal_dashboard', methods: ['GET'])]
    public function getDashboard(UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied. Patient access required.'
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        $patient = $this->patientRepository->find($patientId);
        if (!$patient) {
            return $this->json([
                'success' => false,
                'message' => 'Patient record not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Log dashboard access
        $this->auditLogService->log(
            $user,
            'patient_portal_dashboard',
            [
                'action' => 'view_dashboard',
                'patientId' => (string)$patientId
            ]
        );

        // Prepare dashboard data
        $patientData = $patient->toArray('ROLE_PATIENT');
        
        // Get upcoming appointments
        $upcomingAppointments = [];
        try {
            $objectId = new ObjectId($patientId);
            $fromDate = new \DateTime(); // From now
            $upcomingAppts = $this->appointmentRepository->findUpcoming($fromDate, $objectId, 3);
            $upcomingAppointments = array_map(function($appointment) {
                return $appointment->toArray();
            }, $upcomingAppts);
        } catch (\Exception $e) {
            // If there's an error getting appointments, continue without them
            error_log('Error fetching upcoming appointments: ' . $e->getMessage());
        }
        
        $dashboardData = [
            'patient' => [
                'id' => $patientData['id'],
                'firstName' => $patientData['firstName'],
                'lastName' => $patientData['lastName'],
                'email' => $patientData['email'],
                'phoneNumber' => $patientData['phoneNumber']
            ],
            'medications' => $patientData['medications'] ?? [],
            'insurance' => $patientData['insuranceDetails'] ?? null,
            'upcomingAppointments' => $upcomingAppointments,
            'lastUpdated' => $patientData['createdAt']
        ];

        return $this->json([
            'success' => true,
            'data' => $dashboardData
        ]);
    }

    /**
     * Get patient's appointments
     */
    #[Route('/appointments', name: 'patient_portal_appointments', methods: ['GET'])]
    public function getMyAppointments(UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied. Patient access required.'
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $objectId = new ObjectId($patientId);
            $appointments = $this->appointmentRepository->findByPatient($objectId);
            
            // Sort appointments by scheduled date (upcoming first)
            usort($appointments, function($a, $b) {
                return $a->getScheduledAt()->toDateTime() <=> $b->getScheduledAt()->toDateTime();
            });

            $appointmentData = array_map(function($appointment) {
                return $appointment->toArray();
            }, $appointments);

            // Log the access
            $this->auditLogService->log(
                $user,
                'patient_portal_appointments',
                [
                    'action' => 'view_own_appointments',
                    'patientId' => (string)$patientId,
                    'appointmentCount' => count($appointmentData)
                ]
            );

            return $this->json([
                'success' => true,
                'data' => $appointmentData
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to retrieve appointments: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get upcoming appointments for patient dashboard
     */
    #[Route('/appointments/upcoming', name: 'patient_portal_upcoming_appointments', methods: ['GET'])]
    public function getUpcomingAppointments(UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied. Patient access required.'
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $objectId = new ObjectId($patientId);
            $fromDate = new \DateTime(); // From now
            $upcomingAppointments = $this->appointmentRepository->findUpcoming($fromDate, $objectId, 5);

            $appointmentData = array_map(function($appointment) {
                return $appointment->toArray();
            }, $upcomingAppointments);

            return $this->json([
                'success' => true,
                'data' => $appointmentData
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming appointments: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List messages for the logged-in patient
     */
    #[Route('/messages', name: 'patient_portal_messages', methods: ['GET'])]
    public function getMyMessages(UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied. Patient access required.'
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $objectId = new ObjectId($patientId);
            $messages = $this->messageRepository->findByPatient($objectId, 200);
            $data = array_map(fn($m) => $m->toArray(), $messages);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to retrieve messages: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->auditLogService->log(
            $user,
            'patient_portal_messages_list',
            [
                'action' => 'list_messages',
                'entityType' => 'Patient',
                'entityId' => (string)$patientId
            ]
        );

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Send a message from the logged-in patient to staff
     */
    #[Route('/messages', name: 'patient_portal_send_message', methods: ['POST'])]
    public function sendMessage(Request $request, UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied. Patient access required.'
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $subject = $payload['subject'] ?? null;
        $body = trim($payload['body'] ?? '');
        $recipientRoles = $payload['recipientRoles'] ?? ['ROLE_DOCTOR', 'ROLE_NURSE'];

        if ($body === '') {
            return $this->json([
                'success' => false,
                'message' => 'Message body is required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $message = new \App\Document\Message();
        $message->setPatientId($patientId);
        $message->setSenderUserId(method_exists($user, 'getId') ? (string)$user->getId() : $user->getUserIdentifier());
        $message->setSenderName(method_exists($user, 'getUsername') ? $user->getUsername() : $user->getUserIdentifier());
        $message->setSenderRoles($user->getRoles());
        $message->setDirection('to_staff');
        $message->setRecipientRoles($recipientRoles);
        $message->setSubject($subject);
        $message->setBody($body);

        try {
            $this->messageRepository->save($message, true);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->auditLogService->log(
            $user,
            'patient_portal_message_send',
            [
                'action' => 'send_message',
                'entityType' => 'Patient',
                'entityId' => (string)$patientId,
                'recipientRoles' => $recipientRoles
            ]
        );

        return $this->json([
            'success' => true,
            'data' => $message->toArray()
        ], Response::HTTP_CREATED);
    }

    /**
     * Health check for patient portal
     */
    #[Route('/health', name: 'patient_portal_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'service' => 'patient-portal'
        ]);
    }
}
