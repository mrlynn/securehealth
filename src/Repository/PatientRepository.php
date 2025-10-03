<?php

namespace App\Repository;

use App\Document\Patient;
use App\Service\MongoDBEncryptionService;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;

class PatientRepository
{
    private Client $mongoClient;
    private MongoDBEncryptionService $encryptionService;
    private string $databaseName;
    private string $collectionName;
    private Collection $collection;

    public function __construct(
        Client $mongoClient, 
        MongoDBEncryptionService $encryptionService,
        string $databaseName = 'secure_health',
        string $collectionName = 'patient'
    ) {
        $this->mongoClient = $mongoClient;
        $this->encryptionService = $encryptionService;
        $this->databaseName = $databaseName;
        $this->collectionName = $collectionName;
        $this->collection = $this->mongoClient->selectCollection($databaseName, $collectionName);
    }

    /**
     * Find a patient by ID
     */
    public function findById(ObjectId $id): ?Patient
    {
        $document = $this->collection->findOne(['_id' => $id]);
        
        if (!$document) {
            return null;
        }
        
        return Patient::fromDocument((array) $document, $this->encryptionService);
    }

    /**
     * Find patients by criteria with pagination
     */
    public function findByCriteria(array $criteria, int $page = 1, int $limit = 20): array
    {
        $skip = ($page - 1) * $limit;
        $options = [
            'limit' => $limit,
            'skip' => $skip,
            'sort' => ['lastName' => 1]
        ];
        
        $cursor = $this->collection->find($criteria, $options);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
    }

    /**
     * Count patients matching criteria
     */
    public function countByCriteria(array $criteria): int
    {
        return $this->collection->countDocuments($criteria);
    }

    /**
     * Clear all patients from the collection
     */
    public function clearAll(): void
    {
        $this->collection->deleteMany([]);
    }

    /**
     * Save a patient
     */
    public function save(Patient $patient): void
    {
        $document = $patient->toDocument($this->encryptionService);
        
        if ($patient->getId()) {
            $this->collection->updateOne(
                ['_id' => $patient->getId()],
                ['$set' => $document]
            );
        } else {
            $result = $this->collection->insertOne($document);
            if ($result->getInsertedId()) {
                $patient->setId($result->getInsertedId());
            }
        }
    }

    /**
     * Delete a patient
     */
    public function delete(Patient $patient): void
    {
        if ($patient->getId()) {
            $this->collection->deleteOne(['_id' => $patient->getId()]);
        }
    }

    /**
     * Find patients by lastname (exact match)
     * Note: For encrypted fields, only exact matches are supported
     */
    public function findByLastName(string $lastName): array
    {
        // Encrypt the lastname for searching
        $encryptedLastName = $this->encryptionService->encrypt('patient', 'lastName', $lastName);
        
        $cursor = $this->collection->find(['lastName' => $encryptedLastName]);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by birthDate range
     * Note: This uses range encryption
     */
    public function findByBirthDateRange(\DateTime $fromDate, \DateTime $toDate): array
    {
        $fromDateUtc = new \MongoDB\BSON\UTCDateTime($fromDate);
        $toDateUtc = new \MongoDB\BSON\UTCDateTime($toDate);
        
        // Encrypt the dates for range query
        $encryptedFromDate = $this->encryptionService->encrypt('patient', 'birthDate', $fromDateUtc);
        $encryptedToDate = $this->encryptionService->encrypt('patient', 'birthDate', $toDateUtc);
        
        $cursor = $this->collection->find([
            'birthDate' => [
                '$gte' => $encryptedFromDate,
                '$lte' => $encryptedToDate
            ]
        ]);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by equality criteria (deterministic encryption)
     * Supports exact matches on encrypted fields
     */
    public function findByEqualityCriteria(array $criteria, MongoDBEncryptionService $encryptionService): array
    {
        $query = [];
        
        // Build encrypted query criteria
        if (isset($criteria['lastName'])) {
            $query['lastName'] = $encryptionService->encrypt('patient', 'lastName', $criteria['lastName']);
        }
        
        if (isset($criteria['firstName'])) {
            $query['firstName'] = $encryptionService->encrypt('patient', 'firstName', $criteria['firstName']);
        }
        
        if (isset($criteria['email'])) {
            $query['email'] = $encryptionService->encrypt('patient', 'email', $criteria['email']);
        }
        
        if (isset($criteria['phone'])) {
            $query['phoneNumber'] = $encryptionService->encrypt('patient', 'phoneNumber', $criteria['phone']);
        }
        
        if (empty($query)) {
            return [];
        }
        
        $cursor = $this->collection->find($query);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by range criteria (range encryption)
     * Supports range queries on encrypted date fields
     */
    public function findByRangeCriteria(array $criteria, MongoDBEncryptionService $encryptionService): array
    {
        $query = [];
        
        // Handle birth date range
        if (isset($criteria['birthDateFrom']) || isset($criteria['birthDateTo'])) {
            $birthDateQuery = [];
            
            if (isset($criteria['birthDateFrom'])) {
                $fromDate = new \DateTime($criteria['birthDateFrom']);
                $fromDateUtc = new \MongoDB\BSON\UTCDateTime($fromDate);
                $birthDateQuery['$gte'] = $encryptionService->encrypt('patient', 'birthDate', $fromDateUtc);
            }
            
            if (isset($criteria['birthDateTo'])) {
                $toDate = new \DateTime($criteria['birthDateTo']);
                $toDateUtc = new \MongoDB\BSON\UTCDateTime($toDate);
                $birthDateQuery['$lte'] = $encryptionService->encrypt('patient', 'birthDate', $toDateUtc);
            }
            
            $query['birthDate'] = $birthDateQuery;
        }
        
        // Handle age range (convert to birth date range)
        if (isset($criteria['minAge']) || isset($criteria['maxAge'])) {
            $today = new \DateTime();
            
            if (isset($criteria['maxAge'])) {
                $minBirthDate = clone $today;
                $minBirthDate->modify('-' . $criteria['maxAge'] . ' years');
                $minBirthDateUtc = new \MongoDB\BSON\UTCDateTime($minBirthDate);
                
                if (!isset($query['birthDate'])) {
                    $query['birthDate'] = [];
                }
                $query['birthDate']['$lte'] = $encryptionService->encrypt('patient', 'birthDate', $minBirthDateUtc);
            }
            
            if (isset($criteria['minAge'])) {
                $maxBirthDate = clone $today;
                $maxBirthDate->modify('-' . $criteria['minAge'] . ' years');
                $maxBirthDateUtc = new \MongoDB\BSON\UTCDateTime($maxBirthDate);
                
                if (!isset($query['birthDate'])) {
                    $query['birthDate'] = [];
                }
                $query['birthDate']['$gte'] = $encryptionService->encrypt('patient', 'birthDate', $maxBirthDateUtc);
            }
        }
        
        if (empty($query)) {
            return [];
        }
        
        $cursor = $this->collection->find($query);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by complex criteria (multiple encryption types)
     * Combines deterministic and range encryption in a single query
     */
    public function findByComplexCriteria(array $criteria, MongoDBEncryptionService $encryptionService): array
    {
        $query = [];
        
        // Deterministic encryption fields (exact matches)
        if (isset($criteria['lastName'])) {
            // For complex search, we might want partial matches
            // Since we're using deterministic encryption, we need exact matches
            // In a real implementation, you might want to use regex or other techniques
            $query['lastName'] = $encryptionService->encrypt('patient', 'lastName', $criteria['lastName']);
        }
        
        if (isset($criteria['email'])) {
            $query['email'] = $encryptionService->encrypt('patient', 'email', $criteria['email']);
        }
        
        // Range encryption fields
        if (isset($criteria['minAge'])) {
            $today = new \DateTime();
            $maxBirthDate = clone $today;
            $maxBirthDate->modify('-' . $criteria['minAge'] . ' years');
            $maxBirthDateUtc = new \MongoDB\BSON\UTCDateTime($maxBirthDate);
            
            $query['birthDate'] = [
                '$gte' => $encryptionService->encrypt('patient', 'birthDate', $maxBirthDateUtc)
            ];
        }
        
        if (isset($criteria['birthYear'])) {
            $yearStart = new \DateTime($criteria['birthYear'] . '-01-01');
            $yearEnd = new \DateTime($criteria['birthYear'] . '-12-31');
            $yearStartUtc = new \MongoDB\BSON\UTCDateTime($yearStart);
            $yearEndUtc = new \MongoDB\BSON\UTCDateTime($yearEnd);
            
            $query['birthDate'] = [
                '$gte' => $encryptionService->encrypt('patient', 'birthDate', $yearStartUtc),
                '$lte' => $encryptionService->encrypt('patient', 'birthDate', $yearEndUtc)
            ];
        }
        
        if (isset($criteria['phonePrefix'])) {
            // For phone prefix search, we need to use regex on the encrypted field
            // This is a limitation of deterministic encryption - we can't do partial matches easily
            // In a real implementation, you might want to store phone prefixes separately
            $query['phoneNumber'] = $encryptionService->encrypt('patient', 'phoneNumber', $criteria['phonePrefix'] . '-');
        }
        
        if (empty($query)) {
            return [];
        }
        
        $cursor = $this->collection->find($query);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $encryptionService);
        }
        
        return $patients;
    }

    /**
     * Get search statistics for monitoring and optimization
     */
    public function getSearchStats(): array
    {
        return [
            'totalPatients' => $this->collection->countDocuments(),
            'encryptedFields' => [
                'deterministic' => ['lastName', 'firstName', 'email', 'phoneNumber'],
                'range' => ['birthDate'],
                'random' => ['ssn', 'diagnosis', 'medications', 'insuranceDetails', 'notes']
            ],
            'indexes' => $this->collection->listIndexes()->toArray(),
            'collectionStats' => $this->collection->aggregate([
                ['$collStats' => ['storageStats' => true]]
            ])->toArray()
        ];
    }
}