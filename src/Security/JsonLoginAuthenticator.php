<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Repository\MockUserRepository;
use App\Service\AuditLogService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
use Psr\Log\LoggerInterface;

class JsonLoginAuthenticator extends AbstractAuthenticator
{
    private $userRepository;
    private $mockUserRepository;
    private $auditLogService;
    private $params;
    private $logger;
    private $mongodbDisabled;

    public function __construct(
        UserRepository $userRepository, 
        MockUserRepository $mockUserRepository,
        AuditLogService $auditLogService,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->mockUserRepository = $mockUserRepository;
        $this->auditLogService = $auditLogService;
        $this->params = $params;
        $this->logger = $logger;
        
        // Check if MongoDB is disabled
        $this->mongodbDisabled = false;
        try {
            if ($params->has('mongodb_disabled')) {
                $this->mongodbDisabled = $params->get('mongodb_disabled');
                $logger->info('MongoDB disabled in authenticator: ' . ($this->mongodbDisabled ? 'true' : 'false'));
            }
        } catch (\Exception $e) {
            $logger->warning('Failed to get MongoDB disabled parameter: ' . $e->getMessage());
        }
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/login' 
            && $request->isMethod('POST');
            // Removed content type check to avoid login failures
            // && $request->headers->get('Content-Type') === 'application/json';
    }

    public function authenticate(Request $request): Passport
    {
        // Always try to parse JSON first, as our frontend uses JSON
        try {
            $jsonData = json_decode($request->getContent(), true);
            if ($jsonData) {
                // Try both email/password and _username/_password formats
                if (isset($jsonData['email']) && isset($jsonData['password'])) {
                    $email = $jsonData['email'];
                    $password = $jsonData['password'];
                    return $this->createPassport($email, $password);
                } elseif (isset($jsonData['_username']) && isset($jsonData['_password'])) {
                    $email = $jsonData['_username'];
                    $password = $jsonData['_password'];
                    return $this->createPassport($email, $password);
                }
            }
        } catch (\Exception $e) {
            // If JSON parsing fails, continue to try other methods
        }
        
        // Try form data as fallback
        if ($request->request->has('_username') && $request->request->has('_password')) {
            $email = $request->request->get('_username');
            $password = $request->request->get('_password');
            return $this->createPassport($email, $password);
        }
        
        // If we get here, credentials are missing
        throw new CustomUserMessageAuthenticationException('Missing credentials');
    }
    
    private function createPassport(string $email, string $password): Passport
    {
        return new Passport(
            new UserBadge($email, function($userIdentifier) {
                // Use the appropriate repository based on MongoDB status
                $this->logger->info('Looking up user: ' . $userIdentifier . ', MongoDB disabled: ' . ($this->mongodbDisabled ? 'true' : 'false'));
                
                try {
                    // Always check MockUserRepository first when MongoDB is disabled
                    if ($this->mongodbDisabled) {
                        $this->logger->info('Using mock repository in disabled MongoDB mode');
                        $user = $this->mockUserRepository->findOneByEmail($userIdentifier);
                    } else {
                        // Try real repository first, fallback to mock if MongoDB fails
                        try {
                            $user = $this->userRepository->findOneByEmail($userIdentifier);
                        } catch (\Exception $repoException) {
                            $this->logger->error('MongoDB repository failed: ' . $repoException->getMessage());
                            $this->logger->info('Falling back to mock repository');
                            $user = $this->mockUserRepository->findOneByEmail($userIdentifier);
                        }
                    }
                    
                    if (!$user) {
                        $this->logger->warning('User not found: ' . $userIdentifier);
                        throw new CustomUserMessageAuthenticationException('Invalid credentials');
                    }
                    
                    return $user;
                } catch (\Exception $e) {
                    $this->logger->error('Error during user lookup: ' . $e->getMessage());
                    if (strpos($e->getMessage(), 'MongoDB') !== false) {
                        $this->logger->error('MongoDB error during authentication, trying mock repository');
                        // If MongoDB fails, try mock repository as fallback
                        $user = $this->mockUserRepository->findOneByEmail($userIdentifier);
                        if ($user) {
                            return $user;
                        }
                    }
                    throw new CustomUserMessageAuthenticationException('Authentication error');
                }
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
            'patientId' => $user->getPatientId() ? (string)$user->getPatientId() : null,
        ];
        
        $session->set('user', $userData);
        
        // Force session to save
        $session->save();
        
        $this->logger->info("JsonLoginAuthenticator::onAuthenticationSuccess - Session ID: " . $session->getId());
        $this->logger->info("JsonLoginAuthenticator::onAuthenticationSuccess - User data stored for: " . $userData['email']);
        
        // Log successful login (only if MongoDB is enabled, otherwise skip audit logging)
        if (!$this->mongodbDisabled) {
            try {
                $this->auditLogService->logSecurityEvent(
                    $user,
                    'LOGIN',
                    ['description' => 'Successful login', 'sessionId' => $session->getId()]
                );
            } catch (\Exception $e) {
                $this->logger->warning('Failed to write audit log: ' . $e->getMessage());
            }
        } else {
            $this->logger->info('Skipping audit logging because MongoDB is disabled');
        }
        
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

