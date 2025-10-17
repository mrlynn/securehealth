<?php

namespace App\EventSubscriber;

use App\Service\AuditLogService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Psr\Log\LoggerInterface;

class AuditLogSubscriber implements EventSubscriberInterface
{
    private AuditLogService $auditLogService;
    private TokenStorageInterface $tokenStorage;
    private bool $mongodbDisabled = false;
    private LoggerInterface $logger;
    
    public function __construct(
        AuditLogService $auditLogService, 
        TokenStorageInterface $tokenStorage,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->auditLogService = $auditLogService;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
        
        // Check if MongoDB is disabled
        try {
            if ($params->has('MONGODB_DISABLED')) {
                $this->mongodbDisabled = filter_var($params->get('MONGODB_DISABLED'), FILTER_VALIDATE_BOOLEAN);
                $logger->info('AuditLogSubscriber MongoDB disabled: ' . ($this->mongodbDisabled ? 'true' : 'false'));
            } else if ($params->has('mongodb_disabled')) {
                $this->mongodbDisabled = filter_var($params->get('mongodb_disabled'), FILTER_VALIDATE_BOOLEAN);
                $logger->info('AuditLogSubscriber MongoDB disabled (from params): ' . ($this->mongodbDisabled ? 'true' : 'false'));
            } else if (isset($_ENV['MONGODB_DISABLED'])) {
                $this->mongodbDisabled = filter_var($_ENV['MONGODB_DISABLED'], FILTER_VALIDATE_BOOLEAN);
                $logger->info('AuditLogSubscriber MongoDB disabled (from env): ' . ($this->mongodbDisabled ? 'true' : 'false'));
            }
        } catch (\Exception $e) {
            $logger->warning('Failed to check MongoDB disabled status: ' . $e->getMessage());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // Skip audit logging if MongoDB is disabled
        if ($this->mongodbDisabled) {
            $this->logger->info('Skipping login success audit logging because MongoDB is disabled');
            return;
        }
        
        try {
            $user = $event->getUser();
            $username = $user->getUserIdentifier();
            
            $this->auditLogService->logSecurityEvent(
                $user,
                'LOGIN',
                [
                    'description' => 'Successful login for user ' . $username,
                    'roles' => $user->getRoles(),
                    'ip' => $event->getRequest()->getClientIp()
                ]
            );
        } catch (\Exception $e) {
            // Log but don't fail authentication
            $this->logger->error('Failed to log login success: ' . $e->getMessage());
        }
    }
    
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // Skip audit logging if MongoDB is disabled
        if ($this->mongodbDisabled) {
            $this->logger->info('Skipping login failure audit logging because MongoDB is disabled');
            return;
        }
        
        try {
            $exception = $event->getException();
            $request = $event->getRequest();
            
            // Try to get username from multiple sources
            $username = 'unknown';
            
            // Check for form data
            if ($request->request->has('_username')) {
                $username = $request->request->get('_username');
            } else {
                // Try JSON data
                try {
                    $jsonData = json_decode($request->getContent(), true);
                    if (isset($jsonData['_username'])) {
                        $username = $jsonData['_username'];
                    }
                } catch (\Exception $e) {
                    // Ignore JSON parsing errors
                }
            }
            
            // Create an anonymous user for failed login attempts
            $anonymousUser = new \Symfony\Component\Security\Core\User\InMemoryUser($username, null, []);
            
            $this->auditLogService->logSecurityEvent(
                $anonymousUser,
                'LOGIN_FAILURE',
                [
                    'description' => 'Failed login attempt for user ' . $username . ': ' . $exception->getMessage(),
                    'ip' => $request->getClientIp(),
                    'error' => $exception->getMessage()
                ]
            );
        } catch (\Exception $e) {
            // Log but don't fail authentication
            $this->logger->error('Failed to log login failure: ' . $e->getMessage());
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        // Skip audit logging if MongoDB is disabled
        if ($this->mongodbDisabled) {
            $this->logger->info('Skipping logout audit logging because MongoDB is disabled');
            return;
        }
        
        try {
            $token = $event->getToken();
            
            if ($token) {
                $user = $token->getUser();
                $username = $user ? $user->getUserIdentifier() : 'unknown';
                
                $this->auditLogService->logSecurityEvent(
                    $user,
                    'LOGOUT',
                    [
                        'description' => 'User ' . $username . ' logged out',
                        'ip' => $event->getRequest()->getClientIp()
                    ]
                );
            }
        } catch (\Exception $e) {
            // Log but don't fail logout
            $this->logger->error('Failed to log logout: ' . $e->getMessage());
        }
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Skip audit logging if MongoDB is disabled
        if ($this->mongodbDisabled) {
            return; // Skip silently for API request logs - these are high volume
        }
        
        // Only log main requests, not sub-requests
        if (!$event->isMainRequest()) {
            return;
        }
        
        $request = $event->getRequest();
        
        // Skip logging for non-API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }
        
        // Skip logging for login/logout routes
        $route = $request->attributes->get('_route');
        if ($route && (str_starts_with($route, 'app_login') || str_starts_with($route, 'app_logout'))) {
            return;
        }
        
        try {
            $token = $this->tokenStorage->getToken();
            
            // Only log if user is authenticated
            if ($token && $token->getUser()) {
                $user = $token->getUser();
                $username = $user->getUserIdentifier();
                
                $this->auditLogService->log(
                    $user,
                    'API_REQUEST',
                    [
                        'description' => sprintf(
                            'API request: %s %s',
                            $request->getMethod(),
                            $request->getPathInfo()
                        ),
                        'entityType' => 'API',
                        'method' => $request->getMethod(),
                        'path' => $request->getPathInfo(),
                        'query' => $request->query->all(),
                        'ip' => $request->getClientIp(),
                        'roles' => $user->getRoles()
                    ]
                );
            }
        } catch (\Exception $e) {
            // Log but don't fail request handling
            $this->logger->error('Failed to log API request: ' . $e->getMessage());
        }
    }
}