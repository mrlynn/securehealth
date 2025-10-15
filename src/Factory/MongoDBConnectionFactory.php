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
            'readPreference' => 'primary',
        ];
        $driverOptions = [
            'autoEncryption' => $this->encryptionService->getEncryptionOptions(),
        ];
        return new Client($mongoUrl, $options, $driverOptions);
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