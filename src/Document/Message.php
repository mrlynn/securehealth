<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

#[ODM\Document(collection: 'messages')]
class Message
{
    #[ODM\Id]
    private $id = null;

    #[ODM\Field(type: 'object_id')]
    private $patientId;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $senderUserId = null;

    #[ODM\Field(type: 'string')]
    private string $senderName;

    #[ODM\Field(type: 'collection')]
    private array $senderRoles = [];

    // 'to_patient' | 'to_staff'
    #[ODM\Field(type: 'string')]
    private string $direction;

    // When direction === 'to_staff', optional recipient staff roles (e.g., ROLE_DOCTOR, ROLE_NURSE)
    #[ODM\Field(type: 'collection', nullable: true)]
    private ?array $recipientRoles = null;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $subject = null;

    #[ODM\Field(type: 'string')]
    private string $body;

    #[ODM\Field(type: 'date')]
    private $createdAt;

    #[ODM\Field(type: 'bool')]
    private bool $readByPatient = false;

    #[ODM\Field(type: 'bool')]
    private bool $readByStaff = false;

    // Threading fields
    #[ODM\Field(type: 'object_id', nullable: true)]
    private ?ObjectId $conversationId = null;

    #[ODM\Field(type: 'object_id', nullable: true)]
    private ?ObjectId $parentMessageId = null;

    #[ODM\Field(type: 'int')]
    private int $threadLevel = 0; // 0 = top-level, 1+ = replies

    #[ODM\Field(type: 'date', nullable: true)]
    private ?UTCDateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
    }

    public function getId(): ?ObjectId
    {
        if (is_string($this->id)) {
            return new ObjectId($this->id);
        }
        return $this->id;
    }

    public function setId($id): self
    {
        if (is_string($id)) {
            $this->id = new ObjectId($id);
        } else {
            $this->id = $id;
        }
        return $this;
    }

    public function getPatientId(): ObjectId
    {
        if (is_string($this->patientId)) {
            return new ObjectId($this->patientId);
        }
        return $this->patientId;
    }

    public function setPatientId($patientId): self
    {
        if (is_string($patientId)) {
            $this->patientId = new ObjectId($patientId);
        } else {
            $this->patientId = $patientId;
        }
        return $this;
    }

    public function getSenderUserId(): ?string
    {
        return $this->senderUserId;
    }

    public function setSenderUserId(?string $senderUserId): self
    {
        $this->senderUserId = $senderUserId;
        return $this;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): self
    {
        $this->senderName = $senderName;
        return $this;
    }

    public function getSenderRoles(): array
    {
        return $this->senderRoles;
    }

    public function setSenderRoles(array $senderRoles): self
    {
        $this->senderRoles = $senderRoles;
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = $direction;
        return $this;
    }

    public function getRecipientRoles(): ?array
    {
        return $this->recipientRoles;
    }

    public function setRecipientRoles(?array $recipientRoles): self
    {
        $this->recipientRoles = $recipientRoles;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getCreatedAt(): UTCDateTime
    {
        if ($this->createdAt instanceof \DateTime) {
            return new UTCDateTime($this->createdAt);
        }
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt): self
    {
        if ($createdAt instanceof \DateTime) {
            $this->createdAt = new UTCDateTime($createdAt);
        } else {
            $this->createdAt = $createdAt;
        }
        return $this;
    }

    public function isReadByPatient(): bool
    {
        return $this->readByPatient;
    }

    public function setReadByPatient(bool $readByPatient): self
    {
        $this->readByPatient = $readByPatient;
        return $this;
    }

    public function isReadByStaff(): bool
    {
        return $this->readByStaff;
    }

    public function setReadByStaff(bool $readByStaff): self
    {
        $this->readByStaff = $readByStaff;
        return $this;
    }

    public function getConversationId(): ?ObjectId
    {
        if (is_string($this->conversationId)) {
            return new ObjectId($this->conversationId);
        }
        return $this->conversationId;
    }

    public function setConversationId($conversationId): self
    {
        if (is_string($conversationId)) {
            $this->conversationId = new ObjectId($conversationId);
        } else {
            $this->conversationId = $conversationId;
        }
        return $this;
    }

    public function getParentMessageId(): ?ObjectId
    {
        if (is_string($this->parentMessageId)) {
            return new ObjectId($this->parentMessageId);
        }
        return $this->parentMessageId;
    }

    public function setParentMessageId($parentMessageId): self
    {
        if (is_string($parentMessageId)) {
            $this->parentMessageId = new ObjectId($parentMessageId);
        } else {
            $this->parentMessageId = $parentMessageId;
        }
        return $this;
    }

    public function getThreadLevel(): int
    {
        return $this->threadLevel;
    }

    public function setThreadLevel(int $threadLevel): self
    {
        $this->threadLevel = $threadLevel;
        return $this;
    }

    public function getUpdatedAt(): ?UTCDateTime
    {
        if ($this->updatedAt instanceof \DateTime) {
            return new UTCDateTime($this->updatedAt);
        }
        return $this->updatedAt;
    }

    public function setUpdatedAt($updatedAt): self
    {
        if ($updatedAt instanceof \DateTime) {
            $this->updatedAt = new UTCDateTime($updatedAt);
        } else {
            $this->updatedAt = $updatedAt;
        }
        return $this;
    }

    /**
     * Convert Message to encrypted MongoDB document for storage
     * This method applies proper encryption to sensitive fields for HIPAA compliance
     */
    public function toDocument(\App\Service\MongoDBEncryptionService $encryptionService): array
    {
        $document = [];

        if ($this->id) {
            $document['_id'] = $this->id;
        }

        // Manual encryption for HIPAA compliance - encrypt sensitive message data
        $document['patientId'] = $encryptionService->encrypt('message', 'patientId', $this->patientId);
        
        if ($this->senderUserId) {
            $document['senderUserId'] = $encryptionService->encrypt('message', 'senderUserId', $this->senderUserId);
        }
        
        $document['senderName'] = $encryptionService->encrypt('message', 'senderName', $this->senderName);
        
        // Handle arrays by converting to JSON string for encryption
        $document['senderRoles'] = $encryptionService->encrypt('message', 'senderRoles', json_encode($this->senderRoles));
        $document['direction'] = $encryptionService->encrypt('message', 'direction', $this->direction);
        
        if ($this->recipientRoles) {
            $document['recipientRoles'] = $encryptionService->encrypt('message', 'recipientRoles', json_encode($this->recipientRoles));
        }
        
        if ($this->subject) {
            $document['subject'] = $encryptionService->encrypt('message', 'subject', $this->subject);
        }
        
        // Body contains sensitive medical information - use RANDOM encryption
        $document['body'] = $encryptionService->encrypt('message', 'body', $this->body);
        
        $document['createdAt'] = $this->createdAt;
        
        if ($this->updatedAt) {
            $document['updatedAt'] = $this->updatedAt;
        }
        
        $document['readByPatient'] = $this->readByPatient;
        $document['readByStaff'] = $this->readByStaff;
        
        if ($this->conversationId) {
            $document['conversationId'] = $encryptionService->encrypt('message', 'conversationId', $this->conversationId);
        }
        
        if ($this->parentMessageId) {
            $document['parentMessageId'] = $encryptionService->encrypt('message', 'parentMessageId', $this->parentMessageId);
        }
        
        $document['threadLevel'] = $this->threadLevel;

        return $document;
    }

    /**
     * Create Message from encrypted MongoDB document
     * This method decrypts sensitive fields when reading from storage
     */
    public static function fromDocument(array $document, \App\Service\MongoDBEncryptionService $encryptionService): self
    {
        $message = new self();
        
        if (isset($document['_id'])) {
            $message->id = $document['_id'];
        }
        
        // Decrypt sensitive fields
        $message->patientId = $encryptionService->decrypt($document['patientId'] ?? null);
        $message->senderUserId = $encryptionService->decrypt($document['senderUserId'] ?? null);
        $message->senderName = $encryptionService->decrypt($document['senderName'] ?? '');
        
        // Handle arrays by decrypting JSON strings
        $senderRolesJson = $encryptionService->decrypt($document['senderRoles'] ?? '[]');
        
        // Handle both old data (arrays) and new data (JSON strings)
        if (is_array($senderRolesJson)) {
            // Old data format - already an array
            $message->senderRoles = $senderRolesJson;
        } elseif (is_string($senderRolesJson)) {
            // New data format - JSON string that needs decoding
            $message->senderRoles = json_decode($senderRolesJson, true) ?? [];
        } else {
            // Fallback for unexpected data types
            $message->senderRoles = [];
        }
        
        $message->direction = $encryptionService->decrypt($document['direction'] ?? '');
        
        $recipientRolesJson = $encryptionService->decrypt($document['recipientRoles'] ?? null);
        
        // Handle both old data (arrays) and new data (JSON strings)
        if ($recipientRolesJson === null) {
            $message->recipientRoles = null;
        } elseif (is_array($recipientRolesJson)) {
            // Old data format - already an array
            $message->recipientRoles = $recipientRolesJson;
        } elseif (is_string($recipientRolesJson)) {
            // New data format - JSON string that needs decoding
            $message->recipientRoles = json_decode($recipientRolesJson, true);
        } else {
            // Fallback for unexpected data types
            $message->recipientRoles = null;
        }
        
        $message->subject = $encryptionService->decrypt($document['subject'] ?? null);
        $message->body = $encryptionService->decrypt($document['body'] ?? '');
        
        $message->createdAt = $document['createdAt'] ?? new \MongoDB\BSON\UTCDateTime();
        $message->updatedAt = $document['updatedAt'] ?? null;
        $message->readByPatient = $document['readByPatient'] ?? false;
        $message->readByStaff = $document['readByStaff'] ?? false;
        
        $message->conversationId = $encryptionService->decrypt($document['conversationId'] ?? null);
        $message->parentMessageId = $encryptionService->decrypt($document['parentMessageId'] ?? null);
        $message->threadLevel = $document['threadLevel'] ?? 0;
        
        return $message;
    }

    public function toArray(): array
    {
        $createdAt = $this->getCreatedAt();
        if ($createdAt instanceof UTCDateTime) {
            $createdAtOut = ['\$date' => $createdAt->toDateTime()->format(DATE_ATOM)];
        } elseif ($createdAt instanceof \DateTimeInterface) {
            $createdAtOut = ['\$date' => $createdAt->format(DATE_ATOM)];
        } else {
            $createdAtOut = $createdAt;
        }

        $updatedAt = $this->getUpdatedAt();
        $updatedAtOut = null;
        if ($updatedAt) {
            if ($updatedAt instanceof UTCDateTime) {
                $updatedAtOut = ['\$date' => $updatedAt->toDateTime()->format(\DateTimeInterface::ISO8601)];
            } elseif ($updatedAt instanceof \DateTimeInterface) {
                $updatedAtOut = ['\$date' => $updatedAt->format(\DateTimeInterface::ISO8601)];
            } else {
                $updatedAtOut = $updatedAt;
            }
        }

        return [
            'id' => (string) $this->getId(),
            'patientId' => (string) $this->getPatientId(),
            'senderUserId' => (string) $this->getSenderUserId(),
            'senderName' => $this->getSenderName(),
            'senderRoles' => $this->getSenderRoles(),
            'direction' => $this->getDirection(),
            'recipientRoles' => $this->getRecipientRoles(),
            'subject' => $this->getSubject(),
            'body' => $this->getBody(),
            'createdAt' => $createdAtOut,
            'updatedAt' => $updatedAtOut,
            'readByPatient' => $this->isReadByPatient(),
            'readByStaff' => $this->isReadByStaff(),
            'conversationId' => $this->getConversationId() ? (string) $this->getConversationId() : null,
            'parentMessageId' => $this->getParentMessageId() ? (string) $this->getParentMessageId() : null,
            'threadLevel' => $this->getThreadLevel(),
        ];
    }
}


