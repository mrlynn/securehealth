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
    private $id = null;

    #[ODM\Field(type: 'object_id')]
    private $patientId;

    #[ODM\Field(type: 'string')]
    private string $patientFullName;

    #[ODM\Field(type: 'date')]
    private $scheduledAt;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $notes = null;

    #[ODM\Field(type: 'string')]
    private string $createdBy;

    #[ODM\Field(type: 'date')]
    private $createdAt;

    #[ODM\Field(type: 'date', nullable: true)]
    private $updatedAt = null;

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
        if ($this->scheduledAt instanceof \DateTime) {
            return new UTCDateTime($this->scheduledAt);
        }
        return $this->scheduledAt;
    }

    public function setScheduledAt($scheduledAt): self
    {
        if ($scheduledAt instanceof \DateTime) {
            $this->scheduledAt = new UTCDateTime($scheduledAt);
        } else {
            $this->scheduledAt = $scheduledAt;
        }
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
            'scheduledAt' => $this->scheduledAt instanceof \DateTime 
                ? $this->scheduledAt->format(DateTimeInterface::ATOM)
                : $this->scheduledAt->toDateTime()->format(DateTimeInterface::ATOM),
            'notes' => $this->notes,
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt instanceof \DateTime 
                ? $this->createdAt->format(DateTimeInterface::ATOM)
                : $this->createdAt->toDateTime()->format(DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt 
                ? ($this->updatedAt instanceof \DateTime 
                    ? $this->updatedAt->format(DateTimeInterface::ATOM)
                    : $this->updatedAt->toDateTime()->format(DateTimeInterface::ATOM))
                : null,
        ];
    }
}
