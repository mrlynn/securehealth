<?php

namespace App\Controller\Api;

use App\Service\RAGChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Explicitly mark this as public access without auth requirements
#[Route('/api/chatbot-debug')]
#[IsGranted('PUBLIC_ACCESS')]
class ChatbotDebugController extends AbstractController
{
    public function __construct(
        private RAGChatbotService $chatbotService,
        private \Psr\Log\LoggerInterface $logger
    ) {}

    /**
     * Debug endpoint for chatbot query that provides detailed error information
     */
    #[Route('/query', name: 'chatbot_debug_query', methods: ['POST'])]
    public function debugQuery(Request $request): JsonResponse
    {
        // Turn on maximum error reporting
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        try {
            $data = json_decode($request->getContent(), true);
            $query = $data['query'] ?? '';

            if (empty($query)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Query is required',
                    'type' => 'error'
                ], 400);
            }

            // Validate query length
            if (strlen($query) > 1000) {
                return $this->json([
                    'success' => false,
                    'error' => 'Query is too long. Please limit to 1000 characters.',
                    'type' => 'error'
                ], 400);
            }

            // Use a mock user for debugging
            $user = new \App\Document\User();
            $user->setEmail('debug@example.com');
            $user->setRoles(['ROLE_DOCTOR', 'ROLE_USER']);

            // Process the query with extra logging
            $this->logger->info('Debug query processing started', [
                'query' => $query,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // Start measuring execution time
            $startTime = microtime(true);

            // Process the query
            $response = $this->chatbotService->processQuery($query, $user);

            // Calculate execution time
            $executionTime = microtime(true) - $startTime;

            // Log successful response
            $this->logger->info('Debug query processed successfully', [
                'query' => $query,
                'response_type' => $response['type'] ?? 'unknown',
                'execution_time' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // Add detailed diagnostic information
            $response['debug'] = [
                'execution_time' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => phpversion(),
                'memory_usage' => memory_get_usage(true)
            ];

            return $this->json([
                'success' => true,
                'response' => $response['response'],
                'type' => $response['type'],
                'sources' => $response['sources'] ?? [],
                'data' => $response['data'] ?? null,
                'debug' => $response['debug'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            // Collect detailed information about the error
            $this->logger->critical('Debug chatbot critical error', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'query' => $query ?? 'unknown query',
                'trace' => $e->getTraceAsString()
            ]);

            // Return very detailed error information for debugging
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => explode("\n", $e->getTraceAsString()),
                'type' => 'error',
                'timestamp' => date('Y-m-d H:i:s')
            ], 500);
        }
    }
}