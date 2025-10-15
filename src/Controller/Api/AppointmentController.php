<?php

namespace App\Controller\Api;

use App\Document\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/appointments')]
class AppointmentController extends AbstractController
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly PatientRepository $patientRepository,
        private readonly AuditLogService $auditLogService
    ) {
    }

    #[Route('', name: 'appointment_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        // All authenticated users can view appointments
        $patientIdParam = $request->query->get('patientId');
        $fromParam = $request->query->get('from');

        $patientId = null;
        if ($patientIdParam) {
            try {
                $patientId = new ObjectId($patientIdParam);
            } catch (\Exception $e) {
                return $this->json([
                    'message' => 'Invalid patientId provided'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $fromDate = null;
        if ($fromParam) {
            try {
                $fromDate = new DateTimeImmutable($fromParam);
            } catch (\Exception $exception) {
                return $this->json([
                    'message' => 'Invalid from date provided'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $appointments = $this->appointmentRepository->findUpcoming($fromDate, $patientId);
        $data = array_map(static fn(Appointment $appointment) => $appointment->toArray(), $appointments);

        return $this->json([
            'appointments' => $data,
        ]);
    }

    #[Route('/calendar', name: 'appointment_calendar', methods: ['GET'])]
    public function calendar(Request $request): JsonResponse
    {
        // All authenticated users can view calendar
        $month = $request->query->get('month', date('Y-m'));
        
        try {
            // Parse the month parameter which should be in YYYY-MM format
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                throw new \InvalidArgumentException('Month must be in YYYY-MM format');
            }
            
            $startDate = new DateTimeImmutable("$month-01");
            $endDate = $startDate->modify('last day of this month')->setTime(23, 59, 59);
            
            $appointments = $this->appointmentRepository->findByDateRange($startDate, $endDate);
            $data = array_map(static fn(Appointment $appointment) => $appointment->toArray(), $appointments);

            return $this->json([
                'appointments' => $data,
                'month' => $month,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Invalid date parameters: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('', name: 'appointment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->assertReceptionistOnly();

        $payload = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], Response::HTTP_BAD_REQUEST);
        }

        $patientIdValue = $payload['patientId'] ?? null;
        $scheduledAtValue = $payload['scheduledAt'] ?? null;
        $notes = $payload['notes'] ?? null;

        if (!$patientIdValue || !$scheduledAtValue) {
            return $this->json([
                'message' => 'patientId and scheduledAt are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $patientId = new ObjectId($patientIdValue);
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Invalid patientId provided'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $scheduledDateTime = new DateTimeImmutable($scheduledAtValue);
        } catch (\Exception $exception) {
            return $this->json([
                'message' => 'Invalid scheduledAt value provided'
            ], Response::HTTP_BAD_REQUEST);
        }

        $patient = $this->patientRepository->findById($patientId);
        if (!$patient) {
            return $this->json([
                'message' => 'Patient not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $appointment = new Appointment();
        $appointment->setPatientId($patientId);
        $appointment->setPatientFullName(sprintf('%s %s', $patient->getFirstName(), $patient->getLastName()));
        $appointment->setScheduledAt(new UTCDateTime($scheduledDateTime));
        $appointment->setNotes($notes);
        $appointment->setCreatedBy($this->getUser()?->getUserIdentifier() ?? 'unknown');

        $this->appointmentRepository->save($appointment);

        $this->auditLogService->log(
            $this->getUser(),
            'APPOINTMENT_CREATE',
            [
                'description' => 'Created new appointment',
                'entityId' => (string) $appointment->getId(),
                'entityType' => 'Appointment',
                'patientId' => (string) $appointment->getPatientId(),
                'scheduledAt' => $appointment->getScheduledAt()->toDateTime()->format(DateTimeImmutable::ATOM),
            ]
        );

        return $this->json($appointment->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/patient/{patientId}', name: 'appointment_by_patient', methods: ['GET'])]
    public function getPatientAppointments(string $patientId): JsonResponse
    {
        // Doctor, nurse, and admin roles can also view patient appointments
        try {
            $objectId = new ObjectId($patientId);
            
            // Check if patient exists
            $patient = $this->patientRepository->findByIdString($patientId);
            if (!$patient) {
                return $this->json([
                    'message' => 'Patient not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $appointments = $this->appointmentRepository->findByPatient($objectId);
            $data = array_map(static fn(Appointment $appointment) => $appointment->toArray(), $appointments);
            
            return $this->json([
                'appointments' => $data,
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Invalid patient ID format'
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'appointment_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        // Log the incoming request for debugging
        error_log("Appointment update request - ID: $id, Content: " . $request->getContent());
        
        $this->assertReceptionistOnly();
        
        try {
            $objectId = new ObjectId($id);
            $appointment = $this->appointmentRepository->findById((string) $objectId);
            
            if (!$appointment) {
                return $this->json([
                    'message' => 'Appointment not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            
            if (isset($data['patientId'])) {
                error_log("Looking up patient with ID: " . $data['patientId']);
                $patient = $this->patientRepository->findByIdString($data['patientId']);
                if (!$patient) {
                    error_log("Patient not found with ID: " . $data['patientId']);
                    return $this->json([
                        'message' => 'Patient not found'
                    ], Response::HTTP_BAD_REQUEST);
                }
                
                error_log("Patient found: " . $patient->getFullName());
                $appointment->setPatientId($data['patientId']);
                $appointment->setPatientFullName($patient->getFullName());
            }

            if (isset($data['scheduledAt'])) {
                $scheduledAt = new DateTimeImmutable($data['scheduledAt']);
                $appointment->setScheduledAt($scheduledAt);
            }

            if (isset($data['notes'])) {
                $appointment->setNotes($data['notes']);
            }

            error_log("Updating appointment timestamp and saving...");
            $appointment->touchUpdatedAt();
            
            try {
                $this->appointmentRepository->save($appointment);
                error_log("Appointment saved successfully");
            } catch (\Exception $saveError) {
                error_log("Error saving appointment: " . $saveError->getMessage());
                throw $saveError;
            }

            $this->auditLogService->log(
                $this->getUser(),
                'APPOINTMENT_UPDATE',
                [
                    'description' => 'Updated appointment',
                    'entityId' => (string) $appointment->getId(),
                    'entityType' => 'Appointment',
                    'patientId' => (string) $appointment->getPatientId(),
                    'scheduledAt' => $appointment->getScheduledAt()->toDateTime()->format(DateTimeImmutable::ATOM),
                ]
            );

            return $this->json($appointment->toArray());
            
        } catch (\Exception $e) {
            // Log the actual error for debugging
            error_log("Appointment update error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return $this->json([
                'message' => 'Failed to update appointment: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'appointment_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->assertReceptionistOnly();
        
        try {
            $objectId = new ObjectId($id);
            $appointment = $this->appointmentRepository->findById((string) $objectId);
            
            if (!$appointment) {
                return $this->json([
                    'message' => 'Appointment not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $this->auditLogService->log(
                $this->getUser(),
                'APPOINTMENT_DELETE',
                [
                    'description' => 'Deleted appointment',
                    'entityId' => (string) $appointment->getId(),
                    'entityType' => 'Appointment',
                    'patientId' => (string) $appointment->getPatientId(),
                    'scheduledAt' => $appointment->getScheduledAt()->toDateTime()->format(DateTimeImmutable::ATOM),
                ]
            );

            $this->appointmentRepository->remove($appointment);

            return $this->json([
                'message' => 'Appointment deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Invalid appointment ID format'
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    
    private function assertReceptionistOnly(): void
    {
        // Log user information for debugging
        $user = $this->getUser();
        error_log("Checking permissions for user: " . ($user ? $user->getUserIdentifier() : 'null'));
        error_log("User roles: " . json_encode($user ? $user->getRoles() : []));
        
        // Allow receptionists, admins, and doctors to create/edit/delete appointments
        if (!$this->isGranted('ROLE_RECEPTIONIST') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_DOCTOR')) {
            error_log("Access denied - user does not have required roles");
            throw $this->createAccessDeniedException('Appointment management is restricted to receptionists, admins, and doctors.');
        }
        
        error_log("Access granted to user");
    }
}
