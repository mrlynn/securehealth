<?php

namespace App\Service;

use App\Document\MedicalKnowledge;
use App\Repository\MedicalKnowledgeRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class MedicalKnowledgeService
{
    private MedicalKnowledgeRepository $repository;
    private EmbeddingService $embeddingService;
    private LoggerInterface $logger;

    public function __construct(
        MedicalKnowledgeRepository $repository,
        EmbeddingService $embeddingService,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->embeddingService = $embeddingService;
        $this->logger = $logger;
    }

    /**
     * Create a new medical knowledge entry
     */
    public function createKnowledgeEntry(
        string $title,
        string $content,
        string $source,
        array $tags = [],
        array $specialties = [],
        ?string $summary = null,
        ?string $sourceUrl = null,
        ?UTCDateTime $sourceDate = null,
        int $confidenceLevel = 5,
        int $evidenceLevel = 3,
        array $relatedConditions = [],
        array $relatedMedications = [],
        array $relatedProcedures = [],
        bool $requiresReview = false,
        ?UserInterface $createdBy = null
    ): MedicalKnowledge {
        
        $knowledge = new MedicalKnowledge();
        $knowledge->setTitle($title);
        $knowledge->setContent($content);
        $knowledge->setSummary($summary ?? $this->generateSummary($content));
        $knowledge->setSource($source);
        $knowledge->setSourceUrl($sourceUrl);
        $knowledge->setSourceDate($sourceDate);
        $knowledge->setConfidenceLevel($confidenceLevel);
        $knowledge->setEvidenceLevel($evidenceLevel);
        $knowledge->setTags($tags);
        $knowledge->setSpecialties($specialties);
        $knowledge->setRelatedConditions($relatedConditions);
        $knowledge->setRelatedMedications($relatedMedications);
        $knowledge->setRelatedProcedures($relatedProcedures);
        $knowledge->setRequiresReview($requiresReview);
        
        if ($createdBy) {
            $userId = $createdBy->getUserIdentifier();
            if (is_string($userId) && strlen($userId) === 24) {
                $knowledge->setCreatedBy(new ObjectId($userId));
            }
            // Skip setting createdBy if userId is not a valid ObjectId string
        }

        // Generate embedding for semantic search
        try {
            $embedding = $this->generateEmbedding($title, $content, $summary);
            $knowledge->setEmbedding($embedding);
        } catch (\Exception $e) {
            // If embedding generation fails, use empty array for now
            $this->logger->warning('Failed to generate embedding, using empty array', [
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            $knowledge->setEmbedding([]);
        }

        $this->repository->save($knowledge);

        $this->logger->info('Created new medical knowledge entry', [
            'id' => (string)$knowledge->getId(),
            'title' => $title,
            'source' => $source,
            'createdBy' => $createdBy ? $createdBy->getUserIdentifier() : 'system'
        ]);

        return $knowledge;
    }

    /**
     * Update an existing medical knowledge entry
     */
    public function updateKnowledgeEntry(
        string $id,
        ?string $title = null,
        ?string $content = null,
        ?string $summary = null,
        ?int $confidenceLevel = null,
        ?int $evidenceLevel = null,
        ?array $tags = null,
        ?array $specialties = null,
        ?array $relatedConditions = null,
        ?array $relatedMedications = null,
        ?array $relatedProcedures = null,
        ?bool $requiresReview = null
    ): ?MedicalKnowledge {
        
        $knowledge = $this->repository->findById($id);
        
        if (!$knowledge) {
            return null;
        }

        $updated = false;

        if ($title !== null && $title !== $knowledge->getTitle()) {
            $knowledge->setTitle($title);
            $updated = true;
        }

        if ($content !== null && $content !== $knowledge->getContent()) {
            $knowledge->setContent($content);
            $knowledge->setSummary($summary ?? $this->generateSummary($content));
            $updated = true;
        }

        if ($summary !== null && $summary !== $knowledge->getSummary()) {
            $knowledge->setSummary($summary);
            $updated = true;
        }

        if ($confidenceLevel !== null && $confidenceLevel !== $knowledge->getConfidenceLevel()) {
            $knowledge->setConfidenceLevel($confidenceLevel);
            $updated = true;
        }

        if ($evidenceLevel !== null && $evidenceLevel !== $knowledge->getEvidenceLevel()) {
            $knowledge->setEvidenceLevel($evidenceLevel);
            $updated = true;
        }

        if ($tags !== null) {
            $knowledge->setTags($tags);
            $updated = true;
        }

        if ($specialties !== null) {
            $knowledge->setSpecialties($specialties);
            $updated = true;
        }

        if ($relatedConditions !== null) {
            $knowledge->setRelatedConditions($relatedConditions);
            $updated = true;
        }

        if ($relatedMedications !== null) {
            $knowledge->setRelatedMedications($relatedMedications);
            $updated = true;
        }

        if ($relatedProcedures !== null) {
            $knowledge->setRelatedProcedures($relatedProcedures);
            $updated = true;
        }

        if ($requiresReview !== null && $requiresReview !== $knowledge->getRequiresReview()) {
            $knowledge->setRequiresReview($requiresReview);
            $updated = true;
        }

        if ($updated) {
            // Regenerate embedding if content or title changed
            if (($title !== null && $title !== $knowledge->getTitle()) || 
                ($content !== null && $content !== $knowledge->getContent())) {
                $embedding = $this->generateEmbedding($knowledge->getTitle(), $knowledge->getContent(), $knowledge->getSummary());
                $knowledge->setEmbedding($embedding);
            }

            $knowledge->touchUpdatedAt();
            $this->repository->save($knowledge);

            $this->logger->info('Updated medical knowledge entry', [
                'id' => $id,
                'title' => $knowledge->getTitle()
            ]);
        }

        return $knowledge;
    }

    /**
     * Search medical knowledge using semantic similarity
     */
    public function semanticSearch(
        string $query,
        ?string $specialty = null,
        ?array $tags = null,
        ?int $minConfidenceLevel = null,
        ?int $minEvidenceLevel = null,
        int $limit = 10,
        float $scoreThreshold = 0.7
    ): array {
        
        // Generate embedding for the query
        $queryEmbedding = $this->embeddingService->generateEmbedding($query);

        // Perform hybrid search
        $results = $this->repository->hybridSearch(
            $queryEmbedding,
            $specialty,
            $tags,
            $minConfidenceLevel,
            $minEvidenceLevel,
            $limit,
            $scoreThreshold
        );

        $this->logger->info('Performed semantic search', [
            'query' => $query,
            'specialty' => $specialty,
            'resultsCount' => count($results)
        ]);

        return $results;
    }

    /**
     * Search medical knowledge by text (keyword search)
     */
    public function textSearch(string $query): array
    {
        return $this->repository->searchByText($query);
    }

    /**
     * Get medical knowledge by specialty
     */
    public function getBySpecialty(string $specialty): array
    {
        return $this->repository->findBySpecialty($specialty);
    }

    /**
     * Get medical knowledge by tags
     */
    public function getByTags(array $tags): array
    {
        return $this->repository->findByTags($tags);
    }

    /**
     * Get medical knowledge related to a specific condition
     */
    public function getByCondition(string $condition): array
    {
        return $this->repository->findByRelatedCondition($condition);
    }

    /**
     * Get medical knowledge related to a specific medication
     */
    public function getByMedication(string $medication): array
    {
        return $this->repository->findByRelatedMedication($medication);
    }

    /**
     * Get all active medical knowledge entries
     */
    public function getAllActive(): array
    {
        return $this->repository->findAllActive();
    }

    /**
     * Get a specific medical knowledge entry by ID
     */
    public function getById(string $id): ?MedicalKnowledge
    {
        return $this->repository->findById($id);
    }

    /**
     * Get statistics about the knowledge base
     */
    public function getKnowledgeBaseStats(): array
    {
        return $this->repository->getKnowledgeBaseStats();
    }

    /**
     * Import medical knowledge from external sources
     */
    public function importFromExternalSource(
        string $source,
        array $data,
        ?UserInterface $importedBy = null
    ): array {
        $imported = [];
        $errors = [];

        foreach ($data as $entry) {
            try {
                $knowledge = $this->createKnowledgeEntry(
                    $entry['title'] ?? 'Imported Knowledge',
                    $entry['content'] ?? '',
                    $source,
                    $entry['tags'] ?? [],
                    $entry['specialties'] ?? [],
                    $entry['summary'] ?? null,
                    $entry['sourceUrl'] ?? null,
                    isset($entry['sourceDate']) ? new UTCDateTime(new \DateTime($entry['sourceDate'])) : null,
                    $entry['confidenceLevel'] ?? 3,
                    $entry['evidenceLevel'] ?? 3,
                    $entry['relatedConditions'] ?? [],
                    $entry['relatedMedications'] ?? [],
                    $entry['relatedProcedures'] ?? [],
                    true, // Require review for imported data
                    $importedBy
                );

                $imported[] = $knowledge;
            } catch (\Exception $e) {
                $errors[] = [
                    'entry' => $entry,
                    'error' => $e->getMessage()
                ];
                
                $this->logger->error('Failed to import medical knowledge entry', [
                    'source' => $source,
                    'entry' => $entry,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Completed medical knowledge import', [
            'source' => $source,
            'imported' => count($imported),
            'errors' => count($errors),
            'importedBy' => $importedBy ? $importedBy->getUserIdentifier() : 'system'
        ]);

        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }

    /**
     * Delete medical knowledge entry
     */
    public function deleteKnowledgeEntry(string $id): bool
    {
        $knowledge = $this->repository->findById($id);
        
        if (!$knowledge) {
            return false;
        }

        $this->repository->delete($knowledge);

        $this->logger->info('Deleted medical knowledge entry', [
            'id' => $id,
            'title' => $knowledge->getTitle()
        ]);

        return true;
    }

    /**
     * Get related medical knowledge for a patient
     */
    public function getRelatedKnowledgeForPatient(
        array $patientConditions = [],
        array $patientMedications = [],
        ?string $specialty = null,
        int $limit = 5
    ): array {
        $relatedKnowledge = [];

        // Search by patient conditions
        foreach ($patientConditions as $condition) {
            $conditionKnowledge = $this->getByCondition($condition);
            $relatedKnowledge = array_merge($relatedKnowledge, $conditionKnowledge);
        }

        // Search by patient medications
        foreach ($patientMedications as $medication) {
            $medicationKnowledge = $this->getByMedication($medication);
            $relatedKnowledge = array_merge($relatedKnowledge, $medicationKnowledge);
        }

        // Remove duplicates and sort by confidence level
        $uniqueKnowledge = [];
        $seen = [];
        
        foreach ($relatedKnowledge as $knowledge) {
            $id = (string)$knowledge->getId();
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $uniqueKnowledge[] = $knowledge;
            }
        }

        // Sort by confidence level and evidence level
        usort($uniqueKnowledge, function($a, $b) {
            if ($a->getConfidenceLevel() === $b->getConfidenceLevel()) {
                return $b->getEvidenceLevel() - $a->getEvidenceLevel();
            }
            return $b->getConfidenceLevel() - $a->getConfidenceLevel();
        });

        return array_slice($uniqueKnowledge, 0, $limit);
    }

    /**
     * Generate embedding for medical knowledge
     */
    private function generateEmbedding(string $title, string $content, ?string $summary = null): array
    {
        $text = $title . ' ' . $content;
        if ($summary) {
            $text .= ' ' . $summary;
        }

        return $this->embeddingService->generateEmbedding($text);
    }

    /**
     * Generate a summary from content
     */
    private function generateSummary(string $content): string
    {
        // Simple summary generation - in production, you might use AI for this
        $sentences = preg_split('/[.!?]+/', $content);
        $summary = '';
        $sentenceCount = 0;
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (!empty($sentence) && strlen($sentence) > 20) {
                $summary .= $sentence . '. ';
                $sentenceCount++;
                
                if ($sentenceCount >= 2 || strlen($summary) > 200) {
                    break;
                }
            }
        }
        
        return trim($summary);
    }
}
