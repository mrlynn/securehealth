<?php

namespace App\Repository;

use App\Document\PrescriptionRefill;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\UTCDateTime;

class PrescriptionRefillRepository extends DocumentRepository
{
    public function __construct(DocumentManager $dm)
    {
        parent::__construct($dm, $dm->getUnitOfWork(), $dm->getClassMetadata(PrescriptionRefill::class));
    }

    /**
     * Find refill requests by prescription ID
     */
    public function findByPrescriptionId(string $prescriptionId): array
    {
        return $this->findBy(['prescriptionId' => $prescriptionId]);
    }

    /**
     * Find refill requests by patient ID
     */
    public function findByPatientId(string $patientId): array
    {
        return $this->findBy(['patientId' => $patientId]);
    }

    /**
     * Find refill requests by status
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Find pending refill requests
     */
    public function findPendingRefills(): array
    {
        return $this->findBy(['status' => 'pending']);
    }

    /**
     * Find urgent refill requests
     */
    public function findUrgentRefills(): array
    {
        return $this->findBy(['isUrgent' => true]);
    }

    /**
     * Find refill requests within a date range
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder();
        $qb->field('requestedDate')->gte($startDate)->lte($endDate);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find recent refill requests
     */
    public function findRecentRefills(int $limit = 20): array
    {
        $qb = $this->createQueryBuilder();
        $qb->sort('requestedDate', -1)->limit($limit);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find refill requests by patient and status
     */
    public function findByPatientAndStatus(string $patientId, string $status): array
    {
        return $this->findBy([
            'patientId' => $patientId,
            'status' => $status
        ]);
    }

    /**
     * Get refill statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder();
        $qb->group(['status'], ['count' => 0])
           ->reduce('function(obj, prev) { prev.count++; }');
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find refill requests requiring provider attention
     */
    public function findRequiringAttention(): array
    {
        $qb = $this->createQueryBuilder();
        $qb->addOr($qb->expr()->field('status')->equals('pending'))
           ->addOr($qb->expr()->field('isUrgent')->equals(true));
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find completed refill requests
     */
    public function findCompletedRefills(): array
    {
        return $this->findBy(['status' => 'completed']);
    }
}
