<?php

namespace App\Command;

use App\Document\Patient;
use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-patient-data',
    description: 'Generate demo patient records for testing',
)]
class GeneratePatientDataCommand extends Command
{
    public function __construct(
        private DocumentManager $documentManager,
        private MongoDBEncryptionService $encryptionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of patients to generate', 50)
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing patient data before generating new data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $clear = $input->getOption('clear');

        if ($count < 1 || $count > 1000) {
            $io->error('Count must be between 1 and 1000');
            return Command::FAILURE;
        }

        $io->title('Generating Demo Patient Data');

        try {
            // Clear existing data if requested
            if ($clear) {
                $io->section('Clearing existing patient data...');
                $this->clearPatientData();
                $io->success('Existing patient data cleared');
            }

            // Generate new patient data
            $io->section("Generating {$count} patient records...");
            $patients = $this->generatePatientData($count);

            // Save to database
            $io->section('Saving to database...');
            $this->savePatients($patients);

            $io->success("Successfully generated {$count} patient records");
            
            // Display sample data
            $io->section('Sample generated data:');
            $this->displaySampleData($patients, $io);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to generate patient data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function clearPatientData(): void
    {
        $collection = $this->documentManager->getDocumentCollection(Patient::class);
        $collection->deleteMany([]);
    }

    private function generatePatientData(int $count): array
    {
        $patients = [];
        
        // Sample data arrays
        $firstNames = [
            'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Jessica',
            'William', 'Ashley', 'James', 'Amanda', 'Christopher', 'Jennifer', 'Daniel',
            'Lisa', 'Matthew', 'Nancy', 'Anthony', 'Karen', 'Mark', 'Betty', 'Donald',
            'Helen', 'Steven', 'Sandra', 'Paul', 'Donna', 'Andrew', 'Carol', 'Joshua',
            'Ruth', 'Kenneth', 'Sharon', 'Kevin', 'Michelle', 'Brian', 'Laura', 'George',
            'Sarah', 'Edward', 'Kimberly', 'Ronald', 'Deborah', 'Timothy', 'Dorothy'
        ];

        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
            'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
            'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker',
            'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell'
        ];

        $conditions = [
            'Hypertension', 'Diabetes Type 2', 'Asthma', 'Arthritis', 'Depression',
            'Anxiety', 'High Cholesterol', 'Migraine', 'Sleep Apnea', 'GERD',
            'Allergies', 'Back Pain', 'Osteoporosis', 'COPD', 'Heart Disease'
        ];

        $medications = [
            'Lisinopril', 'Metformin', 'Albuterol', 'Ibuprofen', 'Sertraline',
            'Lorazepam', 'Atorvastatin', 'Sumatriptan', 'CPAP', 'Omeprazole',
            'Cetirizine', 'Tramadol', 'Alendronate', 'Spiriva', 'Aspirin'
        ];

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $email = strtolower($firstName . '.' . $lastName . '@example.com');
            
            // Generate realistic SSN (for demo purposes only)
            $ssn = sprintf('%03d-%02d-%04d', rand(100, 999), rand(10, 99), rand(1000, 9999));
            
            // Generate phone number
            $phone = sprintf('(%03d) %03d-%04d', rand(200, 999), rand(200, 999), rand(1000, 9999));
            
            // Generate date of birth (18-80 years old)
            $age = rand(18, 80);
            $dob = new \DateTime();
            $dob->modify("-{$age} years");
            $dob->modify('-' . rand(0, 365) . ' days');
            
            // Generate random conditions and medications
            $patientConditions = array_rand(array_flip($conditions), rand(1, 3));
            $patientMedications = array_rand(array_flip($medications), rand(1, 4));
            
            if (!is_array($patientConditions)) {
                $patientConditions = [$patientConditions];
            }
            if (!is_array($patientMedications)) {
                $patientMedications = [$patientMedications];
            }

            $patient = new Patient();
            $patient->setFirstName($firstName);
            $patient->setLastName($lastName);
            $patient->setEmail($email);
            $patient->setSsn($ssn);
            $patient->setPhone($phone);
            $patient->setDateOfBirth($dob);
            $patient->setAddress([
                'street' => rand(100, 9999) . ' ' . ['Main St', 'Oak Ave', 'Pine Rd', 'Elm St', 'Cedar Ln'][array_rand(['Main St', 'Oak Ave', 'Pine Rd', 'Elm St', 'Cedar Ln'])],
                'city' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'][array_rand(['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'])],
                'state' => ['NY', 'CA', 'IL', 'TX', 'AZ', 'PA', 'TX', 'CA', 'TX', 'CA'][array_rand(['NY', 'CA', 'IL', 'TX', 'AZ', 'PA', 'TX', 'CA', 'TX', 'CA'])],
                'zipCode' => sprintf('%05d', rand(10000, 99999))
            ]);
            $patient->setMedicalConditions($patientConditions);
            $patient->setMedications($patientMedications);
            $patient->setEmergencyContact([
                'name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
                'relationship' => ['Spouse', 'Parent', 'Sibling', 'Child', 'Friend'][array_rand(['Spouse', 'Parent', 'Sibling', 'Child', 'Friend'])],
                'phone' => sprintf('(%03d) %03d-%04d', rand(200, 999), rand(200, 999), rand(1000, 9999))
            ]);
            $patient->setInsuranceInfo([
                'provider' => ['Blue Cross', 'Aetna', 'Cigna', 'UnitedHealth', 'Humana'][array_rand(['Blue Cross', 'Aetna', 'Cigna', 'UnitedHealth', 'Humana'])],
                'policyNumber' => 'POL' . sprintf('%08d', rand(10000000, 99999999)),
                'groupNumber' => 'GRP' . sprintf('%06d', rand(100000, 999999))
            ]);
            $patient->setCreatedAt(new \DateTime());
            $patient->setUpdatedAt(new \DateTime());

            $patients[] = $patient;
        }

        return $patients;
    }

    private function savePatients(array $patients): void
    {
        foreach ($patients as $patient) {
            $this->documentManager->persist($patient);
        }
        $this->documentManager->flush();
    }

    private function displaySampleData(array $patients, SymfonyStyle $io): void
    {
        $sample = array_slice($patients, 0, 3);
        
        foreach ($sample as $index => $patient) {
            $io->text([
                "Patient " . ($index + 1) . ":",
                "  Name: {$patient->getFirstName()} {$patient->getLastName()}",
                "  Email: {$patient->getEmail()}",
                "  Phone: {$patient->getPhone()}",
                "  DOB: {$patient->getDateOfBirth()->format('Y-m-d')}",
                "  Conditions: " . implode(', ', $patient->getMedicalConditions()),
                "  Medications: " . implode(', ', $patient->getMedications()),
                ""
            ]);
        }
    }
}
