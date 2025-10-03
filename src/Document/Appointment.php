<?php

namespace App\Document;

use DateTimeInterface;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

#[ODM\Document(collection: 'appointments')]
class Appointment
{
    #[ODM\Id]
    private ?ObjectId $id = null;

    #[ODM\Field(type: 'object_id')]
    private ObjectId $patientId;

    #[ODM\Field(type: 'string')]
    private string $patientFullName;

    #[ODM\Field(type: 'date')]
    private UTCDateTime $scheduledAt;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $notes = null;

    #[ODM\Field(type: 'string')]
    private string $createdBy;

    #[ODM\Field(type: 'date')]
    private UTCDateTime $createdAt;

    #[ODM\Field(type: 'date', nullable: true)]
    private ?UTCDateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
    }

    public function getId(): ?ObjectId
    {
        return $this->id;
    }

    public function setId(ObjectId $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPatientId(): ObjectId
    {
        return $this->patientId;
    }

    public function setPatientId(ObjectId $patientId): self
    {
        $this->patientId = $patientId;
        return $this;
    }

    public function getPatientFullName(): string
    {
        return $this->patientFullName;
    }

    public function setPatientFullName(string $patientFullName): self
    {
        $this->patientFullName = $patientFullName;
        return $this;
    }

    public function getScheduledAt(): UTCDateTime
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(UTCDateTime $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): UTCDateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(UTCDateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?UTCDateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?UTCDateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new UTCDateTime();
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ? (string) $this->id : null,
            'patientId' => (string) $this->patientId,
            'patientFullName' => $this->patientFullName,
            'scheduledAt' => $this->scheduledAt->toDateTime()->format(DateTimeInterface::ATOM),
            'notes' => $this->notes,
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt->toDateTime()->format(DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->toDateTime()->format(DateTimeInterface::ATOM),
        ];
    }
}
