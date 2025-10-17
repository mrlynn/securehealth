<?php

namespace App\EventSubscriber;

use MongoDB\Driver\Exception\Exception as MongoDBException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();
        
        // Only transform exceptions for API endpoints (and login which expects JSON)
        if (str_starts_with($request->getPathInfo(), '/api') || $request->getPathInfo() === '/api/login') {
            $statusCode = 500;
            $errorMessage = 'Internal server error';
            
            // Log the exception
            $this->logger->error('ExceptionSubscriber caught exception: ' . $exception->getMessage(), [
                'exception' => $exception,
                'path' => $request->getPathInfo(),
            ]);
            
            // Determine proper status code and message
            if ($exception instanceof HttpExceptionInterface) {
                $statusCode = $exception->getStatusCode();
                $errorMessage = $exception->getMessage();
            } elseif ($exception instanceof AuthenticationException) {
                $statusCode = 401;
                $errorMessage = $exception->getMessage();
            } elseif ($exception instanceof MongoDBException || 
                     (strpos($exception->getMessage(), 'MongoDB') !== false)) {
                $statusCode = 503;
                $errorMessage = 'Database service unavailable';
                
                // Log more details about MongoDB errors
                $this->logger->error('MongoDB error details: ' . $exception->getMessage(), [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ]);
            }
            
            // Create a standardized JSON error response
            $errorResponse = [
                'success' => false,
                'error' => [
                    'code' => $statusCode,
                    'message' => $errorMessage
                ]
            ];
            
            // Add debug information in development environment only
            if ($_ENV['APP_ENV'] === 'dev') {
                $errorResponse['debug'] = [
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString())
                ];
            }
            
            $response = new JsonResponse($errorResponse, $statusCode);
            $event->setResponse($response);
        }
    }
}