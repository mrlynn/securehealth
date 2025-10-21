<?php

namespace App\Controller\Api;

use App\Document\Prescription;
use App\Document\PrescriptionRefill;
use App\Repository\PrescriptionRepository;
use App\Repository\PrescriptionRefillRepository;
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

#[Route('/api/patient-portal/prescriptions')]
class PatientPrescriptionController extends AbstractController
{
    private DocumentManager $documentManager;
    private AuditLogService $auditLogService;
    private ValidatorInterface $validator;
    private PrescriptionRepository $prescriptionRepository;
    private PrescriptionRefillRepository $prescriptionRefillRepository;

    public function __construct(
        DocumentManager $documentManager,
        AuditLogService $auditLogService,
        ValidatorInterface $validator,
        PrescriptionRepository $prescriptionRepository,
        PrescriptionRefillRepository $prescriptionRefillRepository
    ) {
        $this->documentManager = $documentManager;
        $this->auditLogService = $auditLogService;
        $this->validator = $validator;
        $this->prescriptionRepository = $prescriptionRepository;
        $this->prescriptionRefillRepository = $prescriptionRefillRepository;
    }

    /**
     * Get prescriptions for the authenticated patient
     */
    #[Route('', name: 'patient_prescriptions_list', methods: ['GET'])]
    public function getPrescriptions(UserInterface $user): JsonResponse
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

        try {
            // In a real application, this would fetch from a Prescription collection
            // For demo purposes, we'll create some mock prescription data
            $prescriptions = $this->getMockPrescriptions($patientId);
            
            $this->auditLogService->log(
                $user,
                'patient_portal_prescriptions_view',
                [
                    'action' => 'view_prescriptions',
                    'patientId' => (string)$patientId,
                    'prescriptionCount' => count($prescriptions)
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $prescriptions
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving prescriptions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific prescription details
     */
    #[Route('/{id}', name: 'patient_prescription_detail', methods: ['GET'])]
    public function getPrescription(string $id, UserInterface $user): JsonResponse
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

        try {
            // In a real application, this would fetch from a Prescription collection
            // For demo purposes, we'll use our mock data
            $prescriptions = $this->getMockPrescriptions($patientId);
            $prescription = null;
            
            foreach ($prescriptions as $p) {
                if ($p['id'] === $id) {
                    $prescription = $p;
                    break;
                }
            }
            
            if (!$prescription) {
                return $this->json([
                    'success' => false,
                    'message' => 'Prescription not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Add refill history for detail view
            $prescription['refillHistory'] = [
                [
                    'id' => 'refill1',
                    'requestedDate' => (new \DateTime('-60 days'))->format('Y-m-d'),
                    'status' => 'completed',
                    'approvedDate' => (new \DateTime('-58 days'))->format('Y-m-d'),
                    'approvedBy' => 'Dr. Smith',
                    'quantityDispensed' => $prescription['quantity'],
                    'notes' => 'Prescription refilled as prescribed'
                ],
                [
                    'id' => 'refill2',
                    'requestedDate' => (new \DateTime('-30 days'))->format('Y-m-d'),
                    'status' => 'completed',
                    'approvedDate' => (new \DateTime('-29 days'))->format('Y-m-d'),
                    'approvedBy' => 'Dr. Johnson',
                    'quantityDispensed' => $prescription['quantity'],
                    'notes' => ''
                ]
            ];
            
            $this->auditLogService->log(
                $user,
                'patient_portal_prescription_view',
                [
                    'action' => 'view_prescription_details',
                    'patientId' => (string)$patientId,
                    'prescriptionId' => $id
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $prescription
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving prescription details: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Request a refill for a prescription
     */
    #[Route('/{id}/request-refill', name: 'patient_prescription_refill_request', methods: ['POST'])]
    public function requestRefill(string $id, Request $request, UserInterface $user): JsonResponse
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

        try {
            // In a real application, this would fetch and validate the prescription
            // Check if the prescription exists in our mock data
            $prescriptions = $this->getMockPrescriptions($patientId);
            $prescription = null;
            
            foreach ($prescriptions as $p) {
                if ($p['id'] === $id) {
                    $prescription = $p;
                    break;
                }
            }
            
            if (!$prescription) {
                return $this->json([
                    'success' => false,
                    'message' => 'Prescription not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if refills are available
            if ($prescription['refillsRemaining'] <= 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'No refills remaining for this prescription.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get optional notes from request
            $data = json_decode($request->getContent(), true) ?? [];
            $notes = $data['notes'] ?? '';
            $pharmacyId = $data['pharmacyId'] ?? null;
            
            // Create a refill request record (in a real app)
            // For demo, we just return a success response with mock data
            $refillRequest = [
                'id' => 'refill' . uniqid(),
                'prescriptionId' => $id,
                'patientId' => $patientId,
                'requestedDate' => (new \DateTime())->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'notes' => $notes,
                'pharmacyId' => $pharmacyId,
                'pharmacyName' => $pharmacyId ? 'Local Pharmacy' : $prescription['pharmacy'], 
                'medication' => $prescription['medication'],
                'dosage' => $prescription['dosage'],
                'expectedProcessingTime' => '24-48 hours'
            ];
            
            $this->auditLogService->log(
                $user,
                'patient_portal_prescription_refill_request',
                [
                    'action' => 'request_refill',
                    'patientId' => (string)$patientId,
                    'prescriptionId' => $id,
                    'pharmacyId' => $pharmacyId
                ]
            );
            
            return $this->json([
                'success' => true,
                'message' => 'Refill request submitted successfully',
                'data' => $refillRequest
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error processing refill request: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get refill requests for the patient
     */
    #[Route('/refill-requests', name: 'patient_refill_requests_list', methods: ['GET'])]
    public function getRefillRequests(UserInterface $user): JsonResponse
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

        try {
            // Mock refill requests data
            $refillRequests = [
                [
                    'id' => 'refill123',
                    'prescriptionId' => 'rx1',
                    'medication' => 'Lisinopril 10mg',
                    'requestedDate' => (new \DateTime('-2 days'))->format('Y-m-d'),
                    'status' => 'pending',
                    'pharmacy' => 'Main Street Pharmacy',
                    'expectedProcessingTime' => '24-48 hours'
                ],
                [
                    'id' => 'refill124',
                    'prescriptionId' => 'rx3',
                    'medication' => 'Metformin 500mg',
                    'requestedDate' => (new \DateTime('-5 days'))->format('Y-m-d'),
                    'status' => 'approved',
                    'approvedDate' => (new \DateTime('-3 days'))->format('Y-m-d'),
                    'pharmacy' => 'Valley Pharmacy',
                    'pickupReady' => true
                ],
                [
                    'id' => 'refill122',
                    'prescriptionId' => 'rx2',
                    'medication' => 'Atorvastatin 20mg',
                    'requestedDate' => (new \DateTime('-10 days'))->format('Y-m-d'),
                    'status' => 'completed',
                    'approvedDate' => (new \DateTime('-9 days'))->format('Y-m-d'),
                    'filledDate' => (new \DateTime('-8 days'))->format('Y-m-d'),
                    'pharmacy' => 'Main Street Pharmacy'
                ],
                [
                    'id' => 'refill121',
                    'prescriptionId' => 'rx5',
                    'medication' => 'Amoxicillin 500mg',
                    'requestedDate' => (new \DateTime('-15 days'))->format('Y-m-d'),
                    'status' => 'denied',
                    'deniedDate' => (new \DateTime('-14 days'))->format('Y-m-d'),
                    'deniedReason' => 'No refills remaining. Please schedule an appointment for evaluation.',
                    'pharmacy' => 'Main Street Pharmacy'
                ]
            ];
            
            $this->auditLogService->log(
                $user,
                'patient_portal_refill_requests_view',
                [
                    'action' => 'view_refill_requests',
                    'patientId' => (string)$patientId,
                    'requestCount' => count($refillRequests)
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $refillRequests
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving refill requests: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pharmacies for refill requests
     */
    #[Route('/pharmacies', name: 'patient_pharmacies_list', methods: ['GET'])]
    public function getPharmacies(UserInterface $user): JsonResponse
    {
        if (!$this->isPatientUser($user)) {
            return $this->accessDeniedResponse();
        }

        try {
            // Mock pharmacy data
            $pharmacies = [
                [
                    'id' => 'pharm1',
                    'name' => 'Main Street Pharmacy',
                    'address' => '123 Main Street, Anytown, USA',
                    'phone' => '555-123-4567',
                    'hours' => 'Mon-Fri 9am-9pm, Sat 9am-6pm, Sun 10am-4pm',
                    'isPreferred' => true
                ],
                [
                    'id' => 'pharm2',
                    'name' => 'Valley Pharmacy',
                    'address' => '456 Valley Road, Anytown, USA',
                    'phone' => '555-987-6543',
                    'hours' => 'Mon-Sat 8am-8pm, Sun Closed',
                    'isPreferred' => false
                ],
                [
                    'id' => 'pharm3',
                    'name' => 'Central Drug Store',
                    'address' => '789 Central Avenue, Anytown, USA',
                    'phone' => '555-456-7890',
                    'hours' => '24 Hours',
                    'isPreferred' => false
                ],
                [
                    'id' => 'pharm4',
                    'name' => 'HealthRx Pharmacy',
                    'address' => '101 Health Boulevard, Anytown, USA',
                    'phone' => '555-222-3333',
                    'hours' => 'Mon-Fri 9am-7pm, Sat-Sun 10am-3pm',
                    'isPreferred' => false
                ]
            ];
            
            return $this->json([
                'success' => true,
                'data' => $pharmacies
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving pharmacies: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate mock prescriptions for demo purposes
     */
    private function getMockPrescriptions(string $patientId): array
    {
        // Create dates for the prescriptions
        $today = new \DateTime();
        $thirtyDaysAgo = new \DateTime('-30 days');
        $sixtyDaysAgo = new \DateTime('-60 days');
        $ninetyDaysAgo = new \DateTime('-90 days');
        
        // Mock data for prescriptions
        return [
            [
                'id' => 'rx1',
                'patientId' => $patientId,
                'medication' => 'Lisinopril 10mg',
                'dosage' => '1 tablet daily',
                'quantity' => 30,
                'prescribedDate' => $thirtyDaysAgo->format('Y-m-d'),
                'expirationDate' => (clone $thirtyDaysAgo)->modify('+1 year')->format('Y-m-d'),
                'prescribedBy' => 'Dr. Smith',
                'refillsTotal' => 3,
                'refillsRemaining' => 2,
                'lastFilled' => $thirtyDaysAgo->format('Y-m-d'),
                'nextRefillDate' => (clone $thirtyDaysAgo)->modify('+30 days')->format('Y-m-d'),
                'instructions' => 'Take 1 tablet by mouth once daily for high blood pressure.',
                'pharmacy' => 'Main Street Pharmacy',
                'status' => 'active',
                'category' => 'cardiovascular',
                'isControlled' => false,
                'hasRefillsRemaining' => true
            ],
            [
                'id' => 'rx2',
                'patientId' => $patientId,
                'medication' => 'Atorvastatin 20mg',
                'dosage' => '1 tablet daily at bedtime',
                'quantity' => 30,
                'prescribedDate' => $sixtyDaysAgo->format('Y-m-d'),
                'expirationDate' => (clone $sixtyDaysAgo)->modify('+1 year')->format('Y-m-d'),
                'prescribedBy' => 'Dr. Smith',
                'refillsTotal' => 3,
                'refillsRemaining' => 1,
                'lastFilled' => (clone $today)->modify('-10 days')->format('Y-m-d'),
                'nextRefillDate' => (clone $today)->modify('+20 days')->format('Y-m-d'),
                'instructions' => 'Take 1 tablet by mouth once daily at bedtime for high cholesterol.',
                'pharmacy' => 'Main Street Pharmacy',
                'status' => 'active',
                'category' => 'cardiovascular',
                'isControlled' => false,
                'hasRefillsRemaining' => true
            ],
            [
                'id' => 'rx3',
                'patientId' => $patientId,
                'medication' => 'Metformin 500mg',
                'dosage' => '1 tablet twice daily with meals',
                'quantity' => 60,
                'prescribedDate' => $ninetyDaysAgo->format('Y-m-d'),
                'expirationDate' => (clone $ninetyDaysAgo)->modify('+1 year')->format('Y-m-d'),
                'prescribedBy' => 'Dr. Johnson',
                'refillsTotal' => 5,
                'refillsRemaining' => 3,
                'lastFilled' => (clone $today)->modify('-15 days')->format('Y-m-d'),
                'nextRefillDate' => (clone $today)->modify('+15 days')->format('Y-m-d'),
                'instructions' => 'Take 1 tablet by mouth twice daily with meals for diabetes.',
                'pharmacy' => 'Valley Pharmacy',
                'status' => 'active',
                'category' => 'endocrine',
                'isControlled' => false,
                'hasRefillsRemaining' => true
            ],
            [
                'id' => 'rx4',
                'patientId' => $patientId,
                'medication' => 'Hydrocodone/Acetaminophen 5-325mg',
                'dosage' => '1 tablet every 6 hours as needed for pain',
                'quantity' => 20,
                'prescribedDate' => (clone $today)->modify('-10 days')->format('Y-m-d'),
                'expirationDate' => (clone $today)->modify('+10 days')->format('Y-m-d'),
                'prescribedBy' => 'Dr. Williams',
                'refillsTotal' => 0,
                'refillsRemaining' => 0,
                'lastFilled' => (clone $today)->modify('-10 days')->format('Y-m-d'),
                'nextRefillDate' => null,
                'instructions' => 'Take 1 tablet by mouth every 6 hours as needed for pain. Do not exceed 4 tablets in 24 hours.',
                'pharmacy' => 'Main Street Pharmacy',
                'status' => 'active',
                'category' => 'pain',
                'isControlled' => true,
                'hasRefillsRemaining' => false
            ],
            [
                'id' => 'rx5',
                'patientId' => $patientId,
                'medication' => 'Amoxicillin 500mg',
                'dosage' => '1 capsule three times daily',
                'quantity' => 21,
                'prescribedDate' => (clone $today)->modify('-20 days')->format('Y-m-d'),
                'expirationDate' => (clone $today)->modify('-13 days')->format('Y-m-d'),
                'prescribedBy' => 'Dr. Davis',
                'refillsTotal' => 0,
                'refillsRemaining' => 0,
                'lastFilled' => (clone $today)->modify('-20 days')->format('Y-m-d'),
                'nextRefillDate' => null,
                'instructions' => 'Take 1 capsule by mouth three times daily for 7 days until all medication is gone.',
                'pharmacy' => 'Main Street Pharmacy',
                'status' => 'completed',
                'category' => 'antibiotic',
                'isControlled' => false,
                'hasRefillsRemaining' => false
            ]
        ];
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