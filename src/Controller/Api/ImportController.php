<?php

namespace App\Controller\Api;

use App\Service\PatientImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for patient data import operations
 */
#[Route('/api/import')]
class ImportController extends AbstractController
{
    private PatientImportService $importService;

    public function __construct(PatientImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Import patients from a CSV file
     */
    #[Route('/csv', name: 'import_csv', methods: ['POST'])]
    public function importCsv(Request $request): JsonResponse
    {
        // Only doctors can import patients
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');

        // Get uploaded file
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check file type
        $mimeType = $file->getMimeType();
        if ($mimeType !== 'text/csv' && $mimeType !== 'text/plain') {
            return $this->json([
                'success' => false,
                'message' => 'Invalid file type. Expected CSV file.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get import options from request
        $options = [];
        
        // CSV specific options
        if ($request->request->has('delimiter')) {
            $options['delimiter'] = $request->request->get('delimiter');
        }
        
        if ($request->request->has('enclosure')) {
            $options['enclosure'] = $request->request->get('enclosure');
        }
        
        if ($request->request->has('headerRow')) {
            $options['headerRow'] = (bool)$request->request->get('headerRow');
        }
        
        // Common options
        if ($request->request->has('skipDuplicates')) {
            $options['skipDuplicates'] = (bool)$request->request->get('skipDuplicates');
        }
        
        if ($request->request->has('batchSize')) {
            $options['batchSize'] = (int)$request->request->get('batchSize');
        }
        
        // Field mapping (if provided)
        if ($request->request->has('fieldMapping')) {
            $fieldMappingJson = $request->request->get('fieldMapping');
            $fieldMapping = json_decode($fieldMappingJson, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($fieldMapping)) {
                $options['fieldMapping'] = $fieldMapping;
            }
        }

        try {
            $result = $this->importService->importFromCsv($file, $options);

            return $this->json([
                'success' => true,
                'message' => "Import completed: {$result['imported']} imported, {$result['skipped']} skipped",
                'result' => [
                    'total' => $result['total'],
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'errors' => array_slice($result['errors'], 0, 100) // Limit errors to avoid huge responses
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import patients from a JSON file
     */
    #[Route('/json', name: 'import_json', methods: ['POST'])]
    public function importJson(Request $request): JsonResponse
    {
        // Only doctors can import patients
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');

        // Get uploaded file
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check file type
        $mimeType = $file->getMimeType();
        if ($mimeType !== 'application/json' && $mimeType !== 'text/plain') {
            return $this->json([
                'success' => false,
                'message' => 'Invalid file type. Expected JSON file.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get import options from request
        $options = [];
        
        // JSON specific options
        if ($request->request->has('rootPath')) {
            $options['rootPath'] = $request->request->get('rootPath');
        }
        
        // Common options
        if ($request->request->has('skipDuplicates')) {
            $options['skipDuplicates'] = (bool)$request->request->get('skipDuplicates');
        }
        
        if ($request->request->has('batchSize')) {
            $options['batchSize'] = (int)$request->request->get('batchSize');
        }
        
        // Field mapping (if provided)
        if ($request->request->has('fieldMapping')) {
            $fieldMappingJson = $request->request->get('fieldMapping');
            $fieldMapping = json_decode($fieldMappingJson, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($fieldMapping)) {
                $options['fieldMapping'] = $fieldMapping;
            }
        }

        try {
            $result = $this->importService->importFromJson($file, $options);

            return $this->json([
                'success' => true,
                'message' => "Import completed: {$result['imported']} imported, {$result['skipped']} skipped",
                'result' => [
                    'total' => $result['total'],
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'errors' => array_slice($result['errors'], 0, 100) // Limit errors to avoid huge responses
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}