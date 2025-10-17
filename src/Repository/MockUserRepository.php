<?php

namespace App\Repository;

use App\Document\User;
use Psr\Log\LoggerInterface;

/**
 * A mock user repository that can be used when MongoDB is disabled
 */
class MockUserRepository
{
    private $users = [];
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        
        // Create mock users for testing
        $this->initializeTestUsers();
    }
    
    private function initializeTestUsers(): void
    {
        // Create doctor user
        $doctorUser = new User();
        $doctorUser->setEmail('doctor@example.com');
        $doctorUser->setPassword('$2y$13$97.3mzm8OjidLTl4hQEv9OqJoH6hRFMN80hjf2qdyMElBLnxFtBAG'); // "doctor" hashed
        $doctorUser->setRoles(['ROLE_DOCTOR']);
        $doctorUser->setUsername('Doctor Smith');
        $this->users['doctor@example.com'] = $doctorUser;
        
        // Create nurse user
        $nurseUser = new User();
        $nurseUser->setEmail('nurse@example.com');
        $nurseUser->setPassword('$2y$13$QVIyYzAaDgyjpM9mHl.oje27QmQoZECvTXYMmTn/OXlh0ZUlNK/Ca'); // "nurse" hashed
        $nurseUser->setRoles(['ROLE_NURSE']);
        $nurseUser->setUsername('Nurse Johnson');
        $this->users['nurse@example.com'] = $nurseUser;
        
        // Create admin user
        $adminUser = new User();
        $adminUser->setEmail('admin@securehealth.com');
        $adminUser->setPassword('$2y$13$OEuntI9ZQZQnbWo8qVGP5.VLIHa/JZZgKwZlBXvjALGfZ0PL6DFni'); // "admin123" hashed
        $adminUser->setRoles(['ROLE_ADMIN']);
        $adminUser->setUsername('Admin User');
        $this->users['admin@securehealth.com'] = $adminUser;
        
        // Create receptionist user
        $receptionistUser = new User();
        $receptionistUser->setEmail('receptionist@example.com');
        $receptionistUser->setPassword('$2y$13$/uzozN0nPq05cVKmuw1cyOMY8UqHMDmG.ZLLbuIdlPXBIGDGw8E4G'); // "receptionist" hashed
        $receptionistUser->setRoles(['ROLE_RECEPTIONIST']);
        $receptionistUser->setUsername('Receptionist Garcia');
        $this->users['receptionist@example.com'] = $receptionistUser;
        
        $this->logger->info('Initialized mock user repository with test users');
    }

    public function findOneByEmail(string $email): ?User
    {
        $this->logger->info('Mock repository: Looking up user by email: ' . $email);
        return $this->users[$email] ?? null;
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->logger->info('Mock repository: Saving user: ' . $user->getEmail());
        $this->users[$user->getEmail()] = $user;
    }

    public function findAll(): array
    {
        return array_values($this->users);
    }
}