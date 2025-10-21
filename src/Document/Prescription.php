<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

#[ODM\Document(collection: "prescriptions")]
class Prescription
{
    #[ODM\Id]
    private ?ObjectId $id = null;

    #[ODM\Field(type: "string")]
    private string $patientId;

    #[ODM\Field(type: "string")]
    private string $providerId;

    #[ODM\Field(type: "string")]
    private string $providerName;

    #[ODM\Field(type: "string")]
    private string $medicationName;

    #[ODM\Field(type: "string")]
    private string $dosage;

    #[ODM\Field(type: "string")]
    private string $frequency;

    #[ODM\Field(type: "string")]
    private string $route; // e.g., "oral", "topical", "injection"

    #[ODM\Field(type: "int")]
    private int $quantity;

    #[ODM\Field(type: "int")]
    private int $refillsAllowed;

    #[ODM\Field(type: "int")]
    private int $refillsUsed = 0;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $instructions = null;

    #[ODM\Field(type: "string", nullable: true)]
    private ?string $pharmacy = null;

    #[ODM\Field(type: "string")]
    private string $status = 'active'; // active, completed, cancelled, expired

    #[ODM\Field(type: "date")]
    private UTCDateTime $prescribedDate;

    #[ODM\Field(type: "date", nullable: true)]
    private ?UTCDateTime $startDate = null;

    #[ODM\Field(type: "date", nullable: true)]
    private ?UTCDateTime $endDate = null;

    #[ODM\Field(type: "date")]
    private UTCDateTime $createdAt;

    #[ODM\Field(type: "date")]
    private UTCDateTime $updatedAt;

    #[ODM\Field(type: "collection", nullable: true)]
    private ?array $sideEffects = null;

    #[ODM\Field(type: "collection", nullable: true)]
    private ?array $interactions = null;

    #[ODM\Field(type: "bool")]
    private bool $isEncrypted = false;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
        $this->updatedAt = new UTCDateTime();
        $this->prescribedDate = new UTCDateTime();
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

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function setProviderId(string $providerId): self
    {
        $this->providerId = $providerId;
        return $this;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): self
    {
        $this->providerName = $providerName;
        return $this;
    }

    public function getMedicationName(): string
    {
        return $this->medicationName;
    }

    public function setMedicationName(string $medicationName): self
    {
        $this->medicationName = $medicationName;
        return $this;
    }

    public function getDosage(): string
    {
        return $this->dosage;
    }

    public function setDosage(string $dosage): self
    {
        $this->dosage = $dosage;
        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function setRoute(string $route): self
    {
        $this->route = $route;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getRefillsAllowed(): int
    {
        return $this->refillsAllowed;
    }

    public function setRefillsAllowed(int $refillsAllowed): self
    {
        $this->refillsAllowed = $refillsAllowed;
        return $this;
    }

    public function getRefillsUsed(): int
    {
        return $this->refillsUsed;
    }

    public function setRefillsUsed(int $refillsUsed): self
    {
        $this->refillsUsed = $refillsUsed;
        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): self
    {
        $this->instructions = $instructions;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPrescribedDate(): UTCDateTime
    {
        return $this->prescribedDate;
    }

    public function setPrescribedDate(UTCDateTime $prescribedDate): self
    {
        $this->prescribedDate = $prescribedDate;
        return $this;
    }

    public function getStartDate(): ?UTCDateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?UTCDateTime $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?UTCDateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?UTCDateTime $endDate): self
    {
        $this->endDate = $endDate;
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

    public function getSideEffects(): ?array
    {
        return $this->sideEffects;
    }

    public function setSideEffects(?array $sideEffects): self
    {
        $this->sideEffects = $sideEffects;
        return $this;
    }

    public function getInteractions(): ?array
    {
        return $this->interactions;
    }

    public function setInteractions(?array $interactions): self
    {
        $this->interactions = $interactions;
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
            'providerId' => $this->providerId,
            'providerName' => $this->providerName,
            'medicationName' => $this->medicationName,
            'dosage' => $this->dosage,
            'frequency' => $this->frequency,
            'route' => $this->route,
            'quantity' => $this->quantity,
            'refillsAllowed' => $this->refillsAllowed,
            'refillsUsed' => $this->refillsUsed,
            'instructions' => $this->instructions,
            'pharmacy' => $this->pharmacy,
            'status' => $this->status,
            'prescribedDate' => $this->prescribedDate ? $this->prescribedDate->toDateTime()->format('Y-m-d H:i:s') : null,
            'startDate' => $this->startDate ? $this->startDate->toDateTime()->format('Y-m-d H:i:s') : null,
            'endDate' => $this->endDate ? $this->endDate->toDateTime()->format('Y-m-d H:i:s') : null,
            'createdAt' => $this->createdAt ? $this->createdAt->toDateTime()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $this->updatedAt ? $this->updatedAt->toDateTime()->format('Y-m-d H:i:s') : null,
            'sideEffects' => $this->sideEffects,
            'interactions' => $this->interactions,
            'isEncrypted' => $this->isEncrypted
        ];
    }
}
