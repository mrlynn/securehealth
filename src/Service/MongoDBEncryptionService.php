<?php

namespace App\Service;

use MongoDB\Client;
use MongoDB\Driver\ClientEncryption;
use MongoDB\BSON\Binary;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Exception\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MongoDBEncryptionService
{
    private $client;
    private $clientEncryption;
    private $keyVaultNamespace;
    private $masterKey;
    private $keyVaultCollection;
    private $logger;
    private $encryptedFields = [];
    
    // Encryption algorithms for MongoDB Atlas
    const ALGORITHM_DETERMINISTIC = 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic';
    const ALGORITHM_RANDOM = 'AEAD_AES_256_CBC_HMAC_SHA_512-Random';
    const ALGORITHM_RANGE = 'range'; // Use 'range' for MongoDB Atlas
    const ALGORITHM_EQUALITY = 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic'; // Use Deterministic for equality searches

    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        
        // Check if MongoDB is disabled with fallbacks - only do this once
        $mongodbDisabled = false;
        try {
            if ($params->has('MONGODB_DISABLED')) {
                $mongodbDisabled = filter_var($params->get('MONGODB_DISABLED'), FILTER_VALIDATE_BOOLEAN);
                $this->logger->info('MongoDB disabled status: ' . ($mongodbDisabled ? 'true' : 'false'));
            }
        } catch (\Exception $e) {
            $this->logger->info('MongoDB disabled parameter not found, using default false');
        }
        
        // Respect configuration; do not force-disable encryption
        
        // Always set up the key vault namespace for auto encryption
        $this->keyVaultNamespace = $_ENV['MONGODB_KEY_VAULT_NAMESPACE'] ?? 'encryption.__keyVault';
        
        // Always read the encryption key for auto encryption
        $keyFile = $_ENV['MONGODB_ENCRYPTION_KEY_PATH'] ?? 'docker/encryption.key';
        if (!file_exists($keyFile)) {
            $this->logger->warning('Encryption key file not found, generating new key: ' . $keyFile);
            file_put_contents($keyFile, random_bytes(96));
        } else {
            // Validate key length (96 bytes)
            $existing = file_get_contents($keyFile);
            if ($existing === false || strlen($existing) !== 96) {
                $this->logger->warning('Invalid local KMS key length, regenerating 96-byte key');
                file_put_contents($keyFile, random_bytes(96));
            }
        }
        $this->masterKey = file_get_contents($keyFile); // Keep as binary for auto encryption
        
        if ($mongodbDisabled) {
            $this->logger->info('MongoDB is disabled, running in documentation-only mode');
            $this->configureEncryptedFieldsDefinitions();
            return;
        }
        
        // Get connection parameters
        $mongoUrl = $params->get('mongodb_url', 'mongodb://localhost:27017');
        $this->keyVaultNamespace = $_ENV['MONGODB_KEY_VAULT_NAMESPACE'] ?? 'encryption.__keyVault';
        
        try {
            try {
                // Initialize MongoDB client with readPreference: primary
                $this->client = new Client($mongoUrl, [
                    'readPreference' => 'primary',
                    'serverSelectionTimeoutMS' => 5000 // 5 second timeout for server selection
                ]);
                
                // Verify connection with a ping command
                $this->client->selectDatabase('admin')->command(['ping' => 1]);
                $this->logger->info('MongoDB connection successful');
                
                // Set up key vault namespace
                list($keyVaultDb, $keyVaultColl) = explode('.', $this->keyVaultNamespace, 2);
                
                // Set up encryption key vault
                $this->keyVaultCollection = $this->client->selectCollection($keyVaultDb, $keyVaultColl);
                
                // Create key vault collection if it doesn't exist
                try {
                    $this->client->selectDatabase($keyVaultDb)->createCollection($keyVaultColl);
                } catch (\Exception $e) {
                    // Collection may already exist, that's fine
                    $this->logger->info('Key vault collection already exists: ' . $e->getMessage());
                }
            } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
                $this->logger->error('MongoDB connection timeout: ' . $e->getMessage());
                throw new \RuntimeException('Failed to connect to MongoDB: connection timeout. Check your MongoDB connection string and network settings.');
            } catch (\MongoDB\Driver\Exception\AuthenticationException $e) {
                $this->logger->error('MongoDB authentication failed: ' . $e->getMessage());
                throw new \RuntimeException('Failed to authenticate with MongoDB. Check your credentials in the connection string.');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize MongoDB client: ' . $e->getMessage());
        }
        
        // We already initialized MongoDB above, so just create client encryption
        if (!$mongodbDisabled) {
            // Create client encryption using the masterKey already loaded
            $this->clientEncryption = $this->createClientEncryption();
        }
        
        // Configure encrypted fields
        $this->configureEncryptedFieldsDefinitions();
    }
    
    /**
     * Configure which fields should be encrypted and how
     */
    private function configureEncryptedFieldsDefinitions(): void
    {
        // Patient document fields - using only Deterministic and Random encryption for Atlas compatibility
        $this->encryptedFields['patient'] = [
            // Deterministic encryption for searchable fields
            'lastName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'firstName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'email' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'phoneNumber' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            
            // For demo purposes, use deterministic instead of range for birthDate
            // Range encryption requires MongoDB Atlas or Enterprise with specific configuration
            'birthDate' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            
            // Standard encryption for highly sensitive data (no query)
            'ssn' => ['algorithm' => self::ALGORITHM_RANDOM],
            'diagnosis' => ['algorithm' => self::ALGORITHM_RANDOM],
            'medications' => ['algorithm' => self::ALGORITHM_RANDOM],
            'insuranceDetails' => ['algorithm' => self::ALGORITHM_RANDOM],
            'notes' => ['algorithm' => self::ALGORITHM_RANDOM],
            'notesHistory' => ['algorithm' => self::ALGORITHM_RANDOM],
            
            // Keep deterministic for backwards compatibility with existing queries
            'patientId' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        ];

        // Message document fields
        $this->encryptedFields['message'] = [
            // Queryable linkage
            'patientId' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'senderUserId' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'senderName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'senderRoles' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'recipientRoles' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'direction' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            // Threading fields
            'conversationId' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'parentMessageId' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            // Optional search by subject
            'subject' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            // Sensitive content
            'body' => ['algorithm' => self::ALGORITHM_RANDOM],
        ];

        // Conversation document fields
        $this->encryptedFields['conversation'] = [
            'patientId' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'subject' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'participants' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'status' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'lastMessagePreview' => ['algorithm' => self::ALGORITHM_RANDOM],
        ];
    }
    
    /**
     * Create the ClientEncryption object
     */
    private function createClientEncryption(): ClientEncryption
    {
        // Set up client encryption options
        $clientEncryptionOpts = [
            'keyVaultNamespace' => $this->keyVaultNamespace,
            'kmsProviders' => [
                'local' => ['key' => new Binary($this->masterKey, Binary::TYPE_GENERIC)]
            ]
        ];
        
        return $this->client->createClientEncryption($clientEncryptionOpts);
    }
    
    /**
     * Get or create a data encryption key
     *
     * @param string $keyAltName The alternate name for the key
     * @return Binary The data encryption key UUID
     */
    public function getOrCreateDataKey(string $keyAltName = 'default_encryption_key'): Binary
    {
        // If MongoDB is disabled, return a dummy key
        if (!isset($this->keyVaultCollection) || !isset($this->clientEncryption)) {
            return new Binary('dummy-key-for-documentation-mode', 4);
        }
        
        // Check if the key already exists
        $existingKey = $this->keyVaultCollection->findOne(['keyAltNames' => $keyAltName]);
        
        if ($existingKey) {
            return $existingKey->_id;
        }
        
        try {
            // Create a new data encryption key
            $dataKeyOptions = [
                'keyAltNames' => [$keyAltName]
            ];
            
            // Create a new key using the local KMS provider
            $keyId = $this->clientEncryption->createDataKey('local', $dataKeyOptions);
            $this->logger->info('Created new encryption data key: ' . $keyAltName);
            return $keyId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create encryption key: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get encryption options for MongoDB client
     *
     * @return array The encryption options
     */
    public function getEncryptionOptions(): array
    {
        $extraOptions = [];
        
        // Allow overriding the shared lib path via env if needed
        $sharedLibPath = $_ENV['MONGOCRYPT_SHARED_LIB_PATH'] ?? null;
        if ($sharedLibPath && file_exists($sharedLibPath)) {
            $extraOptions['cryptSharedLibPath'] = $sharedLibPath;
            $extraOptions['cryptSharedLibRequired'] = true;
        } else {
            // Don't require crypt_shared if not available - fall back to mongocryptd or manual encryption
            $extraOptions['cryptSharedLibRequired'] = false;
        }

        // Note: For auto-encryption to work properly, encryption must be configured
        // at the MongoDB collection level using encrypted collections feature.
        // This requires MongoDB 6.0+ with Queryable Encryption support.
        // Currently, this application uses manual encryption/decryption in the repository layer.
        
        return [
            'keyVaultNamespace' => $this->keyVaultNamespace,
            'kmsProviders' => [
                'local' => [
                    'key' => new Binary($this->masterKey, Binary::TYPE_GENERIC)
                ]
            ],
            'bypassAutoEncryption' => true, // Use manual encryption instead of auto-encryption
            'extraOptions' => $extraOptions,
            'encryptedFieldsMap' => [],
            'bypassQueryAnalysis' => true, // Skip query analysis for manual encryption
            'schemaMap' => []
        ];
    }
    
    /**
     * Encrypt a value
     *
     * @param string $documentType Document type (e.g., 'patient')
     * @param string $fieldName Field name to encrypt
     * @param mixed $value Value to encrypt
     * @return mixed Encrypted value or original if not configured for encryption
     */
    public function encrypt(string $documentType, string $fieldName, $value)
    {
        // If value is null, return as is
        if ($value === null) {
            return null;
        }
        
        // If MongoDB encryption is disabled, return the value as-is
        if (!isset($this->clientEncryption) || !isset($this->keyVaultCollection)) {
            return $value;
        }
        
        // Check if the field is configured for encryption
        if (!isset($this->encryptedFields[$documentType][$fieldName])) {
            return $value;
        }
        
        try {
            $algorithm = $this->encryptedFields[$documentType][$fieldName]['algorithm'];
            $keyAltName = 'hipaa_encryption_key';
            $dataKeyId = $this->getOrCreateDataKey($keyAltName);
            
            // Encrypt based on algorithm
            $encryptOptions = [
                'algorithm' => $algorithm, 
                'keyId' => $dataKeyId
            ];
            
            // For range encryption, add additional options
            if ($algorithm === self::ALGORITHM_RANGE) {
                // Use range options from field configuration if available
                if (isset($this->encryptedFields[$documentType][$fieldName]['rangeOptions'])) {
                    $encryptOptions['rangeOptions'] = $this->encryptedFields[$documentType][$fieldName]['rangeOptions'];
                } else {
                    // Default range options for MongoDB Atlas
                    $encryptOptions['rangeOptions'] = [
                        'min' => null,           // Optional min bound, or null for automatic
                        'max' => null,           // Optional max bound, or null for automatic
                        'contentionFactor' => 10 // Required for range algorithm
                    ];
                }
            }
            
            return $this->clientEncryption->encrypt($value, $encryptOptions);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Failed to encrypt %s.%s: %s', 
                $documentType, 
                $fieldName, 
                $e->getMessage()
            ));
            throw $e;
        }
    }
    
    /**
     * Decrypt an encrypted value
     *
     * @param Binary $value The encrypted value
     * @return mixed The decrypted value
     */
    public function decrypt($value)
    {
        // If MongoDB encryption is disabled, return the value as-is
        if (!isset($this->clientEncryption)) {
            return $value;
        }
        
        if ($value instanceof Binary && $value->getType() === 6) { // Binary subtype 6 is for encrypted data
            return $this->clientEncryption->decrypt($value);
        }
        
        return $value;
    }
    
    /**
     * Configure encrypted fields for a collection
     *
     * @param string $database The database name
     * @param string $collection The collection name
     * @param array $encryptedFields The encrypted fields configuration
     */
    public function configureEncryptedFields(string $database, string $collection, array $encryptedFields): void
    {
        $command = [
            'createCollection' => $database . '.' . $collection,
            'encryptedFields' => $encryptedFields
        ];
        
        try {
            $this->client->getManager()->executeCommand('admin', new \MongoDB\Driver\Command($command));
        } catch (Exception $e) {
            // Handle errors
            throw new \RuntimeException('Failed to configure encrypted fields: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get all field definitions that should be encrypted
     */
    public function getEncryptedFieldDefinitions(): array
    {
        return $this->encryptedFields;
    }
    
    /**
     * Check if a field should be encrypted
     *
     * @param string $documentType Document type (e.g., 'patient')
     * @param string $fieldName Field name
     * @return bool True if the field should be encrypted
     */
    public function shouldEncrypt(string $documentType, string $fieldName): bool
    {
        return isset($this->encryptedFields[$documentType][$fieldName]);
    }
}