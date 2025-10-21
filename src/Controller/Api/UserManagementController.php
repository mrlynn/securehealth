<?php

namespace App\Controller\Api;

use App\Document\User;
use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users', name: 'api_users_')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private DocumentManager $documentManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        try {
            $users = $this->userRepository->findAll();
            
            $userData = [];
            foreach ($users as $user) {
                $userData[] = [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles(),
                    'isAdmin' => $user->isAdmin(),
                    'isPatient' => $user->isPatient(),
                    'isActive' => !in_array('ROLE_INACTIVE', $user->getRoles()),
                ];
            }
            
            return new JsonResponse([
                'success' => true,
                'users' => $userData
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/', name: 'create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validate required fields
            if (!isset($data['email']) || !isset($data['username']) || !isset($data['password']) || !isset($data['roles'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }
            
            // Check if user already exists
            $existingUser = $this->userRepository->findOneByEmail($data['email']);
            if ($existingUser) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User with this email already exists'
                ], 409);
            }
            
            // Create new user
            $user = new User();
            $user->setEmail($data['email']);
            $user->setUsername($data['username']);
            $user->setRoles($data['roles']);
            
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
            
            // Set admin flag if ROLE_ADMIN is present
            if (in_array('ROLE_ADMIN', $data['roles'])) {
                $user->setIsAdmin(true);
            }
            
            // Set patient flag if ROLE_PATIENT is present
            if (in_array('ROLE_PATIENT', $data['roles'])) {
                $user->setIsPatient(true);
            }
            
            $this->userRepository->save($user);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles(),
                    'isAdmin' => $user->isAdmin(),
                    'isPatient' => $user->isPatient(),
                ]
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getUserById(string $id): JsonResponse
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            return new JsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles(),
                    'isAdmin' => $user->isAdmin(),
                    'isPatient' => $user->isPatient(),
                    'isActive' => !in_array('ROLE_INACTIVE', $user->getRoles()),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function updateUser(Request $request, string $id): JsonResponse
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            $data = json_decode($request->getContent(), true);
            
            // Update user properties if provided
            if (isset($data['username'])) {
                $user->setUsername($data['username']);
            }
            
            if (isset($data['roles'])) {
                $user->setRoles($data['roles']);
                
                // Update admin and patient flags based on roles
                $user->setIsAdmin(in_array('ROLE_ADMIN', $data['roles']));
                $user->setIsPatient(in_array('ROLE_PATIENT', $data['roles']));
            }
            
            // Update password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }
            
            $this->userRepository->save($user);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles(),
                    'isAdmin' => $user->isAdmin(),
                    'isPatient' => $user->isPatient(),
                    'isActive' => !in_array('ROLE_INACTIVE', $user->getRoles()),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            // Toggle status by modifying roles
            $roles = $user->getRoles();
            $isActive = !in_array('ROLE_INACTIVE', $roles);
            
            if ($isActive) {
                // Deactivate user
                $roles[] = 'ROLE_INACTIVE';
                $user->setRoles(array_unique($roles));
                $message = 'User deactivated successfully';
            } else {
                // Reactivate user
                $roles = array_filter($roles, function($role) {
                    return $role !== 'ROLE_INACTIVE';
                });
                $user->setRoles($roles);
                $message = 'User reactivated successfully';
            }
            
            $this->userRepository->save($user);
            
            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles(),
                    'isActive' => !in_array('ROLE_INACTIVE', $user->getRoles()),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, string $id): JsonResponse
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['password']) || empty($data['password'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Password is required'
                ], 400);
            }
            
            // Hash and set the new password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
            
            $this->userRepository->save($user);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}