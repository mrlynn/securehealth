<?php

namespace App\Document;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class KnowledgeBase
{
    private ?ObjectId $id = null;
    private string $title;
    private string $content;
    private array $embedding;
    private string $category;
    private array $metadata = [];
    private ?string $sourceFile = null;
    private UTCDateTime $createdAt;
    private UTCDateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
        $this->updatedAt = new UTCDateTime();
    }

    public function getId(): ?ObjectId
    {
        return $this->id;
    }

    public function setId(?ObjectId $id): self
    {
        $this->id = $id;
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    public function setEmbedding(array $embedding): self
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
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

    public function getSourceFile(): ?string
    {
        return $this->sourceFile;
    }

    public function setSourceFile(?string $sourceFile): self
    {
        $this->sourceFile = $sourceFile;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id ? (string)$this->id : null,
            'title' => $this->title,
            'content' => $this->content,
            'category' => $this->category,
            'metadata' => $this->metadata,
            'sourceFile' => $this->sourceFile,
            'createdAt' => $this->createdAt->toDateTime()->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->toDateTime()->format('Y-m-d H:i:s')
        ];
    }
}

