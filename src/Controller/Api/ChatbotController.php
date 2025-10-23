<?php

namespace App\Controller\Api;

use App\Service\RAGChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/chatbot')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ChatbotController extends AbstractController
{
    public function __construct(
        private RAGChatbotService $chatbotService
    ) {}

    /**
     * Process a chatbot query
     */
    #[Route('/query', name: 'chatbot_query', methods: ['POST'])]
    public function query(Request $request, UserInterface $user): JsonResponse
    {
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

            $response = $this->chatbotService->processQuery($query, $user);

            // Ensure response is a string
            if (!is_string($response['response'])) {
                // If it's an object/array, convert to string in a readable format
                if (is_array($response['response']) || is_object($response['response'])) {
                    $response['response'] = json_encode($response['response']);
                } else {
                    $response['response'] = (string)$response['response'];
                }
            }

            // Log successful response for debugging (using error_log for now)
            error_log('Chatbot query processed successfully: ' . $query);

            return $this->json([
                'success' => true,
                'response' => $response['response'],
                'type' => $response['type'],
                'sources' => $response['sources'] ?? [],
                'data' => $response['data'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            // Get detailed error message but don't expose sensitive details
            $message = 'An error occurred while processing your request';
            $errorType = get_class($e);
            $errorDetails = [];

            // Collect more detailed information for specific error types
            if ($e instanceof \MongoDB\Driver\Exception\ConnectionTimeoutException) {
                $errorDetails['problem'] = 'mongodb_connection_timeout';
                $message = 'MongoDB connection timeout - check network connectivity and MongoDB server status';
            } elseif ($e instanceof \MongoDB\Driver\Exception\AuthenticationException) {
                $errorDetails['problem'] = 'mongodb_authentication';
                $message = 'MongoDB authentication failed - check credentials';
            } elseif ($e instanceof \MongoDB\Driver\Exception\BulkWriteException) {
                $errorDetails['problem'] = 'mongodb_write_error';
                $message = 'MongoDB write operation failed';
            } elseif ($e instanceof \MongoDB\Driver\Exception\ServerException) {
                $errorDetails['problem'] = 'mongodb_server_error';
                $message = 'MongoDB server error';
            } elseif (strpos($e->getMessage(), 'OpenAI') !== false || strpos($e->getMessage(), 'API key') !== false) {
                $errorDetails['problem'] = 'openai_api_error';
                $message = 'OpenAI API error - check API key and connectivity';
            } elseif ($e instanceof \RuntimeException && strpos($e->getMessage(), 'vector') !== false) {
                $errorDetails['problem'] = 'vector_search_error';
                $message = 'Vector search error - possible missing index or incompatible data';
            }

            // Extract more details from the exception
            $errorDetails['class'] = get_class($e);
            $errorDetails['file'] = $e->getFile();
            $errorDetails['line'] = $e->getLine();
            $errorDetails['code'] = $e->getCode();
            $errorDetails['trace'] = explode("\n", $e->getTraceAsString());

            // Log the detailed error with full stack trace (using error_log for now)
            error_log('Chatbot API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            // Provide more info in development mode
            if ($_ENV['APP_ENV'] === 'dev') {
                $message .= ' - ' . $e->getMessage();
                $errorDetails['raw_message'] = $e->getMessage();
            }

            // Return detailed error information in dev mode, simpler in production
            return $this->json([
                'success' => false,
                'error' => $message,
                'error_type' => $errorType,
                'type' => 'error',
                'details' => ($_ENV['APP_ENV'] === 'dev') ? $errorDetails : null,
                'timestamp' => date('Y-m-d H:i:s')
            ], 500);
        }
    }

    /**
     * Get chatbot status and capabilities
     */
    #[Route('/status', name: 'chatbot_status', methods: ['GET'])]
    public function status(UserInterface $user): JsonResponse
    {
        $roles = $user->getRoles();
        
        $capabilities = [
            'knowledge_queries' => true,
            'patient_search' => in_array('ROLE_NURSE', $roles),
            'condition_search' => in_array('ROLE_DOCTOR', $roles),
            'diagnosis_view' => in_array('ROLE_DOCTOR', $roles),
            'drug_interactions' => in_array('ROLE_DOCTOR', $roles),
        ];

        return $this->json([
            'success' => true,
            'status' => 'active',
            'user_role' => $roles[0] ?? 'USER',
            'capabilities' => $capabilities,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get example queries for the current user
     */
    #[Route('/examples', name: 'chatbot_examples', methods: ['GET'])]
    public function examples(UserInterface $user): JsonResponse
    {
        $roles = $user->getRoles();
        $examples = [];

        // Knowledge queries (available to all users)
        $examples['knowledge'] = [
            'What is MongoDB Queryable Encryption?',
            'How do Symfony Voters work?',
            'Explain the difference between deterministic and random encryption',
            'How do I set up encryption keys?',
            'What are the HIPAA compliance requirements?'
        ];

        // Data queries based on role
        if (in_array('ROLE_NURSE', $roles)) {
            $examples['patient_data'] = [
                'Show me patients named Smith',
                'Find patients with diabetes',
                'Search for patients by last name Johnson'
            ];
        }

        if (in_array('ROLE_DOCTOR', $roles)) {
            $examples['clinical_data'] = [
                'Show me all patients with diabetes',
                'What is patient ID 123\'s diagnosis?',
                'Check drug interactions for metformin and lisinopril',
                'Find patients with hypertension'
            ];
        }

        return $this->json([
            'success' => true,
            'examples' => $examples,
            'user_role' => $roles[0] ?? 'USER',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
