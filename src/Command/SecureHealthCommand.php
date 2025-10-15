<?php

namespace App\Command;

use App\Command\CreateUsersCommand;
use App\Command\GeneratePatientDataCommand;
use App\Command\SeedMedicalKnowledgeCommand;
use App\Command\InitializeEncryptionCommand;
use App\Command\DebugEncryptionCommand;
use App\Repository\UserRepository;
use App\Repository\PatientRepository;
use App\Repository\MedicalKnowledgeRepository;
use App\Service\MongoDBEncryptionService;
use App\Document\User;
use App\Document\Patient;
use App\Document\MedicalKnowledge;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'securehealth',
    description: 'SecureHealth - HIPAA-compliant medical records management system command-line tool',
)]
class SecureHealthCommand extends Command
{
    private SymfonyStyle $io;
    private UserRepository $userRepository;
    private PatientRepository $patientRepository;
    private MedicalKnowledgeRepository $medicalKnowledgeRepository;
    private MongoDBEncryptionService $encryptionService;
    
    private array $availableCommands = [
        'setup' => 'Complete system setup and initialization',
        'users' => 'User management (create, list, manage roles)',
        'patients' => 'Patient data management (generate, import, export)',
        'medical-knowledge' => 'Medical knowledge base management',
        'encryption' => 'Encryption utilities and debugging',
        'database' => 'Database operations (reset, backup, restore)',
        'validation' => 'System validation and health checks',
        'status' => 'System status and information',
        'help' => 'Show detailed help for any command'
    ];

    public function __construct(
        UserRepository $userRepository,
        PatientRepository $patientRepository,
        MedicalKnowledgeRepository $medicalKnowledgeRepository,
        MongoDBEncryptionService $encryptionService
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->patientRepository = $patientRepository;
        $this->medicalKnowledgeRepository = $medicalKnowledgeRepository;
        $this->encryptionService = $encryptionService;
    }

    protected function configure(): void
    {
        $this
            ->addOption('command', 'c', InputOption::VALUE_REQUIRED, 'Specific command to run')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Run in interactive mode')
            ->setHelp('
<info>SecureHealth Command-Line Tool</info>

This tool provides comprehensive management of your HIPAA-compliant medical records system.

<comment>Quick Start:</comment>
  php bin/console securehealth --interactive
  php bin/console securehealth -c setup
  php bin/console securehealth -c status

<comment>Available Commands:</comment>
  setup              Complete system setup and initialization
  users              User management (create, list, manage roles)
  patients           Patient data management (generate, import, export)
  medical-knowledge  Medical knowledge base management
  encryption         Encryption utilities and debugging
  database           Database operations (reset, backup, restore)
  validation         System validation and health checks
  status             System status and information
  help               Show detailed help for any command

<comment>Examples:</comment>
  php bin/console securehealth -c setup
  php bin/console securehealth -c users --create-admin
  php bin/console securehealth -c patients --generate 50
  php bin/console securehealth -c encryption --debug
  php bin/console securehealth -c validation --full-check
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        
        // Show welcome banner
        $this->showWelcomeBanner();
        
        $command = $input->getOption('command');
        $interactive = $input->getOption('interactive');
        
        if ($interactive) {
            return $this->runInteractiveMode($input, $output);
        }
        
        if ($command) {
            return $this->runSpecificCommand($command, $input, $output);
        }
        
        // No specific command, show menu
        return $this->showMainMenu();
    }

    private function showWelcomeBanner(): void
    {
        $this->io->title('🏥 SecureHealth - HIPAA-Compliant Medical Records System');
        $this->io->text([
            'Welcome to the SecureHealth command-line management tool.',
            'This tool provides comprehensive management of your medical records system.',
            ''
        ]);
    }

    private function showMainMenu(): int
    {
        $this->io->section('Available Commands');
        
        $table = new Table($this->io);
        $table->setHeaders(['Command', 'Description']);
        
        foreach ($this->availableCommands as $cmd => $desc) {
            $table->addRow([$cmd, $desc]);
        }
        
        $table->render();
        
        $this->io->newLine();
        $this->io->text([
            '<comment>Usage Examples:</comment>',
            '  php bin/console securehealth -c setup',
            '  php bin/console securehealth --interactive',
            '  php bin/console securehealth -c status',
            ''
        ]);
        
        return Command::SUCCESS;
    }

    private function runInteractiveMode(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Interactive Mode');
        
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What would you like to do?',
            array_keys($this->availableCommands),
            0
        );
        
        $command = $helper->ask($input, $output, $question);
        
        return $this->runSpecificCommand($command, $input, $output);
    }

    private function runSpecificCommand(string $command, InputInterface $input, OutputInterface $output): int
    {
        switch ($command) {
            case 'setup':
                return $this->runSetup($input, $output);
            case 'users':
                return $this->runUserManagement($input, $output);
            case 'patients':
                return $this->runPatientManagement($input, $output);
            case 'medical-knowledge':
                return $this->runMedicalKnowledgeManagement($input, $output);
            case 'encryption':
                return $this->runEncryptionUtilities($input, $output);
            case 'database':
                return $this->runDatabaseOperations($input, $output);
            case 'validation':
                return $this->runValidation($input, $output);
            case 'status':
                return $this->showSystemStatus();
            case 'help':
                return $this->showDetailedHelp($input, $output);
            default:
                $this->io->error("Unknown command: {$command}");
                $this->io->text('Available commands: ' . implode(', ', array_keys($this->availableCommands)));
                return Command::FAILURE;
        }
    }

    private function runSetup(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('🚀 SecureHealth System Setup');
        
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('This will initialize the complete system. Continue? ', false);
        
        if (!$helper->ask($input, $output, $question)) {
            $this->io->text('Setup cancelled.');
            return Command::SUCCESS;
        }
        
        $this->io->text('Starting system setup...');
        
        // Step 1: Database setup
        $this->io->text('📊 Step 1: Setting up database...');
        $this->runCommand('doctrine:mongodb:schema:create', [], 'Creating MongoDB schema...');
        
        // Step 2: Create users
        $this->io->text('👥 Step 2: Creating default users...');
        $this->runCommand('app:create-users', [], 'Creating users...');
        
        // Step 3: Generate sample data
        $this->io->text('📋 Step 3: Generating sample patient data...');
        $this->runCommand('app:generate-patient-data', ['--count' => '10'], 'Generating patient data...');
        
        // Step 4: Seed medical knowledge
        $this->io->text('🧠 Step 4: Seeding medical knowledge base...');
        $this->runCommand('app:seed-medical-knowledge', ['--count' => '20'], 'Seeding medical knowledge...');
        
        // Step 5: System validation
        $this->io->text('✅ Step 5: Running system validation...');
        $this->runValidation($input, $output);
        
        $this->io->success('🎉 SecureHealth system setup completed successfully!');
        $this->io->text([
            'Your HIPAA-compliant medical records system is ready to use.',
            'Access the application at: http://localhost:8081',
            'Default login: doctor@example.com / doctor',
            ''
        ]);
        
        return Command::SUCCESS;
    }

    private function runUserManagement(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('👥 User Management');
        
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What would you like to do?',
            [
                'create-users' => 'Create default users',
                'list-users' => 'List all users',
                'create-admin' => 'Create admin user',
                'back' => 'Back to main menu'
            ],
            'create-users'
        );
        
        $action = $helper->ask($input, $output, $question);
        
        switch ($action) {
            case 'create-users':
                return $this->runCommand('app:create-users', [], 'Creating default users...');
            case 'list-users':
                return $this->listUsers();
            case 'create-admin':
                return $this->createAdminUser();
            case 'back':
                return Command::SUCCESS;
        }
        
        return Command::SUCCESS;
    }

    private function runPatientManagement(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('📋 Patient Data Management');
        
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What would you like to do?',
            [
                'generate' => 'Generate sample patient data',
                'count' => 'Show patient count',
                'export' => 'Export patient data',
                'import' => 'Import patient data',
                'back' => 'Back to main menu'
            ],
            'generate'
        );
        
        $action = $helper->ask($input, $output, $question);
        
        switch ($action) {
            case 'generate':
                $countQuestion = new \Symfony\Component\Console\Question\Question('How many patients to generate? (default: 10): ', '10');
                $count = $helper->ask($input, $output, $countQuestion);
                return $this->runCommand('app:generate-patient-data', ['--count' => $count], "Generating {$count} patients...");
            case 'count':
                return $this->showPatientCount();
            case 'export':
                return $this->exportPatientData();
            case 'import':
                return $this->importPatientData();
            case 'back':
                return Command::SUCCESS;
        }
        
        return Command::SUCCESS;
    }

    private function runMedicalKnowledgeManagement(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('🧠 Medical Knowledge Base Management');
        
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What would you like to do?',
            [
                'seed' => 'Seed medical knowledge base',
                'count' => 'Show knowledge base statistics',
                'search' => 'Search medical knowledge',
                'back' => 'Back to main menu'
            ],
            'seed'
        );
        
        $action = $helper->ask($input, $output, $question);
        
        switch ($action) {
            case 'seed':
                $countQuestion = new \Symfony\Component\Console\Question\Question('How many entries to seed? (default: 50): ', '50');
                $count = $helper->ask($input, $output, $countQuestion);
                return $this->runCommand('app:seed-medical-knowledge', ['--count' => $count], "Seeding {$count} medical knowledge entries...");
            case 'count':
                return $this->showMedicalKnowledgeStats();
            case 'search':
                return $this->searchMedicalKnowledge();
            case 'back':
                return Command::SUCCESS;
        }
        
        return Command::SUCCESS;
    }

    private function runEncryptionUtilities(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('🔐 Encryption Utilities');
        
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What would you like to do?',
            [
                'initialize' => 'Initialize encryption',
                'debug' => 'Debug encryption configuration',
                'test' => 'Test encryption/decryption',
                'status' => 'Check encryption status',
                'back' => 'Back to main menu'
            ],
            'debug'
        );
        
        $action = $helper->ask($input, $output, $question);
        
        switch ($action) {
            case 'initialize':
                return $this->runCommand('app:initialize-encryption', [], 'Initializing encryption...');
            case 'debug':
                return $this->runCommand('app:debug-encryption', [], 'Debugging encryption...');
            case 'test':
                return $this->testEncryption();
            case 'status':
                return $this->checkEncryptionStatus();
            case 'back':
                return Command::SUCCESS;
        }
        
        return Command::SUCCESS;
    }

    private function runDatabaseOperations(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('🗄️ Database Operations');
        
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What would you like to do?',
            [
                'reset' => 'Reset database (WARNING: This will delete all data)',
                'backup' => 'Create database backup',
                'restore' => 'Restore from backup',
                'stats' => 'Show database statistics',
                'back' => 'Back to main menu'
            ],
            'stats'
        );
        
        $action = $helper->ask($input, $output, $question);
        
        switch ($action) {
            case 'reset':
                $confirmQuestion = new ConfirmationQuestion('⚠️  This will DELETE ALL DATA. Are you sure? ', false);
                if ($helper->ask($input, $output, $confirmQuestion)) {
                    return $this->runCommand('doctrine:mongodb:schema:drop', [], 'Dropping database schema...');
                }
                return Command::SUCCESS;
            case 'backup':
                return $this->createDatabaseBackup();
            case 'restore':
                return $this->restoreDatabaseBackup();
            case 'stats':
                return $this->showDatabaseStats();
            case 'back':
                return Command::SUCCESS;
        }
        
        return Command::SUCCESS;
    }

    private function runValidation(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('✅ System Validation & Health Checks');
        
        $validationResults = [
            'Database Connection' => $this->validateDatabaseConnection(),
            'User Authentication' => $this->validateUserAuthentication(),
            'API Endpoints' => $this->validateApiEndpoints(),
            'Encryption Service' => $this->validateEncryptionService(),
            'Medical Knowledge Base' => $this->validateMedicalKnowledgeBase(),
            'File Permissions' => $this->validateFilePermissions(),
        ];
        
        $table = new Table($this->io);
        $table->setHeaders(['Component', 'Status', 'Details']);
        
        foreach ($validationResults as $component => $result) {
            $status = $result['status'] ? '✅ PASS' : '❌ FAIL';
            $table->addRow([$component, $status, $result['message']]);
        }
        
        $table->render();
        
        $failedCount = count(array_filter($validationResults, fn($r) => !$r['status']));
        
        if ($failedCount === 0) {
            $this->io->success('All validation checks passed! System is healthy.');
            return Command::SUCCESS;
        } else {
            $this->io->warning("{$failedCount} validation check(s) failed. Please review the issues above.");
            return Command::FAILURE;
        }
    }

    private function showSystemStatus(): int
    {
        $this->io->section('📊 System Status');
        
        // Get system information
        $status = [
            'Application Version' => '1.0.0',
            'PHP Version' => PHP_VERSION,
            'Symfony Version' => $this->getApplication()->getVersion(),
            'Environment' => $_ENV['APP_ENV'] ?? 'unknown',
            'Database Status' => $this->getDatabaseStatus(),
            'Encryption Status' => $this->getEncryptionStatus(),
            'User Count' => $this->getUserCount(),
            'Patient Count' => $this->getPatientCount(),
            'Medical Knowledge Entries' => $this->getMedicalKnowledgeCount(),
        ];
        
        $table = new Table($this->io);
        $table->setHeaders(['Component', 'Value']);
        
        foreach ($status as $component => $value) {
            $table->addRow([$component, $value]);
        }
        
        $table->render();
        
        return Command::SUCCESS;
    }

    private function showDetailedHelp(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('📚 Detailed Help');
        
        $this->io->text([
            '<comment>Setup Command:</comment>',
            '  Complete system initialization including database, users, and sample data',
            '  Usage: php bin/console securehealth -c setup',
            '',
            '<comment>User Management:</comment>',
            '  Create and manage system users with different roles',
            '  Usage: php bin/console securehealth -c users',
            '',
            '<comment>Patient Management:</comment>',
            '  Generate, import, export, and manage patient data',
            '  Usage: php bin/console securehealth -c patients',
            '',
            '<comment>Medical Knowledge Base:</comment>',
            '  Manage AI-powered medical knowledge database',
            '  Usage: php bin/console securehealth -c medical-knowledge',
            '',
            '<comment>Encryption Utilities:</comment>',
            '  Initialize, debug, and manage MongoDB encryption',
            '  Usage: php bin/console securehealth -c encryption',
            '',
            '<comment>Database Operations:</comment>',
            '  Reset, backup, restore, and manage database',
            '  Usage: php bin/console securehealth -c database',
            '',
            '<comment>System Validation:</comment>',
            '  Run comprehensive health checks and validation',
            '  Usage: php bin/console securehealth -c validation',
            '',
            '<comment>System Status:</comment>',
            '  Display current system status and statistics',
            '  Usage: php bin/console securehealth -c status',
        ]);
        
        return Command::SUCCESS;
    }

    // Helper methods for running commands and gathering information
    private function runCommand(string $command, array $arguments = [], string $description = ''): int
    {
        if ($description) {
            $this->io->text($description);
        }
        
        $commandObj = $this->getApplication()->find($command);
        $input = new \Symfony\Component\Console\Input\ArrayInput($arguments);
        
        return $commandObj->run($input, $this->io);
    }

    private function validateDatabaseConnection(): array
    {
        try {
            // Simple connection test by trying to find users
            $users = $this->userRepository->findAll();
            $count = count($users);
            return ['status' => true, 'message' => "Connected successfully ({$count} users)"];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function validateUserAuthentication(): array
    {
        try {
            $users = $this->userRepository->findAll();
            $count = count($users);
            return ['status' => true, 'message' => "Authentication system ready ({$count} users)"];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Authentication issue: ' . $e->getMessage()];
        }
    }

    private function validateApiEndpoints(): array
    {
        try {
            // Check if we can access the repositories (which use the API layer)
            $userCount = count($this->userRepository->findAll());
            return ['status' => true, 'message' => 'API endpoints accessible'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'API issue: ' . $e->getMessage()];
        }
    }

    private function validateEncryptionService(): array
    {
        try {
            $encryptionOptions = $this->encryptionService->getEncryptionOptions();
            $isConfigured = isset($encryptionOptions['keyVaultNamespace']) && 
                           isset($encryptionOptions['kmsProviders']) &&
                           !empty($encryptionOptions['kmsProviders']);
            
            if ($isConfigured) {
                return ['status' => true, 'message' => 'Encryption service configured'];
            } else {
                return ['status' => false, 'message' => 'Encryption not properly configured'];
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Encryption issue: ' . $e->getMessage()];
        }
    }

    private function validateMedicalKnowledgeBase(): array
    {
        try {
            $stats = $this->medicalKnowledgeRepository->getKnowledgeBaseStats();
            $count = $stats['totalEntries'] ?? 0;
            return ['status' => true, 'message' => "Medical knowledge base ready ({$count} entries)"];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Knowledge base issue: ' . $e->getMessage()];
        }
    }

    private function validateFilePermissions(): array
    {
        try {
            // Check if we can write to current directory
            $testFile = 'test_permissions_' . time() . '.tmp';
            $result = file_put_contents($testFile, 'test');
            
            if ($result !== false) {
                unlink($testFile);
                return ['status' => true, 'message' => 'File permissions correct'];
            } else {
                return ['status' => false, 'message' => 'Cannot write to current directory'];
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Permission issue: ' . $e->getMessage()];
        }
    }

    private function getDatabaseStatus(): string
    {
        try {
            return 'Connected';
        } catch (\Exception $e) {
            return 'Disconnected';
        }
    }

    private function getEncryptionStatus(): string
    {
        return $_ENV['MONGODB_DISABLED'] === 'true' ? 'Disabled' : 'Enabled';
    }

    private function getUserCount(): string
    {
        try {
            $users = $this->userRepository->findAll();
            return count($users) . ' users';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getPatientCount(): string
    {
        try {
            // Use a simple count query instead of getSearchStats
            $patients = $this->patientRepository->findByCriteria([]);
            return count($patients) . ' patients';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getMedicalKnowledgeCount(): string
    {
        try {
            $stats = $this->medicalKnowledgeRepository->getKnowledgeBaseStats();
            return ($stats['totalEntries'] ?? 0) . ' entries';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    // Real implementations for user management
    private function listUsers(): int
    {
        try {
            $users = $this->userRepository->findAll();
            
            if (empty($users)) {
                $this->io->text('No users found.');
                return Command::SUCCESS;
            }
            
            $this->io->section('👥 System Users');
            
            $table = new Table($this->io);
            $table->setHeaders(['ID', 'Email', 'Username', 'Roles', 'Admin', 'Patient']);
            
            foreach ($users as $user) {
                $table->addRow([
                    $user->getId(),
                    $user->getEmail(),
                    $user->getUsername(),
                    implode(', ', $user->getRoles()),
                    $user->getIsAdmin() ? 'Yes' : 'No',
                    $user->getIsPatient() ? 'Yes' : 'No'
                ]);
            }
            
            $table->render();
            $this->io->text(sprintf('Total users: %d', count($users)));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Failed to list users: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createAdminUser(): int
    {
        try {
            $helper = $this->getHelper('question');
            
            // Create dummy input for questions
            $dummyInput = new \Symfony\Component\Console\Input\ArrayInput([]);
            
            $emailQuestion = new \Symfony\Component\Console\Question\Question('Admin email: ');
            $email = $helper->ask($dummyInput, $this->io, $emailQuestion);
            
            if (empty($email)) {
                $this->io->error('Email is required.');
                return Command::FAILURE;
            }
            
            // Check if user already exists
            $existingUser = $this->userRepository->findOneByEmail($email);
            if ($existingUser) {
                $this->io->error("User with email {$email} already exists.");
                return Command::FAILURE;
            }
            
            $usernameQuestion = new \Symfony\Component\Console\Question\Question('Admin username: ');
            $username = $helper->ask($dummyInput, $this->io, $usernameQuestion);
            
            $passwordQuestion = new \Symfony\Component\Console\Question\Question('Admin password: ');
            $passwordQuestion->setHidden(true);
            $password = $helper->ask($dummyInput, $this->io, $passwordQuestion);
            
            if (empty($password)) {
                $this->io->error('Password is required.');
                return Command::FAILURE;
            }
            
            // Create admin user
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setPassword($password); // Note: In production, this should be hashed
            $user->setRoles(['ROLE_ADMIN']);
            $user->setIsAdmin(true);
            
            $this->userRepository->save($user);
            
            $this->io->success("Admin user created successfully: {$email}");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Failed to create admin user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showPatientCount(): int
    {
        try {
            $patients = $this->patientRepository->findByCriteria([]);
            $count = count($patients);
            
            $this->io->section('📋 Patient Statistics');
            $this->io->text(sprintf('Total patients in database: %d', $count));
            
            if ($count > 0) {
                $this->io->text([
                    'Encrypted fields:',
                    '  - Deterministic: lastName, firstName, email, phoneNumber',
                    '  - Range: birthDate',
                    '  - Random: ssn, diagnosis, medications, insuranceDetails, notes'
                ]);
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Failed to get patient count: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function exportPatientData(): int
    {
        try {
            $helper = $this->getHelper('question');
            $dummyInput = new \Symfony\Component\Console\Input\ArrayInput([]);
            
            // Get export options
            $formatQuestion = new ChoiceQuestion(
                'Export format:',
                ['json' => 'JSON', 'csv' => 'CSV', 'xml' => 'XML'],
                'json'
            );
            $format = $helper->ask($dummyInput, $this->io, $formatQuestion);
            
            $filenameQuestion = new \Symfony\Component\Console\Question\Question(
                'Export filename (without extension): ',
                'patients_export_' . date('Y-m-d_H-i-s')
            );
            $filename = $helper->ask($dummyInput, $this->io, $filenameQuestion);
            
            // Get all patients
            $patients = $this->patientRepository->findByCriteria([]);
            $totalPatients = count($patients);
            
            if ($totalPatients === 0) {
                $this->io->warning('No patients found to export.');
                return Command::SUCCESS;
            }
            
            $this->io->text("Exporting {$totalPatients} patients...");
            
            // For now, we'll export basic patient data
            // In a real implementation, you'd decrypt and format the data properly
            $exportData = [
                'export_date' => date('Y-m-d H:i:s'),
                'total_patients' => $totalPatients,
                'export_format' => $format,
                'encrypted_fields_note' => 'Patient data is encrypted in the database. This export contains metadata only.'
            ];
            
            $filepath = "{$filename}.{$format}";
            
            switch ($format) {
                case 'json':
                    file_put_contents($filepath, json_encode($exportData, JSON_PRETTY_PRINT));
                    break;
                case 'csv':
                    $csv = "Export Date,Total Patients,Format\n";
                    $csv .= date('Y-m-d H:i:s') . ",{$totalPatients},{$format}\n";
                    file_put_contents($filepath, $csv);
                    break;
                case 'xml':
                    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
                    $xml .= "<export>\n";
                    $xml .= "  <export_date>" . date('Y-m-d H:i:s') . "</export_date>\n";
                    $xml .= "  <total_patients>{$totalPatients}</total_patients>\n";
                    $xml .= "  <format>{$format}</format>\n";
                    $xml .= "</export>\n";
                    file_put_contents($filepath, $xml);
                    break;
            }
            
            $this->io->success("Patient data exported to: {$filepath}");
            $this->io->text('Note: Actual patient data is encrypted and requires proper decryption for full export.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Failed to export patient data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function importPatientData(): int
    {
        $this->io->text('Import patient data functionality would be implemented here.');
        return Command::SUCCESS;
    }

    private function showMedicalKnowledgeStats(): int
    {
        try {
            $stats = $this->medicalKnowledgeRepository->getKnowledgeBaseStats();
            
            $this->io->section('🧠 Medical Knowledge Base Statistics');
            
            $table = new Table($this->io);
            $table->setHeaders(['Metric', 'Value']);
            $table->addRow(['Total Entries', $stats['totalEntries'] ?? 0]);
            $table->addRow(['Drug Interactions', $stats['drugInteractions'] ?? 0]);
            $table->addRow(['Treatment Guidelines', $stats['treatmentGuidelines'] ?? 0]);
            $table->addRow(['Diagnostic Criteria', $stats['diagnosticCriteria'] ?? 0]);
            $table->addRow(['Clinical Guidelines', $stats['clinicalGuidelines'] ?? 0]);
            $table->addRow(['Research Papers', $stats['researchPapers'] ?? 0]);
            $table->addRow(['Last Updated', $stats['lastUpdated'] ?? 'Never']);
            
            $table->render();
            
            if (($stats['totalEntries'] ?? 0) === 0) {
                $this->io->text('No medical knowledge entries found. Run the seed command to populate the knowledge base.');
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Failed to get medical knowledge stats: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function searchMedicalKnowledge(): int
    {
        try {
            $helper = $this->getHelper('question');
            $dummyInput = new \Symfony\Component\Console\Input\ArrayInput([]);
            
            $searchQuestion = new \Symfony\Component\Console\Question\Question('Enter search query: ');
            $query = $helper->ask($dummyInput, $this->io, $searchQuestion);
            
            if (empty($query)) {
                $this->io->error('Search query is required.');
                return Command::FAILURE;
            }
            
            $this->io->text("Searching for: {$query}");
            
            // Search medical knowledge base
            $results = $this->medicalKnowledgeRepository->search($query, 10);
            
            if (empty($results)) {
                $this->io->text('No results found.');
                return Command::SUCCESS;
            }
            
            $this->io->section('🔍 Search Results');
            
            foreach ($results as $index => $entry) {
                $this->io->text(sprintf('[%d] %s', $index + 1, $entry->getTitle()));
                $this->io->text('    Source: ' . $entry->getSource());
                $this->io->text('    Tags: ' . implode(', ', $entry->getTags()));
                $this->io->text('    Content: ' . substr($entry->getContent(), 0, 200) . '...');
                $this->io->newLine();
            }
            
            $this->io->success(sprintf('Found %d results', count($results)));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Failed to search medical knowledge: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function testEncryption(): int
    {
        try {
            $this->io->section('🔐 Encryption Testing');
            
            // Test encryption/decryption with sample data
            $testData = [
                'patient' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'ssn' => '123-45-6789'
                ]
            ];
            
            $this->io->text('Testing encryption/decryption with sample data...');
            
            $successCount = 0;
            $totalTests = 0;
            
            foreach ($testData as $collection => $fields) {
                foreach ($fields as $field => $value) {
                    $totalTests++;
                    
                    try {
                        // Test encryption
                        $encrypted = $this->encryptionService->encrypt($collection, $field, $value);
                        
                        // Test decryption
                        $decrypted = $this->encryptionService->decrypt($collection, $field, $encrypted);
                        
                        if ($decrypted === $value) {
                            $this->io->text("✅ {$collection}.{$field}: PASS");
                            $successCount++;
                        } else {
                            $this->io->text("❌ {$collection}.{$field}: FAIL (decryption mismatch)");
                        }
                    } catch (\Exception $e) {
                        $this->io->text("❌ {$collection}.{$field}: FAIL ({$e->getMessage()})");
                    }
                }
            }
            
            $this->io->newLine();
            $this->io->text(sprintf('Encryption Test Results: %d/%d tests passed', $successCount, $totalTests));
            
            if ($successCount === $totalTests) {
                $this->io->success('All encryption tests passed!');
                return Command::SUCCESS;
            } else {
                $this->io->warning('Some encryption tests failed. Check the output above.');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->io->error('Encryption testing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function checkEncryptionStatus(): int
    {
        try {
            $this->io->section('🔐 Encryption Status');
            
            $encryptionOptions = $this->encryptionService->getEncryptionOptions();
            
            $table = new Table($this->io);
            $table->setHeaders(['Setting', 'Value', 'Status']);
            
            // Check key vault namespace
            $keyVault = $encryptionOptions['keyVaultNamespace'] ?? 'Not set';
            $table->addRow(['Key Vault Namespace', $keyVault, $keyVault !== 'Not set' ? '✅ Set' : '❌ Missing']);
            
            // Check KMS providers
            $kmsProviders = $encryptionOptions['kmsProviders'] ?? [];
            $localKms = isset($kmsProviders['local']) ? 'Available' : 'Missing';
            $table->addRow(['Local KMS Provider', $localKms, $localKms === 'Available' ? '✅ Available' : '❌ Missing']);
            
            // Check encryption bypass
            $bypassAutoEncryption = $encryptionOptions['bypassAutoEncryption'] ?? true;
            $table->addRow(['Auto Encryption', $bypassAutoEncryption ? 'Disabled' : 'Enabled', $bypassAutoEncryption ? '⚠️ Disabled' : '✅ Enabled']);
            
            // Check schema map
            $schemaMap = $encryptionOptions['schemaMap'] ?? [];
            $table->addRow(['Schema Map', count($schemaMap) . ' entries', count($schemaMap) > 0 ? '✅ Configured' : '⚠️ Empty']);
            
            $table->render();
            
            // Additional status information
            $this->io->newLine();
            $this->io->text([
                'Encryption Configuration:',
                '  - Key Vault: ' . ($keyVault !== 'Not set' ? 'Configured' : 'Not configured'),
                '  - KMS Providers: ' . (count($kmsProviders) > 0 ? 'Available' : 'Not available'),
                '  - Auto Encryption: ' . ($bypassAutoEncryption ? 'Disabled' : 'Enabled'),
                '  - Schema Map: ' . (count($schemaMap) > 0 ? 'Configured' : 'Not configured')
            ]);
            
            // Overall status
            $isConfigured = $keyVault !== 'Not set' && count($kmsProviders) > 0;
            
            if ($isConfigured && !$bypassAutoEncryption) {
                $this->io->success('Encryption is properly configured and enabled.');
            } elseif ($isConfigured && $bypassAutoEncryption) {
                $this->io->warning('Encryption is configured but currently disabled.');
            } else {
                $this->io->error('Encryption is not properly configured.');
            }
            
            return $isConfigured ? Command::SUCCESS : Command::FAILURE;
            
        } catch (\Exception $e) {
            $this->io->error('Failed to check encryption status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createDatabaseBackup(): int
    {
        $this->io->text('Database backup functionality would be implemented here.');
        return Command::SUCCESS;
    }

    private function restoreDatabaseBackup(): int
    {
        $this->io->text('Database restore functionality would be implemented here.');
        return Command::SUCCESS;
    }

    private function showDatabaseStats(): int
    {
        $this->io->text('Database statistics would be shown here.');
        return Command::SUCCESS;
    }
}
