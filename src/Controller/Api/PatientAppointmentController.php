<?php

namespace App\Controller\Api;

use App\Document\Appointment;
use App\Repository\AppointmentRepository;
use App\Service\AuditLogService;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/patient-portal/appointments')]
class PatientAppointmentController extends AbstractController
{
    private DocumentManager $documentManager;
    private AppointmentRepository $appointmentRepository;
    private AuditLogService $auditLogService;
    private ValidatorInterface $validator;

    public function __construct(
        DocumentManager $documentManager,
        AppointmentRepository $appointmentRepository,
        AuditLogService $auditLogService,
        ValidatorInterface $validator
    ) {
        $this->documentManager = $documentManager;
        $this->appointmentRepository = $appointmentRepository;
        $this->auditLogService = $auditLogService;
        $this->validator = $validator;
    }

    /**
     * Get available appointment slots
     */
    #[Route('/available-slots', name: 'patient_portal_available_slots', methods: ['GET'])]
    public function getAvailableSlots(Request $request, UserInterface $user): JsonResponse
    {
        if (!$this->isPatientUser($user)) {
            return $this->accessDeniedResponse();
        }

        // Optional date range parameters
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');
        $doctorId = $request->query->get('doctor_id');
        
        if ($startDate) {
            try {
                $startDateTime = new \DateTime($startDate);
                $startMongoDate = new UTCDateTime($startDateTime);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid start date format. Use YYYY-MM-DD.'
                ], Response::HTTP_BAD_REQUEST);
            }
        } else {
            // Default to today
            $startDateTime = new \DateTime('today');
            $startMongoDate = new UTCDateTime($startDateTime);
        }
        
        if ($endDate) {
            try {
                $endDateTime = new \DateTime($endDate);
                $endMongoDate = new UTCDateTime($endDateTime);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid end date format. Use YYYY-MM-DD.'
                ], Response::HTTP_BAD_REQUEST);
            }
        } else {
            // Default to 14 days from start date
            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+14 days');
            $endMongoDate = new UTCDateTime($endDateTime);
        }

        // Business hours (9 AM to 5 PM)
        $startHour = 9;
        $endHour = 17;
        
        // Generate available slots (30-minute intervals)
        $availableSlots = [];
        $currentDate = clone $startDateTime;
        $endDateTime->setTime(23, 59, 59); // End of the day
        
        // Get booked appointments in this date range
        $bookedAppointments = $this->appointmentRepository->findByDateRange($startMongoDate, $endMongoDate);
        $bookedSlots = [];
        
        // Mark booked slots
        foreach ($bookedAppointments as $appointment) {
            $dateTime = $appointment->getScheduledAt()->toDateTime();
            $dateString = $dateTime->format('Y-m-d H:i');
            $bookedSlots[$dateString] = true;
            
            // Also consider a doctor filter
            if ($doctorId && $appointment->getDoctorId()) {
                $appointmentDoctorId = (string)$appointment->getDoctorId();
                if ($appointmentDoctorId !== $doctorId) {
                    // This slot is booked by a different doctor, so it might still be available
                    // for the requested doctor
                    unset($bookedSlots[$dateString]);
                }
            }
        }
        
        // Generate available slots
        while ($currentDate <= $endDateTime) {
            // Skip weekends
            $dayOfWeek = (int)$currentDate->format('N');
            if ($dayOfWeek < 6) { // 6 = Saturday, 7 = Sunday
                for ($hour = $startHour; $hour < $endHour; $hour++) {
                    for ($minute = 0; $minute < 60; $minute += 30) {
                        $currentDate->setTime($hour, $minute);
                        $dateString = $currentDate->format('Y-m-d H:i');
                        
                        // Only add if not already booked
                        if (!isset($bookedSlots[$dateString])) {
                            $availableSlots[] = [
                                'dateTime' => $dateString,
                                'timestamp' => $currentDate->getTimestamp() * 1000, // JavaScript timestamp
                                'formattedDate' => $currentDate->format('l, F j, Y'),
                                'formattedTime' => $currentDate->format('g:i A')
                            ];
                        }
                    }
                }
            }
            
            // Move to next day
            $currentDate->modify('+1 day');
            $currentDate->setTime(0, 0);
        }
        
        $this->auditLogService->log(
            $user,
            'patient_portal_appointment_slots',
            [
                'action' => 'view_available_slots',
                'patientId' => (string)$user->getPatientId(),
                'startDate' => $startDateTime->format('Y-m-d'),
                'endDate' => $endDateTime->format('Y-m-d'),
                'doctorId' => $doctorId ?? 'any'
            ]
        );
        
        return $this->json([
            'success' => true,
            'data' => [
                'availableSlots' => $availableSlots,
                'dateRange' => [
                    'start' => $startDateTime->format('Y-m-d'),
                    'end' => $endDateTime->format('Y-m-d')
                ]
            ]
        ]);
    }

    /**
     * Book a new appointment for the patient
     */
    #[Route('/book', name: 'patient_portal_book_appointment', methods: ['POST'])]
    public function bookAppointment(Request $request, UserInterface $user): JsonResponse
    {
        if (!$this->isPatientUser($user)) {
            return $this->accessDeniedResponse();
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        // Validate required fields
        if (!isset($data['dateTime']) || empty($data['dateTime'])) {
            return $this->json([
                'success' => false,
                'message' => 'Appointment date and time are required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['reason']) || empty($data['reason'])) {
            return $this->json([
                'success' => false,
                'message' => 'Appointment reason is required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Parse date time
        try {
            $scheduledDateTime = new \DateTime($data['dateTime']);
            $scheduledMongoDate = new UTCDateTime($scheduledDateTime);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid date/time format. Use YYYY-MM-DD HH:MM.'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Check if the slot is available
        $conflictingAppointments = $this->appointmentRepository->findByDateTimeRange(
            $scheduledMongoDate,
            new UTCDateTime($scheduledDateTime->modify('+30 minutes'))
        );
        
        if (count($conflictingAppointments) > 0) {
            return $this->json([
                'success' => false,
                'message' => 'This appointment slot is no longer available. Please select a different time.'
            ], Response::HTTP_CONFLICT);
        }

        // Create new appointment
        $appointment = new Appointment();
        $appointment->setPatientId(new ObjectId($patientId));
        $appointment->setScheduledAt($scheduledMongoDate);
        $appointment->setStatus('scheduled');
        $appointment->setReason($data['reason']);
        $appointment->setNotes($data['notes'] ?? '');
        
        // Optional doctor assignment
        if (isset($data['doctorId']) && !empty($data['doctorId'])) {
            try {
                $appointment->setDoctorId(new ObjectId($data['doctorId']));
            } catch (\Exception $e) {
                // Invalid ObjectId, just don't set it
            }
        }
        
        // Add additional metadata
        $appointment->setCreatedBy(new ObjectId($user->getId()));
        $appointment->setCreatedAt(new UTCDateTime());
        $appointment->setPatientRequestedAppointment(true);
        
        // Validate appointment
        $errors = $this->validator->validate($appointment);
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

        // Save appointment
        try {
            $this->documentManager->persist($appointment);
            $this->documentManager->flush();
            
            $this->auditLogService->log(
                $user,
                'patient_portal_appointment_book',
                [
                    'action' => 'book_appointment',
                    'patientId' => (string)$patientId,
                    'appointmentId' => (string)$appointment->getId(),
                    'scheduledAt' => $scheduledDateTime->format('Y-m-d H:i')
                ]
            );
            
            return $this->json([
                'success' => true,
                'message' => 'Appointment booked successfully',
                'data' => $appointment->toArray()
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to book appointment: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel an appointment
     */
    #[Route('/{id}/cancel', name: 'patient_portal_cancel_appointment', methods: ['POST'])]
    public function cancelAppointment(string $id, Request $request, UserInterface $user): JsonResponse
    {
        if (!$this->isPatientUser($user)) {
            return $this->accessDeniedResponse();
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Find the appointment
        try {
            $appointment = $this->appointmentRepository->find($id);
            
            if (!$appointment) {
                return $this->json([
                    'success' => false,
                    'message' => 'Appointment not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Ensure the appointment belongs to this patient
            $appointmentPatientId = (string)$appointment->getPatientId();
            if ($appointmentPatientId !== $patientId) {
                return $this->json([
                    'success' => false,
                    'message' => 'You do not have permission to cancel this appointment'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Check if appointment can be canceled (not in the past)
            $now = new \DateTime();
            $appointmentDateTime = $appointment->getScheduledAt()->toDateTime();
            if ($appointmentDateTime < $now) {
                return $this->json([
                    'success' => false,
                    'message' => 'Cannot cancel appointments in the past'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Update appointment status
            $appointment->setStatus('canceled');
            $appointment->setCanceledAt(new UTCDateTime());
            $appointment->setCanceledBy(new ObjectId($user->getId()));
            $appointment->setCancellationReason($request->request->get('reason', 'Canceled by patient'));
            
            // Save changes
            $this->documentManager->flush();
            
            $this->auditLogService->log(
                $user,
                'patient_portal_appointment_cancel',
                [
                    'action' => 'cancel_appointment',
                    'patientId' => (string)$patientId,
                    'appointmentId' => (string)$appointment->getId(),
                    'scheduledAt' => $appointmentDateTime->format('Y-m-d H:i')
                ]
            );
            
            return $this->json([
                'success' => true,
                'message' => 'Appointment canceled successfully',
                'data' => $appointment->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to cancel appointment: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reschedule an appointment
     */
    #[Route('/{id}/reschedule', name: 'patient_portal_reschedule_appointment', methods: ['POST'])]
    public function rescheduleAppointment(string $id, Request $request, UserInterface $user): JsonResponse
    {
        if (!$this->isPatientUser($user)) {
            return $this->accessDeniedResponse();
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json([
                'success' => false,
                'message' => 'No patient record associated with this account.'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['dateTime']) || empty($data['dateTime'])) {
            return $this->json([
                'success' => false,
                'message' => 'New appointment date and time are required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find the appointment
        try {
            $appointment = $this->appointmentRepository->find($id);
            
            if (!$appointment) {
                return $this->json([
                    'success' => false,
                    'message' => 'Appointment not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Ensure the appointment belongs to this patient
            $appointmentPatientId = (string)$appointment->getPatientId();
            if ($appointmentPatientId !== $patientId) {
                return $this->json([
                    'success' => false,
                    'message' => 'You do not have permission to reschedule this appointment'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Parse new date time
            try {
                $newScheduledDateTime = new \DateTime($data['dateTime']);
                $newScheduledMongoDate = new UTCDateTime($newScheduledDateTime);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid date/time format. Use YYYY-MM-DD HH:MM.'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Check if the slot is available
            $conflictingAppointments = $this->appointmentRepository->findByDateTimeRange(
                $newScheduledMongoDate,
                new UTCDateTime($newScheduledDateTime->modify('+30 minutes'))
            );
            
            foreach ($conflictingAppointments as $conflictingAppointment) {
                // Skip the current appointment
                if ((string)$conflictingAppointment->getId() === $id) {
                    continue;
                }
                
                return $this->json([
                    'success' => false,
                    'message' => 'This appointment slot is not available. Please select a different time.'
                ], Response::HTTP_CONFLICT);
            }
            
            // Store original date for auditing
            $originalDateTime = $appointment->getScheduledAt()->toDateTime()->format('Y-m-d H:i');
            
            // Update appointment
            $appointment->setScheduledAt($newScheduledMongoDate);
            $appointment->setRescheduled(true);
            $appointment->setRescheduledAt(new UTCDateTime());
            $appointment->setRescheduledBy(new ObjectId($user->getId()));
            
            if (isset($data['reason']) && !empty($data['reason'])) {
                $appointment->setReason($data['reason']);
            }
            
            if (isset($data['notes'])) {
                $appointment->setNotes($data['notes']);
            }
            
            // Save changes
            $this->documentManager->flush();
            
            $this->auditLogService->log(
                $user,
                'patient_portal_appointment_reschedule',
                [
                    'action' => 'reschedule_appointment',
                    'patientId' => (string)$patientId,
                    'appointmentId' => (string)$appointment->getId(),
                    'originalDateTime' => $originalDateTime,
                    'newDateTime' => $newScheduledDateTime->format('Y-m-d H:i')
                ]
            );
            
            return $this->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully',
                'data' => $appointment->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to reschedule appointment: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Utility method to check if user is a patient
     */
    private function isPatientUser(UserInterface $user): bool
    {
        return $user->isPatient();
    }

    /**
     * Utility method for access denied response
     */
    private function accessDeniedResponse(): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => 'Access denied. Patient access required.'
        ], Response::HTTP_FORBIDDEN);
    }
}