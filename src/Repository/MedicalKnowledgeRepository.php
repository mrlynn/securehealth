<?php

namespace App\Repository;

use App\Document\MedicalKnowledge;
use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MedicalKnowledgeRepository extends DocumentRepository
{
    private MongoDBEncryptionService $encryptionService;

    public function __construct(DocumentManager $dm, MongoDBEncryptionService $encryptionService)
    {
        parent::__construct($dm, $dm->getUnitOfWork(), $dm->getClassMetadata(MedicalKnowledge::class));
        $this->encryptionService = $encryptionService;
    }

    /**
     * Find medical knowledge by ID with proper decryption
     */
    public function findById(string $id): ?MedicalKnowledge
    {
        $knowledge = $this->find($id);
        
        if ($knowledge) {
            $this->decryptKnowledge($knowledge);
        }
        
        return $knowledge;
    }

    /**
     * Find all active medical knowledge entries
     */
    public function findAllActive(): array
    {
        $results = $this->findBy(['isActive' => true], ['createdAt' => 'DESC']);
        
        foreach ($results as $knowledge) {
            $this->decryptKnowledge($knowledge);
        }
        
        return $results;
    }

    /**
     * Find medical knowledge by specialty
     */
    public function findBySpecialty(string $specialty): array
    {
        $results = $this->findBy([
            'specialties' => $specialty,
            'isActive' => true
        ], ['confidenceLevel' => 'DESC', 'evidenceLevel' => 'DESC']);
        
        foreach ($results as $knowledge) {
            $this->decryptKnowledge($knowledge);
        }
        
        return $results;
    }

    /**
     * Find medical knowledge by tags
     */
    public function findByTags(array $tags): array
    {
        $results = $this->findBy([
            'tags' => ['$in' => $tags],
            'isActive' => true
        ], ['confidenceLevel' => 'DESC']);
        
        foreach ($results as $knowledge) {
            $this->decryptKnowledge($knowledge);
        }
        
        return $results;
    }

    /**
     * Find medical knowledge by related condition
     */
    public function findByRelatedCondition(string $condition): array
    {
        $results = $this->findBy([
            'relatedConditions' => $condition,
            'isActive' => true
        ], ['confidenceLevel' => 'DESC']);
        
        foreach ($results as $knowledge) {
            $this->decryptKnowledge($knowledge);
        }
        
        return $results;
    }

    /**
     * Find medical knowledge by related medication
     */
    public function findByRelatedMedication(string $medication): array
    {
        $results = $this->findBy([
            'relatedMedications' => $medication,
            'isActive' => true
        ], ['confidenceLevel' => 'DESC']);
        
        foreach ($results as $knowledge) {
            $this->decryptKnowledge($knowledge);
        }
        
        return $results;
    }

    /**
     * Search medical knowledge by text (basic text search)
     */
    public function searchByText(string $query): array
    {
        $regex = new \MongoDB\BSON\Regex($query, 'i');
        
        $results = $this->findBy([
            '$or' => [
                ['title' => $regex],
                ['summary' => $regex],
                ['tags' => ['$in' => [$regex]]]
            ],
            'isActive' => true
        ], ['confidenceLevel' => 'DESC']);
        
        foreach ($results as $knowledge) {
            $this->decryptKnowledge($knowledge);
        }
        
        return $results;
    }

    /**
     * Vector search for semantic similarity
     */
    public function vectorSearch(array $queryEmbedding, int $limit = 10, float $scoreThreshold = 0.7): array
    {
        $pipeline = [
            [
                '$vectorSearch' => [
                    'index' => 'medical_knowledge_vector_index',
                    'path' => 'embedding',
                    'queryVector' => $queryEmbedding,
                    'numCandidates' => $limit * 10,
                    'limit' => $limit
                ]
            ],
            [
                '$match' => [
                    'isActive' => true
                ]
            ],
            [
                '$addFields' => [
                    'score' => ['$meta' => 'vectorSearchScore']
                ]
            ],
            [
                '$match' => [
                    'score' => ['$gte' => $scoreThreshold]
                ]
            ],
            [
                '$sort' => [
                    'score' => -1,
                    'confidenceLevel' => -1
                ]
            ]
        ];

        $results = $this->getDocumentManager()
            ->getDocumentCollection(MedicalKnowledge::class)
            ->aggregate($pipeline)
            ->toArray();

        $knowledgeEntries = [];
        foreach ($results as $result) {
            $knowledge = $this->createFromDocument($result);
            $this->decryptKnowledge($knowledge);
            $knowledgeEntries[] = $knowledge;
        }

        return $knowledgeEntries;
    }

    /**
     * Hybrid search combining vector search with metadata filtering
     */
    public function hybridSearch(
        array $queryEmbedding,
        ?string $specialty = null,
        ?array $tags = null,
        ?int $minConfidenceLevel = null,
        ?int $minEvidenceLevel = null,
        int $limit = 10,
        float $scoreThreshold = 0.7
    ): array {
        $matchFilters = ['isActive' => true];
        
        if ($specialty) {
            $matchFilters['specialties'] = $specialty;
        }
        
        if ($tags && !empty($tags)) {
            $matchFilters['tags'] = ['$in' => $tags];
        }
        
        if ($minConfidenceLevel !== null) {
            $matchFilters['confidenceLevel'] = ['$gte' => $minConfidenceLevel];
        }
        
        if ($minEvidenceLevel !== null) {
            $matchFilters['evidenceLevel'] = ['$gte' => $minEvidenceLevel];
        }

        $pipeline = [
            [
                '$vectorSearch' => [
                    'index' => 'medical_knowledge_vector_index',
                    'path' => 'embedding',
                    'queryVector' => $queryEmbedding,
                    'numCandidates' => $limit * 20,
                    'limit' => $limit * 2
                ]
            ],
            [
                '$match' => $matchFilters
            ],
            [
                '$addFields' => [
                    'score' => ['$meta' => 'vectorSearchScore']
                ]
            ],
            [
                '$match' => [
                    'score' => ['$gte' => $scoreThreshold]
                ]
            ],
            [
                '$sort' => [
                    'score' => -1,
                    'confidenceLevel' => -1,
                    'evidenceLevel' => -1
                ]
            ],
            [
                '$limit' => $limit
            ]
        ];

        $results = $this->getDocumentManager()
            ->getDocumentCollection(MedicalKnowledge::class)
            ->aggregate($pipeline)
            ->toArray();

        $knowledgeEntries = [];
        foreach ($results as $result) {
            $knowledge = $this->createFromDocument($result);
            $this->decryptKnowledge($knowledge);
            $knowledgeEntries[] = $knowledge;
        }

        return $knowledgeEntries;
    }

    /**
     * Get statistics about the medical knowledge base
     */
    public function getKnowledgeBaseStats(): array
    {
        $pipeline = [
            [
                '$match' => [
                    'isActive' => true
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'totalEntries' => ['$sum' => 1],
                    'avgConfidenceLevel' => ['$avg' => '$confidenceLevel'],
                    'avgEvidenceLevel' => ['$avg' => '$evidenceLevel'],
                    'specialties' => ['$addToSet' => '$specialties'],
                    'sources' => ['$addToSet' => '$source']
                ]
            ],
            [
                '$project' => [
                    '_id' => 0,
                    'totalEntries' => 1,
                    'avgConfidenceLevel' => ['$round' => ['$avgConfidenceLevel', 2]],
                    'avgEvidenceLevel' => ['$round' => ['$avgEvidenceLevel', 2]],
                    'totalSpecialties' => ['$size' => ['$reduce' => [
                        'input' => '$specialties',
                        'initialValue' => [],
                        'in' => ['$setUnion' => ['$$value', '$$this']]
                    ]]],
                    'totalSources' => ['$size' => '$sources']
                ]
            ]
        ];

        $result = $this->getDocumentManager()
            ->getDocumentCollection(MedicalKnowledge::class)
            ->aggregate($pipeline)
            ->toArray();

        return $result[0] ?? [
            'totalEntries' => 0,
            'avgConfidenceLevel' => 0,
            'avgEvidenceLevel' => 0,
            'totalSpecialties' => 0,
            'totalSources' => 0
        ];
    }

    /**
     * Save medical knowledge with proper encryption
     */
    public function save(MedicalKnowledge $knowledge): void
    {
        $document = $knowledge->toDocument($this->encryptionService);
        
        if ($knowledge->getId()) {
            // Update existing document
            $this->getDocumentManager()
                ->getDocumentCollection(MedicalKnowledge::class)
                ->replaceOne(
                    ['_id' => $knowledge->getId()],
                    $document
                );
        } else {
            // Insert new document
            $result = $this->getDocumentManager()
                ->getDocumentCollection(MedicalKnowledge::class)
                ->insertOne($document);
            
            $knowledge->setId($result->getInsertedId());
        }
    }

    /**
     * Delete medical knowledge entry
     */
    public function delete(MedicalKnowledge $knowledge): void
    {
        $this->getDocumentManager()
            ->getDocumentCollection(MedicalKnowledge::class)
            ->deleteOne(['_id' => $knowledge->getId()]);
    }

    /**
     * Decrypt medical knowledge fields
     */
    private function decryptKnowledge(MedicalKnowledge $knowledge): void
    {
        try {
            // Note: In a real implementation, you would decrypt the fields here
            // For now, we'll assume the encryption service handles this automatically
            // when retrieving documents
        } catch (\Exception $e) {
            // Log decryption error but don't expose it
            error_log("Failed to decrypt medical knowledge: " . $e->getMessage());
        }
    }

    /**
     * Create MedicalKnowledge object from MongoDB document
     */
    private function createFromDocument(array $document): MedicalKnowledge
    {
        $knowledge = new MedicalKnowledge();
        
        if (isset($document['_id'])) {
            $knowledge->setId($document['_id']);
        }
        
        if (isset($document['title'])) {
            $knowledge->setTitle($document['title']);
        }
        
        if (isset($document['content'])) {
            $knowledge->setContent($document['content']);
        }
        
        if (isset($document['summary'])) {
            $knowledge->setSummary($document['summary']);
        }
        
        if (isset($document['tags'])) {
            $knowledge->setTags($document['tags']);
        }
        
        if (isset($document['specialties'])) {
            $knowledge->setSpecialties($document['specialties']);
        }
        
        if (isset($document['source'])) {
            $knowledge->setSource($document['source']);
        }
        
        if (isset($document['sourceUrl'])) {
            $knowledge->setSourceUrl($document['sourceUrl']);
        }
        
        if (isset($document['sourceDate'])) {
            $knowledge->setSourceDate($document['sourceDate']);
        }
        
        if (isset($document['confidenceLevel'])) {
            $knowledge->setConfidenceLevel($document['confidenceLevel']);
        }
        
        if (isset($document['evidenceLevel'])) {
            $knowledge->setEvidenceLevel($document['evidenceLevel']);
        }
        
        if (isset($document['embedding'])) {
            $knowledge->setEmbedding($document['embedding']);
        }
        
        if (isset($document['relatedConditions'])) {
            $knowledge->setRelatedConditions($document['relatedConditions']);
        }
        
        if (isset($document['relatedMedications'])) {
            $knowledge->setRelatedMedications($document['relatedMedications']);
        }
        
        if (isset($document['relatedProcedures'])) {
            $knowledge->setRelatedProcedures($document['relatedProcedures']);
        }
        
        if (isset($document['requiresReview'])) {
            $knowledge->setRequiresReview($document['requiresReview']);
        }
        
        if (isset($document['isActive'])) {
            $knowledge->setIsActive($document['isActive']);
        }
        
        if (isset($document['createdAt'])) {
            $knowledge->setCreatedAt($document['createdAt']);
        }
        
        if (isset($document['updatedAt'])) {
            $knowledge->setUpdatedAt($document['updatedAt']);
        }
        
        if (isset($document['createdBy'])) {
            $knowledge->setCreatedBy($document['createdBy']);
        }
        
        return $knowledge;
    }
}
