<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class AuthController extends AbstractController
{
    /**
     * Get current authenticated user information
     */
    #[Route('/user', name: 'api_user', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getUserIdentifier(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles()),
                'isDoctor' => in_array('ROLE_DOCTOR', $user->getRoles()),
                'isNurse' => in_array('ROLE_NURSE', $user->getRoles()),
                'isReceptionist' => in_array('ROLE_RECEPTIONIST', $user->getRoles()),
                'isPatient' => in_array('ROLE_PATIENT', $user->getRoles()),
            ]
        ]);
    }
    
    /**
     * Check if user has required role(s)
     */
    #[Route('/check-permission', name: 'api_check_permission', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkPermission(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $requiredRoles = $data['roles'] ?? [];
        
        if (empty($requiredRoles)) {
            return $this->json([
                'success' => false,
                'message' => 'No roles specified'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $user = $this->getUser();
        $userRoles = $user->getRoles();
        
        // Check if user has any of the required roles
        $hasPermission = false;
        foreach ($requiredRoles as $role) {
            if ($this->isGranted($role)) {
                $hasPermission = true;
                break;
            }
        }
        
        return $this->json([
            'success' => true,
            'hasPermission' => $hasPermission,
            'userRoles' => $userRoles,
            'requiredRoles' => $requiredRoles
        ]);
    }
    
    /**
     * Verify page access for static HTML pages
     */
    #[Route('/verify-access/{page}', name: 'api_verify_access', methods: ['GET'])]
    public function verifyPageAccess(string $page): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Not authenticated',
                'redirectTo' => '/login.html'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Define page access requirements
        $pagePermissions = [
            'calendar' => ['ROLE_USER'], // All authenticated users
            'patients' => ['ROLE_DOCTOR', 'ROLE_NURSE', 'ROLE_RECEPTIONIST', 'ROLE_ADMIN'],
            'patient-add' => ['ROLE_DOCTOR', 'ROLE_NURSE', 'ROLE_RECEPTIONIST'],
            'patient-detail' => ['ROLE_DOCTOR', 'ROLE_NURSE', 'ROLE_RECEPTIONIST', 'ROLE_ADMIN'],
            'patient-edit' => ['ROLE_DOCTOR', 'ROLE_NURSE'],
            'patient-notes-demo' => ['ROLE_DOCTOR', 'ROLE_NURSE'],
            'scheduling' => ['ROLE_RECEPTIONIST', 'ROLE_DOCTOR', 'ROLE_NURSE', 'ROLE_ADMIN'],
            'medical-knowledge-search' => ['ROLE_DOCTOR', 'ROLE_NURSE', 'ROLE_ADMIN'],
            'admin' => ['ROLE_ADMIN', 'ROLE_DOCTOR'], // Doctors can view audit logs
            'admin-demo-data' => ['ROLE_ADMIN'],
            'queryable-encryption-search' => ['ROLE_ADMIN'],
        ];
        
        $requiredRoles = $pagePermissions[$page] ?? ['ROLE_USER'];
        $hasAccess = false;
        
        foreach ($requiredRoles as $role) {
            if ($this->isGranted($role)) {
                $hasAccess = true;
                break;
            }
        }
        
        if (!$hasAccess) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied',
                'redirectTo' => '/patients.html',
                'requiredRoles' => $requiredRoles,
                'userRoles' => $user->getRoles()
            ], Response::HTTP_FORBIDDEN);
        }
        
        return $this->json([
            'success' => true,
            'hasAccess' => true,
            'user' => [
                'username' => $user->getUsername(),
                'roles' => $user->getRoles()
            ]
        ]);
    }
}
