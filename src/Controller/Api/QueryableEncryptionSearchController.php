<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use App\Service\MongoDBEncryptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/api/encrypted-search', name: 'encrypted_search_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class QueryableEncryptionSearchController extends AbstractController
{
    public function __construct(
        private PatientRepository $patientRepository,
        private MongoDBEncryptionService $encryptionService,
        private AuditLogService $auditLogService,
        private LoggerInterface $logger
    ) {}

    /**
     * Equality search on encrypted fields
     * Demonstrates deterministic encryption for exact matches
     */
    #[Route('/equality', name: 'equality', methods: ['POST'])]
    public function equalitySearch(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validate input
            $criteria = $this->validateEqualityCriteria($data);
            
            if (empty($criteria)) {
                return $this->json([
                    'error' => 'At least one search criteria is required',
                    'message' => 'Please provide lastName, firstName, email, or phone for equality search'
                ], 400);
            }

            // Perform encrypted search
            $patients = $this->patientRepository->findByEqualityCriteria($criteria, $this->encryptionService);
            
            $searchTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log the search
            $this->auditLogService->log(
                $this->getUser(),
                'ENCRYPTED_EQUALITY_SEARCH',
                [
                    'description' => 'Performed equality search on encrypted fields',
                    'criteria' => $criteria,
                    'resultCount' => count($patients),
                    'searchTime' => $searchTime . 'ms'
                ]
            );

            // Convert to array with role-based filtering
            $patientsArray = array_map(function(Patient $patient) {
                return $patient->toArray($this->getUser());
            }, $patients);

            return $this->json([
                'success' => true,
                'searchType' => 'equality',
                'criteria' => $criteria,
                'results' => $patientsArray,
                'totalResults' => count($patients),
                'searchTime' => $searchTime,
                'encryptedFields' => ['lastName', 'firstName', 'email', 'phoneNumber'],
                'encryptionType' => 'deterministic',
                'message' => 'Equality search completed on encrypted data'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Equality search failed: ' . $e->getMessage());
            
            return $this->json([
                'error' => 'Search failed',
                'message' => 'An error occurred while performing the equality search',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Range search on encrypted fields
     * Demonstrates range encryption for comparison operations
     */
    #[Route('/range', name: 'range', methods: ['POST'])]
    public function rangeSearch(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validate input
            $criteria = $this->validateRangeCriteria($data);
            
            if (empty($criteria)) {
                return $this->json([
                    'error' => 'At least one range criteria is required',
                    'message' => 'Please provide birthDateFrom, birthDateTo, minAge, or maxAge for range search'
                ], 400);
            }

            // Perform encrypted range search
            $patients = $this->patientRepository->findByRangeCriteria($criteria, $this->encryptionService);
            
            $searchTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log the search
            $this->auditLogService->log(
                $this->getUser(),
                'ENCRYPTED_RANGE_SEARCH',
                [
                    'description' => 'Performed range search on encrypted fields',
                    'criteria' => $criteria,
                    'resultCount' => count($patients),
                    'searchTime' => $searchTime . 'ms'
                ]
            );

            // Convert to array with role-based filtering
            $patientsArray = array_map(function(Patient $patient) {
                return $patient->toArray($this->getUser());
            }, $patients);

            return $this->json([
                'success' => true,
                'searchType' => 'range',
                'criteria' => $criteria,
                'results' => $patientsArray,
                'totalResults' => count($patients),
                'searchTime' => $searchTime,
                'encryptedFields' => ['birthDate'],
                'encryptionType' => 'deterministic', // Note: Using deterministic encryption workaround
                'message' => 'Range search completed using deterministic encryption workaround',
                'note' => 'Range queries are implemented using client-side filtering due to deterministic encryption limitations'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Range search failed: ' . $e->getMessage());
            
            return $this->json([
                'error' => 'Search failed',
                'message' => 'An error occurred while performing the range search',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complex multi-field search
     * Demonstrates combining different encryption types in a single query
     */
    #[Route('/complex', name: 'complex', methods: ['POST'])]
    public function complexSearch(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validate input
            $criteria = $this->validateComplexCriteria($data);
            
            if (empty($criteria)) {
                return $this->json([
                    'error' => 'At least one search criteria is required',
                    'message' => 'Please provide lastName, email, minAge, phonePrefix, or birthYear for complex search'
                ], 400);
            }

            // Check if this search includes email domain search (not supported by deterministic encryption)
            $hasEmailDomainSearch = isset($criteria['email']) && !str_contains($criteria['email'], '@');
            
            if ($hasEmailDomainSearch) {
                // Email domain search requires frontend fallback since deterministic encryption can't handle partial matches
                $searchTime = round((microtime(true) - $startTime) * 1000, 2);
                
                return $this->json([
                    'success' => false,
                    'searchType' => 'complex',
                    'criteria' => $criteria,
                    'results' => [],
                    'totalResults' => 0,
                    'searchTime' => $searchTime,
                    'encryptedFields' => ['lastName', 'email', 'birthDate', 'phoneNumber'],
                    'encryptionTypes' => ['deterministic', 'range'],
                    'message' => 'Email domain search requires frontend fallback',
                    'requiresFallback' => true,
                    'fallbackReason' => 'Email domain search not supported by deterministic encryption'
                ], 200); // Return 200 but indicate fallback needed
            }

            // Perform complex encrypted search
            $patients = $this->patientRepository->findByComplexCriteria($criteria, $this->encryptionService);
            
            $searchTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log the search
            $this->auditLogService->log(
                $this->getUser(),
                'ENCRYPTED_COMPLEX_SEARCH',
                [
                    'description' => 'Performed complex multi-field search on encrypted data',
                    'criteria' => $criteria,
                    'resultCount' => count($patients),
                    'searchTime' => $searchTime . 'ms'
                ]
            );

            // Convert to array with role-based filtering
            $patientsArray = array_map(function(Patient $patient) {
                return $patient->toArray($this->getUser());
            }, $patients);

            return $this->json([
                'success' => true,
                'searchType' => 'complex',
                'criteria' => $criteria,
                'results' => $patientsArray,
                'totalResults' => count($patients),
                'searchTime' => $searchTime,
                'encryptedFields' => ['lastName', 'email', 'birthDate', 'phoneNumber'],
                'encryptionTypes' => ['deterministic', 'range'],
                'message' => 'Complex search completed on encrypted data'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Complex search failed: ' . $e->getMessage());
            
            return $this->json([
                'error' => 'Search failed',
                'message' => 'An error occurred while performing the complex search',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get search capabilities and encryption information
     */
    #[Route('/capabilities', name: 'capabilities', methods: ['GET'])]
    public function getCapabilities(): JsonResponse
    {
        return $this->json([
            'searchTypes' => [
                'equality' => [
                    'description' => 'Exact match searches using deterministic encryption',
                    'supportedFields' => ['lastName', 'firstName', 'email', 'phoneNumber'],
                    'encryptionType' => 'deterministic',
                    'example' => 'Find all patients with lastName "Smith"'
                ],
                'range' => [
                    'description' => 'Range queries using range encryption',
                    'supportedFields' => ['birthDate'],
                    'encryptionType' => 'range',
                    'example' => 'Find all patients born between 1980 and 1990'
                ],
                'complex' => [
                    'description' => 'Multi-field searches combining different encryption types',
                    'supportedFields' => ['lastName', 'email', 'birthDate', 'phoneNumber'],
                    'encryptionTypes' => ['deterministic', 'range'],
                    'example' => 'Find patients with lastName containing "John" and age > 30'
                ]
            ],
            'encryptionTypes' => [
                'deterministic' => [
                    'description' => 'Same input always produces same encrypted output',
                    'useCase' => 'Exact match searches',
                    'security' => 'Medium - allows pattern analysis'
                ],
                'range' => [
                    'description' => 'Enables comparison operations on encrypted data',
                    'useCase' => 'Range queries, sorting',
                    'security' => 'Medium - allows ordering analysis'
                ],
                'random' => [
                    'description' => 'Maximum security encryption',
                    'useCase' => 'Highly sensitive data (SSN, diagnosis)',
                    'security' => 'High - no search capabilities'
                ]
            ],
            'fieldEncryptionMap' => [
                'lastName' => 'deterministic',
                'firstName' => 'deterministic',
                'email' => 'deterministic',
                'phoneNumber' => 'deterministic',
                'birthDate' => 'deterministic', // Using deterministic for demo
                'ssn' => 'random',
                'diagnosis' => 'random',
                'medications' => 'random',
                'insuranceDetails' => 'random',
                'notes' => 'random'
            ]
        ]);
    }

    /**
     * Validate equality search criteria
     */
    private function validateEqualityCriteria(array $data): array
    {
        $criteria = [];
        
        if (!empty($data['lastName'])) {
            $criteria['lastName'] = trim($data['lastName']);
        }
        
        if (!empty($data['firstName'])) {
            $criteria['firstName'] = trim($data['firstName']);
        }
        
        if (!empty($data['email'])) {
            $email = trim($data['email']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $criteria['email'] = $email;
            }
        }
        
        if (!empty($data['phone'])) {
            $phone = trim($data['phone']);
            if (preg_match('/^\d{3}-\d{3}-\d{4}$/', $phone)) {
                $criteria['phone'] = $phone;
            }
        }
        
        return $criteria;
    }

    /**
     * Validate range search criteria
     */
    private function validateRangeCriteria(array $data): array
    {
        $criteria = [];
        
        if (!empty($data['birthDateFrom'])) {
            $criteria['birthDateFrom'] = $data['birthDateFrom'];
        }
        
        if (!empty($data['birthDateTo'])) {
            $criteria['birthDateTo'] = $data['birthDateTo'];
        }
        
        if (!empty($data['minAge'])) {
            $minAge = (int) $data['minAge'];
            if ($minAge >= 0 && $minAge <= 120) {
                $criteria['minAge'] = $minAge;
            }
        }
        
        if (!empty($data['maxAge'])) {
            $maxAge = (int) $data['maxAge'];
            if ($maxAge >= 0 && $maxAge <= 120) {
                $criteria['maxAge'] = $maxAge;
            }
        }
        
        return $criteria;
    }

    /**
     * Validate complex search criteria
     */
    private function validateComplexCriteria(array $data): array
    {
        $criteria = [];
        
        if (!empty($data['lastName'])) {
            $criteria['lastName'] = trim($data['lastName']);
        }
        
        if (!empty($data['email'])) {
            $criteria['email'] = trim($data['email']);
        }
        
        if (!empty($data['minAge'])) {
            $minAge = (int) $data['minAge'];
            if ($minAge >= 0 && $minAge <= 120) {
                $criteria['minAge'] = $minAge;
            }
        }
        
        if (!empty($data['phonePrefix'])) {
            $phonePrefix = trim($data['phonePrefix']);
            if (preg_match('/^\d{3}$/', $phonePrefix)) {
                $criteria['phonePrefix'] = $phonePrefix;
            }
        }
        
        if (!empty($data['birthYear'])) {
            $birthYear = (int) $data['birthYear'];
            if ($birthYear >= 1900 && $birthYear <= date('Y')) {
                $criteria['birthYear'] = $birthYear;
            }
        }
        
        return $criteria;
    }
}
