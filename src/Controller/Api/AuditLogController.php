<?php

namespace App\Controller\Api;

use App\Document\AuditLog;
use App\Service\AuditLogService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/audit-logs')]
class AuditLogController extends AbstractController
{
    private DocumentManager $dm;
    private AuditLogService $auditLogService;
    
    public function __construct(DocumentManager $dm, AuditLogService $auditLogService)
    {
        $this->dm = $dm;
        $this->auditLogService = $auditLogService;
    }
    
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Only allow administrators and doctors to view all audit logs
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_DOCTOR')) {
            throw $this->createAccessDeniedException('You do not have permission to view audit logs.');
        }
        
        $limit = $request->query->getInt('limit', 100);
        $page = $request->query->getInt('page', 1);
        $skip = ($page - 1) * $limit;
        
        // Build query based on filters
        $criteria = [];
        
        if ($username = $request->query->get('username')) {
            $criteria['username'] = $username;
        }
        
        if ($actionType = $request->query->get('actionType')) {
            $criteria['actionType'] = $actionType;
        }
        
        if ($entityType = $request->query->get('entityType')) {
            $criteria['entityType'] = $entityType;
        }
        
        if ($entityId = $request->query->get('entityId')) {
            $criteria['entityId'] = $entityId;
        }
        
        // Get logs using AuditLogService to avoid Doctrine hydration issues
        try {
            $logs = $this->auditLogService->searchLogs($criteria, $limit, $skip);
        } catch (\Exception $e) {
            // MongoDB connection failed, return empty array
            error_log('Audit log retrieval error: ' . $e->getMessage());
            $logs = [];
        }
        
        // Convert to array format
        $result = [];
        foreach ($logs as $log) {
            $result[] = [
                'id' => $log->getId(),
                'username' => $log->getUsername(),
                'actionType' => $log->getActionType(),
                'description' => $log->getDescription(),
                'timestamp' => $log->getTimestamp()->toDateTime()->format('Y-m-d H:i:s'),
                'ipAddress' => $log->getIpAddress(),
                'entityId' => $log->getEntityId(),
                'entityType' => $log->getEntityType(),
                'metadata' => $log->getMetadata()
            ];
        }
        
        // Log this audit log access (meta!)
        $this->auditLogService->logSecurityEvent(
            $this->getUser(),
            'AUDIT_LOG_ACCESS',
            [
                'description' => 'Accessed audit logs with filters: ' . json_encode($criteria),
                'filters' => $criteria,
                'page' => $page,
                'limit' => $limit
            ]
        );
        
        return new JsonResponse($result);
    }
    
    #[Route('/patient/{id}', methods: ['GET'])]
    public function patientLogs(string $id): JsonResponse
    {
        // Verify the user has access to view patient logs
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');
        
        // Get logs for this patient
        $logs = $this->auditLogService->getLogsForEntity('Patient', $id);
        
        // Convert to array format
        $result = [];
        foreach ($logs as $log) {
            $result[] = [
                'id' => $log->getId(),
                'username' => $log->getUsername(),
                'actionType' => $log->getActionType(),
                'description' => $log->getDescription(),
                'timestamp' => $log->getTimestamp()->toDateTime()->format('Y-m-d H:i:s'),
                'ipAddress' => $log->getIpAddress(),
                'metadata' => $log->getMetadata()
            ];
        }
        
        // Log this audit log access
        $this->auditLogService->logSecurityEvent(
            $this->getUser(),
            'PATIENT_AUDIT_LOG_ACCESS',
            'Accessed audit logs for patient: ' . $id
        );
        
        return new JsonResponse($result);
    }
    
    #[Route('/user/{username}', methods: ['GET'])]
    public function userLogs(string $username): JsonResponse
    {
        // Only allow administrators or the user themself to view their own audit logs
        $currentUser = $this->getUser()->getUserIdentifier();
        if ($currentUser !== $username && !in_array('ROLE_DOCTOR', $this->getUser()->getRoles())) {
            throw $this->createAccessDeniedException('You do not have permission to view these audit logs.');
        }
        
        // Get logs for this user
        $logs = $this->auditLogService->getLogsForUser($username);
        
        // Convert to array format
        $result = [];
        foreach ($logs as $log) {
            $result[] = [
                'id' => $log->getId(),
                'actionType' => $log->getActionType(),
                'description' => $log->getDescription(),
                'timestamp' => $log->getTimestamp()->toDateTime()->format('Y-m-d H:i:s'),
                'ipAddress' => $log->getIpAddress(),
                'entityId' => $log->getEntityId(),
                'entityType' => $log->getEntityType()
            ];
        }
        
        // Log this audit log access
        $this->auditLogService->logSecurityEvent(
            $this->getUser(),
            'USER_AUDIT_LOG_ACCESS',
            'Accessed audit logs for user: ' . $username
        );
        
        return new JsonResponse($result);
    }
}