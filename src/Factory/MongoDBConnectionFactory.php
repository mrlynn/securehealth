<?php

namespace App\Factory;

use MongoDB\Client;

class MongoDBConnectionFactory
{
    public function __construct()
    {
    }
    
    /**
     * Create a MongoDB client with encryption capabilities
     */
    public function createEncryptedClient(string $mongoUrl): Client
    {
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