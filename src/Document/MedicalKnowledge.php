<?php

namespace App\Document;

use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document(collection: 'medical_knowledge')]
class MedicalKnowledge
{
    #[ODM\Id]
    private $id = null;

    /**
     * Title of the medical knowledge entry
     */
    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank(message: "Title is required")]
    #[Assert\Length(min: 5, max: 200)]
    private string $title;

    /**
     * Main content of the medical knowledge entry
     */
    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank(message: "Content is required")]
    private string $content;

    /**
     * Summary or abstract of the content
     */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $summary = null;

    /**
     * Medical categories and tags
     */
    #[ODM\Field(type: 'collection')]
    private array $tags = [];

    /**
     * Medical specialties this applies to
     */
    #[ODM\Field(type: 'collection')]
    private array $specialties = [];

    /**
     * Source of the medical knowledge
     */
    #[ODM\Field(type: 'string')]
    private string $source;

    /**
     * Source URL or reference
     */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $sourceUrl = null;

    /**
     * Publication or update date of the source
     */
    #[ODM\Field(type: 'date', nullable: true)]
    private $sourceDate = null;

    /**
     * Confidence level of the medical information (1-10)
     */
    #[ODM\Field(type: 'int')]
    #[Assert\Range(min: 1, max: 10)]
    private int $confidenceLevel = 5;

    /**
     * Vector embedding for semantic search
     */
    #[ODM\Field(type: 'collection')]
    private array $embedding = [];

    /**
     * Related medical conditions
     */
    #[ODM\Field(type: 'collection')]
    private array $relatedConditions = [];

    /**
     * Related medications
     */
    #[ODM\Field(type: 'collection')]
    private array $relatedMedications = [];

    /**
     * Related procedures or treatments
     */
    #[ODM\Field(type: 'collection')]
    private array $relatedProcedures = [];

    /**
     * Evidence level (1-5: case reports to systematic reviews)
     */
    #[ODM\Field(type: 'int')]
    #[Assert\Range(min: 1, max: 5)]
    private int $evidenceLevel = 3;

    /**
     * Whether this knowledge requires doctor review
     */
    #[ODM\Field(type: 'bool')]
    private bool $requiresReview = false;

    /**
     * Created timestamp
     */
    #[ODM\Field(type: 'date')]
    private $createdAt;

    /**
     * Last updated timestamp
     */
    #[ODM\Field(type: 'date', nullable: true)]
    private $updatedAt = null;

    /**
     * Created by user ID
     */
    #[ODM\Field(type: 'object_id', nullable: true)]
    private ?ObjectId $createdBy = null;

    /**
     * Whether this entry is active/published
     */
    #[ODM\Field(type: 'bool')]
    private bool $isActive = true;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
        $this->tags = [];
        $this->specialties = [];
        $this->relatedConditions = [];
        $this->relatedMedications = [];
        $this->relatedProcedures = [];
    }

    /**
     * Helper method to format date objects
     */
    private function formatDate($date, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (!$date) {
            return null;
        }
        
        if ($date instanceof \MongoDB\BSON\UTCDateTime) {
            return $date->toDateTime()->format($format);
        } elseif ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        return null;
    }

    /**
     * Convert to array with role-based access control
     */
    public function toArray($userOrRole = null): array
    {
        $data = [
            'id' => (string)$this->getId(),
            'title' => $this->getTitle(),
            'summary' => $this->getSummary(),
            'source' => $this->getSource(),
            'confidenceLevel' => $this->getConfidenceLevel(),
            'evidenceLevel' => $this->getEvidenceLevel(),
            'tags' => $this->getTags(),
            'specialties' => $this->getSpecialties(),
            'createdAt' => $this->formatDate($this->getCreatedAt()),
            'isActive' => $this->getIsActive()
        ];

        // Get roles from user object or string
        $roles = [];
        if ($userOrRole instanceof UserInterface) {
            $roles = $userOrRole->getRoles();
        } elseif (is_string($userOrRole)) {
            $roles = [$userOrRole];
        }

        // Full content access for doctors and admins
        if (in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_ADMIN', $roles)) {
            $data['content'] = $this->getContent();
            $data['sourceUrl'] = $this->getSourceUrl();
            $data['sourceDate'] = $this->formatDate($this->getSourceDate(), 'Y-m-d');
            $data['relatedConditions'] = $this->getRelatedConditions();
            $data['relatedMedications'] = $this->getRelatedMedications();
            $data['relatedProcedures'] = $this->getRelatedProcedures();
            $data['requiresReview'] = $this->getRequiresReview();
            $data['updatedAt'] = $this->formatDate($this->getUpdatedAt());
        }
        // Nurses get limited content access
        elseif (in_array('ROLE_NURSE', $roles)) {
            $data['content'] = $this->getContent();
            $data['relatedConditions'] = $this->getRelatedConditions();
            $data['relatedMedications'] = $this->getRelatedMedications();
        }

        return $data;
    }

    /**
     * Convert to document for MongoDB storage
     */
    public function toDocument(MongoDBEncryptionService $encryptionService): array
    {
        $document = [];

        if ($this->id) {
            $document['_id'] = $this->id;
        }

        // Encrypt sensitive medical content
        $document['title'] = $encryptionService->encrypt('medical_knowledge', 'title', $this->title);
        $document['content'] = $encryptionService->encrypt('medical_knowledge', 'content', $this->content);
        
        if ($this->summary) {
            $document['summary'] = $encryptionService->encrypt('medical_knowledge', 'summary', $this->summary);
        }

        // Store metadata (not encrypted for search purposes)
        $document['tags'] = $this->tags;
        $document['specialties'] = $this->specialties;
        $document['source'] = $this->source;
        
        if ($this->sourceUrl) {
            $document['sourceUrl'] = $this->sourceUrl;
        }
        
        if ($this->sourceDate) {
            $document['sourceDate'] = $this->sourceDate;
        }

        $document['confidenceLevel'] = $this->confidenceLevel;
        $document['evidenceLevel'] = $this->evidenceLevel;
        $document['embedding'] = $this->embedding;
        $document['relatedConditions'] = $this->relatedConditions;
        $document['relatedMedications'] = $this->relatedMedications;
        $document['relatedProcedures'] = $this->relatedProcedures;
        $document['requiresReview'] = $this->requiresReview;
        $document['isActive'] = $this->isActive;
        $document['createdAt'] = $this->createdAt;
        
        if ($this->updatedAt) {
            $document['updatedAt'] = $this->updatedAt;
        }
        
        if ($this->createdBy) {
            $document['createdBy'] = $this->createdBy;
        }

        return $document;
    }

    // Getters and Setters
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

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag(string $tag): self
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function getSpecialties(): array
    {
        return $this->specialties;
    }

    public function setSpecialties(array $specialties): self
    {
        $this->specialties = $specialties;
        return $this;
    }

    public function addSpecialty(string $specialty): self
    {
        if (!in_array($specialty, $this->specialties)) {
            $this->specialties[] = $specialty;
        }
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;
        return $this;
    }

    public function getSourceDate()
    {
        return $this->sourceDate;
    }

    public function setSourceDate($sourceDate): self
    {
        $this->sourceDate = $sourceDate;
        return $this;
    }

    public function getConfidenceLevel(): int
    {
        return $this->confidenceLevel;
    }

    public function setConfidenceLevel(int $confidenceLevel): self
    {
        $this->confidenceLevel = $confidenceLevel;
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

    public function getRelatedConditions(): array
    {
        return $this->relatedConditions;
    }

    public function setRelatedConditions(array $relatedConditions): self
    {
        $this->relatedConditions = $relatedConditions;
        return $this;
    }

    public function addRelatedCondition(string $condition): self
    {
        if (!in_array($condition, $this->relatedConditions)) {
            $this->relatedConditions[] = $condition;
        }
        return $this;
    }

    public function getRelatedMedications(): array
    {
        return $this->relatedMedications;
    }

    public function setRelatedMedications(array $relatedMedications): self
    {
        $this->relatedMedications = $relatedMedications;
        return $this;
    }

    public function addRelatedMedication(string $medication): self
    {
        if (!in_array($medication, $this->relatedMedications)) {
            $this->relatedMedications[] = $medication;
        }
        return $this;
    }

    public function getRelatedProcedures(): array
    {
        return $this->relatedProcedures;
    }

    public function setRelatedProcedures(array $relatedProcedures): self
    {
        $this->relatedProcedures = $relatedProcedures;
        return $this;
    }

    public function addRelatedProcedure(string $procedure): self
    {
        if (!in_array($procedure, $this->relatedProcedures)) {
            $this->relatedProcedures[] = $procedure;
        }
        return $this;
    }

    public function getEvidenceLevel(): int
    {
        return $this->evidenceLevel;
    }

    public function setEvidenceLevel(int $evidenceLevel): self
    {
        $this->evidenceLevel = $evidenceLevel;
        return $this;
    }

    public function getRequiresReview(): bool
    {
        return $this->requiresReview;
    }

    public function setRequiresReview(bool $requiresReview): self
    {
        $this->requiresReview = $requiresReview;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt($updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new UTCDateTime();
        return $this;
    }

    public function getCreatedBy(): ?ObjectId
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?ObjectId $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }
}
