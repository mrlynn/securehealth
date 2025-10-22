<?php

namespace App\Service;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Collection;

class RobustMongoDBService
{
    private string $connectionString;
    private string $databaseName;
    private ?Client $client = null;
    private ?Database $database = null;

    public function __construct(string $connectionString, string $databaseName)
    {
        $this->connectionString = $connectionString;
        $this->databaseName = $databaseName;
    }

    /**
     * Get a MongoDB client with robust connection options
     */
    public function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client($this->connectionString, [
                'retryWrites' => true,
                'readPreference' => 'primary',
                'writeConcern' => ['w' => 'majority', 'j' => true],
                'serverSelectionTimeoutMS' => 30000,
                'connectTimeoutMS' => 30000,
                'socketTimeoutMS' => 30000,
                'maxPoolSize' => 10,
                'minPoolSize' => 1,
                'maxIdleTimeMS' => 30000,
                'waitQueueTimeoutMS' => 5000,
            ]);
        }

        return $this->client;
    }

    /**
     * Get database with retry logic
     */
    public function getDatabase(): Database
    {
        if ($this->database === null) {
            $this->database = $this->getClient()->selectDatabase($this->databaseName);
        }

        return $this->database;
    }

    /**
     * Get collection with retry logic
     */
    public function getCollection(string $collectionName): Collection
    {
        return $this->getDatabase()->selectCollection($collectionName);
    }

    /**
     * Execute operation with retry logic for "not primary" errors
     */
    public function executeWithRetry(callable $operation, int $maxRetries = 3): mixed
    {
        $retryCount = 0;
        $lastException = null;

        while ($retryCount < $maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $retryCount++;
                $lastException = $e;

                if (strpos($e->getMessage(), 'not primary') !== false && $retryCount < $maxRetries) {
                    // Wait before retrying, with exponential backoff
                    $waitTime = min(1000000 * $retryCount, 5000000); // 1s, 2s, 5s max
                    usleep($waitTime);
                    
                    error_log("MongoDB 'not primary' error, retry $retryCount/$maxRetries after {$waitTime}μs");
                    
                    // Force reconnection
                    $this->client = null;
                    $this->database = null;
                } else {
                    break;
                }
            }
        }

        throw $lastException;
    }

    /**
     * Insert document with retry logic
     */
    public function insertOne(string $collectionName, array $document): \MongoDB\InsertOneResult
    {
        return $this->executeWithRetry(function() use ($collectionName, $document) {
            $collection = $this->getCollection($collectionName);
            return $collection->insertOne($document);
        });
    }

    /**
     * Insert multiple documents with retry logic
     */
    public function insertMany(string $collectionName, array $documents): \MongoDB\InsertManyResult
    {
        return $this->executeWithRetry(function() use ($collectionName, $documents) {
            $collection = $this->getCollection($collectionName);
            return $collection->insertMany($documents);
        });
    }

    /**
     * Update document with retry logic
     */
    public function updateOne(string $collectionName, array $filter, array $update): \MongoDB\UpdateResult
    {
        return $this->executeWithRetry(function() use ($collectionName, $filter, $update) {
            $collection = $this->getCollection($collectionName);
            return $collection->updateOne($filter, $update);
        });
    }

    /**
     * Find documents with retry logic
     */
    public function find(string $collectionName, array $filter = [], array $options = []): \MongoDB\Driver\Cursor
    {
        return $this->executeWithRetry(function() use ($collectionName, $filter, $options) {
            $collection = $this->getCollection($collectionName);
            return $collection->find($filter, $options);
        });
    }
}
