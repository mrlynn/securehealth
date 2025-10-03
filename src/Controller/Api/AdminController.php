<?php

namespace App\Controller\Api;

use App\Command\GeneratePatientDataCommand;
use App\Document\Patient;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private DocumentManager $documentManager,
        private GeneratePatientDataCommand $generatePatientDataCommand
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
            $patientCount = $this->documentManager->getRepository(Patient::class)->count([]);
            
            // Get some basic statistics
            $patients = $this->documentManager->getRepository(Patient::class)->findBy([], null, 100);
            
            $conditions = [];
            $medications = [];
            $ageGroups = ['18-30' => 0, '31-50' => 0, '51-70' => 0, '71+' => 0];
            
            foreach ($patients as $patient) {
                // Count conditions
                foreach ($patient->getMedicalConditions() as $condition) {
                    $conditions[$condition] = ($conditions[$condition] ?? 0) + 1;
                }
                
                // Count medications
                foreach ($patient->getMedications() as $medication) {
                    $medications[$medication] = ($medications[$medication] ?? 0) + 1;
                }
                
                // Count age groups
                $age = $patient->getDateOfBirth()->diff(new \DateTime())->y;
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
            $collection = $this->documentManager->getDocumentCollection(Patient::class);
            $result = $collection->deleteMany([]);
            
            return new JsonResponse([
                'success' => true,
                'message' => "Cleared {$result->getDeletedCount()} patient records"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
