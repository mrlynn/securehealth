<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Repository\PatientRepository;
use App\Service\AIDocumentationService;
use App\Service\AuditLogService;
use App\Security\Voter\PatientVoter;
use MongoDB\BSON\ObjectId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/ai-documentation')]
class AIDocumentationController extends AbstractController
{
    public function __construct(
        private AIDocumentationService $aiDocumentationService,
        private PatientRepository $patientRepository,
        private AuditLogService $auditLogService,
        private ValidatorInterface $validator
    ) {}

    /**
     * Generate SOAP note for a patient
     */
    #[Route('/soap-note/{patientId}', name: 'ai_documentation_soap_note', methods: ['POST'])]
    public function generateSOAPNote(
        string $patientId,
        Request $request,
        UserInterface $user
    ): JsonResponse {
        try {
            // Find patient and check access
            $patient = $this->patientRepository->find(new ObjectId($patientId));
            if (!$patient) {
                return $this->json(['success' => false, 'message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
            }

            // Check permissions
            $this->denyAccessUnlessGranted(PatientVoter::EDIT, $patient);

            // Get request data
            $data = json_decode($request->getContent(), true);
            $conversationText = $data['conversationText'] ?? '';
            $vitalSigns = $data['vitalSigns'] ?? [];
            $physicalExam = $data['physicalExam'] ?? [];

            if (empty($conversationText)) {
                return $this->json(['success' => false, 'message' => 'Conversation text is required'], Response::HTTP_BAD_REQUEST);
            }

            // Log the AI documentation request
            $this->auditLogService->log($user, 'ai_documentation_request', [
                'action' => 'generate_soap_note',
                'patientId' => $patientId,
                'conversationLength' => strlen($conversationText)
            ]);

            // Generate SOAP note
            $soapNote = $this->aiDocumentationService->generateSOAPNote(
                $patient,
                $user,
                $conversationText,
                $vitalSigns,
                $physicalExam
            );

            // Log successful generation
            $this->auditLogService->log($user, 'ai_documentation_success', [
                'action' => 'generate_soap_note',
                'patientId' => $patientId,
                'confidenceScore' => $soapNote['confidence_score'] ?? 0
            ]);

            return $this->json([
                'success' => true,
                'data' => $soapNote,
                'message' => 'SOAP note generated successfully'
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'ai_documentation_error', [
                'action' => 'generate_soap_note',
                'patientId' => $patientId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Failed to generate SOAP note: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate visit summary
     */
    #[Route('/visit-summary/{patientId}', name: 'ai_documentation_visit_summary', methods: ['POST'])]
    public function generateVisitSummary(
        string $patientId,
        Request $request,
        UserInterface $user
    ): JsonResponse {
        try {
            // Find patient and check access
            $patient = $this->patientRepository->find(new ObjectId($patientId));
            if (!$patient) {
                return $this->json(['success' => false, 'message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
            }

            // Check permissions
            $this->denyAccessUnlessGranted(PatientVoter::VIEW, $patient);

            // Get request data
            $data = json_decode($request->getContent(), true);
            $rawNotes = $data['rawNotes'] ?? '';
            $diagnosis = $data['diagnosis'] ?? [];
            $medications = $data['medications'] ?? [];

            if (empty($rawNotes)) {
                return $this->json(['success' => false, 'message' => 'Raw notes are required'], Response::HTTP_BAD_REQUEST);
            }

            // Log the AI documentation request
            $this->auditLogService->log($user, 'ai_documentation_request', [
                'action' => 'generate_visit_summary',
                'patientId' => $patientId
            ]);

            // Generate visit summary
            $summary = $this->aiDocumentationService->generateVisitSummary(
                $patient,
                $user,
                $rawNotes,
                $diagnosis,
                $medications
            );

            // Log successful generation
            $this->auditLogService->log($user, 'ai_documentation_success', [
                'action' => 'generate_visit_summary',
                'patientId' => $patientId
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'generatedAt' => new \DateTime(),
                    'generatedBy' => $user->getUsername()
                ],
                'message' => 'Visit summary generated successfully'
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'ai_documentation_error', [
                'action' => 'generate_visit_summary',
                'patientId' => $patientId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Failed to generate visit summary: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Suggest ICD-10 codes
     */
    #[Route('/icd-codes', name: 'ai_documentation_icd_codes', methods: ['POST'])]
    public function suggestICDCodes(Request $request, UserInterface $user): JsonResponse {
        try {
            // Check permissions - only doctors and admins can suggest ICD codes
            $this->denyAccessUnlessGranted('ROLE_DOCTOR');

            // Get request data
            $data = json_decode($request->getContent(), true);
            $symptoms = $data['symptoms'] ?? [];
            $diagnosis = $data['diagnosis'] ?? [];
            $patientData = $data['patientData'] ?? [];

            if (empty($symptoms) && empty($diagnosis)) {
                return $this->json(['success' => false, 'message' => 'Symptoms or diagnosis required'], Response::HTTP_BAD_REQUEST);
            }

            // Log the AI documentation request
            $this->auditLogService->log($user, 'ai_documentation_request', [
                'action' => 'suggest_icd_codes',
                'symptomsCount' => count($symptoms),
                'diagnosisCount' => count($diagnosis)
            ]);

            // Suggest ICD codes
            $icdCodes = $this->aiDocumentationService->suggestICDCodes(
                $symptoms,
                $diagnosis,
                $patientData
            );

            // Log successful generation
            $this->auditLogService->log($user, 'ai_documentation_success', [
                'action' => 'suggest_icd_codes',
                'codesCount' => count($icdCodes)
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'icdCodes' => $icdCodes,
                    'generatedAt' => new \DateTime(),
                    'generatedBy' => $user->getUsername()
                ],
                'message' => 'ICD-10 codes suggested successfully'
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'ai_documentation_error', [
                'action' => 'suggest_icd_codes',
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Failed to suggest ICD codes: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Enhance existing clinical notes
     */
    #[Route('/enhance-notes/{patientId}', name: 'ai_documentation_enhance_notes', methods: ['POST'])]
    public function enhanceClinicalNotes(
        string $patientId,
        Request $request,
        UserInterface $user
    ): JsonResponse {
        try {
            // Find patient and check access
            $patient = $this->patientRepository->find(new ObjectId($patientId));
            if (!$patient) {
                return $this->json(['success' => false, 'message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
            }

            // Check permissions
            $this->denyAccessUnlessGranted(PatientVoter::EDIT, $patient);

            // Get request data
            $data = json_decode($request->getContent(), true);
            $existingNotes = $data['existingNotes'] ?? '';
            $context = $data['context'] ?? [];

            if (empty($existingNotes)) {
                return $this->json(['success' => false, 'message' => 'Existing notes are required'], Response::HTTP_BAD_REQUEST);
            }

            // Log the AI documentation request
            $this->auditLogService->log($user, 'ai_documentation_request', [
                'action' => 'enhance_clinical_notes',
                'patientId' => $patientId,
                'notesLength' => strlen($existingNotes)
            ]);

            // Enhance clinical notes
            $enhancedNotes = $this->aiDocumentationService->enhanceClinicalNotes(
                $existingNotes,
                $patient,
                $context
            );

            // Log successful generation
            $this->auditLogService->log($user, 'ai_documentation_success', [
                'action' => 'enhance_clinical_notes',
                'patientId' => $patientId,
                'confidence' => $enhancedNotes['confidence'] ?? 0
            ]);

            return $this->json([
                'success' => true,
                'data' => $enhancedNotes,
                'message' => 'Clinical notes enhanced successfully'
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'ai_documentation_error', [
                'action' => 'enhance_clinical_notes',
                'patientId' => $patientId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Failed to enhance clinical notes: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Save AI-generated note to patient record
     */
    #[Route('/save-note/{patientId}', name: 'ai_documentation_save_note', methods: ['POST'])]
    public function saveAINote(
        string $patientId,
        Request $request,
        UserInterface $user
    ): JsonResponse {
        try {
            // Find patient and check access
            $patient = $this->patientRepository->find(new ObjectId($patientId));
            if (!$patient) {
                return $this->json(['success' => false, 'message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
            }

            // Check permissions
            $this->denyAccessUnlessGranted(PatientVoter::ADD_NOTE, $patient);

            // Get request data
            $data = json_decode($request->getContent(), true);
            $content = $data['content'] ?? '';
            $aiType = $data['aiType'] ?? 'ai_generated';
            $confidenceScore = $data['confidenceScore'] ?? 0.0;
            $metadata = $data['metadata'] ?? [];

            if (empty($content)) {
                return $this->json(['success' => false, 'message' => 'Note content is required'], Response::HTTP_BAD_REQUEST);
            }

            // Add AI note to patient
            $patient->addAINote(
                $content,
                new ObjectId(), // Generate new ObjectId for the doctor
                $user->getUsername(),
                $aiType,
                $confidenceScore,
                $metadata
            );

            // Save patient
            $this->patientRepository->save($patient);

            // Log the action
            $this->auditLogService->log($user, 'ai_documentation_save_note', [
                'action' => 'save_ai_note',
                'patientId' => $patientId,
                'aiType' => $aiType,
                'confidenceScore' => $confidenceScore,
                'noteLength' => strlen($content)
            ]);

            return $this->json([
                'success' => true,
                'message' => 'AI-generated note saved successfully',
                'data' => [
                    'noteId' => $patient->getNotesHistory()[count($patient->getNotesHistory()) - 1]['id'],
                    'savedAt' => new \DateTime()
                ]
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'ai_documentation_error', [
                'action' => 'save_ai_note',
                'patientId' => $patientId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Failed to save AI note: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get basic patient information for AI documentation
     */
    #[Route('/patient-info/{patientId}', name: 'ai_documentation_patient_info', methods: ['GET'])]
    public function getPatientInfo(string $patientId, UserInterface $user): JsonResponse
    {
        try {
            // Check permissions
            $this->denyAccessUnlessGranted('ROLE_DOCTOR');

            // Get patient
            $patient = $this->patientRepository->findById(new ObjectId($patientId));
            if (!$patient) {
                return $this->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Return basic patient information without requiring identity verification
            $patientData = [
                'id' => (string)$patient->getId(),
                'firstName' => $patient->getFirstName(),
                'lastName' => $patient->getLastName(),
                'email' => $patient->getEmail(),
                'phoneNumber' => $patient->getPhoneNumber(),
                'birthDate' => $patient->getBirthDate() ? $patient->getBirthDate()->toDateTime()->format('Y-m-d') : null,
                'createdAt' => $patient->getCreatedAt() ? $patient->getCreatedAt()->toDateTime()->format('Y-m-d H:i:s') : null
            ];

            return $this->json([
                'success' => true,
                'data' => $patientData
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to load patient information: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    #[Route('/test', name: 'ai_documentation_test', methods: ['GET'])]
    public function testAI(UserInterface $user): JsonResponse {
        try {
            // Check permissions
            $this->denyAccessUnlessGranted('ROLE_DOCTOR');

            // Simple test prompt
            $testPrompt = "Generate a simple medical note for a patient with headache.";
            
            // Use reflection to access the private method for testing
            $reflection = new \ReflectionClass($this->aiDocumentationService);
            $callOpenAIMethod = $reflection->getMethod('callOpenAI');
            $callOpenAIMethod->setAccessible(true);
            
            // Get service properties for debugging
            $apiKeyProperty = $reflection->getProperty('openaiApiKey');
            $apiKeyProperty->setAccessible(true);
            $apiUrlProperty = $reflection->getProperty('openaiApiUrl');
            $apiUrlProperty->setAccessible(true);
            
            $apiKey = $apiKeyProperty->getValue($this->aiDocumentationService);
            $apiUrl = $apiUrlProperty->getValue($this->aiDocumentationService);
            
            return $this->json([
                'success' => false,
                'message' => 'Debug info',
                'apiKey' => $apiKey ? 'Present (length: ' . strlen($apiKey) . ')' : 'Missing',
                'apiUrl' => $apiUrl,
                'constructedUrl' => rtrim($apiUrl, '/') . '/chat/completions'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'AI service test failed: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get AI documentation capabilities and status
     */
    #[Route('/capabilities', name: 'ai_documentation_capabilities', methods: ['GET'])]
    public function getCapabilities(UserInterface $user): JsonResponse {
        try {
            // Check permissions
            $this->denyAccessUnlessGranted('ROLE_DOCTOR');

            $capabilities = [
                'soapNoteGeneration' => [
                    'available' => true,
                    'description' => 'Generate structured SOAP notes from conversation text',
                    'requiredRole' => 'ROLE_DOCTOR',
                    'features' => [
                        'Subjective section generation',
                        'Objective findings organization',
                        'Assessment and clinical reasoning',
                        'Treatment plan suggestions'
                    ]
                ],
                'visitSummary' => [
                    'available' => true,
                    'description' => 'Generate concise visit summaries',
                    'requiredRole' => 'ROLE_DOCTOR',
                    'features' => [
                        'Concise visit summaries',
                        'Patient communication ready',
                        'Referral letter format',
                        'Insurance documentation'
                    ]
                ],
                'icdCodeSuggestion' => [
                    'available' => true,
                    'description' => 'Suggest appropriate ICD-10 codes',
                    'requiredRole' => 'ROLE_DOCTOR',
                    'features' => [
                        'Primary diagnosis codes',
                        'Secondary diagnosis codes',
                        'Symptom codes',
                        'Confidence scoring'
                    ]
                ],
                'noteEnhancement' => [
                    'available' => true,
                    'description' => 'Enhance existing clinical notes',
                    'requiredRole' => 'ROLE_DOCTOR',
                    'features' => [
                        'Clarity improvements',
                        'Completeness suggestions',
                        'Medical terminology enhancement',
                        'Structure optimization'
                    ]
                ]
            ];

            return $this->json([
                'success' => true,
                'data' => $capabilities,
                'message' => 'AI documentation capabilities retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get capabilities: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
