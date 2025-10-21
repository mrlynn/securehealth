<?php

namespace App\Repository;

use App\Document\MedicalRecord;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\UTCDateTime;

class MedicalRecordRepository extends DocumentRepository
{
    public function __construct(DocumentManager $dm)
    {
        parent::__construct($dm, $dm->getUnitOfWork(), $dm->getClassMetadata(MedicalRecord::class));
    }

    /**
     * Find medical records by patient ID
     */
    public function findByPatientId(string $patientId): array
    {
        return $this->findBy(['patientId' => $patientId]);
    }

    /**
     * Find medical records by record type
     */
    public function findByRecordType(string $recordType): array
    {
        return $this->findBy(['recordType' => $recordType]);
    }

    /**
     * Find medical records by status
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Find medical records by provider
     */
    public function findByProvider(string $providerId): array
    {
        return $this->findBy(['providerId' => $providerId]);
    }

    /**
     * Find medical records within a date range
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder();
        $qb->field('recordDate')->gte($startDate)->lte($endDate);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find recent medical records for a patient
     */
    public function findRecentByPatient(string $patientId, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder();
        $qb->field('patientId')->equals($patientId)
           ->sort('recordDate', -1)
           ->limit($limit);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find medical records by patient and record type
     */
    public function findByPatientAndType(string $patientId, string $recordType): array
    {
        return $this->findBy([
            'patientId' => $patientId,
            'recordType' => $recordType
        ]);
    }

    /**
     * Get medical record statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder();
        $qb->group(['recordType'], ['count' => 0])
           ->reduce('function(obj, prev) { prev.count++; }');
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find abnormal medical records
     */
    public function findAbnormalRecords(): array
    {
        return $this->findBy(['status' => 'abnormal']);
    }

    /**
     * Find critical medical records
     */
    public function findCriticalRecords(): array
    {
        return $this->findBy(['status' => 'critical']);
    }

    /**
     * Search medical records by title or description
     */
    public function searchByContent(string $searchTerm): array
    {
        $qb = $this->createQueryBuilder();
        $qb->addOr($qb->expr()->field('title')->equals(new \MongoDB\BSON\Regex($searchTerm, 'i')))
           ->addOr($qb->expr()->field('description')->equals(new \MongoDB\BSON\Regex($searchTerm, 'i')));
        
        return $qb->getQuery()->execute()->toArray();
    }
}
