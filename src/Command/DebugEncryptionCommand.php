<?php

namespace App\Command;

use App\Service\MongoDBEncryptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-encryption',
    description: 'Debug MongoDB encryption configuration',
)]
class DebugEncryptionCommand extends Command
{
    public function __construct(
        private MongoDBEncryptionService $encryptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('MongoDB Encryption Debug Information');

        // Get encryption options
        $encryptionOptions = $this->encryptionService->getEncryptionOptions();
        
        $io->section('Encryption Options');
        $io->table(
            ['Option', 'Value'],
            [
                ['keyVaultNamespace', $encryptionOptions['keyVaultNamespace'] ?? 'not set'],
                ['bypassAutoEncryption', isset($encryptionOptions['bypassAutoEncryption']) ? ($encryptionOptions['bypassAutoEncryption'] ? 'true' : 'false') : 'not set'],
                ['cryptSharedLibRequired', isset($encryptionOptions['extraOptions']['cryptSharedLibRequired']) ? ($encryptionOptions['extraOptions']['cryptSharedLibRequired'] ? 'true' : 'false') : 'not set'],
            ]
        );

        $io->section('Environment Variables');
        $io->table(
            ['Variable', 'Value'],
            [
                ['MONGODB_KEY_VAULT_NAMESPACE', $_ENV['MONGODB_KEY_VAULT_NAMESPACE'] ?? 'not set'],
                ['MONGODB_DB', $_ENV['MONGODB_DB'] ?? 'not set'],
                ['MONGODB_URI', $_ENV['MONGODB_URI'] ?? 'not set'],
            ]
        );

        return Command::SUCCESS;
    }
}
