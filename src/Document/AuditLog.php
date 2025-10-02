<?php

namespace App\Document;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
class AuditLog
{
    private ?ObjectId $id = null;
    private string $username;
    private string $actionType;
    private string $description;
    private ?string $ipAddress = null;
    private UTCDateTime $timestamp;
    private ?string $entityId = null;
    private ?string $entityType = null;
    private array $metadata = [];
    private ?string $userId = null;
    private ?string $sessionId = null;
    private ?string $status = null;
    private ?string $requestMethod = null;
    private ?string $requestUrl = null;
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->timestamp = new UTCDateTime();
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

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): self
    {
        $this->actionType = $actionType;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getTimestamp(): UTCDateTime
    {
        return $this->timestamp;
    }

    public function setTimestamp(UTCDateTime $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }
    
    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }
    
    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
    
    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
    
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }
    
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }
    
    public function getRequestMethod(): ?string
    {
        return $this->requestMethod;
    }

    public function setRequestMethod(?string $requestMethod): self
    {
        $this->requestMethod = $requestMethod;
        return $this;
    }
    
    public function getRequestUrl(): ?string
    {
        return $this->requestUrl;
    }

    public function setRequestUrl(?string $requestUrl): self
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }
    
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }
    
    /**
     * Convert AuditLog to array representation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id ? (string)$this->id : null,
            'username' => $this->username,
            'actionType' => $this->actionType,
            'description' => $this->description,
            'timestamp' => $this->timestamp->toDateTime()->format('Y-m-d H:i:s'),
            'ipAddress' => $this->ipAddress,
            'entityId' => $this->entityId,
            'entityType' => $this->entityType,
            'userId' => $this->userId,
            'sessionId' => $this->sessionId,
            'status' => $this->status,
            'requestMethod' => $this->requestMethod,
            'requestUrl' => $this->requestUrl,
            'userAgent' => $this->userAgent,
            'metadata' => $this->metadata
        ];
    }
}