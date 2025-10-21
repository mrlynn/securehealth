<?php

namespace App\Controller;

use App\Document\User;
use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private DocumentManager $documentManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->userRepository->findAll();
        
        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $email = $request->request->get('email');
                $username = $request->request->get('username');
                $password = $request->request->get('password');
                $roles = $request->request->all('roles');
                
                // Validate required fields
                if (!$email || !$username || !$password || empty($roles)) {
                    $this->addFlash('error', 'All fields are required');
                    return $this->redirectToRoute('admin_users_new');
                }
                
                // Check if user already exists
                $existingUser = $this->userRepository->findOneByEmail($email);
                if ($existingUser) {
                    $this->addFlash('error', 'User with this email already exists');
                    return $this->redirectToRoute('admin_users_new');
                }
                
                // Create new user
                $user = new User();
                $user->setEmail($email);
                $user->setUsername($username);
                $user->setRoles($roles);
                
                // Hash the password
                $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
                
                // Set admin flag if ROLE_ADMIN is present
                if (in_array('ROLE_ADMIN', $roles)) {
                    $user->setIsAdmin(true);
                }
                
                // Set patient flag if ROLE_PATIENT is present
                if (in_array('ROLE_PATIENT', $roles)) {
                    $user->setIsPatient(true);
                }
                
                $this->userRepository->save($user);
                
                $this->addFlash('success', 'User created successfully');
                return $this->redirectToRoute('admin_users_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating user: ' . $e->getMessage());
            }
        }
        
        return $this->render('admin/users/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                $this->addFlash('error', 'User not found');
                return $this->redirectToRoute('admin_users_index');
            }
            
            if ($request->isMethod('POST')) {
                $username = $request->request->get('username');
                $roles = $request->request->all('roles');
                $newPassword = $request->request->get('password');
                
                // Validate required fields
                if (!$username || empty($roles)) {
                    $this->addFlash('error', 'Username and roles are required');
                    return $this->redirectToRoute('admin_users_edit', ['id' => $id]);
                }
                
                $user->setUsername($username);
                $user->setRoles($roles);
                
                // Update password if provided
                if ($newPassword) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                    $user->setPassword($hashedPassword);
                }
                
                // Update admin flag based on roles
                $user->setIsAdmin(in_array('ROLE_ADMIN', $roles));
                $user->setIsPatient(in_array('ROLE_PATIENT', $roles));
                
                $this->userRepository->save($user);
                
                $this->addFlash('success', 'User updated successfully');
                return $this->redirectToRoute('admin_users_index');
            }
            
            return $this->render('admin/users/edit.html.twig', [
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error updating user: ' . $e->getMessage());
            return $this->redirectToRoute('admin_users_index');
        }
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(string $id): Response
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                return $this->json(['success' => false, 'message' => 'User not found'], 404);
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
            
            return $this->json(['success' => true, 'message' => $message, 'isActive' => !$isActive]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, string $id): Response
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                return $this->json(['success' => false, 'message' => 'User not found'], 404);
            }
            
            $password = $request->request->get('password');
            
            if (!$password) {
                return $this->json(['success' => false, 'message' => 'Password is required'], 400);
            }
            
            // Hash and set the new password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            
            $this->userRepository->save($user);
            
            return $this->json(['success' => true, 'message' => 'Password reset successfully']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $id): Response
    {
        try {
            $user = $this->documentManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                $this->addFlash('error', 'User not found');
                return $this->redirectToRoute('admin_users_index');
            }
            
            // Instead of actually deleting, mark as inactive
            $roles = $user->getRoles();
            if (!in_array('ROLE_INACTIVE', $roles)) {
                $roles[] = 'ROLE_INACTIVE';
                $user->setRoles(array_unique($roles));
                $this->userRepository->save($user);
            }
            
            $this->addFlash('success', 'User deactivated successfully');
            return $this->redirectToRoute('admin_users_index');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error deactivating user: ' . $e->getMessage());
            return $this->redirectToRoute('admin_users_index');
        }
    }
}