<?php

namespace App\Command;

use App\Document\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-users',
    description: 'Creates the default users for the SecureHealth application',
)]
class CreateUsersCommand extends Command
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Creating default users for SecureHealth');

        // Default users
        $users = [
            [
                'email' => 'doctor@example.com',
                'username' => 'Dr. Smith',
                'password' => 'doctor', // Plain password for demo purposes only
                'roles' => ['ROLE_DOCTOR']
            ],
            [
                'email' => 'nurse@example.com',
                'username' => 'Nurse Johnson',
                'password' => 'nurse', // Plain password for demo purposes only
                'roles' => ['ROLE_NURSE']
            ],
            [
                'email' => 'receptionist@example.com',
                'username' => 'Receptionist Davis',
                'password' => 'receptionist', // Plain password for demo purposes only
                'roles' => ['ROLE_RECEPTIONIST']
            ],
            [
                'email' => 'admin@securehealth.com',
                'username' => 'System Administrator',
                'password' => 'admin123', // Plain password for demo purposes only
                'roles' => ['ROLE_ADMIN'],
                'isAdmin' => true
            ]
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($users as $userData) {
            // Check if user already exists
            $existingUser = $this->userRepository->findOneByEmail($userData['email']);
            
            if ($existingUser) {
                $io->text("User {$userData['email']} already exists. Skipping.");
                $skippedCount++;
                continue;
            }
            
            // Create new user
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setUsername($userData['username']);
            $user->setPassword($userData['password']); // We're storing plain passwords for the demo
            $user->setRoles($userData['roles']);
            
            // Set admin flag if specified
            if (isset($userData['isAdmin']) && $userData['isAdmin']) {
                $user->setIsAdmin(true);
            }
            
            $this->userRepository->save($user);
            $createdCount++;
            
            $io->text("Created user: {$userData['email']} with roles: " . implode(', ', $userData['roles']));
        }

        if ($createdCount > 0) {
            $io->success("Successfully created $createdCount users. Skipped $skippedCount existing users.");
        } else {
            $io->info("No new users were created. Skipped $skippedCount existing users.");
        }

        return Command::SUCCESS;
    }
}