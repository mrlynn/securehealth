<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Document\MedicalRecord;
use App\Repository\PatientRepository;
use App\Repository\MedicalRecordRepository;
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

#[Route('/api/patient-portal/health-records')]
class PatientHealthRecordController extends AbstractController
{
    private DocumentManager $documentManager;
    private PatientRepository $patientRepository;
    private MedicalRecordRepository $medicalRecordRepository;
    private AuditLogService $auditLogService;

    public function __construct(
        DocumentManager $documentManager,
        PatientRepository $patientRepository,
        MedicalRecordRepository $medicalRecordRepository,
        AuditLogService $auditLogService
    ) {
        $this->documentManager = $documentManager;
        $this->patientRepository = $patientRepository;
        $this->medicalRecordRepository = $medicalRecordRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get health records for the authenticated patient
     */
    #[Route('', name: 'patient_health_records_list', methods: ['GET'])]
    public function getHealthRecords(UserInterface $user): JsonResponse
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
            // In a real application, this would fetch from a MedicalRecord collection
            // For demo purposes, we'll create some mock health record data
            $healthRecords = $this->getMockHealthRecords($patientId);
            
            $this->auditLogService->log(
                $user,
                'patient_portal_health_records_view',
                [
                    'action' => 'view_health_records',
                    'patientId' => (string)$patientId,
                    'recordCount' => count($healthRecords)
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $healthRecords
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving health records: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific health record
     */
    #[Route('/{id}', name: 'patient_health_record_detail', methods: ['GET'])]
    public function getHealthRecord(string $id, UserInterface $user): JsonResponse
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
            // In a real application, this would fetch from a MedicalRecord collection
            // For demo purposes, we'll use our mock data
            $healthRecords = $this->getMockHealthRecords($patientId);
            $healthRecord = null;
            
            foreach ($healthRecords as $record) {
                if ($record['id'] === $id) {
                    $healthRecord = $record;
                    break;
                }
            }
            
            if (!$healthRecord) {
                return $this->json([
                    'success' => false,
                    'message' => 'Health record not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Add detailed content for specific record
            if ($healthRecord['type'] === 'visit') {
                $healthRecord['details'] = [
                    'chiefComplaint' => 'Chest pain and shortness of breath',
                    'vitalSigns' => [
                        'bloodPressure' => '130/85 mmHg',
                        'heartRate' => '88 bpm',
                        'respiratoryRate' => '18/min',
                        'temperature' => '98.6 °F',
                        'oxygenSaturation' => '97%'
                    ],
                    'assessment' => 'Patient presents with substernal chest pain, radiating to left arm. Pain began approximately 2 hours prior to arrival. Patient describes pain as "pressure-like" and rates it 7/10. Associated symptoms include mild shortness of breath and diaphoresis. No nausea or vomiting.',
                    'diagnosis' => 'Acute coronary syndrome, rule out myocardial infarction',
                    'plan' => 'ECG performed showing ST elevation in leads II, III, aVF. Cardiac enzymes ordered. Patient given aspirin 325mg PO and nitroglycerin 0.4mg SL with partial relief of symptoms. Will admit for further cardiac evaluation and monitoring.',
                    'medications' => [
                        'Aspirin 325mg PO daily',
                        'Metoprolol 25mg PO BID',
                        'Atorvastatin 40mg PO daily'
                    ],
                    'followUp' => 'Cardiology appointment scheduled for 2 weeks',
                    'provider' => [
                        'name' => 'Dr. Johnson',
                        'specialty' => 'Emergency Medicine',
                        'npi' => '1234567890'
                    ]
                ];
            } else if ($healthRecord['type'] === 'procedure') {
                $healthRecord['details'] = [
                    'procedureName' => 'Cardiac Catheterization',
                    'indication' => 'Evaluation of coronary artery disease',
                    'findings' => 'Left anterior descending coronary artery stenosis (70%)',
                    'complications' => 'None',
                    'anesthesia' => 'Conscious sedation',
                    'specimens' => 'None',
                    'devices' => [
                        'type' => 'Drug-eluting stent',
                        'manufacturer' => 'Medtronic',
                        'model' => 'Resolute Onyx',
                        'size' => '3.0mm x 18mm',
                        'location' => 'Left anterior descending coronary artery'
                    ],
                    'preOpDiagnosis' => 'Coronary artery disease',
                    'postOpDiagnosis' => 'Single-vessel coronary artery disease',
                    'surgeon' => [
                        'name' => 'Dr. Smith',
                        'specialty' => 'Interventional Cardiology',
                        'npi' => '0987654321'
                    ]
                ];
            } else if ($healthRecord['type'] === 'discharge') {
                $healthRecord['details'] = [
                    'admissionDate' => '2024-05-10',
                    'dischargeDate' => '2024-05-13',
                    'admissionDiagnosis' => 'Non-ST elevation myocardial infarction (NSTEMI)',
                    'dischargeDiagnosis' => 'Non-ST elevation myocardial infarction, single-vessel coronary artery disease s/p PCI with DES to LAD',
                    'procedures' => [
                        'Cardiac catheterization with percutaneous coronary intervention (PCI) and drug-eluting stent placement to the left anterior descending coronary artery'
                    ],
                    'hospitalCourse' => 'Patient admitted for NSTEMI. Underwent cardiac catheterization with placement of drug-eluting stent to LAD. Post-procedure course uncomplicated. Patient tolerated diet and ambulatory activities well. Discharge with cardiac rehabilitation referral.',
                    'dischargeCondition' => 'Stable',
                    'dischargeInstructions' => [
                        'Activity restrictions: No heavy lifting (>10 lbs) for 1 week. May resume normal activities as tolerated thereafter.',
                        'Diet: Heart healthy, low sodium diet.',
                        'Follow-up appointments: Cardiology in 2 weeks.',
                        'When to seek medical attention: Chest pain, shortness of breath, dizziness, palpitations, or bleeding from catheterization site.'
                    ],
                    'dischargeMedications' => [
                        'Aspirin 81mg daily',
                        'Clopidogrel 75mg daily',
                        'Atorvastatin 40mg daily',
                        'Metoprolol succinate 25mg twice daily',
                        'Lisinopril 10mg daily'
                    ],
                    'attendingPhysician' => 'Dr. Davis'
                ];
            }
            
            $this->auditLogService->log(
                $user,
                'patient_portal_health_record_view',
                [
                    'action' => 'view_health_record_detail',
                    'patientId' => (string)$patientId,
                    'recordId' => $id,
                    'recordType' => $healthRecord['type']
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $healthRecord
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving health record: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get patient demographics for health record PDF
     */
    #[Route('/demographics', name: 'patient_demographics', methods: ['GET'])]
    public function getPatientDemographics(UserInterface $user): JsonResponse
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
            // In a real application, we would fetch the patient record
            // For demo purposes, we'll create a mock patient record
            $patientData = [
                'id' => $patientId,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'dateOfBirth' => '1980-05-15',
                'age' => 44,
                'gender' => 'Male',
                'address' => '123 Main Street, Anytown, USA, 12345',
                'phoneNumber' => '555-123-4567',
                'email' => 'john.doe@example.com',
                'emergencyContact' => [
                    'name' => 'Jane Doe',
                    'relationship' => 'Spouse',
                    'phoneNumber' => '555-987-6543'
                ],
                'insuranceProvider' => 'HealthPlus Insurance',
                'insurancePolicyNumber' => 'HP123456789',
                'primaryCareProvider' => 'Dr. Johnson',
                'allergies' => ['Penicillin', 'Sulfa drugs'],
                'bloodType' => 'O+',
                'medicalConditions' => ['Hypertension', 'Hyperlipidemia', 'Coronary Artery Disease'],
                'height' => '5\'10"',
                'weight' => '180 lbs',
                'bmi' => 25.8
            ];
            
            $this->auditLogService->log(
                $user,
                'patient_portal_demographics_view',
                [
                    'action' => 'view_demographics',
                    'patientId' => (string)$patientId
                ]
            );
            
            return $this->json([
                'success' => true,
                'data' => $patientData
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error retrieving patient demographics: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate mock health records for demo purposes
     */
    private function getMockHealthRecords(string $patientId): array
    {
        // Create dates for records
        $oneWeekAgo = new \DateTime('-1 week');
        $oneMonthAgo = new \DateTime('-1 month');
        $threeMonthsAgo = new \DateTime('-3 months');
        $sixMonthsAgo = new \DateTime('-6 months');
        $oneYearAgo = new \DateTime('-1 year');
        
        // Mock data for health records
        return [
            [
                'id' => 'hr1',
                'patientId' => $patientId,
                'type' => 'visit',
                'visitType' => 'Emergency Department',
                'title' => 'Emergency Department Visit',
                'date' => $oneMonthAgo->format('Y-m-d'),
                'provider' => 'Dr. Johnson',
                'facility' => 'General Hospital',
                'diagnosis' => 'Acute Coronary Syndrome',
                'summary' => 'Patient presented with chest pain and shortness of breath. ECG showed ST elevations. Admitted for further evaluation.',
                'documentType' => 'Clinical Note',
                'availableFormats' => ['PDF']
            ],
            [
                'id' => 'hr2',
                'patientId' => $patientId,
                'type' => 'procedure',
                'title' => 'Cardiac Catheterization',
                'date' => $oneMonthAgo->modify('+1 day')->format('Y-m-d'),
                'provider' => 'Dr. Smith',
                'facility' => 'General Hospital',
                'diagnosis' => 'Coronary Artery Disease',
                'summary' => 'Left heart catheterization with coronary angiography and percutaneous intervention. Drug-eluting stent placed in LAD.',
                'documentType' => 'Procedure Note',
                'availableFormats' => ['PDF']
            ],
            [
                'id' => 'hr3',
                'patientId' => $patientId,
                'type' => 'discharge',
                'title' => 'Hospital Discharge Summary',
                'date' => $oneMonthAgo->modify('+3 days')->format('Y-m-d'),
                'provider' => 'Dr. Davis',
                'facility' => 'General Hospital',
                'diagnosis' => 'NSTEMI, Coronary Artery Disease',
                'summary' => 'Patient admitted for NSTEMI. Underwent cardiac catheterization with stent placement. Discharged home in stable condition with medication changes and outpatient follow-up.',
                'documentType' => 'Discharge Summary',
                'availableFormats' => ['PDF']
            ],
            [
                'id' => 'hr4',
                'patientId' => $patientId,
                'type' => 'visit',
                'visitType' => 'Outpatient',
                'title' => 'Cardiology Follow-up Visit',
                'date' => $oneMonthAgo->modify('+14 days')->format('Y-m-d'),
                'provider' => 'Dr. Smith',
                'facility' => 'Cardiac Specialists Clinic',
                'diagnosis' => 'CAD s/p PCI',
                'summary' => 'Two-week post-hospitalization follow-up. Patient reports feeling well. No chest pain or shortness of breath. Medication reconciliation performed.',
                'documentType' => 'Progress Note',
                'availableFormats' => ['PDF']
            ],
            [
                'id' => 'hr5',
                'patientId' => $patientId,
                'type' => 'visit',
                'visitType' => 'Outpatient',
                'title' => 'Primary Care Visit',
                'date' => $sixMonthsAgo->format('Y-m-d'),
                'provider' => 'Dr. Johnson',
                'facility' => 'Community Health Center',
                'diagnosis' => 'Hypertension, Hyperlipidemia',
                'summary' => 'Routine check-up. Blood pressure elevated at 148/92. Medication adjustments made. Lab work ordered.',
                'documentType' => 'Progress Note',
                'availableFormats' => ['PDF']
            ],
            [
                'id' => 'hr6',
                'patientId' => $patientId,
                'type' => 'report',
                'reportType' => 'Laboratory',
                'title' => 'Comprehensive Metabolic Panel',
                'date' => $sixMonthsAgo->modify('+1 day')->format('Y-m-d'),
                'provider' => 'Dr. Johnson',
                'facility' => 'Community Health Center',
                'summary' => 'Comprehensive metabolic panel results. Glucose 110 mg/dL (slightly elevated), LDL 142 mg/dL (elevated).',
                'documentType' => 'Lab Report',
                'availableFormats' => ['PDF']
            ],
            [
                'id' => 'hr7',
                'patientId' => $patientId,
                'type' => 'report',
                'reportType' => 'Imaging',
                'title' => 'Chest X-Ray',
                'date' => $oneYearAgo->format('Y-m-d'),
                'provider' => 'Dr. Wilson',
                'facility' => 'General Hospital',
                'diagnosis' => 'Rule out pneumonia',
                'summary' => 'No acute pulmonary disease. No pneumonia, effusion, or pneumothorax. Heart size normal.',
                'documentType' => 'Radiology Report',
                'availableFormats' => ['PDF', 'Image']
            ],
            [
                'id' => 'hr8',
                'patientId' => $patientId,
                'type' => 'report',
                'reportType' => 'Imaging',
                'title' => 'Echocardiogram',
                'date' => $oneMonthAgo->modify('+2 days')->format('Y-m-d'),
                'provider' => 'Dr. Smith',
                'facility' => 'General Hospital',
                'diagnosis' => 'Evaluate cardiac function',
                'summary' => 'Left ventricular ejection fraction 45-50%. Mild hypokinesis of inferior wall. No significant valvular disease.',
                'documentType' => 'Cardiology Report',
                'availableFormats' => ['PDF']
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