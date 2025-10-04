<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Service\AuditLogService;
use App\Repository\UserRepository;

#[Route('/api', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private AuditLogService $auditLogService;
    private UserRepository $userRepository;
    
    public function __construct(AuditLogService $auditLogService, UserRepository $userRepository)
    {
        $this->auditLogService = $auditLogService;
        $this->userRepository = $userRepository;
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Handle both _username/_password (Symfony standard) and email/password formats
        $email = $data['_username'] ?? $data['email'] ?? null;
        $password = $data['_password'] ?? $data['password'] ?? null;
        
        if (!$email || !$password) {
            return $this->json([
                'success' => false,
                'message' => 'Email and password are required'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Find user in MongoDB
        $user = $this->userRepository->findOneByEmail($email);
        
        // Check if user exists and password is correct
        // TODO: Implement proper password hashing (currently using plain text comparison)
        if (!$user || $user->getPassword() !== $password) {
            // Log failed login attempt
            $this->auditLogService->log(
                new AnonymousUser($email),
                'SECURITY_LOGIN_FAILED',
                [
                    'description' => 'Failed login attempt',
                    'email' => $email,
                    'ip' => $request->getClientIp(),
                    'status' => 'failed'
                ]
            );
            
            return $this->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Set user in session
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        $session->set('user', [
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'isPatient' => $user->isPatient(),
            'patientId' => $user->getPatientId() ? (string)$user->getPatientId() : null
        ]);
        
        // Create an authenticated user for logging
        $authenticatedUser = new class($user->getEmail(), $user->getUsername(), $user->getRoles()) implements UserInterface {
            private string $email;
            private string $username;
            private array $roles;

            public function __construct(string $email, string $username, array $roles)
            {
                $this->email = $email;
                $this->username = $username;
                $this->roles = $roles;
            }

            public function getRoles(): array
            {
                return $this->roles;
            }

            public function getPassword(): ?string
            {
                return null;
            }

            public function getSalt(): ?string
            {
                return null;
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return $this->email;
            }
        };

        // Log successful login
        $this->auditLogService->logSecurityEvent(
            $authenticatedUser,
            'LOGIN',
            [
                'description' => 'User logged in successfully',
                'username' => $user->getUsername(),
                'ip' => $request->getClientIp(),
                'status' => 'success'
            ]
        );
        
        // Return user data
        return $this->json([
            'success' => true,
            'user' => [
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
                'isAdmin' => $user->isAdmin()
            ]
        ]);
    }
    
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // Get current user from session
        $user = $request->getSession()->get('user');
        
        if ($user) {
            // Create an authenticated user for logging
            $authenticatedUser = new class($user['email'], $user['username'], $user['roles']) implements UserInterface {
                private string $email;
                private string $username;
                private array $roles;

                public function __construct(string $email, string $username, array $roles)
                {
                    $this->email = $email;
                    $this->username = $username;
                    $this->roles = $roles;
                }

                public function getRoles(): array
                {
                    return $this->roles;
                }

                public function getPassword(): ?string
                {
                    return null;
                }

                public function getSalt(): ?string
                {
                    return null;
                }

                public function eraseCredentials(): void
                {
                }

                public function getUserIdentifier(): string
                {
                    return $this->email;
                }
            };

            // Log logout
            $this->auditLogService->logSecurityEvent(
                $authenticatedUser,
                'LOGOUT',
                [
                    'description' => 'User logged out',
                    'username' => $user['username'],
                    'ip' => $request->getClientIp(),
                    'status' => 'success'
                ]
            );
            
            // Clear session
            $request->getSession()->remove('user');
        }
        
        return $this->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
    
    #[Route('/user', name: 'current_user', methods: ['GET'])]
    public function currentUser(Request $request): JsonResponse
    {
        // Get current user from session
        $user = $request->getSession()->get('user');
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        return $this->json([
            'success' => true,
            'user' => $user
        ]);
    }
}

/**
 * Simple anonymous user class for audit logging before authentication
 */
class AnonymousUser implements UserInterface
{
    private string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function getRoles(): array
    {
        return ['ROLE_ANONYMOUS'];
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}