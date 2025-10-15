<?php

namespace App\Controller\Api;

use App\Service\AuditLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private AuditLogService $auditLogService;
    
    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }
    
    #[Route('/api/login', name: 'app_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        if ($this->getUser()) {
            $user = $this->getUser();
            $username = $user->getUserIdentifier();
            $roles = $user->getRoles();
            
            // Log successful login
            $this->auditLogService->logEvent(
                $username,
                'USER_LOGIN',
                'Successful login'
            );
            
            return new JsonResponse([
                'username' => $username,
                'roles' => $roles
            ]);
        }
        
        // Login failed - this will be handled by Symfony's security system
        return new JsonResponse([
            'error' => 'Invalid credentials'
        ], Response::HTTP_UNAUTHORIZED);
    }
    
    #[Route('/api/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): void
    {
        // This method can be empty, it will be intercepted by the logout key on the firewall
        // Log the logout event in your AuditLogService
        $this->auditLogService->logEvent(
            $this->getUser()->getUserIdentifier(),
            'USER_LOGOUT',
            'User logged out'
        );
        
        throw new \LogicException('This method should not be reached!');
    }
}