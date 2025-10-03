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
     */
    public function createEncryptedClient(string $mongoUrl): Client
    {
        $options = [
            // Real MongoDB Enterprise/Atlas encryption configuration
            'autoEncryption' => $this->encryptionService->getEncryptionOptions(),
            'driver' => [
                'ssl' => true
            ],
            'readPreference' => 'primary'
        ];
        
        // Use Atlas with proper encryption configuration
        return new Client($mongoUrl, [], $options);
    }
    
    /**
     * Create a standard MongoDB client (without encryption)
     */
    public function createClient(string $mongoUrl): Client
    {
        return new Client($mongoUrl, [
            'readPreference' => 'primary'
        ]);
    }
}