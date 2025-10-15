<?php

namespace App\Repository;

use App\Document\Message;
use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MessageRepository
{
    private DocumentManager $documentManager;
    private DocumentRepository $repository;
    private MongoDBEncryptionService $encryptionService;

    public function __construct(DocumentManager $documentManager, MongoDBEncryptionService $encryptionService)
    {
        $this->documentManager = $documentManager;
        $this->repository = $documentManager->getRepository(Message::class);
        $this->encryptionService = $encryptionService;
    }

    public function save(Message $message, bool $flush = true): void
    {
        // MongoDB auto-encryption handles encryption automatically
        // Manual encryption is disabled to prevent double-encryption
        
        $this->documentManager->persist($message);
        if ($flush) {
            $this->documentManager->flush();
        }
    }

    public function findById(string $id): ?Message
    {
        try {
            return $this->repository->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }


    public function markReadByStaff(Message $message, bool $isRead = true, bool $flush = true): void
    {
        $message->setReadByStaff($isRead);
        $message->setUpdatedAt(new UTCDateTime());
        if ($flush) {
            $this->documentManager->flush();
        }
    }

    /**
     * Find messages in a conversation, ordered by thread level and creation time
     * @return Message[]
     */
    public function findByConversation(ObjectId $conversationId, int $limit = 100): array
    {
        return $this->documentManager
            ->createQueryBuilder(Message::class)
            ->field('conversationId')->equals($conversationId)
            ->sort('threadLevel', 'asc')
            ->sort('createdAt', 'asc')
            ->limit($limit)
            ->getQuery()
            ->execute()
            ->toArray(false);
    }

    /**
     * Find replies to a specific message
     * @return Message[]
     */
    public function findReplies(ObjectId $parentMessageId, int $limit = 50): array
    {
        return $this->documentManager
            ->createQueryBuilder(Message::class)
            ->field('parentMessageId')->equals($parentMessageId)
            ->sort('createdAt', 'asc')
            ->limit($limit)
            ->getQuery()
            ->execute()
            ->toArray(false);
    }

    /**
     * Find the root message of a thread (message with threadLevel = 0)
     */
    public function findRootMessage(ObjectId $conversationId): ?Message
    {
        return $this->documentManager
            ->createQueryBuilder(Message::class)
            ->field('conversationId')->equals($conversationId)
            ->field('threadLevel')->equals(0)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Find messages by patient
     */
    public function findByPatient(ObjectId $patientId, int $limit = 100): array
    {
        return $this->documentManager
            ->createQueryBuilder(Message::class)
            ->field('patientId')->equals($patientId)
            ->sort('createdAt', 'desc')
            ->limit($limit)
            ->getQuery()
            ->execute()
            ->toArray(false);
    }

    public function findForStaff(array $staffRoles, int $limit = 100): array
    {
        $qb = $this->documentManager
            ->createQueryBuilder(Message::class)
            ->field('direction')->equals('to_staff')
            ->sort('createdAt', 'desc')
            ->limit($limit);

        if (!empty($staffRoles)) {
            $qb->field('recipientRoles')->in($staffRoles);
        }

        return $qb->getQuery()->execute()->toArray(false);
    }

    public function countUnreadForStaff(array $staffRoles): int
    {
        return $this->documentManager->createQueryBuilder(Message::class)
            ->field('direction')->equals('to_staff')
            ->field('recipientRoles')->in($staffRoles)
            ->field('readByStaff')->equals(false)
            ->getQuery()
            ->execute()
            ->count();
    }
}


