<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/debug')]
class DebugController extends AbstractController
{
    #[Route('/auth', name: 'debug_auth', methods: ['GET'])]
    public function debugAuth(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'authenticated' => $user !== null,
            'user' => $user ? [
                'identifier' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'class' => get_class($user)
            ] : null,
            'session_id' => $request->getSession()->getId(),
            'cookies' => $request->cookies->all(),
            'headers' => $request->headers->all()
        ]);
    }
}
