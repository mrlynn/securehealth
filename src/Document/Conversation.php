<?php

namespace App\Document;

use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document(collection: 'conversations')]
class Conversation
{
    #[ODM\Id]
    private $id = null;

    #[ODM\Field(type: 'object_id')]
    #[Assert\NotBlank(message: "Patient ID is required")]
    private ObjectId $patientId;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank(message: "Subject is required")]
    #[Assert\Length(min: 1, max: 200)]
    private string $subject;

    #[ODM\Field(type: 'collection')]
    #[Assert\NotBlank(message: "Participants are required")]
    private array $participants = []; // Array of user IDs involved in the conversation

    #[ODM\Field(type: 'string')]
    #[Assert\Choice(choices: ['active', 'closed', 'archived'], message: "Status must be 'active', 'closed', or 'archived'")]
    private string $status = 'active';

    #[ODM\Field(type: 'date')]
    private UTCDateTime $createdAt;

    #[ODM\Field(type: 'date', nullable: true)]
    private ?UTCDateTime $lastMessageAt = null;

    #[ODM\Field(type: 'int')]
    private int $messageCount = 0;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $lastMessagePreview = null;

    #[ODM\Field(type: 'bool')]
    private bool $hasUnreadForPatient = false;

    #[ODM\Field(type: 'bool')]
    private bool $hasUnreadForStaff = false;

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

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function setParticipants(array $participants): self
    {
        $this->participants = $participants;
        return $this;
    }

    public function addParticipant(string $userId): self
    {
        if (!in_array($userId, $this->participants)) {
            $this->participants[] = $userId;
        }
        return $this;
    }

    public function removeParticipant(string $userId): self
    {
        $this->participants = array_values(array_filter($this->participants, fn($id) => $id !== $userId));
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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

    public function getLastMessageAt(): ?UTCDateTime
    {
        if ($this->lastMessageAt instanceof \DateTime) {
            return new UTCDateTime($this->lastMessageAt);
        }
        return $this->lastMessageAt;
    }

    public function setLastMessageAt($lastMessageAt): self
    {
        if ($lastMessageAt instanceof \DateTime) {
            $this->lastMessageAt = new UTCDateTime($lastMessageAt);
        } else {
            $this->lastMessageAt = $lastMessageAt;
        }
        return $this;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    public function setMessageCount(int $messageCount): self
    {
        $this->messageCount = $messageCount;
        return $this;
    }

    public function incrementMessageCount(): self
    {
        $this->messageCount++;
        return $this;
    }

    public function getLastMessagePreview(): ?string
    {
        return $this->lastMessagePreview;
    }

    public function setLastMessagePreview(?string $lastMessagePreview): self
    {
        $this->lastMessagePreview = $lastMessagePreview;
        return $this;
    }

    public function isHasUnreadForPatient(): bool
    {
        return $this->hasUnreadForPatient;
    }

    public function setHasUnreadForPatient(bool $hasUnreadForPatient): self
    {
        $this->hasUnreadForPatient = $hasUnreadForPatient;
        return $this;
    }

    public function isHasUnreadForStaff(): bool
    {
        return $this->hasUnreadForStaff;
    }

    public function setHasUnreadForStaff(bool $hasUnreadForStaff): self
    {
        $this->hasUnreadForStaff = $hasUnreadForStaff;
        return $this;
    }

    /**
     * Convert Conversation to encrypted MongoDB document for storage
     * This method applies proper encryption to sensitive fields for HIPAA compliance
     */
    public function toDocument(\App\Service\MongoDBEncryptionService $encryptionService): array
    {
        $document = [];

        if ($this->id) {
            $document['_id'] = $this->id;
        }

        // Manual encryption for HIPAA compliance - encrypt sensitive conversation data
        $document['patientId'] = $encryptionService->encrypt('conversation', 'patientId', $this->patientId);
        $document['subject'] = $encryptionService->encrypt('conversation', 'subject', $this->subject);
        
        // Handle arrays by converting to JSON string for encryption
        $document['participants'] = $encryptionService->encrypt('conversation', 'participants', json_encode($this->participants));
        $document['status'] = $encryptionService->encrypt('conversation', 'status', $this->status);
        
        if ($this->lastMessagePreview) {
            $document['lastMessagePreview'] = $encryptionService->encrypt('conversation', 'lastMessagePreview', $this->lastMessagePreview);
        }
        
        $document['createdAt'] = $this->createdAt;
        
        if ($this->lastMessageAt) {
            $document['lastMessageAt'] = $this->lastMessageAt;
        }
        
        $document['messageCount'] = $this->messageCount;
        $document['hasUnreadForPatient'] = $this->hasUnreadForPatient;
        $document['hasUnreadForStaff'] = $this->hasUnreadForStaff;

        return $document;
    }

    /**
     * Create Conversation from encrypted MongoDB document
     * This method decrypts sensitive fields when reading from storage
     */
    public static function fromDocument(array $document, \App\Service\MongoDBEncryptionService $encryptionService): self
    {
        $conversation = new self();
        
        if (isset($document['_id'])) {
            $conversation->id = $document['_id'];
        }
        
        // Decrypt sensitive fields
        $conversation->patientId = $encryptionService->decrypt($document['patientId'] ?? null);
        $conversation->subject = $encryptionService->decrypt($document['subject'] ?? '');
        
        // Handle arrays by decrypting JSON strings
        $participantsJson = $encryptionService->decrypt($document['participants'] ?? '[]');
        
        // Handle both old data (arrays) and new data (JSON strings)
        if (is_array($participantsJson)) {
            // Old data format - already an array
            $conversation->participants = $participantsJson;
        } elseif (is_string($participantsJson)) {
            // New data format - JSON string that needs decoding
            $conversation->participants = json_decode($participantsJson, true) ?? [];
        } else {
            // Fallback for unexpected data types
            $conversation->participants = [];
        }
        
        $conversation->status = $encryptionService->decrypt($document['status'] ?? 'active');
        $conversation->lastMessagePreview = $encryptionService->decrypt($document['lastMessagePreview'] ?? null);
        
        $conversation->createdAt = $document['createdAt'] ?? new \MongoDB\BSON\UTCDateTime();
        $conversation->lastMessageAt = $document['lastMessageAt'] ?? null;
        $conversation->messageCount = $document['messageCount'] ?? 0;
        $conversation->hasUnreadForPatient = $document['hasUnreadForPatient'] ?? false;
        $conversation->hasUnreadForStaff = $document['hasUnreadForStaff'] ?? false;
        
        return $conversation;
    }

    public function toArray(): array
    {
        $createdAt = $this->getCreatedAt();
        if ($createdAt instanceof UTCDateTime) {
            $createdAtOut = ['\$date' => $createdAt->toDateTime()->format(\DateTimeInterface::ISO8601)];
        } elseif ($createdAt instanceof \DateTimeInterface) {
            $createdAtOut = ['\$date' => $createdAt->format(\DateTimeInterface::ISO8601)];
        } else {
            $createdAtOut = $createdAt;
        }

        $lastMessageAt = $this->getLastMessageAt();
        $lastMessageAtOut = null;
        if ($lastMessageAt) {
            if ($lastMessageAt instanceof UTCDateTime) {
                $lastMessageAtOut = ['\$date' => $lastMessageAt->toDateTime()->format(\DateTimeInterface::ISO8601)];
            } elseif ($lastMessageAt instanceof \DateTimeInterface) {
                $lastMessageAtOut = ['\$date' => $lastMessageAt->format(\DateTimeInterface::ISO8601)];
            } else {
                $lastMessageAtOut = $lastMessageAt;
            }
        }

        return [
            'id' => (string)$this->getId(),
            'patientId' => (string)$this->getPatientId(),
            'subject' => $this->getSubject(),
            'participants' => $this->getParticipants(),
            'status' => $this->getStatus(),
            'createdAt' => $createdAtOut,
            'lastMessageAt' => $lastMessageAtOut,
            'messageCount' => $this->getMessageCount(),
            'lastMessagePreview' => $this->getLastMessagePreview(),
            'hasUnreadForPatient' => $this->isHasUnreadForPatient(),
            'hasUnreadForStaff' => $this->isHasUnreadForStaff(),
        ];
    }
}
