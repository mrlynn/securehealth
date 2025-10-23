<?php

namespace App\Controller\Api;

use App\Service\RAGChatbotService;
use App\Service\VectorSearchService;
use App\Service\MongoDBEncryptionService;
use App\Document\User;
use MongoDB\Client;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\User\UserInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/diagnostic')]
// Available to all authenticated users - helps with diagnosing issues
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DiagnosticController extends AbstractController
{
    private $mongoClient;
    private $encryptionService;
    private $vectorSearchService;
    private $chatbotService;
    private $logger;
    private $openaiKey;

    public function __construct(
        Client $mongoClient,
        MongoDBEncryptionService $encryptionService,
        VectorSearchService $vectorSearchService,
        RAGChatbotService $chatbotService,
        LoggerInterface $logger
    ) {
        // Try to get OpenAI key from environment
        $this->openaiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        $this->mongoClient = $mongoClient;
        $this->encryptionService = $encryptionService;
        $this->vectorSearchService = $vectorSearchService;
        $this->chatbotService = $chatbotService;
        $this->logger = $logger;
        $this->openaiKey = $openaiApiKey;
    }

    /**
     * Run diagnostics on all components involved in the chatbot
     */
    #[Route('/run', name: 'run_diagnostics', methods: ['GET'])]
    public function runDiagnostics(UserInterface $user): JsonResponse
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $user->getUserIdentifier(),
            'components' => []
        ];

        // 1. Check MongoDB connection
        $results['components']['mongodb'] = $this->checkMongoDB();

        // 2. Check MongoDB encryption service
        $results['components']['encryption'] = $this->checkEncryption();

        // 3. Check OpenAI API
        $results['components']['openai'] = $this->checkOpenAI();

        // 4. Check Vector Search
        $results['components']['vector_search'] = $this->checkVectorSearch();

        // 5. Check RAGChatbotService
        $results['components']['chatbot_service'] = $this->checkChatbotService($user);

        // Overall status
        $results['status'] = $this->getOverallStatus($results['components']);

        // Return diagnostic results
        return $this->json($results);
    }

    /**
     * Check MongoDB connection
     */
    private function checkMongoDB(): array
    {
        try {
            $start = microtime(true);

            // Try to ping the database
            $pingResult = $this->mongoClient->selectDatabase('admin')->command(['ping' => 1]);

            $duration = microtime(true) - $start;

            $status = [
                'status' => 'ok',
                'ping_time_ms' => round($duration * 1000, 2),
                'details' => [
                    'connected' => true,
                ]
            ];

            // Try to get connection info
            try {
                $mongodbInfo = $this->mongoClient->selectDatabase('admin')->command(['buildInfo' => 1]);
                $status['details']['version'] = $mongodbInfo->toArray()[0]['version'] ?? 'unknown';
            } catch (\Exception $e) {
                $status['details']['info_error'] = $e->getMessage();
            }

            return $status;

        } catch (\Exception $e) {
            $this->logger->error('MongoDB diagnostic error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'details' => [
                    'connected' => false,
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Check MongoDB encryption service
     */
    private function checkEncryption(): array
    {
        try {
            $isDisabled = false;
            $isAvailable = false;

            // Check if methods exist
            if (method_exists($this->encryptionService, 'isMongoDBDisabled')) {
                $isDisabled = $this->encryptionService->isMongoDBDisabled();
            }

            if (method_exists($this->encryptionService, 'isEncryptionAvailable')) {
                $isAvailable = $this->encryptionService->isEncryptionAvailable();
            }

            return [
                'status' => 'ok',
                'details' => [
                    'mongodb_disabled' => $isDisabled,
                    'encryption_available' => $isAvailable
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Encryption diagnostic error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Check OpenAI API connection
     */
    private function checkOpenAI(): array
    {
        try {
            // Check if OpenAI key is provided
            if (empty($this->openaiKey)) {
                return [
                    'status' => 'warning',
                    'error' => 'OpenAI API key not configured',
                    'details' => [
                        'key_available' => false,
                    ]
                ];
            }

            // Try to create an OpenAI client
            $openai = OpenAI::client($this->openaiKey);

            // Try a simple models list request which requires minimal permissions
            $start = microtime(true);
            $openai->models()->list();
            $duration = microtime(true) - $start;

            return [
                'status' => 'ok',
                'response_time_ms' => round($duration * 1000, 2),
                'details' => [
                    'key_available' => true,
                    'api_responsive' => true
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('OpenAI diagnostic error: ' . $e->getMessage(), [
                'exception' => get_class($e)
            ]);

            // Check for specific error types
            $errorMessage = $e->getMessage();
            $errorDetails = [
                'key_available' => !empty($this->openaiKey),
                'api_responsive' => false
            ];

            if (strpos($errorMessage, 'Incorrect API key') !== false) {
                $errorDetails['error_type'] = 'invalid_api_key';
            } elseif (strpos($errorMessage, 'timeout') !== false || strpos($errorMessage, 'timed out') !== false) {
                $errorDetails['error_type'] = 'timeout';
            }

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'details' => $errorDetails
            ];
        }
    }

    /**
     * Check Vector Search service
     */
    private function checkVectorSearch(): array
    {
        try {
            // First check if the search method exists
            if (!method_exists($this->vectorSearchService, 'search')) {
                return [
                    'status' => 'warning',
                    'error' => 'Vector search service missing search method',
                    'details' => [
                        'method_exists' => false
                    ]
                ];
            }

            // Create a test embedding (fake one with 1536 dimensions)
            $testEmbedding = array_fill(0, 1536, 0.1);

            // Try to execute the search method
            $start = microtime(true);
            $results = $this->vectorSearchService->search($testEmbedding, 1);
            $duration = microtime(true) - $start;

            return [
                'status' => 'ok',
                'response_time_ms' => round($duration * 1000, 2),
                'details' => [
                    'method_exists' => true,
                    'results_count' => count($results),
                    'returned_type' => gettype($results),
                    'is_array' => is_array($results),
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Vector search diagnostic error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'details' => [
                    'method_exists' => method_exists($this->vectorSearchService, 'search'),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Check RAGChatbotService
     */
    private function checkChatbotService(UserInterface $user): array
    {
        try {
            // Try to process a simple query
            $start = microtime(true);
            $response = $this->chatbotService->processQuery('what is hipaa?', $user);
            $duration = microtime(true) - $start;

            return [
                'status' => 'ok',
                'response_time_ms' => round($duration * 1000, 2),
                'details' => [
                    'response_type' => $response['type'] ?? 'unknown',
                    'has_response' => isset($response['response']),
                    'response_length' => isset($response['response']) ? strlen($response['response']) : 0,
                    'sources_count' => isset($response['sources']) ? count($response['sources']) : 0
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('RAGChatbotService diagnostic error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Get overall system status based on component checks
     */
    private function getOverallStatus(array $components): array
    {
        $errors = [];
        $warnings = [];
        $overall = 'ok';

        foreach ($components as $component => $data) {
            if ($data['status'] === 'error') {
                $errors[] = $component;
                $overall = 'error';
            } elseif ($data['status'] === 'warning') {
                $warnings[] = $component;
                if ($overall !== 'error') {
                    $overall = 'warning';
                }
            }
        }

        return [
            'status' => $overall,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => $this->getStatusSummary($components)
        ];
    }

    /**
     * Get human-readable status summary
     */
    private function getStatusSummary(array $components): string
    {
        $errorParts = [];

        if ($components['mongodb']['status'] !== 'ok') {
            $errorParts[] = "MongoDB connection issue: " . ($components['mongodb']['error'] ?? 'Unknown error');
        }

        if ($components['openai']['status'] !== 'ok') {
            $errorParts[] = "OpenAI API issue: " . ($components['openai']['error'] ?? 'Unknown error');
        }

        if ($components['vector_search']['status'] !== 'ok') {
            $errorParts[] = "Vector search issue: " . ($components['vector_search']['error'] ?? 'Unknown error');
        }

        if ($components['chatbot_service']['status'] !== 'ok') {
            $errorParts[] = "Chatbot service issue: " . ($components['chatbot_service']['error'] ?? 'Unknown error');
        }

        if (empty($errorParts)) {
            return "All systems operational";
        } else {
            return implode("; ", $errorParts);
        }
    }

    /**
     * Test MongoDB connection specifically
     */
    #[Route('/mongodb', name: 'test_mongodb', methods: ['GET'])]
    public function testMongoDB(): JsonResponse
    {
        return $this->json($this->checkMongoDB());
    }

    /**
     * Test OpenAI connection specifically
     */
    #[Route('/openai', name: 'test_openai', methods: ['GET'])]
    public function testOpenAI(): JsonResponse
    {
        return $this->json($this->checkOpenAI());
    }

    /**
     * Test vector search specifically
     */
    #[Route('/vector', name: 'test_vector_search', methods: ['GET'])]
    public function testVectorSearch(): JsonResponse
    {
        return $this->json($this->checkVectorSearch());
    }

    /**
     * Test environment variables
     */
    #[Route('/env', name: 'test_environment', methods: ['GET'])]
    public function testEnvironment(): JsonResponse
    {
        $envVars = [
            'MONGODB_URI' => isset($_ENV['MONGODB_URI']) ? 'set (first few chars: ' . substr($_ENV['MONGODB_URI'], 0, 10) . '...)' : 'not set',
            'MONGODB_DB' => isset($_ENV['MONGODB_DB']) ? 'set: ' . $_ENV['MONGODB_DB'] : 'not set',
            'MONGODB_KEY_VAULT_NAMESPACE' => isset($_ENV['MONGODB_KEY_VAULT_NAMESPACE']) ? 'set: ' . $_ENV['MONGODB_KEY_VAULT_NAMESPACE'] : 'not set',
            'MONGODB_ENCRYPTION_KEY_PATH' => isset($_ENV['MONGODB_ENCRYPTION_KEY_PATH']) ? 'set: ' . $_ENV['MONGODB_ENCRYPTION_KEY_PATH'] : 'not set',
            'MONGODB_DISABLED' => isset($_ENV['MONGODB_DISABLED']) ? 'set: ' . $_ENV['MONGODB_DISABLED'] : 'not set',
            'OPENAI_API_KEY' => isset($_ENV['OPENAI_API_KEY']) ? 'set (first few chars: ' . substr($_ENV['OPENAI_API_KEY'], 0, 5) . '...)' : 'not set',
            'OPENAI_API_URL' => isset($_ENV['OPENAI_API_URL']) ? 'set: ' . $_ENV['OPENAI_API_URL'] : 'not set',
        ];

        return $this->json([
            'environment_variables' => $envVars,
            'php_version' => phpversion(),
            'symfony_environment' => $_ENV['APP_ENV'] ?? 'unknown',
            'debug_mode' => isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true'
        ]);
    }
}