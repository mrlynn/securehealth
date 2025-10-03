<?php

namespace App\Repository;

use App\Document\Appointment;
use DateTimeInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class AppointmentRepository
{
    private DocumentManager $documentManager;
    private DocumentRepository $repository;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
        $this->repository = $documentManager->getRepository(Appointment::class);
    }

    public function save(Appointment $appointment, bool $flush = true): void
    {
        $this->documentManager->persist($appointment);

        if ($flush) {
            $this->documentManager->flush();
        }
    }

    public function remove(Appointment $appointment, bool $flush = true): void
    {
        $this->documentManager->remove($appointment);

        if ($flush) {
            $this->documentManager->flush();
        }
    }

    public function findById(string $id): ?Appointment
    {
        if (!ObjectId::isValid($id)) {
            return null;
        }

        return $this->repository->find((string) $id);
    }

    /**
     * @return Appointment[]
     */
    public function findUpcoming(?DateTimeInterface $from = null, ?ObjectId $patientId = null, int $limit = 50): array
    {
        $qb = $this->documentManager
            ->createQueryBuilder(Appointment::class)
            ->sort('scheduledAt', 'asc')
            ->limit($limit);

        if ($from) {
            $qb->field('scheduledAt')->gte(new UTCDateTime($from));
        }

        if ($patientId) {
            $qb->field('patientId')->equals($patientId);
        }

        return $qb->getQuery()->execute()->toArray(false);
    }

    /**
     * @return Appointment[]
     */
    public function findByPatient(ObjectId $patientId): array
    {
        return $this->documentManager
            ->createQueryBuilder(Appointment::class)
            ->field('patientId')->equals($patientId)
            ->sort('scheduledAt', 'asc')
            ->getQuery()
            ->execute()
            ->toArray(false);
    }
}
