<?php

namespace App\Controller\Api;

use App\Service\MedicalKnowledgeService;
use App\Service\VectorSearchService;
use App\Service\AuditLogService;
use App\Security\Voter\MedicalKnowledgeVoter;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/medical-knowledge')]
class MedicalKnowledgeController extends AbstractController
{
    private MedicalKnowledgeService $knowledgeService;
    private VectorSearchService $vectorSearchService;
    private AuditLogService $auditLogService;
    private ValidatorInterface $validator;

    public function __construct(
        MedicalKnowledgeService $knowledgeService,
        VectorSearchService $vectorSearchService,
        AuditLogService $auditLogService,
        ValidatorInterface $validator
    ) {
        $this->knowledgeService = $knowledgeService;
        $this->vectorSearchService = $vectorSearchService;
        $this->auditLogService = $auditLogService;
        $this->validator = $validator;
    }

    /**
     * Search medical knowledge using semantic search
     */
    #[Route('/search', name: 'medical_knowledge_search', methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        // Only doctors and admins can search medical knowledge
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::SEARCH);

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['query'])) {
            return $this->json(['error' => 'Query is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $query = trim($data['query']);
            $filters = $data['filters'] ?? [];
            $limit = min($data['limit'] ?? 10, 50); // Cap at 50 results
            $threshold = $data['threshold'] ?? 0.7;

            if (empty($query)) {
                return $this->json(['error' => 'Query cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            $results = $this->vectorSearchService->searchMedicalKnowledge(
                $query,
                $filters,
                $limit,
                $threshold
            );

            // Convert results to array format
            $formattedResults = array_map(function($knowledge) {
                return $knowledge->toArray($this->getUser());
            }, $results);

            // Log the search
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_SEARCH',
                [
                    'query' => $query,
                    'filters' => $filters,
                    'resultsCount' => count($results)
                ]
            );

            return $this->json([
                'results' => $formattedResults,
                'total' => count($results),
                'query' => $query,
                'filters' => $filters
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_SEARCH_ERROR',
                [
                    'query' => $data['query'] ?? '',
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Search failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get clinical decision support for a patient
     */
    #[Route('/clinical-decision-support', name: 'clinical_decision_support', methods: ['POST'])]
    public function getClinicalDecisionSupport(Request $request): JsonResponse
    {
        // Only doctors can get clinical decision support
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT);

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['patientData'])) {
            return $this->json(['error' => 'Patient data is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $patientData = $data['patientData'];
            $specialty = $data['specialty'] ?? null;
            $limit = min($data['limit'] ?? 10, 20);

            $results = $this->vectorSearchService->getClinicalDecisionSupport(
                $patientData,
                $specialty,
                $limit
            );

            // Convert results to array format
            $formattedResults = array_map(function($knowledge) {
                return $knowledge->toArray($this->getUser());
            }, $results);

            // Log the clinical decision support request
            $this->auditLogService->log(
                $this->getUser(),
                'CLINICAL_DECISION_SUPPORT',
                [
                    'patientDataKeys' => array_keys($patientData),
                    'specialty' => $specialty,
                    'resultsCount' => count($results)
                ]
            );

            return $this->json([
                'results' => $formattedResults,
                'total' => count($results),
                'patientData' => array_keys($patientData),
                'specialty' => $specialty
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'CLINICAL_DECISION_SUPPORT_ERROR',
                [
                    'patientDataKeys' => array_keys($data['patientData'] ?? []),
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Clinical decision support failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search for drug interactions
     */
    #[Route('/drug-interactions', name: 'drug_interactions_search', methods: ['POST'])]
    public function searchDrugInteractions(Request $request): JsonResponse
    {
        // Only doctors and nurses can search drug interactions
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::DRUG_INTERACTIONS);

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['medications'])) {
            return $this->json(['error' => 'Medications are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $medications = $data['medications'];
            $conditions = $data['conditions'] ?? null;
            $allergies = $data['allergies'] ?? null;

            if (!is_array($medications) || empty($medications)) {
                return $this->json(['error' => 'Medications must be a non-empty array'], Response::HTTP_BAD_REQUEST);
            }

            $results = $this->vectorSearchService->searchDrugInteractions(
                $medications,
                $conditions,
                $allergies
            );

            // Convert results to array format
            $formattedResults = array_map(function($knowledge) {
                return $knowledge->toArray($this->getUser());
            }, $results);

            // Log the drug interaction search
            $this->auditLogService->log(
                $this->getUser(),
                'DRUG_INTERACTION_SEARCH',
                [
                    'medications' => $medications,
                    'conditions' => $conditions,
                    'allergies' => $allergies,
                    'resultsCount' => count($results)
                ]
            );

            return $this->json([
                'results' => $formattedResults,
                'total' => count($results),
                'medications' => $medications,
                'conditions' => $conditions,
                'allergies' => $allergies
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'DRUG_INTERACTION_SEARCH_ERROR',
                [
                    'medications' => $data['medications'] ?? [],
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Drug interaction search failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search for treatment guidelines
     */
    #[Route('/treatment-guidelines', name: 'treatment_guidelines_search', methods: ['POST'])]
    public function searchTreatmentGuidelines(Request $request): JsonResponse
    {
        // Only doctors can search treatment guidelines
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::TREATMENT_GUIDELINES);

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['condition'])) {
            return $this->json(['error' => 'Condition is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $condition = trim($data['condition']);
            $specialty = $data['specialty'] ?? null;
            $severity = $data['severity'] ?? null;
            $patientAge = $data['patientAge'] ?? null;

            if (empty($condition)) {
                return $this->json(['error' => 'Condition cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            $results = $this->vectorSearchService->searchTreatmentGuidelines(
                $condition,
                $specialty,
                $severity,
                $patientAge
            );

            // Convert results to array format
            $formattedResults = array_map(function($knowledge) {
                return $knowledge->toArray($this->getUser());
            }, $results);

            // Log the treatment guidelines search
            $this->auditLogService->log(
                $this->getUser(),
                'TREATMENT_GUIDELINES_SEARCH',
                [
                    'condition' => $condition,
                    'specialty' => $specialty,
                    'severity' => $severity,
                    'patientAge' => $patientAge,
                    'resultsCount' => count($results)
                ]
            );

            return $this->json([
                'results' => $formattedResults,
                'total' => count($results),
                'condition' => $condition,
                'specialty' => $specialty,
                'severity' => $severity,
                'patientAge' => $patientAge
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'TREATMENT_GUIDELINES_SEARCH_ERROR',
                [
                    'condition' => $data['condition'] ?? '',
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Treatment guidelines search failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search for diagnostic criteria
     */
    #[Route('/diagnostic-criteria', name: 'diagnostic_criteria_search', methods: ['POST'])]
    public function searchDiagnosticCriteria(Request $request): JsonResponse
    {
        // Only doctors can search diagnostic criteria
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA);

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['symptoms'])) {
            return $this->json(['error' => 'Symptoms are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $symptoms = $data['symptoms'];
            $signs = $data['signs'] ?? null;
            $testResults = $data['testResults'] ?? null;
            $specialty = $data['specialty'] ?? null;

            if (!is_array($symptoms) || empty($symptoms)) {
                return $this->json(['error' => 'Symptoms must be a non-empty array'], Response::HTTP_BAD_REQUEST);
            }

            $results = $this->vectorSearchService->searchDiagnosticCriteria(
                $symptoms,
                $signs,
                $testResults,
                $specialty
            );

            // Convert results to array format
            $formattedResults = array_map(function($knowledge) {
                return $knowledge->toArray($this->getUser());
            }, $results);

            // Log the diagnostic criteria search
            $this->auditLogService->log(
                $this->getUser(),
                'DIAGNOSTIC_CRITERIA_SEARCH',
                [
                    'symptoms' => $symptoms,
                    'signs' => $signs,
                    'testResults' => $testResults,
                    'specialty' => $specialty,
                    'resultsCount' => count($results)
                ]
            );

            return $this->json([
                'results' => $formattedResults,
                'total' => count($results),
                'symptoms' => $symptoms,
                'signs' => $signs,
                'testResults' => $testResults,
                'specialty' => $specialty
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'DIAGNOSTIC_CRITERIA_SEARCH_ERROR',
                [
                    'symptoms' => $data['symptoms'] ?? [],
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Diagnostic criteria search failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get knowledge base statistics
     */
    #[Route('/stats', name: 'medical_knowledge_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        // Only doctors and admins can view stats
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::VIEW_STATS);

        try {
            $stats = $this->knowledgeService->getKnowledgeBaseStats();

            // Log the stats request
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_STATS_VIEW',
                [
                    'totalEntries' => $stats['totalEntries'] ?? 0
                ]
            );

            return $this->json($stats);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_STATS_ERROR',
                [
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Failed to retrieve stats: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new medical knowledge entry
     */
    #[Route('', name: 'medical_knowledge_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Only doctors and admins can create medical knowledge
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::CREATE);

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Validate required fields
            $requiredFields = ['title', 'content', 'source'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    return $this->json(['error' => "Field '{$field}' is required"], Response::HTTP_BAD_REQUEST);
                }
            }

            $knowledge = $this->knowledgeService->createKnowledgeEntry(
                trim($data['title']),
                trim($data['content']),
                trim($data['source']),
                $data['tags'] ?? [],
                $data['specialties'] ?? [],
                $data['summary'] ?? null,
                $data['sourceUrl'] ?? null,
                isset($data['sourceDate']) ? new UTCDateTime($data['sourceDate']) : null,
                $data['confidenceLevel'] ?? 5,
                $data['evidenceLevel'] ?? 3,
                $data['relatedConditions'] ?? [],
                $data['relatedMedications'] ?? [],
                $data['relatedProcedures'] ?? [],
                $data['requiresReview'] ?? false,
                $this->getUser()
            );

            // Log the creation
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_CREATE',
                [
                    'id' => (string)$knowledge->getId(),
                    'title' => $knowledge->getTitle(),
                    'source' => $knowledge->getSource()
                ]
            );

            return $this->json([
                'message' => 'Medical knowledge entry created successfully',
                'knowledge' => $knowledge->toArray($this->getUser())
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_CREATE_ERROR',
                [
                    'title' => $data['title'] ?? '',
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Failed to create medical knowledge: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific medical knowledge entry
     */
    #[Route('/{id}', name: 'medical_knowledge_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        // Only doctors and nurses can view medical knowledge
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::VIEW);

        try {
            $knowledge = $this->knowledgeService->getById($id);
            
            if (!$knowledge) {
                return $this->json(['error' => 'Medical knowledge not found'], Response::HTTP_NOT_FOUND);
            }

            // Log the access
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_VIEW',
                [
                    'id' => $id,
                    'title' => $knowledge->getTitle()
                ]
            );

            return $this->json([
                'knowledge' => $knowledge->toArray($this->getUser())
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_VIEW_ERROR',
                [
                    'id' => $id,
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Failed to retrieve medical knowledge: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import medical knowledge from external source
     */
    #[Route('/import', name: 'medical_knowledge_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        // Only admins can import medical knowledge
        $this->denyAccessUnlessGranted(MedicalKnowledgeVoter::IMPORT);

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        try {
            if (!isset($data['source']) || !isset($data['data'])) {
                return $this->json(['error' => 'Source and data are required'], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->knowledgeService->importFromExternalSource(
                $data['source'],
                $data['data'],
                $this->getUser()
            );

            // Log the import
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_IMPORT',
                [
                    'source' => $data['source'],
                    'imported' => count($result['imported']),
                    'errors' => count($result['errors'])
                ]
            );

            return $this->json([
                'message' => 'Import completed',
                'imported' => count($result['imported']),
                'errors' => count($result['errors']),
                'details' => $result
            ]);

        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_IMPORT_ERROR',
                [
                    'source' => $data['source'] ?? '',
                    'error' => $e->getMessage()
                ]
            );

            return $this->json(['error' => 'Import failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
