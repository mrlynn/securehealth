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
        string $databaseName = 'securehealth',
        string $collectionName = 'patients'
    ) {
        $this->mongoClient = $mongoClient;
        $this->encryptionService = $encryptionService;
        $this->databaseName = $databaseName;
        $this->collectionName = $collectionName;
        $this->collection = $this->mongoClient->selectCollection($databaseName, $collectionName);
        
        // Set the encryption service for Patient document decryption
        \App\Document\Patient::setEncryptionService($encryptionService);
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
     * Find a patient by email
     */
    public function findOneByEmail(string $email): ?Patient
    {
        // Encrypt the email for searching
        $encryptedEmail = $this->encryptionService->encrypt('patient', 'email', $email);
        
        $document = $this->collection->findOne(['email' => $encryptedEmail]);
        
        if (!$document) {
            return null;
        }
        
        return Patient::fromDocument((array) $document, $this->encryptionService);
    }

    /**
     * Find a patient by ID (generic find method)
     */
    public function find($id): ?Patient
    {
        if (is_string($id)) {
            $id = new ObjectId($id);
        }
        
        return $this->findById($id);
    }

    /**
     * Find a patient by ID (string version)
     */
    public function findByIdString(string $id): ?Patient
    {
        try {
            $objectId = new ObjectId($id);
            return $this->findById($objectId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find all patients
     */
    public function findAll(): array
    {
        $cursor = $this->collection->find([]);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by date range (created between dates)
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        $startDateUtc = new \MongoDB\BSON\UTCDateTime($startDate);
        $endDateUtc = new \MongoDB\BSON\UTCDateTime($endDate);
        
        $cursor = $this->collection->find([
            'createdAt' => [
                '$gte' => $startDateUtc,
                '$lte' => $endDateUtc
            ]
        ]);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by primary doctor ID
     */
    public function findByPrimaryDoctorId($doctorId): array
    {
        // Convert string ID to ObjectId if needed
        if (is_string($doctorId)) {
            $doctorId = new ObjectId($doctorId);
        }
        
        $cursor = $this->collection->find(['primaryDoctorId' => $doctorId]);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by criteria (simple version)
     */
    public function findBy(array $criteria): array
    {
        $cursor = $this->collection->find($criteria);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
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
     * Count all patients
     */
    public function count(array $criteria = []): int
    {
        try {
            return $this->collection->countDocuments($criteria);
        } catch (\Exception $e) {
            // MongoDB connection failed, return 0
            return 0;
        }
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
     * Find patients by name (first and/or last name)
     */
    public function findByName(string $firstName = '', string $lastName = ''): array
    {
        $query = [];
        
        if (!empty($firstName)) {
            $encryptedFirstName = $this->encryptionService->encrypt('patient', 'firstName', $firstName);
            $query['firstName'] = $encryptedFirstName;
        }
        
        if (!empty($lastName)) {
            $encryptedLastName = $this->encryptionService->encrypt('patient', 'lastName', $lastName);
            $query['lastName'] = $encryptedLastName;
        }
        
        if (empty($query)) {
            return [];
        }
        
        $cursor = $this->collection->find($query);
        
        $patients = [];
        foreach ($cursor as $document) {
            $patients[] = Patient::fromDocument((array) $document, $this->encryptionService);
        }
        
        return $patients;
    }

    /**
     * Find patients by medical condition/diagnosis
     */
    public function findByCondition(string $condition): array
    {
        // Encrypt the condition for searching
        $encryptedCondition = $this->encryptionService->encrypt('patient', 'diagnosis', $condition);
        
        $cursor = $this->collection->find(['diagnosis' => $encryptedCondition]);
        
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
     * Find patients by range criteria (deterministic encryption workaround)
     * Since birthDate uses deterministic encryption, we need to convert range queries
     * into multiple exact matches or use a different approach
     */
    public function findByRangeCriteria(array $criteria, MongoDBEncryptionService $encryptionService): array
    {
        // For deterministic encryption, we can't do true range queries
        // Instead, we'll fetch all patients and filter them in PHP
        // This is not ideal for large datasets but works for demo purposes
        
        $allPatients = [];
        $cursor = $this->collection->find([]);
        
        foreach ($cursor as $document) {
            $allPatients[] = Patient::fromDocument((array) $document, $encryptionService);
        }
        
        // Filter patients based on criteria
        $filteredPatients = [];
        
        foreach ($allPatients as $patient) {
            $matches = true;
            
            // Handle birth date range
            if (isset($criteria['birthDateFrom']) || isset($criteria['birthDateTo'])) {
                $patientBirthDate = $patient->getBirthDate()->toDateTime();
                
                if (isset($criteria['birthDateFrom'])) {
                    $fromDate = new \DateTime($criteria['birthDateFrom'] . ' 00:00:00');
                    if ($patientBirthDate < $fromDate) {
                        $matches = false;
                    }
                }
                
                if (isset($criteria['birthDateTo'])) {
                    $toDate = new \DateTime($criteria['birthDateTo'] . ' 23:59:59');
                    if ($patientBirthDate > $toDate) {
                        $matches = false;
                    }
                }
            }
            
            // Handle age range (convert to birth date range)
            if (isset($criteria['minAge']) || isset($criteria['maxAge'])) {
                $today = new \DateTime();
                $patientBirthDate = $patient->getBirthDate()->toDateTime();
                $patientAge = $today->diff($patientBirthDate)->y;
                
                if (isset($criteria['minAge']) && $patientAge < $criteria['minAge']) {
                    $matches = false;
                }
                
                if (isset($criteria['maxAge']) && $patientAge > $criteria['maxAge']) {
                    $matches = false;
                }
            }
            
            if ($matches) {
                $filteredPatients[] = $patient;
            }
        }
        
        // Debug logging
        error_log("Range search criteria: " . json_encode($criteria, JSON_PRETTY_PRINT));
        error_log("Total patients in database: " . count($allPatients));
        error_log("Filtered patients: " . count($filteredPatients));
        
        return $filteredPatients;
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
            // Check if this is an email domain search vs full email search
            if (!str_contains($criteria['email'], '@')) {
                // This is a domain search - we can't handle this with deterministic encryption
                // The frontend fallback will handle this case with proper domain matching
                // Skip this filter for the backend query
            } else {
                // This is a full email search
                $query['email'] = $encryptionService->encrypt('patient', 'email', $criteria['email']);
            }
        }
        
        // Range encryption fields
        if (isset($criteria['minAge'])) {
            $today = new \DateTime();
            // For minAge (e.g., 50), we want patients who are AT LEAST 50 years old
            // So their birth date should be AT MOST 50 years ago (newer birth dates)
            $newestBirthDate = clone $today;
            $newestBirthDate->modify('-' . $criteria['minAge'] . ' years');
            $newestBirthDateUtc = new \MongoDB\BSON\UTCDateTime($newestBirthDate);
            
            $query['birthDate'] = [
                '$lte' => $encryptionService->encrypt('patient', 'birthDate', $newestBirthDateUtc)
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
            'indexes' => iterator_to_array($this->collection->listIndexes()),
            'collectionStats' => iterator_to_array($this->collection->aggregate([
                ['$collStats' => ['storageStats' => []]]
            ]))
        ];
    }
}