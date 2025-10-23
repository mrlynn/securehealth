<?php

namespace App\Controller;

use App\Service\RAGChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use MongoDB\Client;
use Psr\Log\LoggerInterface;

/**
 * Controller for debugging without authentication restrictions
 */
#[Route('/debug')]
#[IsGranted('PUBLIC_ACCESS')]
class DebugController extends AbstractController
{
    private $mongoClient;
    private $logger;
    private $chatbotService;

    public function __construct(
        Client $mongoClient,
        LoggerInterface $logger,
        RAGChatbotService $chatbotService
    ) {
        $this->mongoClient = $mongoClient;
        $this->logger = $logger;
        $this->chatbotService = $chatbotService;
    }

    /**
     * Debug endpoint for MongoDB connection
     */
    #[Route('/mongo', name: 'debug_mongo', methods: ['GET'])]
    public function testMongoConnection(): JsonResponse
    {
        // Turn on maximum error reporting
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        try {
            // Test MongoDB connection with basic ping
            $start = microtime(true);
            $pingResult = $this->mongoClient->selectDatabase('admin')->command(['ping' => 1]);
            $duration = microtime(true) - $start;

            // Get MongoDB server info
            $buildInfo = $this->mongoClient->selectDatabase('admin')->command(['buildInfo' => 1]);
            $version = $buildInfo->toArray()[0]['version'] ?? 'unknown';

            // List collections
            $collections = [];
            $database = $_ENV['MONGODB_DB'] ?? 'securehealth';
            $cursor = $this->mongoClient->selectDatabase($database)->listCollections();
            foreach ($cursor as $collection) {
                $collections[] = $collection->getName();
            }

            // Count documents in medical_knowledge collection
            $knowledgeCount = $this->mongoClient->selectDatabase($database)
                ->selectCollection('medical_knowledge')
                ->countDocuments();

            return $this->json([
                'success' => true,
                'mongodb_status' => 'connected',
                'ping_time_ms' => round($duration * 1000, 2),
                'version' => $version,
                'database' => $database,
                'collections' => $collections,
                'knowledge_count' => $knowledgeCount,
                'uri' => $this->getMaskedUri(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Debug MongoDB error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->json([
                'success' => false,
                'mongodb_status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => explode("\n", $e->getTraceAsString()),
                'uri' => $this->getMaskedUri(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Debug endpoint for testing the chatbot service
     */
    #[Route('/chatbot', name: 'debug_chatbot', methods: ['GET'])]
    public function testChatbot(Request $request): JsonResponse
    {
        try {
            // Get query parameter
            $query = $request->query->get('q', 'what is hipaa?');

            // Mock a user with doctor role
            $user = new \App\Document\User();
            $user->setEmail('debug@example.com');
            $user->setRoles(['ROLE_DOCTOR', 'ROLE_USER']);

            // Process the query
            $start = microtime(true);
            $response = $this->chatbotService->processQuery($query, $user);
            $duration = microtime(true) - $start;

            // Add debug info
            return $this->json([
                'success' => true,
                'query' => $query,
                'response' => $response['response'] ?? 'No response',
                'type' => $response['type'] ?? 'unknown',
                'sources_count' => count($response['sources'] ?? []),
                'execution_time_ms' => round($duration * 1000, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'query' => $query ?? 'unknown',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => explode("\n", $e->getTraceAsString()),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Get a masked MongoDB URI for security
     */
    private function getMaskedUri(): string
    {
        $uri = $_ENV['MONGODB_URI'] ?? 'mongodb://localhost:27017';

        // Mask password if present
        if (preg_match('/\/\/(.+?):(.+?)@/', $uri, $matches)) {
            $username = $matches[1];
            $password = $matches[2];
            $maskedPassword = substr($password, 0, 2) . '***' . substr($password, -2);
            $uri = str_replace("$username:$password@", "$username:$maskedPassword@", $uri);
        }

        return $uri;
    }
}