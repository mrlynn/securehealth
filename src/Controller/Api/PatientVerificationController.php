<?php

namespace App\Controller\Api;

use App\Service\PatientVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/patients/{patientId}/verify', name: 'patient_verification_')]
class PatientVerificationController extends AbstractController
{
    private PatientVerificationService $verificationService;

    public function __construct(PatientVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Verify patient identity
     */
    #[Route('', name: 'verify', methods: ['POST'])]
    public function verifyIdentity(string $patientId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        // Check if verification is required for this user role
        if (!$this->verificationService->isVerificationRequired($user)) {
            return $this->json([
                'success' => true,
                'message' => 'Verification not required for your role',
                'verificationRequired' => false
            ]);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (!isset($data['birthDate']) || !isset($data['lastFourSSN'])) {
            return $this->json([
                'message' => 'Birth date and last 4 digits of SSN are required',
                'requiredFields' => ['birthDate', 'lastFourSSN']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate birth date format
        $birthDate = $data['birthDate'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            return $this->json([
                'message' => 'Birth date must be in YYYY-MM-DD format'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate last 4 SSN format
        $lastFourSSN = $data['lastFourSSN'];
        if (!preg_match('/^\d{4}$/', $lastFourSSN)) {
            return $this->json([
                'message' => 'Last 4 digits of SSN must be exactly 4 digits'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Perform verification
        $result = $this->verificationService->verifyPatientIdentity(
            $patientId,
            $birthDate,
            $lastFourSSN,
            $user
        );

        if ($result['success']) {
            return $this->json([
                'success' => true,
                'message' => $result['message'],
                'verificationRequired' => true,
                'patient' => $result['patient']->toArray($user)
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => $result['message'],
                'verificationRequired' => true
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Get verification requirements
     */
    #[Route('/requirements', name: 'requirements', methods: ['GET'])]
    public function getRequirements(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $verificationRequired = $this->verificationService->isVerificationRequired($user);
        $requirements = $this->verificationService->getVerificationRequirements();

        return $this->json([
            'verificationRequired' => $verificationRequired,
            'requirements' => $requirements,
            'message' => $verificationRequired 
                ? 'Patient identity verification is required for your role'
                : 'Patient identity verification is not required for your role'
        ]);
    }

    /**
     * Check if verification is required for a specific patient
     */
    #[Route('/check', name: 'check', methods: ['GET'])]
    public function checkVerificationRequired(string $patientId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $verificationRequired = $this->verificationService->isVerificationRequired($user);

        return $this->json([
            'patientId' => $patientId,
            'verificationRequired' => $verificationRequired,
            'message' => $verificationRequired 
                ? 'Patient identity verification is required'
                : 'Patient identity verification is not required for your role'
        ]);
    }
}
