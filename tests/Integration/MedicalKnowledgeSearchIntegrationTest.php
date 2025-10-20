<?php

namespace App\Tests\Integration;

use App\Document\MedicalKnowledge;
use App\Document\User;
use App\Repository\MedicalKnowledgeRepository;
use App\Service\VectorSearchService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MedicalKnowledgeSearchIntegrationTest extends WebTestCase
{
    private $client;
    private DocumentManager $documentManager;
    private MedicalKnowledgeRepository $medicalKnowledgeRepository;
    private VectorSearchService $vectorSearchService;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        
        $this->documentManager = $container->get('doctrine_mongodb.odm.document_manager');
        $this->medicalKnowledgeRepository = $container->get(MedicalKnowledgeRepository::class);
        $this->vectorSearchService = $container->get(VectorSearchService::class);
        
        // Clear test data
        $this->clearTestData();
        
        // Create test medical knowledge entries
        $this->createTestMedicalKnowledgeEntries();
    }

    protected function tearDown(): void
    {
        $this->clearTestData();
    }

    /**
     * Test 1: Complete Medical Knowledge Search Workflow
     */
    public function testCompleteMedicalKnowledgeSearchWorkflow(): void
    {
        // Step 1: Login as doctor
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Step 2: Search for hypertension information
        $searchQuery = [
            'query' => 'hypertension treatment guidelines',
            'limit' => 10,
            'filters' => [
                'specialties' => ['cardiology'],
                'evidenceLevel' => ['4', '5'] // Systematic reviews and meta-analyses
            ]
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($searchQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Step 3: Get clinical decision support
        $cdsQuery = [
            'patientCondition' => 'hypertension',
            'currentMedications' => ['lisinopril'],
            'symptoms' => ['high blood pressure', 'headaches']
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/clinical-decision-support', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($cdsQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('recommendations', $responseData['data']);
        
        // Step 4: Check drug interactions
        $drugInteractionQuery = [
            'medications' => ['lisinopril', 'metformin', 'aspirin']
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/drug-interactions', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($drugInteractionQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('interactions', $responseData['data']);
        
        // Step 5: Get treatment guidelines
        $guidelinesQuery = [
            'condition' => 'hypertension',
            'patientAge' => 65,
            'comorbidities' => ['diabetes']
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/treatment-guidelines', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($guidelinesQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('guidelines', $responseData['data']);
        
        // Step 6: Get diagnostic criteria
        $diagnosticQuery = [
            'condition' => 'hypertension',
            'patientData' => [
                'systolicBP' => 150,
                'diastolicBP' => 95,
                'age' => 65
            ]
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/diagnostic-criteria', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($diagnosticQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('criteria', $responseData['data']);
    }

    /**
     * Test 2: Role-Based Access Control in Medical Knowledge Search
     */
    public function testRoleBasedAccessControlInMedicalKnowledgeSearch(): void
    {
        // Test Doctor Access
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $searchQuery = ['query' => 'diabetes management'];
        
        // Doctor can search
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($searchQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Doctor can get clinical decision support
        $this->client->request('POST', '/api/medical-knowledge/clinical-decision-support', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['patientCondition' => 'diabetes']));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Test Nurse Access
        $nurseUser = $this->createTestUser(['ROLE_NURSE']);
        $this->loginUser($nurseUser);
        
        // Nurse cannot search
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($searchQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Nurse can check drug interactions
        $this->client->request('POST', '/api/medical-knowledge/drug-interactions', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['medications' => ['metformin']]));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Test Admin Access
        $adminUser = $this->createTestUser(['ROLE_ADMIN']);
        $this->loginUser($adminUser);
        
        // Admin can search
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($searchQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Admin cannot get clinical decision support
        $this->client->request('POST', '/api/medical-knowledge/clinical-decision-support', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['patientCondition' => 'diabetes']));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Test Receptionist Access
        $receptionistUser = $this->createTestUser(['ROLE_RECEPTIONIST']);
        $this->loginUser($receptionistUser);
        
        // Receptionist cannot access any medical knowledge features
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($searchQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Test 3: Vector Search and Semantic Search
     */
    public function testVectorSearchAndSemanticSearch(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test semantic search with natural language queries
        $semanticQueries = [
            'What are the latest treatments for high blood pressure?',
            'How to manage diabetes in elderly patients?',
            'Side effects of ACE inhibitors',
            'Best practices for heart disease prevention'
        ];
        
        foreach ($semanticQueries as $query) {
            $searchQuery = [
                'query' => $query,
                'limit' => 5,
                'searchType' => 'semantic'
            ];
            
            $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode($searchQuery));
            
            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
            
            $responseData = json_decode($response->getContent(), true);
            $this->assertTrue($responseData['success']);
            $this->assertGreaterThan(0, count($responseData['data']));
        }
        
        // Test keyword search
        $keywordQuery = [
            'query' => 'hypertension ACE inhibitors',
            'limit' => 5,
            'searchType' => 'keyword'
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($keywordQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Test hybrid search (semantic + keyword)
        $hybridQuery = [
            'query' => 'diabetes management guidelines',
            'limit' => 10,
            'searchType' => 'hybrid',
            'semanticWeight' => 0.7,
            'keywordWeight' => 0.3
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($hybridQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
    }

    /**
     * Test 4: Medical Knowledge Management (Create, Edit, Delete)
     */
    public function testMedicalKnowledgeManagement(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test creating new medical knowledge entry
        $newKnowledgeData = [
            'title' => 'New Treatment Protocol for Hypertension',
            'content' => 'This is a comprehensive guide for treating hypertension in patients with diabetes.',
            'summary' => 'Updated treatment protocol for hypertension management',
            'tags' => ['hypertension', 'diabetes', 'treatment'],
            'specialties' => ['cardiology', 'endocrinology'],
            'source' => 'Medical Journal of Cardiology',
            'sourceUrl' => 'https://example.com/article',
            'confidenceLevel' => 8,
            'evidenceLevel' => 4,
            'relatedConditions' => ['hypertension', 'diabetes'],
            'relatedMedications' => ['lisinopril', 'metformin'],
            'relatedProcedures' => ['blood pressure monitoring'],
            'requiresReview' => false
        ];
        
        $this->client->request('POST', '/api/medical-knowledge', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($newKnowledgeData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $knowledgeId = $responseData['data']['id'];
        
        // Test viewing the created entry
        $this->client->request('GET', "/api/medical-knowledge/{$knowledgeId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($newKnowledgeData['title'], $responseData['data']['title']);
        
        // Test editing the entry
        $updatedData = [
            'title' => 'Updated Treatment Protocol for Hypertension',
            'content' => 'This is an updated comprehensive guide for treating hypertension in patients with diabetes.',
            'summary' => 'Updated treatment protocol for hypertension management - Revised',
            'confidenceLevel' => 9,
            'evidenceLevel' => 5
        ];
        
        $this->client->request('PUT', "/api/medical-knowledge/{$knowledgeId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($updatedData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($updatedData['title'], $responseData['data']['title']);
        
        // Test deleting the entry
        $this->client->request('DELETE', "/api/medical-knowledge/{$knowledgeId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        
        // Verify entry is deleted
        $this->client->request('GET', "/api/medical-knowledge/{$knowledgeId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Test 5: Medical Knowledge Statistics and Analytics
     */
    public function testMedicalKnowledgeStatisticsAndAnalytics(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test getting search statistics
        $this->client->request('GET', '/api/medical-knowledge/stats');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('totalEntries', $responseData['data']);
        $this->assertArrayHasKey('searchCount', $responseData['data']);
        $this->assertArrayHasKey('topQueries', $responseData['data']);
        $this->assertArrayHasKey('specialtyDistribution', $responseData['data']);
        
        // Test getting usage analytics
        $this->client->request('GET', '/api/medical-knowledge/analytics', [
            'period' => '30d',
            'groupBy' => 'day'
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('usageData', $responseData['data']);
        
        // Test getting content quality metrics
        $this->client->request('GET', '/api/medical-knowledge/quality-metrics');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('averageConfidenceLevel', $responseData['data']);
        $this->assertArrayHasKey('evidenceLevelDistribution', $responseData['data']);
        $this->assertArrayHasKey('reviewRequiredCount', $responseData['data']);
    }

    /**
     * Test 6: Error Handling in Medical Knowledge Search
     */
    public function testErrorHandlingInMedicalKnowledgeSearch(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test invalid search query
        $invalidQuery = [
            'query' => '', // Invalid: empty query
            'limit' => -1, // Invalid: negative limit
            'filters' => 'invalid' // Invalid: should be array
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($invalidQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
        
        // Test accessing non-existent medical knowledge entry
        $nonExistentId = new ObjectId();
        $this->client->request('GET', "/api/medical-knowledge/{$nonExistentId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        
        // Test unauthorized access
        $unauthorizedUser = $this->createTestUser(['ROLE_PATIENT']);
        $this->loginUser($unauthorizedUser);
        
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['query' => 'test']));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Test invalid clinical decision support query
        $this->loginUser($doctorUser);
        
        $invalidCdsQuery = [
            'patientCondition' => '', // Invalid: empty condition
            'currentMedications' => 'not-array', // Invalid: should be array
            'symptoms' => null // Invalid: should be array
        ];
        
        $this->client->request('POST', '/api/medical-knowledge/clinical-decision-support', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($invalidCdsQuery));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Helper method to create test medical knowledge entries
     */
    private function createTestMedicalKnowledgeEntries(): void
    {
        $entries = [
            [
                'title' => 'Hypertension Management Guidelines',
                'content' => 'Comprehensive guidelines for managing hypertension in adult patients.',
                'summary' => 'Evidence-based guidelines for hypertension treatment',
                'tags' => ['hypertension', 'cardiovascular', 'treatment'],
                'specialties' => ['cardiology'],
                'source' => 'American Heart Association',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'relatedConditions' => ['hypertension', 'cardiovascular disease'],
                'relatedMedications' => ['lisinopril', 'amlodipine'],
                'relatedProcedures' => ['blood pressure monitoring']
            ],
            [
                'title' => 'Diabetes Type 2 Treatment Protocol',
                'content' => 'Step-by-step protocol for managing Type 2 diabetes.',
                'summary' => 'Comprehensive diabetes management protocol',
                'tags' => ['diabetes', 'endocrinology', 'treatment'],
                'specialties' => ['endocrinology'],
                'source' => 'American Diabetes Association',
                'confidenceLevel' => 8,
                'evidenceLevel' => 4,
                'relatedConditions' => ['diabetes', 'insulin resistance'],
                'relatedMedications' => ['metformin', 'insulin'],
                'relatedProcedures' => ['glucose monitoring']
            ],
            [
                'title' => 'Drug Interactions: ACE Inhibitors',
                'content' => 'Comprehensive guide to drug interactions with ACE inhibitors.',
                'summary' => 'Drug interaction guide for ACE inhibitors',
                'tags' => ['drug interactions', 'ACE inhibitors', 'pharmacology'],
                'specialties' => ['pharmacology', 'cardiology'],
                'source' => 'Drug Interaction Database',
                'confidenceLevel' => 7,
                'evidenceLevel' => 3,
                'relatedConditions' => ['hypertension', 'heart failure'],
                'relatedMedications' => ['lisinopril', 'enalapril', 'potassium'],
                'relatedProcedures' => ['drug monitoring']
            ]
        ];
        
        foreach ($entries as $entryData) {
            $knowledge = new MedicalKnowledge();
            $knowledge->setTitle($entryData['title']);
            $knowledge->setContent($entryData['content']);
            $knowledge->setSummary($entryData['summary']);
            $knowledge->setTags($entryData['tags']);
            $knowledge->setSpecialties($entryData['specialties']);
            $knowledge->setSource($entryData['source']);
            $knowledge->setConfidenceLevel($entryData['confidenceLevel']);
            $knowledge->setEvidenceLevel($entryData['evidenceLevel']);
            $knowledge->setRelatedConditions($entryData['relatedConditions']);
            $knowledge->setRelatedMedications($entryData['relatedMedications']);
            $knowledge->setRelatedProcedures($entryData['relatedProcedures']);
            $knowledge->setRequiresReview(false);
            $knowledge->setIsActive(true);
            $knowledge->setCreatedAt(new UTCDateTime());
            $knowledge->setCreatedBy(new ObjectId());
            
            $this->documentManager->persist($knowledge);
        }
        
        $this->documentManager->flush();
    }

    /**
     * Helper method to create a test user
     */
    private function createTestUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password');
        $user->setRoles($roles);
        
        $this->documentManager->persist($user);
        $this->documentManager->flush();
        
        return $user;
    }

    /**
     * Helper method to login a user
     */
    private function loginUser(User $user): void
    {
        $this->client->loginUser($user);
    }

    /**
     * Helper method to clear test data
     */
    private function clearTestData(): void
    {
        // Clear medical knowledge
        $this->documentManager->getSchemaManager()->dropDocumentCollection(MedicalKnowledge::class);
        
        // Clear users
        $this->documentManager->getSchemaManager()->dropDocumentCollection(User::class);
        
        // Clear audit logs
        $this->documentManager->getSchemaManager()->dropDocumentCollection(\App\Document\AuditLog::class);
    }
}
