<?php

namespace App\Repository;

use App\Document\Prescription;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\UTCDateTime;

class PrescriptionRepository extends DocumentRepository
{
    public function __construct(DocumentManager $dm)
    {
        parent::__construct($dm, $dm->getUnitOfWork(), $dm->getClassMetadata(Prescription::class));
    }

    /**
     * Find prescriptions by patient ID
     */
    public function findByPatientId(string $patientId): array
    {
        return $this->findBy(['patientId' => $patientId]);
    }

    /**
     * Find prescriptions by provider ID
     */
    public function findByProviderId(string $providerId): array
    {
        return $this->findBy(['providerId' => $providerId]);
    }

    /**
     * Find prescriptions by status
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Find active prescriptions
     */
    public function findActivePrescriptions(): array
    {
        return $this->findBy(['status' => 'active']);
    }

    /**
     * Find prescriptions by medication name
     */
    public function findByMedicationName(string $medicationName): array
    {
        return $this->findBy(['medicationName' => $medicationName]);
    }

    /**
     * Find prescriptions within a date range
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder();
        $qb->field('prescribedDate')->gte($startDate)->lte($endDate);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find recent prescriptions for a patient
     */
    public function findRecentByPatient(string $patientId, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder();
        $qb->field('patientId')->equals($patientId)
           ->sort('prescribedDate', -1)
           ->limit($limit);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find prescriptions that need refills
     */
    public function findPrescriptionsNeedingRefills(): array
    {
        $qb = $this->createQueryBuilder();
        $qb->field('status')->equals('active')
           ->field('refillsUsed')->lt('$refillsAllowed');
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Find prescriptions expiring soon
     */
    public function findExpiringPrescriptions(int $daysAhead = 30): array
    {
        $expirationDate = new UTCDateTime(strtotime("+{$daysAhead} days") * 1000);
        
        $qb = $this->createQueryBuilder();
        $qb->field('status')->equals('active')
           ->field('endDate')->lte($expirationDate);
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Get prescription statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder();
        $qb->group(['status'], ['count' => 0])
           ->reduce('function(obj, prev) { prev.count++; }');
        
        return $qb->getQuery()->execute()->toArray();
    }

    /**
     * Search prescriptions by medication or provider
     */
    public function searchPrescriptions(string $searchTerm): array
    {
        $qb = $this->createQueryBuilder();
        $qb->addOr($qb->expr()->field('medicationName')->equals(new \MongoDB\BSON\Regex($searchTerm, 'i')))
           ->addOr($qb->expr()->field('providerName')->equals(new \MongoDB\BSON\Regex($searchTerm, 'i')));
        
        return $qb->getQuery()->execute()->toArray();
    }
}
