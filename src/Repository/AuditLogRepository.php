<?php

namespace App\Repository;

use App\Document\AuditLog;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

class AuditLogRepository extends DocumentRepository
{
    public function __construct(DocumentManager $dm)
    {
        parent::__construct($dm, $dm->getUnitOfWork(), $dm->getClassMetadata(AuditLog::class));
    }

    /**
     * Find audit logs by username
     */
    public function findByUsername(string $username): array
    {
        return $this->findBy(['username' => $username]);
    }

    /**
     * Find audit logs by action type
     */
    public function findByActionType(string $actionType): array
    {
        return $this->findBy(['actionType' => $actionType]);
    }

    /**
     * Find audit logs by entity ID
     */
    public function findByEntityId(string $entityId): array
    {
        return $this->findBy(['entityId' => $entityId]);
    }

    /**
     * Find audit logs within a date range
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder();
        $qb->field('timestamp')->gte($startDate)->lte($endDate);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Get audit log statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder();
        $qb->group(['actionType'], ['count' => 0])
           ->reduce('function(obj, prev) { prev.count++; }');
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find recent audit logs
     */
    public function findRecent(int $limit = 50): array
    {
        $qb = $this->createQueryBuilder();
        $qb->sort('timestamp', -1)->limit($limit);
        
        return $qb->getQuery()->execute()->toArray();
    }
}
