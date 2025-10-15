<?php

namespace App\Command;

use App\Service\MongoDBEncryptionService;
use MongoDB\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-encryption-keys',
    description: 'Reset and regenerate MongoDB encryption keys',
)]
class ResetEncryptionKeysCommand extends Command
{
    public function __construct(
        private Client $mongoClient,
        private MongoDBEncryptionService $encryptionService,
        private string $keyVaultNamespace = 'encryption.__keyVault'
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Reset MongoDB Encryption Keys');

        try {
            // Parse key vault namespace
            list($keyVaultDb, $keyVaultColl) = explode('.', $this->keyVaultNamespace, 2);
            
            $io->section('Step 1: Checking current key vault...');
            $keyVaultCollection = $this->mongoClient->selectCollection($keyVaultDb, $keyVaultColl);
            $existingKeys = $keyVaultCollection->find()->toArray();
            $io->text(sprintf('Found %d existing keys in key vault', count($existingKeys)));
            
            foreach ($existingKeys as $key) {
                $keyAltNames = $key->keyAltNames ?? [];
                // Convert BSONArray to array if needed
                if ($keyAltNames instanceof \MongoDB\Model\BSONArray) {
                    $keyAltNames = iterator_to_array($keyAltNames);
                }
                $keyAltName = !empty($keyAltNames) ? implode(', ', $keyAltNames) : '<no alias>';
                $createdAt = $key->creationDate->toDateTime()->format('Y-m-d H:i:s');
                $io->text(sprintf('  - Key: %s (created: %s)', $keyAltName, $createdAt));
            }
            
            $io->section('Step 2: Deleting old encryption keys...');
            $deleteResult = $keyVaultCollection->deleteMany([]);
            $io->success(sprintf('Deleted %d keys from key vault', $deleteResult->getDeletedCount()));
            
            $io->section('Step 3: Creating new encryption key...');
            $newKeyId = $this->encryptionService->getOrCreateDataKey('hipaa_encryption_key');
            $io->success('Created new encryption key: hipaa_encryption_key');
            
            $io->section('Step 4: Verifying new key...');
            $newKeys = $keyVaultCollection->find()->toArray();
            $io->text(sprintf('Key vault now contains %d keys', count($newKeys)));
            
            foreach ($newKeys as $key) {
                $keyAltNames = $key->keyAltNames ?? [];
                // Convert BSONArray to array if needed
                if ($keyAltNames instanceof \MongoDB\Model\BSONArray) {
                    $keyAltNames = iterator_to_array($keyAltNames);
                }
                $keyAltName = !empty($keyAltNames) ? implode(', ', $keyAltNames) : '<no alias>';
                $createdAt = $key->creationDate->toDateTime()->format('Y-m-d H:i:s');
                $io->text(sprintf('  - Key: %s (created: %s)', $keyAltName, $createdAt));
            }
            
            $io->success([
                '✅ Encryption keys have been reset successfully!',
                '',
                'The system is now ready to encrypt patient data with the current master key.',
                'You can now run: php bin/console securehealth -c generate-data'
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Failed to reset encryption keys',
                'Error: ' . $e->getMessage(),
                'Stack trace:',
                $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}

