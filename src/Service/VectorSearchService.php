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
     * Simple search method for backward compatibility
     * This method is used by RAGChatbotService
     */
    public function search(array $queryEmbedding, int $limit = 5): array
    {
        try {
            // Validate embedding format before proceeding
            if (empty($queryEmbedding)) {
                $this->logger->error('Empty embedding provided to search method');
                // Return empty results instead of throwing exception
                return [];
            }

            // Check embedding dimensions - should be 1536 for OpenAI
            $embeddingLength = count($queryEmbedding);
            if ($embeddingLength != 1536) {
                $this->logger->warning('Unexpected embedding dimensions', [
                    'expected' => 1536,
                    'actual' => $embeddingLength
                ]);
                // Continue anyway, but log the warning
            }

            $this->logger->info('Performing simple vector search', [
                'embeddingLength' => $embeddingLength,
                'limit' => $limit
            ]);

            // Try to use performAtlasVectorSearch if possible
            try {
                $results = $this->performAtlasVectorSearch($queryEmbedding, [], $limit, 0.3);

                // Verify we got an array back
                if (!is_array($results)) {
                    throw new \TypeError('performAtlasVectorSearch did not return an array');
                }

                return $results;
            } catch (\MongoDB\Driver\Exception\AuthenticationException $e) {
                $this->logger->error('MongoDB authentication failed - invalid credentials', [
                    'error' => $e->getMessage()
                ]);
                throw new \RuntimeException('MongoDB authentication failed - check your MongoDB connection string credentials', 0, $e);
            } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
                $this->logger->error('MongoDB connection timeout - could not connect to the database server', [
                    'error' => $e->getMessage()
                ]);
                throw new \RuntimeException('MongoDB connection timed out - please check your network and MongoDB server status', 0, $e);
            } catch (\MongoDB\Driver\Exception\CommandException $e) {
                $this->logger->warning('Atlas vector search command error, likely missing index', [
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e)
                ]);
                // Fall through to text search
            } catch (\Exception $e) {
                $this->logger->warning('Atlas vector search failed in search method, falling back', [
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                // Fall through to text search
            }

            // Fallback to simple text search with empty query (will just return most recent docs)
            try {
                $results = $this->performTextSearch('', [], $limit);

                // Verify we got an array back
                if (!is_array($results)) {
                    throw new \TypeError('performTextSearch did not return an array');
                }

                return $results;
            } catch (\Exception $textSearchError) {
                $this->logger->error('Text search fallback also failed', [
                    'error' => $textSearchError->getMessage()
                ]);

                // Last resort: return empty results rather than throw an exception
                return [];
            }
        } catch (\Exception $e) {
            $this->logger->error('Vector search failed', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty array instead of throwing exception
            // This prevents TypeErrors in calling code
            return [];
        }
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
     * Perform Atlas Vector Search with detailed error handling
     * This implementation first attempts to use Atlas Search vector query,
     * and if that fails (most likely because the index doesn't exist),
     * it will throw a detailed error message to help diagnose the issue.
     */
    private function performAtlasVectorSearch(
        array $queryEmbedding,
        array $filters = [],
        int $limit = 10,
        float $similarityThreshold = 0.7
    ): array {
        $this->logger->info('Preparing vector search', [
            'embeddingLength' => count($queryEmbedding),
            'limit' => $limit
        ]);

        try {
            // Get the MongoDB collection - using try/catch for more robust error handling
            try {
                $collection = $this->knowledgeRepository->getDocumentManager()
                    ->getDocumentCollection(\App\Document\MedicalKnowledge::class);

                if (empty($collection)) {
                    throw new \RuntimeException('Unable to get medical_knowledge collection - collection may not exist');
                }
            } catch (\Throwable $collectionError) {
                $this->logger->error('Failed to get MongoDB collection', [
                    'error' => $collectionError->getMessage(),
                    'type' => get_class($collectionError)
                ]);
                throw new \RuntimeException('Cannot access MongoDB collection: ' . $collectionError->getMessage(), 0, $collectionError);
            }

            // Try to perform a vector search aggregation
            $pipeline = [
                [
                    '$vectorSearch' => [
                        'index' => 'medical_knowledge_vector_index',
                        'path' => 'embedding',
                        'queryVector' => $queryEmbedding,
                        'numCandidates' => $limit * 10,
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

            $this->logger->info('Executing Atlas Search with $vectorSearch', [
                'collection' => 'medical_knowledge',
                'index' => 'medical_knowledge_vector_index'
            ]);

            // Check if the vector search index exists first
            try {
                // Try to list search indexes to see if our index exists
                $command = [
                    'listSearchIndexes' => 'medical_knowledge'
                ];

                $database = $this->knowledgeRepository->getDocumentManager()
                    ->getDocumentDatabase(\App\Document\MedicalKnowledge::class);

                $indexes = $database->command($command)->toArray();

                $indexExists = false;
                foreach ($indexes as $index) {
                    if (isset($index->name) && $index->name === 'medical_knowledge_vector_index') {
                        $indexExists = true;
                        break;
                    }
                }

                if (!$indexExists) {
                    $this->logger->warning('Vector search index not found, will skip vector search', [
                        'expected_index' => 'medical_knowledge_vector_index',
                        'available_indexes' => array_map(function($idx) {
                            return $idx->name ?? 'unnamed';
                        }, $indexes)
                    ]);
                    throw new \RuntimeException('Vector search index not found');
                }
            } catch (\Exception $indexCheckError) {
                // If we can't check indexes, we'll try the search anyway
                $this->logger->warning('Could not verify index existence', [
                    'error' => $indexCheckError->getMessage()
                ]);
            }

            // This will throw an exception if the vector search index doesn't exist
            // Using try-catch to be extra safe
            try {
                $cursor = $collection->aggregate($pipeline);
            } catch (\Throwable $aggregateError) {
                $this->logger->error('Aggregate operation failed', [
                    'error' => $aggregateError->getMessage(),
                    'type' => get_class($aggregateError)
                ]);
                throw $aggregateError;
            }

            $results = [];
            try {
                foreach ($cursor as $document) {
                    $result = [
                        'id' => (string)($document['_id'] ?? 'unknown'),
                        'title' => $document['title'] ?? 'Untitled Document',
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
            } catch (\Throwable $iterationError) {
                $this->logger->error('Error processing search results', [
                    'error' => $iterationError->getMessage(),
                    'type' => get_class($iterationError)
                ]);
                // Return partial results if we have any
                if (empty($results)) {
                    throw $iterationError;
                }
            }

            $this->logger->info('Vector search successful', [
                'resultsCount' => count($results)
            ]);

            return $results;

        } catch (\MongoDB\Driver\Exception\CommandException $e) {
            $errorMessage = $e->getMessage();

            // Look for specific error indicating missing index
            if (strpos($errorMessage, 'index not found') !== false ||
                strpos($errorMessage, 'medical_knowledge_vector_index') !== false) {

                $detailedError = "Vector search index 'medical_knowledge_vector_index' doesn't exist in MongoDB Atlas. " .
                                "To fix this error, you need to create the vector search index in your Atlas cluster. " .
                                "Instructions: " .
                                "1. Go to MongoDB Atlas dashboard " .
                                "2. Select your cluster -> Search tab " .
                                "3. Create a new search index named 'medical_knowledge_vector_index' " .
                                "4. Configure it as a vector search index on the 'embedding' field with 1536 dimensions";

                $this->logger->error('Missing vector search index', [
                    'error' => $errorMessage,
                    'solution' => $detailedError
                ]);

                throw new \RuntimeException($detailedError, 0, $e);
            } else {
                $this->logger->error('MongoDB command error during vector search', [
                    'error' => $errorMessage,
                    'code' => $e->getCode()
                ]);

                throw new \RuntimeException('MongoDB error: ' . $errorMessage, 0, $e);
            }
        } catch (\Throwable $e) {
            // Catch any other errors
            $this->logger->error('Unexpected error in performAtlasVectorSearch', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            throw new \RuntimeException('Error during vector search: ' . $e->getMessage(), 0, $e);
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
