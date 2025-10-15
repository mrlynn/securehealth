<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\AuditLogService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class JsonLoginAuthenticator extends AbstractAuthenticator
{
    private UserRepository $userRepository;
    private AuditLogService $auditLogService;

    public function __construct(UserRepository $userRepository, AuditLogService $auditLogService)
    {
        $this->userRepository = $userRepository;
        $this->auditLogService = $auditLogService;
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/login' 
            && $request->isMethod('POST')
            && $request->headers->get('Content-Type') === 'application/json';
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['_username']) || !isset($data['_password'])) {
            throw new CustomUserMessageAuthenticationException('Missing credentials');
        }

        $email = $data['_username'];
        $password = $data['_password'];

        // Create a Passport with user loader and password credentials
        return new Passport(
            new UserBadge($email, function($userIdentifier) {
                $user = $this->userRepository->findOneByEmail($userIdentifier);
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials');
                }
                return $user;
            }),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        
        // Store user in session - CRITICAL for session persistence
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        
        $userData = [
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles()),
            'isDoctor' => in_array('ROLE_DOCTOR', $user->getRoles()),
            'isNurse' => in_array('ROLE_NURSE', $user->getRoles()),
            'isReceptionist' => in_array('ROLE_RECEPTIONIST', $user->getRoles()),
            'isPatient' => in_array('ROLE_PATIENT', $user->getRoles()),
        ];
        
        $session->set('user', $userData);
        
        // Force session to save
        $session->save();
        
        error_log("JsonLoginAuthenticator::onAuthenticationSuccess - Session ID: " . $session->getId());
        error_log("JsonLoginAuthenticator::onAuthenticationSuccess - User data stored: " . json_encode($userData));
        
        // Log successful login
        $this->auditLogService->logSecurityEvent(
            $user,
            'LOGIN',
            ['description' => 'Successful login', 'sessionId' => $session->getId()]
        );
        
        return new JsonResponse([
            'success' => true,
            'user' => $userData,
            'sessionId' => $session->getId() // For debugging
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'error' => 'Invalid credentials',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}

