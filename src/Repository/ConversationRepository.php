<?php

namespace App\Repository;

use App\Document\Conversation;
use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class ConversationRepository
{
    private DocumentManager $documentManager;
    private DocumentRepository $repository;
    private MongoDBEncryptionService $encryptionService;

    public function __construct(DocumentManager $documentManager, MongoDBEncryptionService $encryptionService)
    {
        $this->documentManager = $documentManager;
        $this->repository = $documentManager->getRepository(Conversation::class);
        $this->encryptionService = $encryptionService;
    }

    public function save(Conversation $conversation, bool $flush = true): void
    {
        // MongoDB auto-encryption handles encryption automatically
        // Manual encryption is disabled to prevent double-encryption
        
        $this->documentManager->persist($conversation);
        if ($flush) {
            $this->documentManager->flush();
        }
    }

    public function findById(string $id): ?Conversation
    {
        try {
            return $this->repository->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findByPatient(ObjectId $patientId, int $limit = 50, int $skip = 0): array
    {
        return $this->documentManager
            ->createQueryBuilder(Conversation::class)
            ->field('patientId')->equals($patientId)
            ->sort('lastMessageAt', 'desc')
            ->limit($limit)
            ->skip($skip)
            ->getQuery()
            ->execute()
            ->toArray(false);
    }

    public function findForStaff(array $staffRoles, int $limit = 50, int $skip = 0): array
    {
        try {
            $qb = $this->documentManager->createQueryBuilder(Conversation::class)
                ->sort('lastMessageAt', 'desc')
                ->limit($limit)
                ->skip($skip);

            // Find conversations where any of the staff roles are participants
            $qb->field('participants')->in($staffRoles);

            return $qb->getQuery()->execute()->toArray(false);
        } catch (\Exception $e) {
            // If there's an error, return empty array
            return [];
        }
    }

    public function countUnreadForStaff(array $staffRoles): int
    {
        try {
            return $this->documentManager->createQueryBuilder(Conversation::class)
                ->field('participants')->in($staffRoles)
                ->field('hasUnreadForStaff')->equals(true)
                ->getQuery()
                ->execute()
                ->count();
        } catch (\Exception $e) {
            // If there's an error, return 0
            return 0;
        }
    }

    public function countUnreadForPatient(ObjectId $patientId): int
    {
        return $this->documentManager->createQueryBuilder(Conversation::class)
            ->field('patientId')->equals($patientId)
            ->field('hasUnreadForPatient')->equals(true)
            ->getQuery()
            ->execute()
            ->count();
    }

    public function markAsReadByStaff(string $conversationId): ?Conversation
    {
        $conversation = $this->findById($conversationId);
        if ($conversation) {
            $conversation->setHasUnreadForStaff(false);
            $conversation->setUpdatedAt(new UTCDateTime());
            $this->save($conversation, true);
        }
        return $conversation;
    }

    public function markAsReadByPatient(string $conversationId): ?Conversation
    {
        $conversation = $this->findById($conversationId);
        if ($conversation) {
            $conversation->setHasUnreadForPatient(false);
            $conversation->setUpdatedAt(new UTCDateTime());
            $this->save($conversation, true);
        }
        return $conversation;
    }

    public function updateLastMessage(string $conversationId, string $messagePreview, bool $isFromStaff = false): ?Conversation
    {
        $conversation = $this->findById($conversationId);
        if ($conversation) {
            $conversation->setLastMessageAt(new UTCDateTime());
            $conversation->setLastMessagePreview($messagePreview);
            $conversation->incrementMessageCount();
            
            // Set unread flags based on who sent the message
            if ($isFromStaff) {
                $conversation->setHasUnreadForPatient(true);
                $conversation->setHasUnreadForStaff(false);
            } else {
                $conversation->setHasUnreadForStaff(true);
                $conversation->setHasUnreadForPatient(false);
            }
            
            $conversation->setUpdatedAt(new UTCDateTime());
            $this->save($conversation, true);
        }
        return $conversation;
    }

    public function closeConversation(string $conversationId): ?Conversation
    {
        $conversation = $this->findById($conversationId);
        if ($conversation) {
            $conversation->setStatus('closed');
            $conversation->setUpdatedAt(new UTCDateTime());
            $this->save($conversation, true);
        }
        return $conversation;
    }

    public function archiveConversation(string $conversationId): ?Conversation
    {
        $conversation = $this->findById($conversationId);
        if ($conversation) {
            $conversation->setStatus('archived');
            $conversation->setUpdatedAt(new UTCDateTime());
            $this->save($conversation, true);
        }
        return $conversation;
    }

}
