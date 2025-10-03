<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/debug')]
class SessionDebugController extends AbstractController
{
    #[Route('/session', name: 'api_debug_session', methods: ['GET'])]
    public function debugSession(Request $request): JsonResponse
    {
        $session = $request->getSession();
        
        // Get all session data
        $sessionData = $session->all();
        
        // Get session metadata
        $sessionInfo = [
            'session_id' => $session->getId(),
            'session_name' => session_name(),
            'session_cookie_params' => session_get_cookie_params(),
            'session_data' => $sessionData,
            'session_status' => session_status(),
            'session_user' => $session->get('user'),
            'environment' => [
                'APP_ENV' => $_ENV['APP_ENV'] ?? 'not_set',
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not_set',
                'SESSION_COOKIE_DOMAIN' => $_ENV['SESSION_COOKIE_DOMAIN'] ?? 'not_set'
            ]
        ];
        
        return new JsonResponse($sessionInfo);
    }
    
    #[Route('/set-session-test', name: 'api_debug_set_session', methods: ['GET'])]
    public function setSessionTest(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $session->set('test_value', 'This is a test value set at ' . date('Y-m-d H:i:s'));
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Test session value set',
            'session_id' => $session->getId()
        ]);
    }
    
    #[Route('/get-session-test', name: 'api_debug_get_session', methods: ['GET'])]
    public function getSessionTest(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $testValue = $session->get('test_value', 'No test value found');
        
        return new JsonResponse([
            'success' => true,
            'test_value' => $testValue,
            'session_id' => $session->getId()
        ]);
    }
}