<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        // For API requests, return a JSON response instead of redirecting
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            // This will be handled by the AuthController
            return new RedirectResponse('/api/login');
        }

        // For web requests, redirect back to login with error
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
