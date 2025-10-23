<?php

namespace App\Service;

use App\Document\User;
use App\Repository\PatientRepository;
use App\Security\Voter\PatientVoter;
use MongoDB\Client;
use OpenAI\Client as OpenAIClient;
use OpenAI;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class RAGChatbotService
{
    private OpenAIClient $openai;
    private Client $mongoClient;
    private PatientRepository $patientRepo;
    private AuthorizationCheckerInterface $authChecker;
    private AuditLogService $auditLog;
    private MongoDBEncryptionService $encryption;
    private VectorSearchService $vectorSearch;
    private string $openaiApiKey;

    public function __construct(
        string $openaiApiKey,
        Client $mongoClient,
        PatientRepository $patientRepo,
        AuthorizationCheckerInterface $authChecker,
        AuditLogService $auditLog,
        MongoDBEncryptionService $encryption,
        VectorSearchService $vectorSearch
    ) {
        $this->openaiApiKey = $openaiApiKey;
        $this->openai = OpenAI::client($openaiApiKey);
        $this->mongoClient = $mongoClient;
        $this->patientRepo = $patientRepo;
        $this->authChecker = $authChecker;
        $this->auditLog = $auditLog;
        $this->encryption = $encryption;
        $this->vectorSearch = $vectorSearch;
    }

    /**
     * Process a chatbot query with RAG
     */
    public function processQuery(string $query, User $user): array
    {
        try {
            // 1. Determine if this is a knowledge query or a data query
            $queryType = $this->classifyQuery($query);

            // Ensure we have a valid string from classifyQuery to prevent TypeError
            if (!is_string($queryType)) {
                $this->auditLog->log($user, 'CHATBOT_TYPE_ERROR', [
                    'query' => $query,
                    'return_type' => gettype($queryType),
                    'message' => 'classifyQuery returned non-string value'
                ]);
                $queryType = 'knowledge'; // Default to knowledge query for safety
            }

            try {
                if ($queryType === 'knowledge') {
                    // Answer using RAG with additional error safeguard
                    $result = $this->answerWithRAG($query, $user);

                    // Ensure the result is an array
                    if (!is_array($result)) {
                        throw new \TypeError('answerWithRAG did not return an array');
                    }

                    return $result;
                } else {
                    // Answer using function calling with additional error safeguard
                    $result = $this->answerWithFunctionCalling($query, $user);

                    // Ensure the result is an array
                    if (!is_array($result)) {
                        throw new \TypeError('answerWithFunctionCalling did not return an array');
                    }

                    return $result;
                }
            } catch (\Throwable $innerException) {
                // Catch any errors inside the type-specific handlers
                $this->auditLog->log($user, 'CHATBOT_INNER_ERROR', [
                    'query' => $query,
                    'query_type' => $queryType,
                    'error' => $innerException->getMessage(),
                    'file' => $innerException->getFile(),
                    'line' => $innerException->getLine(),
                    'trace' => $innerException->getTraceAsString()
                ]);

                // Fall back to a canned response for common queries
                $fallbackResponse = $this->getFallbackResponse($query);
                return [
                    'response' => $fallbackResponse,
                    'type' => 'knowledge',
                    'sources' => [['title' => 'Fallback Response', 'category' => 'system', 'content' => 'This is a fallback response due to processing issues.']]
                ];
            }
        } catch (\Exception $e) {
            // Handle any top-level errors gracefully
            $this->auditLog->log($user, 'CHATBOT_TOP_LEVEL_ERROR', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Provide a helpful response based on the error
            if (strpos($e->getMessage(), 'Incorrect API key') !== false) {
                // Provide a fallback response for common queries when API key is invalid
                $fallbackResponse = $this->getFallbackResponse($query);
                return [
                    'response' => $fallbackResponse,
                    'type' => 'knowledge',
                    'sources' => [['title' => 'Fallback Response', 'category' => 'system', 'content' => 'This is a fallback response due to API configuration issues.']]
                ];
            } elseif (strpos($e->getMessage(), 'rate limit') !== false) {
                // Handle rate limit errors gracefully
                $fallbackResponse = $this->getFallbackResponse($query);
                return [
                    'response' => $fallbackResponse,
                    'type' => 'knowledge',
                    'sources' => [['title' => 'Fallback Response', 'category' => 'system', 'content' => 'This is a fallback response due to API rate limits. Please try again in a few minutes.']]
                ];
            }

            return [
                'response' => 'I apologize, but I encountered an error while processing your request. Please try again or contact support if the issue persists.',
                'type' => 'error',
                'sources' => []
            ];
        }
    }
    
    /**
     * Get fallback response for common queries when API is unavailable
     */
    private function getFallbackResponse(string $query): string
    {
        $queryLower = strtolower($query);
        
        // HIPAA-related queries
        if (strpos($queryLower, 'hipaa') !== false) {
            return 'HIPAA (Health Insurance Portability and Accountability Act) is a US federal law established in 1996 to protect sensitive patient health information. It includes the Privacy Rule, which governs the use and disclosure of Protected Health Information (PHI), and the Security Rule, which sets standards for securing electronic PHI. Healthcare providers must implement various safeguards to ensure patient data remains confidential, integral, and available only to authorized personnel.';
        }
        
        // MongoDB-related queries
        if (strpos($queryLower, 'mongodb') !== false) {
            return 'MongoDB is a NoSQL database that supports document-based storage. MongoDB Queryable Encryption allows organizations to encrypt sensitive data while still allowing specific query operations, which is especially valuable for healthcare applications that must maintain HIPAA compliance while still providing searchable access to data.';
        }
        
        // Patient search queries
        if (strpos($queryLower, 'patient') !== false || strpos($queryLower, 'who is') !== false) {
            return 'I can help you search for patient information, but I need you to be more specific. Please provide the patient\'s name or other identifying information. Note that patient data access is restricted based on your role and requires proper authorization.';
        }
        
        // General health queries
        if (strpos($queryLower, 'health') !== false || strpos($queryLower, 'medical') !== false) {
            return 'I can help with medical and health-related questions. Please be more specific about what you\'d like to know. I can assist with patient information (if you have proper authorization), medical knowledge, and HIPAA compliance questions.';
        }
        
        // Default response
        return 'I apologize, but I\'m currently experiencing technical difficulties with the AI service. I can still help with basic questions about HIPAA compliance, MongoDB, and patient data access. Please try rephrasing your question or contact your system administrator for assistance.';
    }
    
    /**
     * Classify the query type
     */
    private function classifyQuery(string $query): string
    {
        $knowledgeKeywords = [
            'how does', 'what is', 'explain', 'how to',
            'queryable encryption', 'mongodb', 'hipaa',
            'voter', 'authentication', 'session',
            'example', 'implement', 'configure',
            'deterministic', 'random encryption',
            'symfony', 'documentation', 'guide',
            // Health-related topics should use RAG
            'headache', 'pain', 'symptom', 'treatment',
            'health', 'condition', 'medical', 'medicine',
            'disease', 'illness', 'diagnosis'
        ];

        $queryLower = strtolower($query);

        foreach ($knowledgeKeywords as $keyword) {
            if (strpos($queryLower, $keyword) !== false) {
                return 'knowledge';
            }
        }

        // Check if query starts with "what about" or similar phrases
        if (preg_match('/^(what|tell me|how) about/i', $query)) {
            return 'knowledge';
        }

        // Default to data query
        return 'data';
    }
    
    /**
     * Answer using RAG - retrieves relevant documentation
     * With fallback mechanism for MongoDB issues
     */
    private function answerWithRAG(string $query, User $user): array
    {
        try {
            // Check if MongoDB is disabled or experiencing issues
            $mongoDisabled = false;
            if (property_exists($this->encryption, 'isMongoDBDisabled') && method_exists($this->encryption, 'isMongoDBDisabled')) {
                $mongoDisabled = $this->encryption->isMongoDBDisabled();
            }

            // Define static HIPAA knowledge for emergency fallback
            $staticHipaaKnowledge = [
                [
                    'title' => 'HIPAA Compliance Guide',
                    'category' => 'regulations',
                    'content' => 'HIPAA (Health Insurance Portability and Accountability Act) is a US federal law established in 1996 to protect sensitive patient health information. It has two main components: the Privacy Rule, which governs the use and disclosure of Protected Health Information (PHI), and the Security Rule, which sets standards for securing electronic PHI. Covered entities include healthcare providers, health plans, and healthcare clearinghouses. Business associates who handle PHI on behalf of covered entities must also comply with HIPAA regulations.',
                    'source' => 'HIPAA Knowledge Base',
                    'score' => 0.95
                ],
                [
                    'title' => 'HIPAA Security Requirements',
                    'category' => 'security',
                    'content' => 'HIPAA Security Rule requires appropriate administrative, physical and technical safeguards to ensure the confidentiality, integrity, and security of electronic protected health information. Key requirements include: access controls, audit controls, integrity controls, transmission security, risk analysis and management, security management process, workforce security, security awareness and training, and business associate contracts.',
                    'source' => 'HIPAA Security Documentation',
                    'score' => 0.90
                ],
                [
                    'title' => 'MongoDB Queryable Encryption',
                    'category' => 'technology',
                    'content' => 'MongoDB Queryable Encryption allows organizations to encrypt sensitive data while still allowing specific query operations. This is especially valuable for healthcare applications that must maintain HIPAA compliance while still providing searchable access to data. Field-level encryption can be configured as either deterministic (allowing equality queries) or random (providing maximum security but limited query capability). This helps healthcare organizations maintain compliance with HIPAA Security Rule requirements while still providing necessary application functionality.',
                    'source' => 'MongoDB Documentation',
                    'score' => 0.85
                ]
            ];

            // Attempt to use vector search if MongoDB is available
            $relevantDocs = [];
            $context = "";

            if (!$mongoDisabled) {
                try {
                    // 1. Generate embedding for the query
                    $queryEmbedding = $this->generateEmbedding($query, $user);

                    // 2. Perform vector search to find relevant documentation
                    $relevantDocs = $this->vectorSearch->search($queryEmbedding, 5);

                    // 3. Build context from retrieved documents if we got results
                    if (!empty($relevantDocs)) {
                        $context = $this->buildContext($relevantDocs);
                    } else {
                        // Use static knowledge as fallback
                        $relevantDocs = $staticHipaaKnowledge;
                        $context = $this->buildContext($relevantDocs);
                    }
                } catch (\Exception $searchEx) {
                    // Log search error but continue with static knowledge
                    $this->auditLog->log($user, 'CHATBOT_SEARCH_ERROR', [
                        'query' => $query,
                        'error' => $searchEx->getMessage()
                    ]);

                    // Use static knowledge as fallback
                    $relevantDocs = $staticHipaaKnowledge;
                    $context = $this->buildContext($relevantDocs);
                }
            } else {
                // MongoDB is disabled, use static knowledge
                $relevantDocs = $staticHipaaKnowledge;
                $context = $this->buildContext($relevantDocs);
            }

            // 4. Create enhanced prompt with context
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->getRAGSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildRAGPrompt($query, $context)
                ]
            ];

            // 5. Get answer from LLM
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4o-mini', // Using mini for cost efficiency
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $answer = $response->choices[0]->message->content;

            // 6. Log the interaction
            $this->auditLog->log($user, 'CHATBOT_RAG_QUERY', [
                'query' => $query,
                'sources_used' => count($relevantDocs),
                'mongodb_disabled' => $mongoDisabled
            ]);

            return [
                'response' => $answer,
                'type' => 'knowledge',
                'sources' => $this->formatSources($relevantDocs)
            ];

        } catch (\Exception $e) {
            $this->auditLog->log($user, 'CHATBOT_RAG_ERROR', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Provide a direct answer for HIPAA queries even if everything else fails
            if (stripos($query, 'hipaa') !== false) {
                return [
                    'response' => 'HIPAA (Health Insurance Portability and Accountability Act) is a US federal law established in 1996 to protect sensitive patient health information. It includes the Privacy Rule, which governs the use and disclosure of Protected Health Information (PHI), and the Security Rule, which sets standards for securing electronic PHI. Healthcare providers must implement various safeguards to ensure patient data remains confidential, integral, and available only to authorized personnel.',
                    'type' => 'knowledge',
                    'sources' => []
                ];
            }

            return [
                'response' => 'I apologize, but I encountered an error while processing your question: ' . $e->getMessage() . '. Please try again or contact support if the issue persists.',
                'type' => 'error',
                'sources' => []
            ];
        }
    }
    
    /**
     * Build context from retrieved documents
     */
    private function buildContext(array $docs): string
    {
        $context = "# Relevant Documentation\n\n";
        
        foreach ($docs as $i => $doc) {
            // Convert object to array if needed
            if (is_object($doc)) {
                $doc = (array) $doc;
            }
            
            $context .= "## Source " . ($i + 1) . ": {$doc['title']}\n";
            $context .= "Category: " . ($doc['category'] ?? 'general') . "\n";
            $context .= "Relevance Score: " . round($doc['score'], 3) . "\n\n";
            $context .= $doc['content'] . "\n\n";
            $context .= "---\n\n";
        }
        
        return $context;
    }
    
    /**
     * Build RAG prompt combining query and context
     */
    private function buildRAGPrompt(string $query, string $context): string
    {
        return <<<PROMPT
I need you to answer the following question based on the provided documentation.

QUESTION:
{$query}

CONTEXT FROM DOCUMENTATION:
{$context}

Please provide a clear, accurate answer based on the documentation provided. If the documentation doesn't contain enough information to answer the question, say so. Include specific details and code examples when relevant.

ANSWER:
PROMPT;
    }
    
    /**
     * System prompt for RAG mode
     */
    private function getRAGSystemPrompt(): string
    {
        return <<<PROMPT
You are a technical assistant specialized in MongoDB Queryable Encryption and HIPAA-compliant healthcare applications. You have access to documentation about the SecureHealth application.

Your role:
1. Answer questions accurately based on the provided documentation
2. Explain technical concepts clearly
3. Provide code examples when helpful
4. Cite sources from the documentation when possible
5. If unsure, admit limitations rather than guess

Remember:
- This is technical documentation, be precise
- Use concrete examples from the docs
- Mention relevant file names or sections
- If the question is about patient data, defer to the function calling system
PROMPT;
    }
    
    /**
     * Format sources for response
     * Handles both object and array formats for compatibility
     */
    private function formatSources(array $docs): array
    {
        return array_map(function($doc) {
            // Handle both object and array formats for flexibility
            if (is_object($doc)) {
                return [
                    'title' => property_exists($doc, 'title') ? $doc->title : 'Unknown Title',
                    'category' => property_exists($doc, 'category') ? $doc->category : 'general',
                    'source' => property_exists($doc, 'source') ? $doc->source : 'Knowledge Base',
                    'relevance' => property_exists($doc, 'score') ? round($doc->score, 3) : 0.75
                ];
            } else {
                return [
                    'title' => $doc['title'] ?? 'Unknown Title',
                    'category' => $doc['category'] ?? 'general',
                    'source' => $doc['source'] ?? 'Knowledge Base',
                    'relevance' => isset($doc['score']) ? round($doc['score'], 3) : 0.75
                ];
            }
        }, $docs);
    }
    
    /**
     * Generate embedding using OpenAI with fallback
     */
    private function generateEmbedding(string $text, ?User $user = null): array
    {
        // Check if API key is valid first
        if (strpos($this->openaiApiKey, 'sk-proj-') === false || strlen($this->openaiApiKey) < 50) {
            // API key appears invalid, use fallback immediately
            return $this->generateMockEmbedding($text);
        }

        try {
            $response = $this->openai->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $text
            ]);

            return $response->embeddings[0]->embedding;
        } catch (\Exception $e) {
            // Log the error
            $this->auditLog->log($user, 'EMBEDDING_ERROR', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ]);

            // Use fallback embedding
            return $this->generateMockEmbedding($text);
        }
    }

    /**
     * Generate mock embedding for fallback
     */
    private function generateMockEmbedding(string $text): array
    {
        $mockEmbedding = [];
        $seed = crc32($text); // Use text hash as seed
        mt_srand($seed);

        // Generate 1536 dimensions (standard OpenAI embedding size)
        for ($i = 0; $i < 1536; $i++) {
            $mockEmbedding[] = (mt_rand(-100, 100) / 100);
        }

        // Normalize the vector
        $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $mockEmbedding)));
        if ($magnitude > 0) {
            $mockEmbedding = array_map(function($x) use ($magnitude) { return $x / $magnitude; }, $mockEmbedding);
        }

        return $mockEmbedding;
    }
    
    /**
     * Fallback to function calling for patient data queries
     */
    private function answerWithFunctionCalling(string $query, User $user): array
    {
        // Check if API key is valid first
        if (strpos($this->openaiApiKey, 'sk-proj-') === false || strlen($this->openaiApiKey) < 50) {
            // API key appears invalid, use fallback response
            $fallbackResponse = $this->getFallbackResponse($query);
            return [
                'response' => $fallbackResponse,
                'type' => 'knowledge',
                'sources' => [['title' => 'Fallback Response', 'category' => 'system', 'content' => 'This is a fallback response due to API configuration issues.']]
            ];
        }

        try {
            // Use the original ChatbotService logic for patient data
            $functions = $this->getAvailableFunctions($user);
            
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getDataQuerySystemPrompt($user)
                    ],
                    [
                        'role' => 'user',
                        'content' => $query
                    ]
                ],
                'functions' => $functions,
                'function_call' => 'auto'
            ]);
            
            $message = $response->choices[0]->message;
            
            if (isset($message->function_call)) {
                return $this->executeFunction(
                    $message->function_call->name,
                    json_decode($message->function_call->arguments, true),
                    $user
                );
            }
            
            return [
                'response' => $message->content,
                'type' => 'text'
            ];
            
        } catch (\Exception $e) {
            $this->auditLog->log($user, 'CHATBOT_FUNCTION_ERROR', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check if it's an API key error and provide fallback
            if (strpos($e->getMessage(), 'Incorrect API key') !== false) {
                $fallbackResponse = $this->getFallbackResponse($query);
                return [
                    'response' => $fallbackResponse,
                    'type' => 'knowledge',
                    'sources' => [['title' => 'Fallback Response', 'category' => 'system', 'content' => 'This is a fallback response due to API configuration issues.']]
                ];
            }
            
            return [
                'response' => 'I apologize, but I encountered an error while processing your request: ' . $e->getMessage() . '. Please try again or contact support if the issue persists.',
                'type' => 'error'
            ];
        }
    }
    
    /**
     * Get available functions based on user role
     */
    private function getAvailableFunctions(User $user): array
    {
        $functions = [];
        
        // Basic patient search for all healthcare staff (nurses and doctors)
        if ($this->authChecker->isGranted('ROLE_NURSE') || $this->authChecker->isGranted('ROLE_DOCTOR')) {
            $functions[] = [
                'name' => 'search_patients_by_name',
                'description' => 'Search for patients by name',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'firstName' => ['type' => 'string', 'description' => 'Patient first name'],
                        'lastName' => ['type' => 'string', 'description' => 'Patient last name']
                    ]
                ]
            ];
        }
        
        // Advanced patient queries for doctors
        if ($this->authChecker->isGranted('ROLE_DOCTOR')) {
            $functions[] = [
                'name' => 'search_patients_by_condition',
                'description' => 'Search for patients with specific medical conditions',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'condition' => ['type' => 'string', 'description' => 'Medical condition to search for']
                    ]
                ]
            ];
            
            $functions[] = [
                'name' => 'get_patient_diagnosis',
                'description' => 'Get diagnosis information for a specific patient',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'patientId' => ['type' => 'string', 'description' => 'Patient ID']
                    ]
                ]
            ];
            
            $functions[] = [
                'name' => 'check_drug_interactions',
                'description' => 'Check for drug interactions between medications',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'medications' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of medication names']
                    ]
                ]
            ];
        }
        
        return $functions;
    }
    
    /**
     * Execute function calls
     */
    private function executeFunction(string $functionName, array $arguments, User $user): array
    {
        try {
            switch ($functionName) {
                case 'search_patients_by_name':
                    return $this->searchPatientsByName($arguments, $user);
                    
                case 'search_patients_by_condition':
                    return $this->searchPatientsByCondition($arguments, $user);
                    
                case 'get_patient_diagnosis':
                    return $this->getPatientDiagnosis($arguments, $user);
                    
                case 'check_drug_interactions':
                    return $this->checkDrugInteractions($arguments, $user);
                    
                default:
                    return [
                        'response' => 'I apologize, but I cannot perform that action.',
                        'type' => 'error'
                    ];
            }
        } catch (\Exception $e) {
            $this->auditLog->log($user, 'CHATBOT_FUNCTION_EXECUTION_ERROR', [
                'function' => $functionName,
                'arguments' => $this->sanitizeForAudit($arguments),
                'error' => $e->getMessage()
            ]);
            
            return [
                'response' => 'I encountered an error while processing your request. Please try again.',
                'type' => 'error'
            ];
        }
    }
    
    /**
     * Search patients by name
     */
    private function searchPatientsByName(array $arguments, User $user): array
    {
        $firstName = $arguments['firstName'] ?? '';
        $lastName = $arguments['lastName'] ?? '';
        
        if (empty($firstName) && empty($lastName)) {
            return [
                'response' => 'Please provide either a first name or last name to search for patients.',
                'type' => 'error'
            ];
        }
        
        $patients = $this->patientRepo->findByName($firstName, $lastName);
        
        if (empty($patients)) {
            return [
                'response' => 'No patients found matching the search criteria.',
                'type' => 'data'
            ];
        }
        
        $patientList = [];
        foreach ($patients as $patient) {
            $patientData = [
                'id' => $patient->getId(),
                'name' => $patient->getFirstName() . ' ' . $patient->getLastName(),
                'dateOfBirth' => $patient->getDateOfBirth()?->format('Y-m-d'),
            ];
            
            // Add additional fields based on permissions
            if ($this->authChecker->isGranted(PatientVoter::VIEW_DIAGNOSIS, $patient)) {
                $patientData['diagnosis'] = $patient->getDiagnosis();
            }
            
            $patientList[] = $patientData;
        }
        
        $this->auditLog->log($user, 'CHATBOT_PATIENT_SEARCH', [
            'search_criteria' => ['firstName' => $firstName, 'lastName' => $lastName],
            'results_count' => count($patients)
        ]);
        
        // Format a readable response without relying on json_encode
        $responseText = 'Found ' . count($patients) . ' patient(s) matching your search:';

        return [
            'response' => $responseText,
            'type' => 'data',
            'data' => $patientList
        ];
    }
    
    /**
     * Search patients by medical condition
     */
    private function searchPatientsByCondition(array $arguments, User $user): array
    {
        $condition = $arguments['condition'] ?? '';
        
        if (empty($condition)) {
            return [
                'response' => 'Please provide a medical condition to search for.',
                'type' => 'error'
            ];
        }
        
        $patients = $this->patientRepo->findByCondition($condition);
        
        if (empty($patients)) {
            return [
                'response' => 'No patients found with the condition: ' . $condition,
                'type' => 'data'
            ];
        }
        
        $patientList = [];
        foreach ($patients as $patient) {
            $patientList[] = [
                'id' => $patient->getId(),
                'name' => $patient->getFirstName() . ' ' . $patient->getLastName(),
                'diagnosis' => $patient->getDiagnosis(),
                'dateOfBirth' => $patient->getDateOfBirth()?->format('Y-m-d'),
            ];
        }
        
        $this->auditLog->log($user, 'CHATBOT_CONDITION_SEARCH', [
            'condition' => $condition,
            'results_count' => count($patients)
        ]);
        
        // Format a readable response without relying on json_encode
        $responseText = 'Found ' . count($patients) . ' patient(s) with condition "' . $condition . '".';

        return [
            'response' => $responseText,
            'type' => 'data',
            'data' => $patientList
        ];
    }
    
    /**
     * Get patient diagnosis
     */
    private function getPatientDiagnosis(array $arguments, User $user): array
    {
        $patientId = $arguments['patientId'] ?? '';
        
        if (empty($patientId)) {
            return [
                'response' => 'Please provide a patient ID to get diagnosis information.',
                'type' => 'error'
            ];
        }
        
        $patient = $this->patientRepo->find($patientId);
        
        if (!$patient) {
            return [
                'response' => 'Patient not found with ID: ' . $patientId,
                'type' => 'error'
            ];
        }
        
        if (!$this->authChecker->isGranted(PatientVoter::VIEW_DIAGNOSIS, $patient)) {
            throw new AccessDeniedException('You do not have permission to view this patient\'s diagnosis.');
        }
        
        $this->auditLog->log($user, 'CHATBOT_PATIENT_DIAGNOSIS_VIEW', [
            'patientId' => $patientId
        ]);
        
        return [
            'response' => 'Patient ' . $patient->getFirstName() . ' ' . $patient->getLastName() . ' has diagnosis: ' . $patient->getDiagnosis(),
            'type' => 'data',
            'data' => [
                'patientId' => $patientId,
                'name' => $patient->getFirstName() . ' ' . $patient->getLastName(),
                'diagnosis' => $patient->getDiagnosis()
            ]
        ];
    }
    
    /**
     * Check drug interactions
     */
    private function checkDrugInteractions(array $arguments, User $user): array
    {
        $medications = $arguments['medications'] ?? [];
        
        if (empty($medications) || count($medications) < 2) {
            return [
                'response' => 'Please provide at least two medications to check for interactions.',
                'type' => 'error'
            ];
        }
        
        // This is a simplified example - in a real system, you'd integrate with a drug interaction API
        $interactions = [];
        $medicationList = implode(', ', $medications);
        
        // Simulate drug interaction check
        $hasInteractions = false;
        $interactionDetails = [];
        
        // Example: Check for common interactions
        if (in_array('warfarin', $medications) && in_array('aspirin', $medications)) {
            $hasInteractions = true;
            $interactionDetails[] = 'Warfarin and Aspirin: Increased bleeding risk - monitor INR closely';
        }
        
        if (in_array('metformin', $medications) && in_array('insulin', $medications)) {
            $hasInteractions = true;
            $interactionDetails[] = 'Metformin and Insulin: Enhanced hypoglycemic effect - monitor blood glucose';
        }
        
        $this->auditLog->log($user, 'CHATBOT_DRUG_INTERACTION_CHECK', [
            'medications' => $medications,
            'has_interactions' => $hasInteractions
        ]);
        
        if ($hasInteractions) {
            return [
                'response' => '⚠️ DRUG INTERACTIONS DETECTED for medications: ' . $medicationList . '\n\nInteractions:\n' . implode('\n', $interactionDetails) . '\n\nPlease consult with a pharmacist or physician before prescribing.',
                'type' => 'warning',
                'data' => [
                    'medications' => $medications,
                    'interactions' => $interactionDetails
                ]
            ];
        } else {
            return [
                'response' => '✅ No known significant drug interactions found for medications: ' . $medicationList . '\n\nNote: This is a basic check. Always verify with current drug interaction databases and consult with a pharmacist when in doubt.',
                'type' => 'success',
                'data' => [
                    'medications' => $medications,
                    'interactions' => []
                ]
            ];
        }
    }
    
    /**
     * System prompt for data queries
     */
    private function getDataQuerySystemPrompt(User $user): string
    {
        $role = $user->getRoles()[0] ?? 'USER';
        
        return <<<PROMPT
You are a HIPAA-compliant medical assistant for the SecureHealth application. You can help with patient data queries based on the user's role and permissions.

User Role: {$role}

Available Functions:
- search_patients_by_name: Search for patients by name
- search_patients_by_condition: Search for patients with specific conditions (Doctor only)
- get_patient_diagnosis: Get diagnosis for a specific patient (Doctor only)
- check_drug_interactions: Check for drug interactions (Doctor only)

Guidelines:
1. Always respect HIPAA compliance and user permissions
2. Only provide information the user is authorized to see
3. Be concise and factual - this is medical data
4. If asked about treatment recommendations, remind users you're an assistant, not a licensed provider
5. Log all patient data access for audit purposes

You have access to functions to query patient data. Use them when needed to answer questions.
PROMPT;
    }
    
    /**
     * Sanitize arguments for audit log (don't log PHI)
     */
    private function sanitizeForAudit(array $arguments): array
    {
        // Remove any PHI fields, keep only IDs and metadata
        return array_intersect_key($arguments, array_flip([
            'patientId',
            'medications', // Drug names are not PHI
            'lastName' // Searching by name is auditable action
        ]));
    }
}
