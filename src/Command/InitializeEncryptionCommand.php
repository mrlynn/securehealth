<?php

namespace App\Command;

use App\Service\MongoDBEncryptionService;
use MongoDB\Client;
use MongoDB\BSON\Binary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:initialize-encryption',
    description: 'Initialize MongoDB encryption key vault and create a data encryption key',
)]
class InitializeEncryptionCommand extends Command
{
    public function __construct(
        private MongoDBEncryptionService $encryptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->title('Initializing MongoDB Encryption');

            // Create a data encryption key
            $dekId = $this->createDataEncryptionKey();
            
            if ($dekId) {
                $io->success("Data encryption key created: {$dekId}");
                
                // Test encryption
                $io->section('Testing Encryption');
                $testResult = $this->testEncryption($dekId);
                
                if ($testResult) {
                    $io->success('Encryption test passed!');
                    return Command::SUCCESS;
                } else {
                    $io->error('Encryption test failed!');
                    return Command::FAILURE;
                }
            } else {
                $io->error('Failed to create data encryption key');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Encryption initialization failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createDataEncryptionKey(): ?string
    {
        try {
            // Get the MongoDB client with encryption
            $client = new Client($_ENV['MONGODB_URI']);
            $keyVault = $client->selectDatabase('encryption')->selectCollection('__keyVault');
            
            // Create a data encryption key
            $dekId = new Binary(random_bytes(16), Binary::TYPE_UUID);
            
            $keyDocument = [
                '_id' => $dekId,
                'keyMaterial' => new Binary(random_bytes(96), Binary::TYPE_GENERIC),
                'creationDate' => new \MongoDB\BSON\UTCDateTime(),
                'updateDate' => new \MongoDB\BSON\UTCDateTime(),
                'status' => 0,
                'keyAltNames' => [],
                'masterKey' => [
                    'provider' => 'local',
                    'key' => base64_encode(random_bytes(96))
                ]
            ];
            
            $result = $keyVault->insertOne($keyDocument);
            
            if ($result->getInsertedId()) {
                return $dekId->getData();
            }
            
            return null;
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to create DEK: " . $e->getMessage());
        }
    }

    private function testEncryption(string $dekId): bool
    {
        try {
            // Simple encryption test
            $client = new Client($_ENV['MONGODB_URI']);
            $collection = $client->selectDatabase('securehealth')->selectCollection('test_encryption');
            
            // Try to insert a test document
            $testDoc = [
                'testField' => 'test value',
                'createdAt' => new \MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $collection->insertOne($testDoc);
            
            // Clean up test document
            $collection->deleteOne(['_id' => $result->getInsertedId()]);
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
