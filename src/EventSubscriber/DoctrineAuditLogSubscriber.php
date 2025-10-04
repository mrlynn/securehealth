<?php

namespace App\EventSubscriber;

use App\Document\Patient;
use App\Service\AuditLogService;
use Doctrine\ODM\MongoDB\Event\LifecycleEventArgs;
use Doctrine\ODM\MongoDB\Event\PreUpdateEventArgs;
use Doctrine\ODM\MongoDB\Events;
use Doctrine\Common\EventSubscriber;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\TokenStorage\TokenStorageInterface;

class DoctrineAuditLogSubscriber implements EventSubscriber
{
    private AuditLogService $auditLogService;
    private TokenStorageInterface $tokenStorage;
    
    public function __construct(AuditLogService $auditLogService, TokenStorageInterface $tokenStorage)
    {
        $this->auditLogService = $auditLogService;
        $this->tokenStorage = $tokenStorage;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::preUpdate,
            Events::preRemove,
        ];
    }
    
    /**
     * Log document creation
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->logDocumentEvent($args->getDocument(), 'CREATE');
    }
    
    /**
     * Log document updates
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $document = $args->getDocument();
        $this->logDocumentEvent($document, 'UPDATE');
        
        // Log changes to specific fields for HIPAA compliance
        if ($document instanceof Patient) {
            $changeSet = $args->getDocumentChangeSet();
            $this->logPatientChanges($document, $changeSet);
        }
    }
    
    /**
     * Log document deletion
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $this->logDocumentEvent($args->getDocument(), 'DELETE');
    }
    
    /**
     * Log general document events
     */
    private function logDocumentEvent(object $document, string $action): void
    {
        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;
        if (!$user) {
            return;
        }
        
        $username = $user->getUserIdentifier();
        $className = get_class($document);
        $entityType = substr($className, strrpos($className, '\\') + 1);
        
        // Only log events for specific entity types
        if (in_array($entityType, ['Patient', 'AuditLog'])) {
            $id = method_exists($document, 'getId') ? $document->getId() : null;
            
            $this->auditLogService->logEvent(
                $username,
                $entityType . '_' . $action,
                'User ' . $username . ' performed ' . $action . ' on ' . $entityType . ($id ? ' with ID: ' . $id : ''),
                $id,
                $entityType
            );
        }
    }
    
    /**
     * Log specific field changes for patient records (HIPAA compliance)
     */
    private function logPatientChanges(Patient $patient, array $changeSet): void
    {
        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;
        if (!$user) {
            return;
        }
        
        $username = $user->getUserIdentifier();
        $sensitiveFields = [
            'socialSecurityNumber',
            'primaryDiagnosis',
            'medications',
            'allergies'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($changeSet[$field])) {
                $this->auditLogService->logEvent(
                    $username,
                    'PATIENT_FIELD_CHANGE',
                    'User ' . $username . ' modified ' . $field . ' for patient ' . $patient->getId(),
                    $patient->getId(),
                    'Patient',
                    ['field' => $field]
                );
            }
        }
    }
}