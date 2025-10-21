<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

#[ODM\Document(collection: "medical_records")]
class MedicalRecord
{
    #[ODM\Id]
    private ?ObjectId $id = null;

    #[ODM\Field(type: "string")]
    private string $patientId;

    #[ODM\Field(type: "string")]
    private string $recordType; // e.g., "diagnosis", "lab_result", "imaging", "vital_signs"

    #[ODM\Field(type: "string")]
    private string $title;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $description = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $value = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $unit = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $normalRange = null;

    #[ODM\Field(type: "string")]
    private string $status; // e.g., "normal", "abnormal", "critical"

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $providerId = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $providerName = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $facility = null;

    #[ODM\Field(type: "date")]
    private UTCDateTime $recordDate;

    #[ODM\Field(type: "date")]
    private UTCDateTime $createdAt;

    #[ODM\Field(type: "date")]
    private UTCDateTime $updatedAt;

    #[ODM\Field(type: "collection", nullable: true)]
    private ?array $attachments = null;

    #[ODM\Field(type: "collection", nullable: true)]
    private ?array $tags = null;

    #[ODM\Field(type: "bool")]
    private bool $isEncrypted = false;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
        $this->updatedAt = new UTCDateTime();
        $this->recordDate = new UTCDateTime();
    }

    // Getters and Setters
    public function getId(): ?ObjectId
    {
        return $this->id;
    }

    public function getPatientId(): string
    {
        return $this->patientId;
    }

    public function setPatientId(string $patientId): self
    {
        $this->patientId = $patientId;
        return $this;
    }

    public function getRecordType(): string
    {
        return $this->recordType;
    }

    public function setRecordType(string $recordType): self
    {
        $this->recordType = $recordType;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getNormalRange(): ?string
    {
        return $this->normalRange;
    }

    public function setNormalRange(?string $normalRange): self
    {
        $this->normalRange = $normalRange;
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

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): self
    {
        $this->providerId = $providerId;
        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(?string $providerName): self
    {
        $this->providerName = $providerName;
        return $this;
    }

    public function getFacility(): ?string
    {
        return $this->facility;
    }

    public function setFacility(?string $facility): self
    {
        $this->facility = $facility;
        return $this;
    }

    public function getRecordDate(): UTCDateTime
    {
        return $this->recordDate;
    }

    public function setRecordDate(UTCDateTime $recordDate): self
    {
        $this->recordDate = $recordDate;
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

    public function getUpdatedAt(): UTCDateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(UTCDateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function setAttachments(?array $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    public function setIsEncrypted(bool $isEncrypted): self
    {
        $this->isEncrypted = $isEncrypted;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ? $this->id->__toString() : null,
            'patientId' => $this->patientId,
            'recordType' => $this->recordType,
            'title' => $this->title,
            'description' => $this->description,
            'value' => $this->value,
            'unit' => $this->unit,
            'normalRange' => $this->normalRange,
            'status' => $this->status,
            'providerId' => $this->providerId,
            'providerName' => $this->providerName,
            'facility' => $this->facility,
            'recordDate' => $this->recordDate ? $this->recordDate->toDateTime()->format('Y-m-d H:i:s') : null,
            'createdAt' => $this->createdAt ? $this->createdAt->toDateTime()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $this->updatedAt ? $this->updatedAt->toDateTime()->format('Y-m-d H:i:s') : null,
            'attachments' => $this->attachments,
            'tags' => $this->tags,
            'isEncrypted' => $this->isEncrypted
        ];
    }
}
