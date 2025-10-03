<?php

namespace App\EventSubscriber;

use App\Service\AuditLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class AuditLogSubscriber implements EventSubscriberInterface
{
    private AuditLogService $auditLogService;
    private TokenStorageInterface $tokenStorage;
    
    public function __construct(AuditLogService $auditLogService, TokenStorageInterface $tokenStorage)
    {
        $this->auditLogService = $auditLogService;
        $this->tokenStorage = $tokenStorage;
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
    }
    
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $exception = $event->getException();
        $request = $event->getRequest();
        
        $username = $request->request->get('_username', 'unknown');
        
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
    }

    public function onLogout(LogoutEvent $event): void
    {
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
    }

    public function onKernelRequest(RequestEvent $event): void
    {
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
    }
}