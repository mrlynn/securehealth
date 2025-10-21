<?php

namespace App\Controller\Api;

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

#[Route('/api/patient-portal/test-results')]
class PatientTestResultsController extends AbstractController
{
    private DocumentManager $documentManager;
    private AuditLogService $auditLogService;

    public function __construct(
        DocumentManager $documentManager,
        AuditLogService $auditLogService
    ) {
        $this->documentManager = $documentManager;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get test results for the authenticated patient
     */
    #[Route('', name: 'patient_test_results_list', methods: ['GET'])]
    public function getTestResults(UserInterface $user): JsonResponse
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
            // In a real application, this would fetch from a TestResults collection
            // For demo purposes, we'll create some mock test results
            $testResults = $this->getMockTestResults($patientId);
            
            $this->auditLogService->log(
                $user,
                'patient_portal_test_results_view',
                [
                    'action' => 'view_test_results',
                    'patientId' => (string)$patientId,
                    'resultCount' => count($testResults)
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $testResults
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving test results: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get details of a specific test result
     */
    #[Route('/{id}', name: 'patient_test_result_detail', methods: ['GET'])]
    public function getTestResult(string $id, UserInterface $user): JsonResponse
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
            // In a real application, this would fetch from a TestResults collection
            // For demo purposes, we'll create a mock test result detail
            $testResults = $this->getMockTestResults($patientId);
            $testResult = null;
            
            foreach ($testResults as $result) {
                if ($result['id'] === $id) {
                    $testResult = $result;
                    
                    // Add detailed results for mock data
                    if ($result['type'] === 'Complete Blood Count (CBC)') {
                        $testResult['details'] = [
                            [
                                'name' => 'Red Blood Cells (RBC)',
                                'value' => '4.8',
                                'unit' => 'million/µL',
                                'referenceRange' => '4.5-5.9',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'White Blood Cells (WBC)',
                                'value' => '8.2',
                                'unit' => 'thousand/µL',
                                'referenceRange' => '4.5-11.0',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Hemoglobin (Hgb)',
                                'value' => '14.2',
                                'unit' => 'g/dL',
                                'referenceRange' => '13.5-17.5',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Hematocrit (Hct)',
                                'value' => '42.0',
                                'unit' => '%',
                                'referenceRange' => '41.0-53.0',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Platelets',
                                'value' => '265',
                                'unit' => 'thousand/µL',
                                'referenceRange' => '150-450',
                                'status' => 'normal'
                            ]
                        ];
                    } else if ($result['type'] === 'Comprehensive Metabolic Panel (CMP)') {
                        $testResult['details'] = [
                            [
                                'name' => 'Glucose (fasting)',
                                'value' => '105',
                                'unit' => 'mg/dL',
                                'referenceRange' => '70-99',
                                'status' => 'high',
                                'note' => 'Slightly elevated, follow up recommended'
                            ],
                            [
                                'name' => 'Blood Urea Nitrogen (BUN)',
                                'value' => '15',
                                'unit' => 'mg/dL',
                                'referenceRange' => '7-20',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Creatinine',
                                'value' => '0.9',
                                'unit' => 'mg/dL',
                                'referenceRange' => '0.6-1.2',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Sodium',
                                'value' => '139',
                                'unit' => 'mmol/L',
                                'referenceRange' => '135-145',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Potassium',
                                'value' => '3.8',
                                'unit' => 'mmol/L',
                                'referenceRange' => '3.5-5.0',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Chloride',
                                'value' => '101',
                                'unit' => 'mmol/L',
                                'referenceRange' => '98-107',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Carbon Dioxide (CO2)',
                                'value' => '24',
                                'unit' => 'mmol/L',
                                'referenceRange' => '23-29',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Calcium',
                                'value' => '9.5',
                                'unit' => 'mg/dL',
                                'referenceRange' => '8.5-10.5',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Total Protein',
                                'value' => '7.0',
                                'unit' => 'g/dL',
                                'referenceRange' => '6.0-8.0',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Albumin',
                                'value' => '4.0',
                                'unit' => 'g/dL',
                                'referenceRange' => '3.5-5.0',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Total Bilirubin',
                                'value' => '0.8',
                                'unit' => 'mg/dL',
                                'referenceRange' => '0.1-1.2',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Alkaline Phosphatase',
                                'value' => '85',
                                'unit' => 'U/L',
                                'referenceRange' => '40-129',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'AST (SGOT)',
                                'value' => '28',
                                'unit' => 'U/L',
                                'referenceRange' => '10-40',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'ALT (SGPT)',
                                'value' => '32',
                                'unit' => 'U/L',
                                'referenceRange' => '10-40',
                                'status' => 'normal'
                            ]
                        ];
                    } else if ($result['type'] === 'Lipid Panel') {
                        $testResult['details'] = [
                            [
                                'name' => 'Total Cholesterol',
                                'value' => '210',
                                'unit' => 'mg/dL',
                                'referenceRange' => '<200',
                                'status' => 'high',
                                'note' => 'Slightly elevated, dietary changes recommended'
                            ],
                            [
                                'name' => 'Triglycerides',
                                'value' => '150',
                                'unit' => 'mg/dL',
                                'referenceRange' => '<150',
                                'status' => 'borderline',
                                'note' => 'Borderline high'
                            ],
                            [
                                'name' => 'HDL Cholesterol',
                                'value' => '45',
                                'unit' => 'mg/dL',
                                'referenceRange' => '>40',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'LDL Cholesterol',
                                'value' => '135',
                                'unit' => 'mg/dL',
                                'referenceRange' => '<100',
                                'status' => 'high',
                                'note' => 'Elevated, follow up with provider'
                            ],
                            [
                                'name' => 'Total Cholesterol/HDL Ratio',
                                'value' => '4.7',
                                'unit' => 'ratio',
                                'referenceRange' => '<5.0',
                                'status' => 'normal'
                            ]
                        ];
                    } else if ($result['type'] === 'Urinalysis') {
                        $testResult['details'] = [
                            [
                                'name' => 'Color',
                                'value' => 'Yellow',
                                'referenceRange' => 'Pale to dark yellow',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Appearance',
                                'value' => 'Clear',
                                'referenceRange' => 'Clear',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Specific Gravity',
                                'value' => '1.020',
                                'referenceRange' => '1.005-1.030',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'pH',
                                'value' => '6.0',
                                'referenceRange' => '4.5-8.0',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Protein',
                                'value' => 'Negative',
                                'referenceRange' => 'Negative',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Glucose',
                                'value' => 'Negative',
                                'referenceRange' => 'Negative',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Ketones',
                                'value' => 'Negative',
                                'referenceRange' => 'Negative',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Blood',
                                'value' => 'Negative',
                                'referenceRange' => 'Negative',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Nitrite',
                                'value' => 'Negative',
                                'referenceRange' => 'Negative',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Leukocyte Esterase',
                                'value' => 'Negative',
                                'referenceRange' => 'Negative',
                                'status' => 'normal'
                            ]
                        ];
                    } else if ($result['type'] === 'Thyroid Panel') {
                        $testResult['details'] = [
                            [
                                'name' => 'TSH',
                                'value' => '2.5',
                                'unit' => 'mIU/L',
                                'referenceRange' => '0.4-4.0',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Free T4',
                                'value' => '1.2',
                                'unit' => 'ng/dL',
                                'referenceRange' => '0.8-1.8',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Free T3',
                                'value' => '3.1',
                                'unit' => 'pg/mL',
                                'referenceRange' => '2.3-4.2',
                                'status' => 'normal'
                            ],
                            [
                                'name' => 'Thyroid Antibodies',
                                'value' => 'Negative',
                                'referenceRange' => 'Negative',
                                'status' => 'normal'
                            ]
                        ];
                    }
                    
                    break;
                }
            }
            
            if (!$testResult) {
                return $this->json([
                    'success' => false,
                    'message' => 'Test result not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $this->auditLogService->log(
                $user,
                'patient_portal_test_result_view',
                [
                    'action' => 'view_test_result_detail',
                    'patientId' => (string)$patientId,
                    'testResultId' => $id,
                    'testType' => $testResult['type']
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $testResult
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving test result: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Generate mock test results for demo purposes
     */
    private function getMockTestResults(string $patientId): array
    {
        // Create test dates going back in time
        $today = new \DateTime();
        $oneMonthAgo = new \DateTime('-1 month');
        $twoMonthsAgo = new \DateTime('-2 months');
        $threeMonthsAgo = new \DateTime('-3 months');
        $sixMonthsAgo = new \DateTime('-6 months');
        $oneYearAgo = new \DateTime('-1 year');
        
        // Mock data for test results
        return [
            [
                'id' => 'test1',
                'patientId' => $patientId,
                'type' => 'Complete Blood Count (CBC)',
                'date' => $oneMonthAgo->format('Y-m-d'),
                'orderingProvider' => 'Dr. Smith',
                'status' => 'final',
                'summary' => 'Normal CBC results',
                'flagged' => false
            ],
            [
                'id' => 'test2',
                'patientId' => $patientId,
                'type' => 'Comprehensive Metabolic Panel (CMP)',
                'date' => $oneMonthAgo->format('Y-m-d'),
                'orderingProvider' => 'Dr. Smith',
                'status' => 'final',
                'summary' => 'Glucose slightly elevated',
                'flagged' => true
            ],
            [
                'id' => 'test3',
                'patientId' => $patientId,
                'type' => 'Lipid Panel',
                'date' => $twoMonthsAgo->format('Y-m-d'),
                'orderingProvider' => 'Dr. Johnson',
                'status' => 'final',
                'summary' => 'Cholesterol and LDL elevated',
                'flagged' => true
            ],
            [
                'id' => 'test4',
                'patientId' => $patientId,
                'type' => 'Urinalysis',
                'date' => $threeMonthsAgo->format('Y-m-d'),
                'orderingProvider' => 'Dr. Smith',
                'status' => 'final',
                'summary' => 'Normal results',
                'flagged' => false
            ],
            [
                'id' => 'test5',
                'patientId' => $patientId,
                'type' => 'Thyroid Panel',
                'date' => $sixMonthsAgo->format('Y-m-d'),
                'orderingProvider' => 'Dr. Johnson',
                'status' => 'final',
                'summary' => 'Normal thyroid function',
                'flagged' => false
            ],
            [
                'id' => 'test6',
                'patientId' => $patientId,
                'type' => 'Complete Blood Count (CBC)',
                'date' => $oneYearAgo->format('Y-m-d'),
                'orderingProvider' => 'Dr. Smith',
                'status' => 'final',
                'summary' => 'Normal CBC results',
                'flagged' => false
            ],
            [
                'id' => 'test7',
                'patientId' => $patientId,
                'type' => 'COVID-19 PCR Test',
                'date' => $threeMonthsAgo->format('Y-m-d'),
                'orderingProvider' => 'Dr. Williams',
                'status' => 'final',
                'summary' => 'Negative',
                'flagged' => false,
                'details' => [
                    [
                        'name' => 'SARS-CoV-2 RNA',
                        'value' => 'Not Detected',
                        'referenceRange' => 'Not Detected',
                        'status' => 'normal'
                    ]
                ]
            ]
        ];
    }
}