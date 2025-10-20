<?php

namespace App\Controller\Api;

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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    private DocumentManager $documentManager;
    private PatientRepository $patientRepository;
    private UserRepository $userRepository;
    private AppointmentRepository $appointmentRepository;
    private MessageRepository $messageRepository;
    private AuditLogService $auditLogService;

    public function __construct(
        DocumentManager $documentManager,
        PatientRepository $patientRepository,
        UserRepository $userRepository,
        AppointmentRepository $appointmentRepository,
        MessageRepository $messageRepository,
        AuditLogService $auditLogService
    ) {
        $this->documentManager = $documentManager;
        $this->patientRepository = $patientRepository;
        $this->userRepository = $userRepository;
        $this->appointmentRepository = $appointmentRepository;
        $this->messageRepository = $messageRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get dashboard data based on user role
     */
    #[Route('/data', name: 'dashboard_data', methods: ['GET'])]
    public function getDashboardData(UserInterface $user): JsonResponse
    {
        $role = $this->getPrimaryRole($user->getRoles());
        
        switch ($role) {
            case 'admin':
                return $this->getAdminDashboardData($user);
            case 'doctor':
                return $this->getDoctorDashboardData($user);
            case 'nurse':
                return $this->getNurseDashboardData($user);
            case 'receptionist':
                return $this->getReceptionistDashboardData($user);
            case 'patient':
                return $this->getPatientDashboardData($user);
            default:
                return $this->json([
                    'success' => false,
                    'message' => 'Unknown role'
                ], 400);
        }
    }

    /**
     * Admin dashboard data
     */
    private function getAdminDashboardData(UserInterface $user): JsonResponse
    {
        try {
            // Get system statistics
            $totalUsers = $this->userRepository->count([]);
            $totalPatients = $this->patientRepository->count([]);
            $totalAppointments = $this->appointmentRepository->count([]);
            
            // Get recent activity (last 10 audit logs)
            $recentActivity = $this->auditLogService->getRecentActivity(10);

            return $this->json([
                'success' => true,
                'role' => 'admin',
                'data' => [
                    'stats' => [
                        'totalUsers' => $totalUsers,
                        'totalPatients' => $totalPatients,
                        'totalAppointments' => $totalAppointments,
                        'auditLogs' => count($recentActivity)
                    ],
                    'recentActivity' => $recentActivity
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error loading admin dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Doctor dashboard data
     */
    private function getDoctorDashboardData(UserInterface $user): JsonResponse
    {
        try {
            // Find the actual User document from the database
            $userDocument = $this->userRepository->findOneByEmail($user->getEmail());
            if (!$userDocument) {
                throw new \Exception('User document not found in database');
            }
            
            // Get doctor's patients
            $patients = $this->patientRepository->findBy(['primaryDoctorId' => $userDocument->getId()]);
            $patientCount = count($patients);

            // Get today's appointments
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            $tomorrow = clone $today;
            $tomorrow->modify('+1 day');
            
            $todayAppointments = $this->appointmentRepository->findByDateRange($today, $tomorrow);
            $todayAppointmentCount = count($todayAppointments);

            // Get unread messages
            $unreadMessages = $this->messageRepository->findUnreadByUser($userDocument->getId());
            $unreadMessageCount = count($unreadMessages);

            // Get recent patients (last 5)
            $recentPatients = array_slice($patients, 0, 5);

            return $this->json([
                'success' => true,
                'role' => 'doctor',
                'data' => [
                    'stats' => [
                        'myPatients' => $patientCount,
                        'todayAppointments' => $todayAppointmentCount,
                        'unreadMessages' => $unreadMessageCount,
                        'clinicalNotes' => 0 // TODO: Implement clinical notes count
                    ],
                    'recentPatients' => array_map(function($patient) {
                        return [
                            'id' => (string)$patient->getId(),
                            'firstName' => $patient->getFirstName(),
                            'lastName' => $patient->getLastName(),
                            'email' => $patient->getEmail()
                        ];
                    }, $recentPatients)
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error loading doctor dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nurse dashboard data
     */
    private function getNurseDashboardData(UserInterface $user): JsonResponse
    {
        try {
            // Find the actual User document from the database
            $userDocument = $this->userRepository->findOneByEmail($user->getEmail());
            if (!$userDocument) {
                throw new \Exception('User document not found in database');
            }
            
            // Get assigned patients (nurses can see all patients)
            $allPatients = $this->patientRepository->findAll();
            $assignedPatientCount = count($allPatients);

            // Get today's tasks (appointments where nurse is involved)
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            $tomorrow = clone $today;
            $tomorrow->modify('+1 day');
            
            $todayAppointments = $this->appointmentRepository->findByDateRange($today, $tomorrow);
            $todayTaskCount = count($todayAppointments);

            // Get unread messages
            $unreadMessages = $this->messageRepository->findUnreadByUser($userDocument->getId());
            $unreadMessageCount = count($unreadMessages);

            return $this->json([
                'success' => true,
                'role' => 'nurse',
                'data' => [
                    'stats' => [
                        'assignedPatients' => $assignedPatientCount,
                        'todayTasks' => $todayTaskCount,
                        'unreadMessages' => $unreadMessageCount
                    ],
                    'patientTasks' => [
                        [
                            'id' => '1',
                            'type' => 'medication_review',
                            'title' => 'Medication review needed',
                            'patient' => 'John Doe',
                            'priority' => 'high'
                        ],
                        [
                            'id' => '2',
                            'type' => 'vital_signs',
                            'title' => 'Vital signs check',
                            'patient' => 'Jane Smith',
                            'priority' => 'medium'
                        ],
                        [
                            'id' => '3',
                            'type' => 'notes_update',
                            'title' => 'Update patient notes',
                            'patient' => 'Bob Johnson',
                            'priority' => 'low'
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error loading nurse dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receptionist dashboard data
     */
    private function getReceptionistDashboardData(UserInterface $user): JsonResponse
    {
        try {
            // Get today's appointments
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            $tomorrow = clone $today;
            $tomorrow->modify('+1 day');
            
            $todayAppointments = $this->appointmentRepository->findByDateRange($today, $tomorrow);
            $todayAppointmentCount = count($todayAppointments);

            // Get new patients (created in last 7 days)
            $weekAgo = new \DateTime();
            $weekAgo->modify('-7 days');
            $newPatients = $this->patientRepository->findByDateRange($weekAgo, new \DateTime());
            $newPatientCount = count($newPatients);

            return $this->json([
                'success' => true,
                'role' => 'receptionist',
                'data' => [
                    'stats' => [
                        'todayAppointments' => $todayAppointmentCount,
                        'newPatients' => $newPatientCount,
                        'pendingCalls' => 0, // TODO: Implement pending calls
                        'pendingForms' => 0  // TODO: Implement pending forms
                    ],
                    'todaySchedule' => array_map(function($appointment) {
                        return [
                            'id' => (string)$appointment->getId(),
                            'time' => $appointment->getScheduledAt()->toDateTime()->format('H:i'),
                            'doctor' => 'Dr. Smith', // TODO: Get actual doctor name
                            'patient' => 'John Doe'  // TODO: Get actual patient name
                        ];
                    }, array_slice($todayAppointments, 0, 5))
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error loading receptionist dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Patient dashboard data
     */
    private function getPatientDashboardData(UserInterface $user): JsonResponse
    {
        try {
            // Get patient record
            $patientId = $user->getPatientId();
            if (!$patientId) {
                return $this->json([
                    'success' => false,
                    'message' => 'No patient record associated with this account'
                ], 404);
            }

            $patient = $this->patientRepository->find($patientId);
            if (!$patient) {
                return $this->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            // Get upcoming appointments
            $fromDate = new \DateTime();
            $upcomingAppointments = $this->appointmentRepository->findUpcoming($fromDate, new ObjectId($patientId), 5);

            // Get recent messages
            $recentMessages = $this->messageRepository->findByPatient(new ObjectId($patientId), 5);

            return $this->json([
                'success' => true,
                'role' => 'patient',
                'data' => [
                    'patient' => [
                        'id' => (string)$patient->getId(),
                        'firstName' => $patient->getFirstName(),
                        'lastName' => $patient->getLastName(),
                        'email' => $patient->getEmail(),
                        'phoneNumber' => $patient->getPhoneNumber()
                    ],
                    'medications' => $patient->getMedications() ?? [],
                    'insurance' => $patient->getInsuranceDetails(),
                    'upcomingAppointments' => array_map(function($appointment) {
                        return [
                            'id' => (string)$appointment->getId(),
                            'scheduledAt' => $appointment->getScheduledAt()->toDateTime()->format('c'),
                            'doctor' => 'Dr. Smith' // TODO: Get actual doctor name
                        ];
                    }, $upcomingAppointments),
                    'recentMessages' => array_map(function($message) {
                        return [
                            'id' => (string)$message->getId(),
                            'subject' => $message->getSubject(),
                            'body' => $message->getBody(),
                            'createdAt' => $message->getCreatedAt()->toDateTime()->format('c')
                        ];
                    }, $recentMessages)
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error loading patient dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get primary role from user roles
     */
    private function getPrimaryRole(array $roles): string
    {
        if (in_array('ROLE_ADMIN', $roles)) return 'admin';
        if (in_array('ROLE_DOCTOR', $roles)) return 'doctor';
        if (in_array('ROLE_NURSE', $roles)) return 'nurse';
        if (in_array('ROLE_RECEPTIONIST', $roles)) return 'receptionist';
        if (in_array('ROLE_PATIENT', $roles)) return 'patient';
        return 'unknown';
    }
}
