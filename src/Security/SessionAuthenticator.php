<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class SessionAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): ?bool
    {
        // Only authenticate API requests (not login endpoint)
        $path = $request->getPathInfo();
        $shouldSupport = str_starts_with($path, '/api/') && 
                        !str_starts_with($path, '/api/login');
        
        error_log("SessionAuthenticator::supports - Path: $path, Should support: " . ($shouldSupport ? 'YES' : 'NO'));
        error_log("SessionAuthenticator::supports - Request URI: " . $request->getRequestUri());
        error_log("SessionAuthenticator::supports - Method: " . $request->getMethod());
        
        return $shouldSupport;
    }

    public function authenticate(Request $request): Passport
    {
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        $userData = $session->get('user');

        error_log("SessionAuthenticator::authenticate - Session data: " . json_encode($userData));

        if (!$userData) {
            error_log("SessionAuthenticator::authenticate - No session found");
            throw new CustomUserMessageAuthenticationException('No session found');
        }

        // Create a user badge that will load the user from session data
        $userBadge = new UserBadge($userData['email'], function() use ($userData) {
            return new SessionUser(
                $userData['email'],
                $userData['username'] ?? $userData['email'],
                $userData['roles'] ?? [],
                $userData['isPatient'] ?? false,
                $userData['patientId'] ?? null
            );
        });

        return new SelfValidatingPassport($userBadge);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Authentication successful, continue with the request
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Return 401 for API requests
        return new Response('Authentication required', Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // For API requests, return 401 instead of redirecting
        return new Response('Authentication required', Response::HTTP_UNAUTHORIZED);
    }
}
