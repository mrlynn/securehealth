<?php

namespace App\Service;

use App\Repository\MedicalKnowledgeRepository;
use Psr\Log\LoggerInterface;

class VectorSearchService
{
    private MedicalKnowledgeRepository $knowledgeRepository;
    private EmbeddingService $embeddingService;
    private LoggerInterface $logger;

    public function __construct(
        MedicalKnowledgeRepository $knowledgeRepository,
        EmbeddingService $embeddingService,
        LoggerInterface $logger
    ) {
        $this->knowledgeRepository = $knowledgeRepository;
        $this->embeddingService = $embeddingService;
        $this->logger = $logger;
    }

    /**
     * Perform semantic search across medical knowledge
     */
    public function searchMedicalKnowledge(
        string $query,
        array $filters = [],
        int $limit = 10,
        float $similarityThreshold = 0.7
    ): array {
        try {
            // Generate embedding for the search query
            $queryEmbedding = $this->embeddingService->generateEmbedding($query);

            // Extract filters
            $specialty = $filters['specialty'] ?? null;
            $tags = $filters['tags'] ?? null;
            $minConfidenceLevel = $filters['minConfidenceLevel'] ?? null;
            $minEvidenceLevel = $filters['minEvidenceLevel'] ?? null;

            // Try Atlas Search vector search first
            try {
                $results = $this->performAtlasVectorSearch(
                    $queryEmbedding,
                    $filters,
                    $limit,
                    0.3  // Lower similarity threshold
                );
                
                if (!empty($results)) {
                    $this->logger->info('Atlas vector search successful', [
                        'query' => $query,
                        'resultsCount' => count($results)
                    ]);
                    return $results;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Atlas vector search failed, falling back to text search', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Fallback to text search
            $this->logger->info('Using text search fallback');
            return $this->performTextSearch($query, $filters, $limit);

        } catch (\Exception $e) {
            $this->logger->error('Failed to perform semantic search', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            // Final fallback to text search
            try {
                return $this->performTextSearch($query, $filters, $limit);
            } catch (\Exception $textError) {
                throw new \Exception('Search failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Test Atlas Search functionality
     */
    public function testAtlasSearch(): array
    {
        try {
            // Simple test query with a basic embedding
            $testEmbedding = array_fill(0, 1536, 0.1); // Simple test embedding
            
            $pipeline = [
                [
                    '$vectorSearch' => [
                        'index' => 'vector_search_index',
                        'path' => 'embedding',
                        'queryVector' => $testEmbedding,
                        'numCandidates' => 10,
                        'limit' => 5
                    ]
                ],
                [
                    '$match' => [
                        'isActive' => true
                    ]
                ]
            ];

            $collection = $this->knowledgeRepository->getDocumentManager()
                ->getDocumentCollection(\App\Document\MedicalKnowledge::class);
            
            $this->logger->info('Testing Atlas Search', ['pipeline' => $pipeline]);
            
            $cursor = $collection->aggregate($pipeline);
            $results = iterator_to_array($cursor);
            
            $this->logger->info('Atlas Search test results', [
                'count' => count($results),
                'results' => array_map(function($doc) {
                    return [
                        'id' => (string)$doc['_id'],
                        'title' => $doc['title'] ?? 'No title'
                    ];
                }, $results)
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logger->error('Atlas Search test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Perform Atlas Search vector search
     */
    private function performAtlasVectorSearch(
        array $queryEmbedding,
        array $filters = [],
        int $limit = 10,
        float $similarityThreshold = 0.7
    ): array {
        $this->logger->info('Performing Atlas vector search', [
            'embeddingLength' => count($queryEmbedding),
            'filters' => $filters,
            'limit' => $limit,
            'similarityThreshold' => $similarityThreshold
        ]);

        try {
            // Build simple Atlas Search aggregation pipeline - $vectorSearch must be first stage
            $pipeline = [
                [
                    '$vectorSearch' => [
                        'index' => 'vector_search_index', // This should match your Atlas Search index name
                        'path' => 'embedding',
                        'queryVector' => $queryEmbedding,
                        'numCandidates' => $limit * 10, // Search more candidates than needed
                        'limit' => $limit
                    ]
                ],
                [
                    '$match' => [
                        'isActive' => true
                    ]
                ],
                [
                    '$addFields' => [
                        'score' => ['$meta' => 'vectorSearchScore']
                    ]
                ],
                [
                    '$match' => [
                        'score' => ['$gte' => $similarityThreshold]
                    ]
                ],
                [
                    '$sort' => ['score' => -1]
                ]
            ];

            $this->logger->info('Atlas Search pipeline', ['pipeline' => $pipeline]);

            // Execute the aggregation
            $collection = $this->knowledgeRepository->getDocumentManager()
                ->getDocumentCollection(\App\Document\MedicalKnowledge::class);
            
            $this->logger->info('Executing Atlas Search aggregation', [
                'collection' => 'medical_knowledge',
                'pipeline' => $pipeline
            ]);
            
            $cursor = $collection->aggregate($pipeline);

            $results = [];
            foreach ($cursor as $document) {
                $result = [
                    'id' => (string)$document['_id'],
                    'title' => $document['title'] ?? '',
                    'summary' => $document['summary'] ?? '',
                    'content' => $document['content'] ?? '',
                    'source' => $document['source'] ?? '',
                    'sourceUrl' => $document['sourceUrl'] ?? null,
                    'confidenceLevel' => $document['confidenceLevel'] ?? 5,
                    'evidenceLevel' => $document['evidenceLevel'] ?? 3,
                    'tags' => $document['tags'] ?? [],
                    'specialties' => $document['specialties'] ?? [],
                    'relatedConditions' => $document['relatedConditions'] ?? [],
                    'relatedMedications' => $document['relatedMedications'] ?? [],
                    'relatedProcedures' => $document['relatedProcedures'] ?? [],
                    'requiresReview' => $document['requiresReview'] ?? false,
                    'isActive' => $document['isActive'] ?? true,
                    'createdAt' => $this->formatDate($document['createdAt'] ?? null),
                    'score' => $document['score'] ?? 0
                ];
                
                $results[] = (object)$result;
            }

            $this->logger->info('Atlas vector search completed', [
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Atlas vector search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Helper method to format date objects
     */
    private function formatDate($date, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (!$date) {
            return null;
        }
        
        if ($date instanceof \MongoDB\BSON\UTCDateTime) {
            return $date->toDateTime()->format($format);
        } elseif ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        return null;
    }

    /**
     * Perform text-based search as fallback
     */
    private function performTextSearch(string $query, array $filters = [], int $limit = 10): array
    {
        $this->logger->info('Performing text search', [
            'query' => $query,
            'filters' => $filters,
            'limit' => $limit
        ]);

        try {
            // Use the repository to search for active medical knowledge
            $allEntries = $this->knowledgeRepository->findAllActive();
            
            $this->logger->info('Found active entries', [
                'count' => count($allEntries),
                'query' => $query
            ]);

            // Filter results based on query
            $filteredResults = [];
            foreach ($allEntries as $entry) {
                $matches = false;
                
                if (empty($query)) {
                    $matches = true;
                } else {
                    $searchText = strtolower($query);
                    $matches = strpos(strtolower($entry->getTitle()), $searchText) !== false ||
                              strpos(strtolower($entry->getSummary() ?? ''), $searchText) !== false ||
                              strpos(strtolower($entry->getContent()), $searchText) !== false ||
                              in_array($searchText, array_map('strtolower', $entry->getTags())) ||
                              in_array($searchText, array_map('strtolower', $entry->getRelatedConditions()));
                }
                
                if ($matches) {
                    // Apply additional filters
                    if (isset($filters['specialty']) && $filters['specialty'] && !in_array($filters['specialty'], $entry->getSpecialties())) {
                        continue;
                    }
                    
                    if (isset($filters['minConfidenceLevel']) && $filters['minConfidenceLevel'] && $entry->getConfidenceLevel() < (int)$filters['minConfidenceLevel']) {
                        continue;
                    }
                    
                    if (isset($filters['minEvidenceLevel']) && $filters['minEvidenceLevel'] && $entry->getEvidenceLevel() < (int)$filters['minEvidenceLevel']) {
                        continue;
                    }
                    
                    // Convert to array format
                    $result = [
                        'id' => (string)$entry->getId(),
                        'title' => $entry->getTitle(),
                        'summary' => $entry->getSummary(),
                        'content' => $entry->getContent(),
                        'source' => $entry->getSource(),
                        'sourceUrl' => $entry->getSourceUrl(),
                        'confidenceLevel' => $entry->getConfidenceLevel(),
                        'evidenceLevel' => $entry->getEvidenceLevel(),
                        'tags' => $entry->getTags(),
                        'specialties' => $entry->getSpecialties(),
                        'relatedConditions' => $entry->getRelatedConditions(),
                        'relatedMedications' => $entry->getRelatedMedications(),
                        'relatedProcedures' => $entry->getRelatedProcedures(),
                        'requiresReview' => $entry->getRequiresReview(),
                        'isActive' => $entry->getIsActive(),
                        'createdAt' => $this->formatDate($entry->getCreatedAt())
                    ];
                    
                    $filteredResults[] = (object)$result;
                }
            }

            // Sort by confidence level, evidence level, then creation date
            usort($filteredResults, function($a, $b) {
                if ($a->confidenceLevel != $b->confidenceLevel) {
                    return $b->confidenceLevel - $a->confidenceLevel;
                }
                if ($a->evidenceLevel != $b->evidenceLevel) {
                    return $b->evidenceLevel - $a->evidenceLevel;
                }
                return strtotime($b->createdAt) - strtotime($a->createdAt);
            });

            // Limit results
            $results = array_slice($filteredResults, 0, $limit);

            $this->logger->info('Text search completed', [
                'query' => $query,
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Text search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to empty results instead of mock data
            return [];
        }
    }

    /**
     * Find similar medical knowledge entries
     */
    public function findSimilarKnowledge(
        string $referenceText,
        int $limit = 5,
        float $similarityThreshold = 0.8
    ): array {
        try {
            $referenceEmbedding = $this->embeddingService->generateEmbedding($referenceText);

            $results = $this->knowledgeRepository->vectorSearch(
                $referenceEmbedding,
                $limit,
                $similarityThreshold
            );

            $this->logger->info('Found similar medical knowledge', [
                'referenceTextLength' => strlen($referenceText),
                'resultsCount' => count($results),
                'similarityThreshold' => $similarityThreshold
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Failed to find similar knowledge', [
                'referenceTextLength' => strlen($referenceText),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Similar knowledge search failed: ' . $e->getMessage());
        }
    }

    /**
     * Get clinical decision support based on patient data
     */
    public function getClinicalDecisionSupport(
        array $patientData,
        ?string $specialty = null,
        int $limit = 10
    ): array {
        try {
            // Build query from patient data
            $queryParts = [];
            
            if (!empty($patientData['conditions'])) {
                $queryParts[] = 'conditions: ' . implode(', ', $patientData['conditions']);
            }
            
            if (!empty($patientData['medications'])) {
                $queryParts[] = 'medications: ' . implode(', ', $patientData['medications']);
            }
            
            if (!empty($patientData['symptoms'])) {
                $queryParts[] = 'symptoms: ' . implode(', ', $patientData['symptoms']);
            }

            $query = implode('; ', $queryParts);
            
            if (empty($query)) {
                return [];
            }

            $filters = [];
            if ($specialty) {
                $filters['specialty'] = $specialty;
            }

            // Set higher confidence and evidence requirements for clinical decisions
            $filters['minConfidenceLevel'] = 7;
            $filters['minEvidenceLevel'] = 4;

            $results = $this->searchMedicalKnowledge(
                $query,
                $filters,
                $limit,
                0.8 // Higher similarity threshold for clinical decisions
            );

            $this->logger->info('Generated clinical decision support', [
                'patientDataKeys' => array_keys($patientData),
                'specialty' => $specialty,
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate clinical decision support', [
                'patientDataKeys' => array_keys($patientData),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Clinical decision support failed: ' . $e->getMessage());
        }
    }

    /**
     * Search for drug interactions and contraindications
     */
    public function searchDrugInteractions(
        array $medications,
        ?array $conditions = null,
        ?array $allergies = null
    ): array {
        try {
            $queryParts = ['drug interactions'];
            
            if (!empty($medications)) {
                $queryParts[] = 'medications: ' . implode(', ', $medications);
            }
            
            if (!empty($conditions)) {
                $queryParts[] = 'conditions: ' . implode(', ', $conditions);
            }
            
            if (!empty($allergies)) {
                $queryParts[] = 'allergies: ' . implode(', ', $allergies);
            }

            $query = implode('; ', $queryParts);

            $filters = [
                'tags' => ['drug-interaction', 'contraindication', 'safety'],
                'minConfidenceLevel' => 8,
                'minEvidenceLevel' => 4
            ];

            $results = $this->searchMedicalKnowledge(
                $query,
                $filters,
                15,
                0.75
            );

            $this->logger->info('Searched for drug interactions', [
                'medications' => $medications,
                'conditions' => $conditions,
                'allergies' => $allergies,
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Failed to search drug interactions', [
                'medications' => $medications,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Drug interaction search failed: ' . $e->getMessage());
        }
    }

    /**
     * Search for treatment protocols and guidelines
     */
    public function searchTreatmentGuidelines(
        string $condition,
        ?string $specialty = null,
        ?string $severity = null,
        ?string $patientAge = null
    ): array {
        try {
            $queryParts = ['treatment guidelines', 'protocols', $condition];
            
            if ($severity) {
                $queryParts[] = $severity . ' severity';
            }
            
            if ($patientAge) {
                $queryParts[] = $patientAge . ' age group';
            }

            $query = implode(' ', $queryParts);

            $filters = [
                'tags' => ['treatment', 'guidelines', 'protocols'],
                'minConfidenceLevel' => 8,
                'minEvidenceLevel' => 4
            ];

            if ($specialty) {
                $filters['specialty'] = $specialty;
            }

            $results = $this->searchMedicalKnowledge(
                $query,
                $filters,
                10,
                0.8
            );

            $this->logger->info('Searched for treatment guidelines', [
                'condition' => $condition,
                'specialty' => $specialty,
                'severity' => $severity,
                'patientAge' => $patientAge,
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Failed to search treatment guidelines', [
                'condition' => $condition,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Treatment guidelines search failed: ' . $e->getMessage());
        }
    }

    /**
     * Search for diagnostic criteria and differential diagnoses
     */
    public function searchDiagnosticCriteria(
        array $symptoms,
        ?array $signs = null,
        ?array $testResults = null,
        ?string $specialty = null
    ): array {
        try {
            $queryParts = ['diagnostic criteria', 'differential diagnosis'];
            
            if (!empty($symptoms)) {
                $queryParts[] = 'symptoms: ' . implode(', ', $symptoms);
            }
            
            if (!empty($signs)) {
                $queryParts[] = 'signs: ' . implode(', ', $signs);
            }
            
            if (!empty($testResults)) {
                $queryParts[] = 'test results: ' . implode(', ', $testResults);
            }

            $query = implode('; ', $queryParts);

            $filters = [
                'tags' => ['diagnosis', 'criteria', 'differential'],
                'minConfidenceLevel' => 7,
                'minEvidenceLevel' => 4
            ];

            if ($specialty) {
                $filters['specialty'] = $specialty;
            }

            $results = $this->searchMedicalKnowledge(
                $query,
                $filters,
                12,
                0.75
            );

            $this->logger->info('Searched for diagnostic criteria', [
                'symptoms' => $symptoms,
                'signs' => $signs,
                'testResults' => $testResults,
                'specialty' => $specialty,
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Failed to search diagnostic criteria', [
                'symptoms' => $symptoms,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Diagnostic criteria search failed: ' . $e->getMessage());
        }
    }

    /**
     * Get related knowledge for a specific medical concept
     */
    public function getRelatedKnowledge(
        string $concept,
        string $relationshipType = 'general',
        int $limit = 8
    ): array {
        try {
            $query = $concept . ' ' . $relationshipType;

            $filters = [
                'minConfidenceLevel' => 6,
                'minEvidenceLevel' => 3
            ];

            $results = $this->searchMedicalKnowledge(
                $query,
                $filters,
                $limit,
                0.7
            );

            $this->logger->info('Retrieved related knowledge', [
                'concept' => $concept,
                'relationshipType' => $relationshipType,
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get related knowledge', [
                'concept' => $concept,
                'relationshipType' => $relationshipType,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Related knowledge retrieval failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform multi-modal search combining multiple query types
     */
    public function performMultiModalSearch(
        array $queries,
        array $filters = [],
        int $limit = 15
    ): array {
        try {
            $allResults = [];
            $seenIds = [];

            foreach ($queries as $query) {
                $results = $this->searchMedicalKnowledge(
                    $query['text'],
                    array_merge($filters, $query['filters'] ?? []),
                    $limit,
                    $query['threshold'] ?? 0.7
                );

                // Add weight to results based on query type
                $weight = $query['weight'] ?? 1.0;
                
                foreach ($results as $result) {
                    $id = (string)$result->getId();
                    
                    if (!isset($seenIds[$id])) {
                        $seenIds[$id] = true;
                        $result->setConfidenceLevel($result->getConfidenceLevel() * $weight);
                        $allResults[] = $result;
                    }
                }
            }

            // Sort by confidence level
            usort($allResults, function($a, $b) {
                return $b->getConfidenceLevel() <=> $a->getConfidenceLevel();
            });

            $finalResults = array_slice($allResults, 0, $limit);

            $this->logger->info('Performed multi-modal search', [
                'queryCount' => count($queries),
                'totalResults' => count($allResults),
                'finalResults' => count($finalResults)
            ]);

            return $finalResults;

        } catch (\Exception $e) {
            $this->logger->error('Failed to perform multi-modal search', [
                'queryCount' => count($queries),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Multi-modal search failed: ' . $e->getMessage());
        }
    }
}
