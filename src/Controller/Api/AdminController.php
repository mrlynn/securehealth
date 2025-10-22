<?php

namespace App\Controller\Api;

use App\Command\GeneratePatientDataCommand;
use App\Document\Patient;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private DocumentManager $documentManager,
        private GeneratePatientDataCommand $generatePatientDataCommand,
        private PatientRepository $patientRepository,
        private AuditLogService $auditLogService,
        private MongoDBEncryptionService $encryptionService
    ) {
    }

    #[Route('/regenerate-data', name: 'regenerate_data', methods: ['POST'])]
    public function regenerateData(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $count = $data['count'] ?? 50;
            $clear = $data['clear'] ?? true;

            // Validate count
            if ($count < 1 || $count > 1000) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Count must be between 1 and 1000'
                ], 400);
            }

            // Execute the command
            $input = new ArrayInput([
                '--count' => $count,
                '--clear' => $clear
            ]);
            $output = new BufferedOutput();

            $result = $this->generatePatientDataCommand->run($input, $output);

            if ($result === 0) {
                return new JsonResponse([
                    'success' => true,
                    'message' => "Successfully generated {$count} patient records",
                    'output' => $output->fetch()
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Failed to generate patient data',
                    'output' => $output->fetch()
                ], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        try {
            $patientCount = $this->patientRepository->countByCriteria([]);
            
            // Get some basic statistics
            $patients = $this->patientRepository->findByCriteria([], 100);
            
            $conditions = [];
            $medications = [];
            $ageGroups = ['18-30' => 0, '31-50' => 0, '51-70' => 0, '71+' => 0];
            
            foreach ($patients as $patient) {
                // Count conditions
                $diagnosis = $patient->getDiagnosis();
                if ($diagnosis) {
                    foreach ($diagnosis as $condition) {
                        $conditions[$condition] = ($conditions[$condition] ?? 0) + 1;
                    }
                }
                
                // Count medications
                $meds = $patient->getMedications();
                if ($meds) {
                    foreach ($meds as $medication) {
                        $medications[$medication] = ($medications[$medication] ?? 0) + 1;
                    }
                }
                
                // Count age groups
                $birthDate = $patient->getBirthDate();
                if ($birthDate) {
                    $age = $birthDate->toDateTime()->diff(new \DateTime())->y;
                    if ($age <= 30) {
                        $ageGroups['18-30']++;
                    } elseif ($age <= 50) {
                        $ageGroups['31-50']++;
                    } elseif ($age <= 70) {
                        $ageGroups['51-70']++;
                    } else {
                        $ageGroups['71+']++;
                    }
                }
            }
            
            // Sort by count
            arsort($conditions);
            arsort($medications);
            
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'totalPatients' => $patientCount,
                    'topConditions' => array_slice($conditions, 0, 10, true),
                    'topMedications' => array_slice($medications, 0, 10, true),
                    'ageGroups' => $ageGroups
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/clear-data', name: 'clear_data', methods: ['POST'])]
    public function clearData(): JsonResponse
    {
        try {
            $result = $this->patientRepository->clearAll();
            
            return new JsonResponse([
                'success' => true,
                'message' => "Cleared all patient records"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get patient data for administrative purposes (filtered to exclude medical data)
     * Admins can see basic patient info and insurance details but NOT medical data
     */
    #[Route('/patients', name: 'admin_patients', methods: ['GET'])]
    public function getPatients(Request $request): JsonResponse
    {
        try {
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

            // Convert to array representation with admin role filtering (no medical data)
            $patientsArray = array_map(function(Patient $patient) {
                return $patient->toArray($this->getUser()); // This will apply admin filtering
            }, $patients);

            // Log the administrative access
            $this->auditLogService->log(
                $this->getUser(),
                'ADMIN_PATIENT_LIST',
                [
                    'description' => 'Admin accessed patient list (filtered - no medical data)',
                    'filters' => ['lastName' => $lastName, 'firstName' => $firstName],
                    'count' => count($patients),
                    'accessType' => 'administrative'
                ]
            );

            return $this->json([
                'success' => true,
                'total' => $totalPatients,
                'page' => $page,
                'limit' => $limit,
                'patients' => $patientsArray,
                'note' => 'Admin access - medical data excluded for HIPAA compliance'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error accessing patient data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get individual patient data for administrative purposes (filtered)
     */
    #[Route('/patients/{id}', name: 'admin_patient_show', methods: ['GET'])]
    public function getPatient(string $id): JsonResponse
    {
        try {
            $patient = $this->patientRepository->findById(new \MongoDB\BSON\ObjectId($id));
            
            if (!$patient) {
                return $this->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Log the administrative access
            $this->auditLogService->log(
                $this->getUser(),
                'ADMIN_PATIENT_VIEW',
                [
                    'description' => 'Admin viewed patient details (filtered - no medical data)',
                    'patientId' => $id,
                    'accessType' => 'administrative'
                ]
            );

            return $this->json([
                'success' => true,
                'patient' => $patient->toArray($this->getUser()), // This will apply admin filtering
                'note' => 'Admin access - medical data excluded for HIPAA compliance'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error accessing patient data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
