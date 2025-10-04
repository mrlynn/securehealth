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
#[IsGranted('ROLE_RECEPTIONIST')]
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
        $this->assertReceptionistOnly();
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
    
    private function assertReceptionistOnly(): void
    {
        if ($this->isGranted('ROLE_DOCTOR') || $this->isGranted('ROLE_NURSE') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Scheduling is restricted to receptionists.');
        }
    }
}
