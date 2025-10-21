<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        
        // Store user data in session for future requests
        $session = $request->getSession();
        $session->set('user', [
            'email' => $user->getUserIdentifier(),
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles())
        ]);
        
        // For API requests, return a JSON response instead of redirecting
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse([
                'success' => true,
                'user' => [
                    'email' => $user->getUserIdentifier(),
                    'username' => $user->getUserIdentifier(),
                    'roles' => $user->getRoles(),
                    'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles())
                ]
            ]);
        }

        // For web requests, redirect to the patients page
        return new RedirectResponse($this->urlGenerator->generate('app_patients'));
    }
}
