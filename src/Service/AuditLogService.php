<?php

namespace App\Service;

use App\Document\AuditLog;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

class AuditLogService
{
    private Client $mongoClient;
    private RequestStack $requestStack;
    private string $databaseName;
    private string $auditLogCollection;
    private ?AuditLog $lastLog = null;
    
    public function __construct(
        Client $mongoClient,
        RequestStack $requestStack,
        string $databaseName = 'securehealth',
        string $auditLogCollection = 'audit_log'
    ) {
        $this->mongoClient = $mongoClient;
        $this->requestStack = $requestStack;
        $this->databaseName = $databaseName;
        $this->auditLogCollection = $auditLogCollection;
    }
    
    /**
     * Main method to log an event to the audit trail
     * 
     * @param UserInterface $user The user performing the action
     * @param string $actionType The type of action being performed
     * @param array $data Additional data to log
     * @return AuditLog The created audit log
     */
    public function log(UserInterface $user, string $actionType, array $data = []): AuditLog
    {
        $auditLog = new AuditLog();
        
        // Basic information
        $auditLog->setUsername($user->getUserIdentifier());
        $auditLog->setActionType($actionType);
        $auditLog->setDescription($data['description'] ?? $actionType);
        
        // If userId is provided in user object
        if (method_exists($user, 'getId')) {
            $auditLog->setUserId((string)$user->getId());
        }
        
        // Get HTTP request information if available
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setRequestMethod($request->getMethod());
            $auditLog->setRequestUrl($request->getRequestUri());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
            
            // Get session ID if available
            if ($request->hasSession()) {
                $auditLog->setSessionId($request->getSession()->getId());
            }
        }
        
        // Set entity information if provided
        if (isset($data['entityId'])) {
            $auditLog->setEntityId($data['entityId']);
        }
        
        if (isset($data['entityType'])) {
            $auditLog->setEntityType($data['entityType']);
        }
        
        // Set status if provided
        if (isset($data['status'])) {
            $auditLog->setStatus($data['status']);
        }
        
        // Add any additional data as metadata
        $metadata = array_diff_key($data, [
            'description' => true,
            'entityId' => true,
            'entityType' => true,
            'status' => true
        ]);
        
        if (!empty($metadata)) {
            $auditLog->setMetadata($metadata);
        }
        
        // Save the audit log
        $this->saveAuditLog($auditLog);
        $this->lastLog = $auditLog;
        
        return $auditLog;
    }
    
    /**
     * Log a patient data access event (HIPAA-compliant)
     *
     * @param UserInterface $user User accessing the data
     * @param string $accessType Type of access (VIEW, CREATE, EDIT, DELETE)
     * @param string $patientId Patient ID being accessed
     * @param array $data Additional data about the access
     * @return AuditLog
     */
    public function logPatientAccess(
        UserInterface $user,
        string $accessType,
        string $patientId,
        array $data = []
    ): AuditLog {
        $data['entityId'] = $patientId;
        $data['entityType'] = 'Patient';
        $data['description'] = $data['description'] ?? "Patient data {$accessType}";
        
        return $this->log($user, 'PATIENT_' . $accessType, $data);
    }
    
    /**
     * Log a security event (login, logout, failed login, etc.)
     *
     * @param UserInterface $user User related to the security event
     * @param string $eventType Type of security event
     * @param array $data Additional data about the event
     * @return AuditLog
     */
    public function logSecurityEvent(
        UserInterface $user,
        string $eventType,
        array $data = []
    ): AuditLog {
        $data['description'] = $data['description'] ?? "Security event: {$eventType}";
        $data['entityType'] = 'Security';
        
        return $this->log($user, 'SECURITY_' . $eventType, $data);
    }
    
    /**
     * Update the last created log entry with additional data
     * Useful for adding results to an already logged action
     *
     * @param array $data Additional data to add to the log
     * @return AuditLog|null The updated audit log or null if no last log
     */
    public function updateLastLog(array $data): ?AuditLog
    {
        if (!$this->lastLog) {
            return null;
        }
        
        // Update specific fields
        if (isset($data['status'])) {
            $this->lastLog->setStatus($data['status']);
        }
        
        if (isset($data['description'])) {
            $this->lastLog->setDescription($data['description']);
        }
        
        // Add all data to metadata
        $metadata = $this->lastLog->getMetadata();
        foreach ($data as $key => $value) {
            $metadata[$key] = $value;
        }
        $this->lastLog->setMetadata($metadata);
        
        // Save updated log
        $this->saveAuditLog($this->lastLog);
        
        return $this->lastLog;
    }
    
    /**
     * Save an audit log to the database
     */
    private function saveAuditLog(AuditLog $auditLog): void
    {
        $collection = $this->mongoClient
            ->selectDatabase($this->databaseName)
            ->selectCollection($this->auditLogCollection);
        
        $document = [
            'username' => $auditLog->getUsername(),
            'actionType' => $auditLog->getActionType(),
            'description' => $auditLog->getDescription(),
            'timestamp' => $auditLog->getTimestamp(),
            'ipAddress' => $auditLog->getIpAddress(),
            'entityId' => $auditLog->getEntityId(),
            'entityType' => $auditLog->getEntityType(),
            'userId' => $auditLog->getUserId(),
            'sessionId' => $auditLog->getSessionId(),
            'status' => $auditLog->getStatus(),
            'requestMethod' => $auditLog->getRequestMethod(),
            'requestUrl' => $auditLog->getRequestUrl(),
            'userAgent' => $auditLog->getUserAgent(),
            'metadata' => $auditLog->getMetadata(),
        ];
        
        try {
            $result = $collection->insertOne($document);
            
            // If ID was assigned by MongoDB, set it in the object
            if ($result->getInsertedId()) {
                $auditLog->setId($result->getInsertedId());
            }
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            // Handle "not primary" error by retrying with primary read preference
            if (strpos($e->getMessage(), 'not primary') !== false) {
                // Create a new client with explicit primary read preference
                $mongoUrl = $_ENV['MONGODB_URI'] ?? 'mongodb://localhost:27017';
                $mongoDb = $_ENV['MONGODB_DB'] ?? 'securehealth';
                
                // Ensure readPreference=primary is in the URI
                if (strpos($mongoUrl, 'readPreference=') === false) {
                    $separator = strpos($mongoUrl, '?') !== false ? '&' : '?';
                    $mongoUrl .= $separator . 'readPreference=primary';
                }
                
                $client = new \MongoDB\Client($mongoUrl);
                
                $database = $client->selectDatabase($mongoDb);
                $collection = $database->selectCollection($this->auditLogCollection);
                
                $result = $collection->insertOne($document);
                
                if ($result->getInsertedId()) {
                    $auditLog->setId($result->getInsertedId());
                }
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Get audit logs for a specific entity
     *
     * @param string $entityType Type of entity (Patient, User, etc.)
     * @param string $entityId ID of the entity
     * @param int $limit Maximum number of logs to return
     * @return array Array of AuditLog objects
     */
    public function getLogsForEntity(string $entityType, string $entityId, int $limit = 100): array
    {
        $collection = $this->mongoClient
            ->selectDatabase($this->databaseName)
            ->selectCollection($this->auditLogCollection);
            
        $cursor = $collection->find(
            ['entityType' => $entityType, 'entityId' => $entityId],
            [
                'sort' => ['timestamp' => -1],
                'limit' => $limit
            ]
        );
        
        return $this->cursorToAuditLogs($cursor);
    }
    
    /**
     * Get audit logs for a specific user
     *
     * @param string $username Username to get logs for
     * @param int $limit Maximum number of logs to return
     * @return array Array of AuditLog objects
     */
    public function getLogsForUser(string $username, int $limit = 100): array
    {
        $collection = $this->mongoClient
            ->selectDatabase($this->databaseName)
            ->selectCollection($this->auditLogCollection);
            
        $cursor = $collection->find(
            ['username' => $username],
            [
                'sort' => ['timestamp' => -1],
                'limit' => $limit
            ]
        );
        
        return $this->cursorToAuditLogs($cursor);
    }
    
    /**
     * Search audit logs with flexible criteria
     *
     * @param array $criteria Search criteria
     * @param int $limit Maximum number of logs to return
     * @param int $skip Number of logs to skip (for pagination)
     * @return array Array of AuditLog objects
     */
    public function searchLogs(array $criteria, int $limit = 100, int $skip = 0): array
    {
        $collection = $this->mongoClient
            ->selectDatabase($this->databaseName)
            ->selectCollection($this->auditLogCollection);
            
        $cursor = $collection->find(
            $criteria,
            [
                'sort' => ['timestamp' => -1],
                'limit' => $limit,
                'skip' => $skip
            ]
        );
        
        return $this->cursorToAuditLogs($cursor);
    }
    
    /**
     * Convert MongoDB cursor to array of AuditLog objects
     */
    private function cursorToAuditLogs($cursor): array
    {
        $logs = [];
        foreach ($cursor as $document) {
            $log = new AuditLog();
            
            if (isset($document['_id'])) {
                $log->setId($document['_id']);
            }
            
            if (isset($document['username'])) {
                $log->setUsername($document['username']);
            }
            
            if (isset($document['actionType'])) {
                $log->setActionType($document['actionType']);
            }
            
            if (isset($document['description'])) {
                $log->setDescription($document['description']);
            }
            
            if (isset($document['timestamp'])) {
                $log->setTimestamp($document['timestamp']);
            }
            
            if (isset($document['ipAddress'])) {
                $log->setIpAddress($document['ipAddress']);
            }
            
            if (isset($document['entityId'])) {
                $log->setEntityId($document['entityId']);
            }
            
            if (isset($document['entityType'])) {
                $log->setEntityType($document['entityType']);
            }
            
            if (isset($document['userId'])) {
                $log->setUserId($document['userId']);
            }
            
            if (isset($document['sessionId'])) {
                $log->setSessionId($document['sessionId']);
            }
            
            if (isset($document['status'])) {
                $log->setStatus($document['status']);
            }
            
            if (isset($document['requestMethod'])) {
                $log->setRequestMethod($document['requestMethod']);
            }
            
            if (isset($document['requestUrl'])) {
                $log->setRequestUrl($document['requestUrl']);
            }
            
            if (isset($document['userAgent'])) {
                $log->setUserAgent($document['userAgent']);
            }
            
            if (isset($document['metadata']) && is_array($document['metadata'])) {
                $log->setMetadata($document['metadata']);
            }
            
            $logs[] = $log;
        }
        
        return $logs;
    }
}