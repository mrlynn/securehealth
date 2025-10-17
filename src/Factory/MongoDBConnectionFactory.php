<?php

namespace App\Factory;

use App\Service\MongoDBEncryptionService;
use MongoDB\Client;

class MongoDBConnectionFactory
{
    private MongoDBEncryptionService $encryptionService;
    
    public function __construct(MongoDBEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }
    
    /**
     * Create a MongoDB client with encryption capabilities
     * or a mock client if MongoDB is disabled
     */
    public function createEncryptedClient(string $mongoUrl): Client
    {
        // Check if MongoDB is disabled through the encryption service
        if ($this->encryptionService->isMongoDBDisabled()) {
            // Return a minimal client that won't actually connect
            return new class extends Client {
                public function __construct() {
                    // Don't actually connect to MongoDB
                }
                
                // Override methods to prevent actual MongoDB connections
                public function selectDatabase($databaseName, array $options = []) {
                    return null;
                }
                
                public function getManager() {
                    return null;
                }
                
                // Add other necessary methods as needed
            };
        }
        
        // Normal MongoDB connection - use manual encryption instead of auto-encryption
        $options = [
            'readPreference' => 'primary',
            'serverSelectionTimeoutMS' => 5000, // Short timeout for fast failure
        ];
        
        // For manual encryption/decryption, we don't use autoEncryption
        // The encryption service handles encryption/decryption manually
        return new Client($mongoUrl, $options);
    }
    
    /**
     * Create a standard MongoDB client (without encryption)
     */
    public function createClient(string $mongoUrl): Client
    {
        // Fallback non-encrypted client
        return new Client($mongoUrl, [ 'readPreference' => 'primary' ]);
    }
}