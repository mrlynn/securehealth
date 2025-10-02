<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class HealthController extends AbstractController
{
    /**
     * Health check endpoint
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'service' => 'MongoDB Queryable Encryption Demo',
            'version' => '1.0.0'
        ]);
    }
}