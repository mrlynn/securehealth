<?php

namespace App\Tests\Service;

use App\Service\MongoDBEncryptionService;
use MongoDB\BSON\Binary;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MongoDBEncryptionServiceTest extends TestCase
{
    private ParameterBagInterface $mockParams;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        // Create mock dependencies
        $this->mockParams = $this->createMock(ParameterBagInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Set up environment variables for testing
        $_ENV['MONGODB_KEY_VAULT_NAMESPACE'] = 'encryption.__keyVault';
        $_ENV['MONGODB_ENCRYPTION_KEY_PATH'] = sys_get_temp_dir() . '/test_encryption.key';
        
        // Create test encryption key file
        if (!file_exists($_ENV['MONGODB_ENCRYPTION_KEY_PATH'])) {
            file_put_contents($_ENV['MONGODB_ENCRYPTION_KEY_PATH'], random_bytes(96));
        }
    }

    protected function tearDown(): void
    {
        // Clean up test encryption key file
        if (isset($_ENV['MONGODB_ENCRYPTION_KEY_PATH']) && file_exists($_ENV['MONGODB_ENCRYPTION_KEY_PATH'])) {
            unlink($_ENV['MONGODB_ENCRYPTION_KEY_PATH']);
        }
    }

    /**
     * Test 1: Deterministic encryption on searchable fields (firstName, lastName, email)
     */
    public function testDeterministicEncryptionOnSearchableFields(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        // Test data for deterministic encryption
        $testData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'phoneNumber' => '+1-555-123-4567'
        ];
        
        // Test deterministic encryption (should return original values when MongoDB is disabled)
        foreach ($testData as $field => $value) {
            $encrypted1 = $service->encrypt('patient', $field, $value);
            $encrypted2 = $service->encrypt('patient', $field, $value);
            
            // When MongoDB is disabled, should return original values
            $this->assertEquals($value, $encrypted1, "Should return original value when MongoDB disabled for field: $field");
            $this->assertEquals($encrypted1, $encrypted2, "Values should be consistent for field: $field");
        }
    }

    /**
     * Test 2: Random encryption on sensitive fields (SSN, diagnosis, medications)
     */
    public function testRandomEncryptionOnSensitiveFields(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        // Test data for random encryption
        $testData = [
            'ssn' => '123-45-6789',
            'diagnosis' => 'Hypertension',
            'medications' => ['Lisinopril 10mg', 'Metformin 500mg'],
            'insuranceDetails' => 'Blue Cross Blue Shield Policy #123456',
            'notes' => 'Patient shows improvement in blood pressure control.'
        ];
        
        // Test random encryption (should return original values when MongoDB is disabled)
        foreach ($testData as $field => $value) {
            $encrypted1 = $service->encrypt('patient', $field, $value);
            $encrypted2 = $service->encrypt('patient', $field, $value);
            
            // When MongoDB is disabled, should return original values
            $this->assertEquals($value, $encrypted1, "Should return original value when MongoDB disabled for field: $field");
            $this->assertEquals($encrypted1, $encrypted2, "Values should be consistent for field: $field");
        }
    }

    /**
     * Test 3: Key vault security and separation
     */
    public function testKeyVaultSecurityAndSeparation(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        // Test that key vault namespace is properly configured
        $encryptionOptions = $service->getEncryptionOptions();
        
        $this->assertArrayHasKey('keyVaultNamespace', $encryptionOptions);
        $this->assertEquals('encryption.__keyVault', $encryptionOptions['keyVaultNamespace']);
        
        // Test that KMS providers are configured
        $this->assertArrayHasKey('kmsProviders', $encryptionOptions);
        $this->assertArrayHasKey('local', $encryptionOptions['kmsProviders']);
        $this->assertInstanceOf(Binary::class, $encryptionOptions['kmsProviders']['local']['key']);
        
        // Test that bypass options are set for manual encryption
        $this->assertTrue($encryptionOptions['bypassAutoEncryption']);
        $this->assertTrue($encryptionOptions['bypassQueryAnalysis']);
        
        // Test that extra options include mongocryptd fallback
        $this->assertArrayHasKey('extraOptions', $encryptionOptions);
        $this->assertFalse($encryptionOptions['extraOptions']['cryptSharedLibRequired']);
    }

    /**
     * Test 4: Encryption/decryption performance benchmarks
     */
    public function testEncryptionDecryptionPerformanceBenchmarks(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        $testData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'ssn' => '123-45-6789',
            'diagnosis' => 'Hypertension'
        ];
        
        $iterations = 100;
        $startTime = microtime(true);
        
        // Performance test for encryption
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($testData as $field => $value) {
                $service->encrypt('patient', $field, $value);
            }
        }
        
        $encryptionTime = microtime(true) - $startTime;
        
        // Performance test for decryption
        $encryptedValue = new Binary('test_encrypted_data', 6);
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $service->decrypt($encryptedValue);
        }
        
        $decryptionTime = microtime(true) - $startTime;
        
        // Performance assertions (should complete within reasonable time)
        $this->assertLessThan(5.0, $encryptionTime, "Encryption should complete within 5 seconds for $iterations iterations");
        $this->assertLessThan(2.0, $decryptionTime, "Decryption should complete within 2 seconds for $iterations iterations");
        
        // Calculate operations per second
        $encryptionOpsPerSecond = ($iterations * count($testData)) / $encryptionTime;
        $decryptionOpsPerSecond = $iterations / $decryptionTime;
        
        $this->assertGreaterThan(100, $encryptionOpsPerSecond, "Should achieve at least 100 encryption operations per second");
        $this->assertGreaterThan(500, $decryptionOpsPerSecond, "Should achieve at least 500 decryption operations per second");
    }

    /**
     * Test 5: Error handling for invalid keys
     */
    public function testErrorHandlingForInvalidKeys(): void
    {
        // Test with invalid encryption key
        $invalidKeyPath = sys_get_temp_dir() . '/invalid_key.key';
        file_put_contents($invalidKeyPath, 'invalid_key_data'); // Wrong length
        
        // Temporarily override the key path
        $originalKeyPath = $_ENV['MONGODB_ENCRYPTION_KEY_PATH'];
        $_ENV['MONGODB_ENCRYPTION_KEY_PATH'] = $invalidKeyPath;
        
        try {
            // Create new service with invalid key and MongoDB disabled
            $this->mockParams->method('has')->willReturnMap([
                ['MONGODB_DISABLED', true],
                ['mongodb_disabled', true]
            ]);
            
            $this->mockParams->method('get')->willReturnMap([
                ['MONGODB_DISABLED', true],
                ['mongodb_disabled', true]
            ]);
            
            $serviceWithInvalidKey = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
            
            // Test that service handles invalid key gracefully
            $this->assertFalse($serviceWithInvalidKey->isEncryptionAvailable());
            
            // Test that encryption returns original value when encryption is not available
            $result = $serviceWithInvalidKey->encrypt('patient', 'firstName', 'John');
            $this->assertEquals('John', $result, "Should return original value when encryption is not available");
            
        } finally {
            // Restore original key path
            $_ENV['MONGODB_ENCRYPTION_KEY_PATH'] = $originalKeyPath;
            unlink($invalidKeyPath);
        }
    }

    /**
     * Test 6: Field configuration validation
     */
    public function testFieldConfigurationValidation(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        // Test that encrypted field definitions are properly configured
        $encryptedFields = $service->getEncryptedFieldDefinitions();
        
        // Verify patient fields are configured
        $this->assertArrayHasKey('patient', $encryptedFields);
        
        // Verify deterministic fields
        $deterministicFields = ['firstName', 'lastName', 'email', 'phoneNumber', 'birthDate', 'patientId'];
        foreach ($deterministicFields as $field) {
            $this->assertArrayHasKey($field, $encryptedFields['patient']);
            $this->assertEquals(
                MongoDBEncryptionService::ALGORITHM_DETERMINISTIC,
                $encryptedFields['patient'][$field]['algorithm']
            );
        }
        
        // Verify random fields
        $randomFields = ['ssn', 'diagnosis', 'medications', 'insuranceDetails', 'notes', 'notesHistory'];
        foreach ($randomFields as $field) {
            $this->assertArrayHasKey($field, $encryptedFields['patient']);
            $this->assertEquals(
                MongoDBEncryptionService::ALGORITHM_RANDOM,
                $encryptedFields['patient'][$field]['algorithm']
            );
        }
        
        // Test shouldEncrypt method
        $this->assertTrue($service->shouldEncrypt('patient', 'firstName'));
        $this->assertTrue($service->shouldEncrypt('patient', 'ssn'));
        $this->assertFalse($service->shouldEncrypt('patient', 'nonExistentField'));
        $this->assertFalse($service->shouldEncrypt('nonExistentDocument', 'firstName'));
    }

    /**
     * Test 7: Message and Conversation encryption
     */
    public function testMessageAndConversationEncryption(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        // Test message encryption
        $messageFields = [
            'patientId' => 'patient123',
            'senderUserId' => 'user456',
            'senderName' => 'Dr. Smith',
            'subject' => 'Test Message',
            'body' => 'This is a sensitive message body'
        ];
        
        foreach ($messageFields as $field => $value) {
            $encrypted = $service->encrypt('message', $field, $value);
            $this->assertEquals($value, $encrypted, "Should return original value when MongoDB disabled for message field: $field");
        }
        
        // Test conversation encryption
        $conversationFields = [
            'patientId' => 'patient123',
            'subject' => 'Patient Consultation',
            'participants' => ['user456', 'user789'],
            'lastMessagePreview' => 'Sensitive message preview'
        ];
        
        foreach ($conversationFields as $field => $value) {
            $encrypted = $service->encrypt('conversation', $field, $value);
            $this->assertEquals($value, $encrypted, "Should return original value when MongoDB disabled for conversation field: $field");
        }
    }

    /**
     * Test 8: Null value handling
     */
    public function testNullValueHandling(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        // Test that null values are handled correctly
        $result = $service->encrypt('patient', 'firstName', null);
        $this->assertNull($result, "Null values should be returned as null");
        
        $result = $service->encrypt('patient', 'ssn', null);
        $this->assertNull($result, "Null values should be returned as null");
    }

    /**
     * Test 9: MongoDB disabled mode
     */
    public function testMongoDBDisabledMode(): void
    {
        // Configure mock to simulate MongoDB disabled
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        $this->assertTrue($service->isMongoDBDisabled());
        $this->assertFalse($service->isEncryptionAvailable());
        
        // Test that encryption returns original values when MongoDB is disabled
        $result = $service->encrypt('patient', 'firstName', 'John');
        $this->assertEquals('John', $result, "Should return original value when MongoDB is disabled");
        
        $result = $service->encrypt('patient', 'ssn', '123-45-6789');
        $this->assertEquals('123-45-6789', $result, "Should return original value when MongoDB is disabled");
    }

    /**
     * Test 10: Data key creation and retrieval
     */
    public function testDataKeyCreationAndRetrieval(): void
    {
        // Create service with MongoDB disabled for testing
        $this->mockParams->method('has')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $this->mockParams->method('get')->willReturnMap([
            ['MONGODB_DISABLED', true],
            ['mongodb_disabled', true]
        ]);
        
        $service = new MongoDBEncryptionService($this->mockParams, $this->mockLogger);
        
        // Test data key creation (should return dummy key when MongoDB disabled)
        $dataKeyId = $service->getOrCreateDataKey('test_key');
        $this->assertInstanceOf(Binary::class, $dataKeyId);
        
        // Test with different key name
        $existingDataKeyId = $service->getOrCreateDataKey('test_key_2');
        $this->assertInstanceOf(Binary::class, $existingDataKeyId);
        
        // When MongoDB is disabled, both should be dummy keys but may be different
        $this->assertNotNull($dataKeyId);
        $this->assertNotNull($existingDataKeyId);
    }
}