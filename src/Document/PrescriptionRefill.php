<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

#[ODM\Document(collection: "prescription_refills")]
class PrescriptionRefill
{
    #[ODM\Id]
    private ?ObjectId $id = null;

    #[ODM\Field(type: "string")]
    private string $prescriptionId;

    #[ODM\Field(type: "string")]
    private string $patientId;

    #[ODM\Field(type: "string")]
    private string $requestedBy; // patient or provider

    #[ODM\Field(type: "string")]
    private string $status = 'pending'; // pending, approved, denied, completed

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $reason = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $providerNotes = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $pharmacy = null;

    #[ODM\Field(type: "date")]
    private UTCDateTime $requestedDate;

    #[ODM\Field(type: "date", nullable: true)]
    private ?UTCDateTime $processedDate = null;

    #[ODM\Field(type: "date", nullable: true)]
    private ?UTCDateTime $pickedUpDate = null;

    #[ODM\Field(type: "date")]
    private UTCDateTime $createdAt;

    #[ODM\Field(type: "date")]
    private UTCDateTime $updatedAt;

    #[ODM\Field(type: "bool")]
    private bool $isUrgent = false;

    #[ODM\Field(type: "collection", nullable: true)]
    private ?array $attachments = null;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
        $this->updatedAt = new UTCDateTime();
        $this->requestedDate = new UTCDateTime();
    }

    // Getters and Setters
    public function getId(): ?ObjectId
    {
        return $this->id;
    }

    public function getPrescriptionId(): string
    {
        return $this->prescriptionId;
    }

    public function setPrescriptionId(string $prescriptionId): self
    {
        $this->prescriptionId = $prescriptionId;
        return $this;
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

    public function getRequestedBy(): string
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(string $requestedBy): self
    {
        $this->requestedBy = $requestedBy;
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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getProviderNotes(): ?string
    {
        return $this->providerNotes;
    }

    public function setProviderNotes(?string $providerNotes): self
    {
        $this->providerNotes = $providerNotes;
        return $this;
    }

    public function getPharmacy(): ?string
    {
        return $this->pharmacy;
    }

    public function setPharmacy(?string $pharmacy): self
    {
        $this->pharmacy = $pharmacy;
        return $this;
    }

    public function getRequestedDate(): UTCDateTime
    {
        return $this->requestedDate;
    }

    public function setRequestedDate(UTCDateTime $requestedDate): self
    {
        $this->requestedDate = $requestedDate;
        return $this;
    }

    public function getProcessedDate(): ?UTCDateTime
    {
        return $this->processedDate;
    }

    public function setProcessedDate(?UTCDateTime $processedDate): self
    {
        $this->processedDate = $processedDate;
        return $this;
    }

    public function getPickedUpDate(): ?UTCDateTime
    {
        return $this->pickedUpDate;
    }

    public function setPickedUpDate(?UTCDateTime $pickedUpDate): self
    {
        $this->pickedUpDate = $pickedUpDate;
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

    public function isUrgent(): bool
    {
        return $this->isUrgent;
    }

    public function setIsUrgent(bool $isUrgent): self
    {
        $this->isUrgent = $isUrgent;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id ? $this->id->__toString() : null,
            'prescriptionId' => $this->prescriptionId,
            'patientId' => $this->patientId,
            'requestedBy' => $this->requestedBy,
            'status' => $this->status,
            'reason' => $this->reason,
            'providerNotes' => $this->providerNotes,
            'pharmacy' => $this->pharmacy,
            'requestedDate' => $this->requestedDate ? $this->requestedDate->toDateTime()->format('Y-m-d H:i:s') : null,
            'processedDate' => $this->processedDate ? $this->processedDate->toDateTime()->format('Y-m-d H:i:s') : null,
            'pickedUpDate' => $this->pickedUpDate ? $this->pickedUpDate->toDateTime()->format('Y-m-d H:i:s') : null,
            'createdAt' => $this->createdAt ? $this->createdAt->toDateTime()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $this->updatedAt ? $this->updatedAt->toDateTime()->format('Y-m-d H:i:s') : null,
            'isUrgent' => $this->isUrgent,
            'attachments' => $this->attachments
        ];
    }
}
