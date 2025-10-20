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
        // Apply manual encryption for HIPAA compliance
        $document = $conversation->toDocument($this->encryptionService);
        
        if ($conversation->getId()) {
            // Update existing document
            $this->documentManager
                ->getDocumentCollection(Conversation::class)
                ->replaceOne(
                    ['_id' => $conversation->getId()],
                    $document
                );
        } else {
            // Insert new document
            $result = $this->documentManager
                ->getDocumentCollection(Conversation::class)
                ->insertOne($document);
            
            if ($result->getInsertedId()) {
                $conversation->setId($result->getInsertedId());
            }
        }
    }

    public function findById(string $id): ?Conversation
    {
        try {
            $objectId = new ObjectId($id);
            $document = $this->documentManager
                ->getDocumentCollection(Conversation::class)
                ->findOne(['_id' => $objectId]);
            
            if (!$document) {
                return null;
            }
            
            return Conversation::fromDocument((array) $document, $this->encryptionService);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findByPatient(ObjectId $patientId, int $limit = 50, int $skip = 0): array
    {
        // Encrypt the patientId for searching
        $encryptedPatientId = $this->encryptionService->encrypt('conversation', 'patientId', $patientId);
        
        $cursor = $this->documentManager
            ->getDocumentCollection(Conversation::class)
            ->find(
                ['patientId' => $encryptedPatientId],
                [
                    'sort' => ['lastMessageAt' => -1],
                    'limit' => $limit,
                    'skip' => $skip
                ]
            );
        
        $conversations = [];
        foreach ($cursor as $document) {
            $conversations[] = Conversation::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $conversations;
    }

    public function findForStaff(array $staffRoles, int $limit = 50, int $skip = 0): array
    {
        try {
            // Encrypt the staff roles for searching
            $encryptedStaffRoles = [];
            foreach ($staffRoles as $role) {
                $encryptedStaffRoles[] = $this->encryptionService->encrypt('conversation', 'participants', json_encode([$role]));
            }
            
            $cursor = $this->documentManager
                ->getDocumentCollection(Conversation::class)
                ->find(
                    ['participants' => ['$in' => $encryptedStaffRoles]],
                    [
                        'sort' => ['lastMessageAt' => -1],
                        'limit' => $limit,
                        'skip' => $skip
                    ]
                );
            
            $conversations = [];
            foreach ($cursor as $document) {
                $conversations[] = Conversation::fromDocument((array) $document, $this->encryptionService);
            }
            
            return $conversations;
        } catch (\Exception $e) {
            // If there's an error, return empty array
            return [];
        }
    }

    public function countUnreadForStaff(array $staffRoles): int
    {
        try {
            // Encrypt the staff roles for searching
            $encryptedStaffRoles = [];
            foreach ($staffRoles as $role) {
                $encryptedStaffRoles[] = $this->encryptionService->encrypt('conversation', 'participants', json_encode([$role]));
            }
            
            return $this->documentManager
                ->getDocumentCollection(Conversation::class)
                ->countDocuments([
                    'participants' => ['$in' => $encryptedStaffRoles],
                    'hasUnreadForStaff' => true
                ]);
        } catch (\Exception $e) {
            // If there's an error, return 0
            return 0;
        }
    }

    public function countUnreadForPatient(ObjectId $patientId): int
    {
        // Encrypt the patientId for searching
        $encryptedPatientId = $this->encryptionService->encrypt('conversation', 'patientId', $patientId);
        
        return $this->documentManager
            ->getDocumentCollection(Conversation::class)
            ->countDocuments([
                'patientId' => $encryptedPatientId,
                'hasUnreadForPatient' => true
            ]);
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
