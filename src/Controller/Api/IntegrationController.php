<?php

namespace App\Controller\Api;

use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use App\Service\ExternalSystemIntegrationService;
use MongoDB\BSON\ObjectId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for integrating with external systems
 */
#[Route('/api/integration')]
class IntegrationController extends AbstractController
{
    private ExternalSystemIntegrationService $integrationService;
    private PatientRepository $patientRepository;
    private AuditLogService $auditLogService;

    public function __construct(
        ExternalSystemIntegrationService $integrationService,
        PatientRepository $patientRepository,
        AuditLogService $auditLogService
    ) {
        $this->integrationService = $integrationService;
        $this->patientRepository = $patientRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * List available external systems
     */
    #[Route('/systems', name: 'integration_systems', methods: ['GET'])]
    public function listSystems(): JsonResponse
    {
        // Only doctors can view integration systems
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');

        $systems = $this->integrationService->getAvailableExternalSystems();

        // Log the access
        $this->auditLogService->log(
            $this->getUser(),
            'INTEGRATION_SYSTEMS_VIEW',
            [
                'description' => 'Listed available integration systems',
                'count' => count($systems)
            ]
        );

        return $this->json([
            'systems' => $systems
        ]);
    }

    /**
     * Import a patient from an external system
     */
    #[Route('/import/{systemId}/{externalPatientId}', name: 'integration_import_patient', methods: ['POST'])]
    public function importPatient(string $systemId, string $externalPatientId): JsonResponse
    {
        // Only doctors can import patients
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');

        $patient = $this->integrationService->importPatient($systemId, $externalPatientId);

        if (!$patient) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to import patient from external system'
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Patient imported successfully',
            'patient' => $patient->toArray($this->getUser())
        ]);
    }

    /**
     * Export a patient to an external system
     */
    #[Route('/export/{patientId}/{systemId}', name: 'integration_export_patient', methods: ['POST'])]
    public function exportPatient(string $patientId, string $systemId): JsonResponse
    {
        // Only doctors can export patients
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');

        try {
            $patient = $this->patientRepository->findById(new ObjectId($patientId));

            if (!$patient) {
                return $this->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $success = $this->integrationService->exportPatient($patient, $systemId);

            if (!$success) {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to export patient to external system'
                ], Response::HTTP_BAD_REQUEST);
            }

            return $this->json([
                'success' => true,
                'message' => 'Patient exported successfully',
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error exporting patient: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get patient external system information
     */
    #[Route('/patient/{patientId}/info', name: 'integration_patient_info', methods: ['GET'])]
    public function getPatientExternalInfo(string $patientId): JsonResponse
    {
        // Only doctors can view external system info
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');

        try {
            $patient = $this->patientRepository->findById(new ObjectId($patientId));

            if (!$patient) {
                return $this->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $info = $this->integrationService->getPatientExternalSystemInfo($patient);

            return $this->json([
                'success' => true,
                'patientId' => $patientId,
                'externalSystemInfo' => $info
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error getting patient external system info: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add or update an external system configuration
     */
    #[Route('/systems', name: 'integration_add_system', methods: ['POST'])]
    public function addSystem(Request $request): JsonResponse
    {
        // Only admins can add external systems
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['systemId']) || empty($data['config'])) {
            return $this->json([
                'success' => false,
                'message' => 'System ID and configuration are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->integrationService->addSystemConfiguration($data['systemId'], $data['config']);

            // Log the addition
            $this->auditLogService->log(
                $this->getUser(),
                'INTEGRATION_SYSTEM_ADD',
                [
                    'description' => 'Added or updated external system configuration',
                    'systemId' => $data['systemId']
                ]
            );

            return $this->json([
                'success' => true,
                'message' => 'External system configuration added successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error adding external system configuration: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}