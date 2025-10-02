<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    private $requestStack;
    
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }
    
    /**
     * Log an action for HIPAA compliance
     *
     * @param string $actionType Type of action (VIEW, CREATE, UPDATE, DELETE, LOGIN, LOGOUT)
     * @param string $resource Resource being accessed (e.g., patient, auth)
     * @param string $username Username or identifier of the user performing the action
     * @param string $description Description of the action
     * @param array $metadata Additional metadata for the log entry
     */
    public function log(string $actionType, string $resource, string $username, string $description, array $metadata = []): void
    {
        // In a real application, this would be stored in MongoDB
        // For now, we'll log to the system log for demonstration
        $logEntry = [
            'id' => uniqid(),
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'actionType' => $actionType,
            'resource' => $resource,
            'username' => $username,
            'description' => $description,
            'metadata' => $metadata,
            'ip' => $this->getClientIp(),
            'userAgent' => $this->getUserAgent()
        ];
        
        // Log to system log
        error_log(json_encode($logEntry));
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return 'unknown';
        }
        
        return $request->getClientIp() ?? 'unknown';
    }
    
    /**
     * Get user agent
     */
    private function getUserAgent(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return 'unknown';
        }
        
        return $request->headers->get('User-Agent') ?? 'unknown';
    }
}