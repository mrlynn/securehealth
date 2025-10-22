<?php

namespace App\Command;

use App\Repository\PatientRepository;
use MongoDB\BSON\ObjectId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:decrypt-patient',
    description: 'Decrypt and display patient data for development/debugging purposes'
)]
class DecryptPatientCommand extends Command
{
    public function __construct(
        private PatientRepository $patientRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('patientId', InputArgument::REQUIRED, 'Patient ID to decrypt')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json, table)', 'json')
            ->addOption('fields', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of fields to show', '')
            ->setHelp('
This command decrypts patient data using the same encryption service as the application.
Useful for development and debugging purposes.

Examples:
  php bin/console app:decrypt-patient 68f38c718b9384eb080682fb
  php bin/console app:decrypt-patient 68f38c718b9384eb080682fb --format=table
  php bin/console app:decrypt-patient 68f38c718b9384eb080682fb --fields="firstName,lastName,notesHistory"
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $patientId = $input->getArgument('patientId');
        $format = $input->getOption('format');
        $fields = $input->getOption('fields');

        try {
            // Validate ObjectId format
            try {
                new ObjectId($patientId);
            } catch (\Exception $e) {
                $io->error("Invalid patient ID format: {$patientId}");
                return Command::FAILURE;
            }

            // Find patient
            $patient = $this->patientRepository->findById(new ObjectId($patientId));
            if (!$patient) {
                $io->error("Patient not found with ID: {$patientId}");
                return Command::FAILURE;
            }

            // Get decrypted data
            $decryptedData = $patient->toArray();

            // Filter fields if specified
            if (!empty($fields)) {
                $fieldList = array_map('trim', explode(',', $fields));
                $decryptedData = array_intersect_key($decryptedData, array_flip($fieldList));
            }

            // Display data based on format
            switch ($format) {
                case 'table':
                    $this->displayAsTable($io, $decryptedData);
                    break;
                case 'json':
                default:
                    $this->displayAsJson($io, $decryptedData);
                    break;
            }

            $io->success("Successfully decrypted patient data for ID: {$patientId}");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Error decrypting patient data: " . $e->getMessage());
            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function displayAsJson(SymfonyStyle $io, array $data): void
    {
        $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function displayAsTable(SymfonyStyle $io, array $data): void
    {
        $rows = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($key === 'notesHistory') {
                    $value = $this->formatNotesHistory($value);
                } else {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $value = 'null';
            }
            
            $rows[] = [$key, $value];
        }

        $io->table(['Field', 'Value'], $rows);
    }

    private function formatNotesHistory(array $notesHistory): string
    {
        if (empty($notesHistory)) {
            return 'No notes';
        }

        $formatted = [];
        foreach ($notesHistory as $note) {
            $noteInfo = [
                'ID: ' . ($note['id'] ?? 'N/A'),
                'Content: ' . substr($note['content'] ?? 'N/A', 0, 100) . (strlen($note['content'] ?? '') > 100 ? '...' : ''),
                'Doctor: ' . ($note['doctorName'] ?? 'N/A'),
                'Created: ' . ($note['createdAt'] ?? 'N/A')
            ];
            
            if (isset($note['aiGenerated']) && $note['aiGenerated']) {
                $noteInfo[] = 'AI Type: ' . ($note['aiType'] ?? 'N/A');
                $noteInfo[] = 'Confidence: ' . ($note['confidenceScore'] ?? 'N/A');
            }
            
            $formatted[] = implode(' | ', $noteInfo);
        }

        return implode("\n", $formatted);
    }
}
