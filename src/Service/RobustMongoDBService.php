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
            // Enhanced connection string with retry parameters
            $enhancedConnectionString = $this->connectionString;
            if (strpos($enhancedConnectionString, '?') === false) {
                $enhancedConnectionString .= '?retryWrites=true&w=majority&readPreference=primary&serverSelectionTimeoutMS=30000&connectTimeoutMS=30000&socketTimeoutMS=30000&maxPoolSize=10&minPoolSize=1&maxIdleTimeMS=30000&waitQueueTimeoutMS=5000';
            }
            
            $this->client = new Client($enhancedConnectionString, [
                'retryWrites' => true,
                'retryReads' => true,
                'readPreference' => 'primaryPreferred', // More resilient than 'primary'
                'writeConcern' => ['w' => 'majority', 'j' => true],
                'serverSelectionTimeoutMS' => 30000,
                'connectTimeoutMS' => 30000,
                'socketTimeoutMS' => 30000,
                'maxPoolSize' => 10,
                'minPoolSize' => 1,
                'maxIdleTimeMS' => 30000,
                'waitQueueTimeoutMS' => 5000,
                'heartbeatFrequencyMS' => 10000,
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
    public function executeWithRetry(callable $operation, int $maxRetries = 5): mixed
    {
        $retryCount = 0;
        $lastException = null;

        while ($retryCount < $maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $retryCount++;
                $lastException = $e;

                // Check for various MongoDB replica set errors
                $isReplicaSetError = (
                    strpos($e->getMessage(), 'not primary') !== false ||
                    strpos($e->getMessage(), 'not master') !== false ||
                    strpos($e->getMessage(), 'node is recovering') !== false ||
                    strpos($e->getMessage(), 'connection refused') !== false ||
                    strpos($e->getMessage(), 'timeout') !== false
                );

                if ($isReplicaSetError && $retryCount < $maxRetries) {
                    // Exponential backoff with jitter: 2s, 4s, 8s, 16s, 30s max
                    $baseDelay = min(pow(2, $retryCount) * 1000000, 30000000); // 30s max
                    $jitter = rand(0, 1000000); // Add up to 1s of jitter
                    $waitTime = $baseDelay + $jitter;
                    
                    usleep($waitTime);
                    
                    error_log("MongoDB replica set error, retry $retryCount/$maxRetries after " . ($waitTime / 1000000) . "s. Error: " . $e->getMessage());
                    
                    // Force complete reconnection
                    $this->client = null;
                    $this->database = null;
                    
                    // Wait a bit more for cluster to stabilize
                    if ($retryCount > 2) {
                        usleep(2000000); // 2s additional wait
                    }
                } else {
                    break;
                }
            }
        }

        throw $lastException;
    }

    /**
     * Check if MongoDB cluster is healthy
     */
    public function isClusterHealthy(): bool
    {
        try {
            $client = $this->getClient();
            $admin = $client->selectDatabase('admin');
            $result = $admin->command(['ping' => 1]);
            return true;
        } catch (\Exception $e) {
            error_log('MongoDB cluster health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Wait for cluster to become healthy
     */
    public function waitForClusterHealth(int $maxWaitSeconds = 30): bool
    {
        $startTime = time();
        while ((time() - $startTime) < $maxWaitSeconds) {
            if ($this->isClusterHealthy()) {
                return true;
            }
            usleep(1000000); // Wait 1 second
        }
        return false;
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
