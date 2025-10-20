<?php

namespace App\Tests\Integration;

use App\Document\Patient;
use App\Document\User;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use App\Service\MongoDBEncryptionService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;

class PatientWorkflowIntegrationTest extends WebTestCase
{
    private $client;
    private DocumentManager $documentManager;
    private PatientRepository $patientRepository;
    private AuditLogService $auditLogService;
    private MongoDBEncryptionService $encryptionService;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        
        $this->documentManager = $container->get('doctrine_mongodb.odm.document_manager');
        $this->patientRepository = $container->get(PatientRepository::class);
        $this->auditLogService = $container->get(AuditLogService::class);
        $this->encryptionService = $container->get(MongoDBEncryptionService::class);
        
        // Clear test data
        $this->clearTestData();
    }

    protected function tearDown(): void
    {
        $this->clearTestData();
    }

    /**
     * Test 1: Complete Patient Workflow (Create → View → Edit → Delete)
     */
    public function testCompletePatientWorkflow(): void
    {
        // Step 1: Login as doctor
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Step 2: Create a new patient
        $patientData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'phoneNumber' => '555-123-4567',
            'birthDate' => '1990-01-01',
            'ssn' => '123-45-6789',
            'diagnosis' => 'Hypertension',
            'medications' => ['Lisinopril 10mg'],
            'insuranceDetails' => 'Blue Cross Blue Shield'
        ];
        
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        
        $patientId = $responseData['data']['id'];
        $this->assertNotNull($patientId);
        
        // Step 3: View the created patient
        $this->client->request('GET', "/api/patients/{$patientId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($patientData['firstName'], $responseData['data']['firstName']);
        $this->assertEquals($patientData['lastName'], $responseData['data']['lastName']);
        $this->assertEquals($patientData['email'], $responseData['data']['email']);
        
        // Step 4: Edit the patient
        $updatedData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'phoneNumber' => '555-123-4567',
            'birthDate' => '1990-01-01',
            'ssn' => '123-45-6789',
            'diagnosis' => 'Hypertension - Controlled',
            'medications' => ['Lisinopril 10mg', 'Metformin 500mg'],
            'insuranceDetails' => 'Blue Cross Blue Shield - Updated'
        ];
        
        $this->client->request('PUT', "/api/patients/{$patientId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($updatedData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($updatedData['diagnosis'], $responseData['data']['diagnosis']);
        $this->assertEquals($updatedData['medications'], $responseData['data']['medications']);
        
        // Step 5: Delete the patient
        $this->client->request('DELETE', "/api/patients/{$patientId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        
        // Step 6: Verify patient is deleted
        $this->client->request('GET', "/api/patients/{$patientId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Test 2: Role-Based Access Control in Patient Workflow
     */
    public function testRoleBasedAccessControlInPatientWorkflow(): void
    {
        // Create a patient first
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patientData = [
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phoneNumber' => '555-987-6543',
            'birthDate' => '1985-05-15',
            'ssn' => '987-65-4321',
            'diagnosis' => 'Diabetes Type 2',
            'medications' => ['Metformin 1000mg'],
            'insuranceDetails' => 'Aetna'
        ];
        
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientData));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $patientId = $responseData['data']['id'];
        
        // Test Nurse Access
        $nurseUser = $this->createTestUser(['ROLE_NURSE']);
        $this->loginUser($nurseUser);
        
        // Nurse can view patient
        $this->client->request('GET', "/api/patients/{$patientId}");
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Nurse can edit basic info but not medical data
        $nurseEditData = [
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phoneNumber' => '555-987-6543',
            'birthDate' => '1985-05-15',
            'ssn' => '987-65-4321',
            'diagnosis' => 'Diabetes Type 2',
            'medications' => ['Metformin 1000mg'],
            'insuranceDetails' => 'Aetna - Updated by Nurse'
        ];
        
        $this->client->request('PUT', "/api/patients/{$patientId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($nurseEditData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Test Receptionist Access
        $receptionistUser = $this->createTestUser(['ROLE_RECEPTIONIST']);
        $this->loginUser($receptionistUser);
        
        // Receptionist can view patient
        $this->client->request('GET', "/api/patients/{$patientId}");
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Receptionist can edit insurance
        $receptionistEditData = [
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phoneNumber' => '555-987-6543',
            'birthDate' => '1985-05-15',
            'ssn' => '987-65-4321',
            'diagnosis' => 'Diabetes Type 2',
            'medications' => ['Metformin 1000mg'],
            'insuranceDetails' => 'Aetna - Updated by Receptionist'
        ];
        
        $this->client->request('PUT', "/api/patients/{$patientId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($receptionistEditData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Test Admin Access
        $adminUser = $this->createTestUser(['ROLE_ADMIN']);
        $this->loginUser($adminUser);
        
        // Admin can view patient
        $this->client->request('GET', "/api/patients/{$patientId}");
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Admin cannot edit patient
        $this->client->request('PUT', "/api/patients/{$patientId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($receptionistEditData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Clean up
        $this->loginUser($doctorUser);
        $this->client->request('DELETE', "/api/patients/{$patientId}");
    }

    /**
     * Test 3: Patient Portal Self-Access Workflow
     */
    public function testPatientPortalSelfAccessWorkflow(): void
    {
        // Create a patient
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patientData = [
            'firstName' => 'Alice',
            'lastName' => 'Johnson',
            'email' => 'alice.johnson@example.com',
            'phoneNumber' => '555-456-7890',
            'birthDate' => '1992-03-20',
            'ssn' => '456-78-9012',
            'diagnosis' => 'Asthma',
            'medications' => ['Albuterol Inhaler'],
            'insuranceDetails' => 'Cigna'
        ];
        
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientData));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $patientId = $responseData['data']['id'];
        
        // Create a patient user linked to this patient
        $patientUser = $this->createTestUser(['ROLE_PATIENT']);
        $patientUser->setPatientId($patientId);
        $this->documentManager->persist($patientUser);
        $this->documentManager->flush();
        
        // Login as patient
        $this->loginUser($patientUser);
        
        // Patient can view their own record
        $this->client->request('GET', "/api/patient-portal/patient/{$patientId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($patientData['firstName'], $responseData['data']['firstName']);
        $this->assertEquals($patientData['lastName'], $responseData['data']['lastName']);
        
        // Patient can edit limited fields
        $patientEditData = [
            'phoneNumber' => '555-456-7890',
            'insuranceDetails' => 'Cigna - Updated by Patient'
        ];
        
        $this->client->request('PUT', "/api/patient-portal/patient/{$patientId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientEditData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Patient cannot access other patients' records
        $otherPatientId = new ObjectId();
        $this->client->request('GET', "/api/patient-portal/patient/{$otherPatientId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Clean up
        $this->loginUser($doctorUser);
        $this->client->request('DELETE', "/api/patients/{$patientId}");
    }

    /**
     * Test 4: Encryption Validation in Patient Workflow
     */
    public function testEncryptionValidationInPatientWorkflow(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patientData = [
            'firstName' => 'Bob',
            'lastName' => 'Wilson',
            'email' => 'bob.wilson@example.com',
            'phoneNumber' => '555-321-0987',
            'birthDate' => '1988-12-10',
            'ssn' => '321-09-8765',
            'diagnosis' => 'High Cholesterol',
            'medications' => ['Atorvastatin 20mg'],
            'insuranceDetails' => 'UnitedHealth'
        ];
        
        // Create patient
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientData));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $patientId = $responseData['data']['id'];
        
        // Verify encryption is working by checking raw database data
        $patient = $this->patientRepository->find($patientId);
        $this->assertNotNull($patient);
        
        // Check that sensitive fields are encrypted in the database
        if ($this->encryptionService->isEncryptionAvailable()) {
            // Verify that encrypted fields are not stored as plain text
            $rawDocument = $this->documentManager->getDocumentCollection(Patient::class)
                ->findOne(['_id' => new ObjectId($patientId)]);
            
            // SSN should be encrypted (not plain text)
            $this->assertNotEquals($patientData['ssn'], $rawDocument['ssn']);
            
            // Diagnosis should be encrypted
            $this->assertNotEquals($patientData['diagnosis'], $rawDocument['diagnosis']);
            
            // Medications should be encrypted
            $this->assertNotEquals($patientData['medications'], $rawDocument['medications']);
        }
        
        // Verify decryption works when retrieving data
        $this->client->request('GET', "/api/patients/{$patientId}");
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals($patientData['ssn'], $responseData['data']['ssn']);
        $this->assertEquals($patientData['diagnosis'], $responseData['data']['diagnosis']);
        $this->assertEquals($patientData['medications'], $responseData['data']['medications']);
        
        // Clean up
        $this->client->request('DELETE', "/api/patients/{$patientId}");
    }

    /**
     * Test 5: Audit Logging in Patient Workflow
     */
    public function testAuditLoggingInPatientWorkflow(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patientData = [
            'firstName' => 'Carol',
            'lastName' => 'Davis',
            'email' => 'carol.davis@example.com',
            'phoneNumber' => '555-654-3210',
            'birthDate' => '1995-07-25',
            'ssn' => '654-32-1098',
            'diagnosis' => 'Migraine',
            'medications' => ['Sumatriptan 50mg'],
            'insuranceDetails' => 'Humana'
        ];
        
        // Create patient
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientData));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $patientId = $responseData['data']['id'];
        
        // View patient
        $this->client->request('GET', "/api/patients/{$patientId}");
        
        // Edit patient
        $updatedData = $patientData;
        $updatedData['diagnosis'] = 'Migraine - Chronic';
        
        $this->client->request('PUT', "/api/patients/{$patientId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($updatedData));
        
        // Check audit logs
        $this->client->request('GET', '/api/audit-logs', [
            'entityType' => 'Patient',
            'entityId' => $patientId
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Verify audit log entries
        $auditLogs = $responseData['data'];
        $actions = array_column($auditLogs, 'actionType');
        
        $this->assertContains('PATIENT_CREATE', $actions);
        $this->assertContains('PATIENT_VIEW', $actions);
        $this->assertContains('PATIENT_EDIT', $actions);
        
        // Clean up
        $this->client->request('DELETE', "/api/patients/{$patientId}");
    }

    /**
     * Test 6: Error Handling in Patient Workflow
     */
    public function testErrorHandlingInPatientWorkflow(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test invalid patient data
        $invalidPatientData = [
            'firstName' => '', // Invalid: empty first name
            'lastName' => 'Test',
            'email' => 'invalid-email', // Invalid: malformed email
            'phoneNumber' => '123', // Invalid: wrong format
            'birthDate' => 'invalid-date', // Invalid: wrong format
            'ssn' => '123', // Invalid: wrong format
        ];
        
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($invalidPatientData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
        
        // Test accessing non-existent patient
        $nonExistentId = new ObjectId();
        $this->client->request('GET', "/api/patients/{$nonExistentId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        
        // Test unauthorized access
        $unauthorizedUser = $this->createTestUser(['ROLE_PATIENT']);
        $this->loginUser($unauthorizedUser);
        
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'test@example.com'
        ]));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
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
        // Create a SessionUser that matches our authenticator
        $sessionUser = new \App\Security\SessionUser(
            $user->getEmail(),
            $user->getUsername(),
            $user->getRoles(),
            $user->isPatient(),
            $user->getPatientId() ? (string)$user->getPatientId() : null
        );
        
        // Use the standard WebTestCase login method
        $this->client->loginUser($sessionUser);
    }

    /**
     * Helper method to clear test data
     */
    private function clearTestData(): void
    {
        // Clear patients
        $this->documentManager->getSchemaManager()->dropDocumentCollection(Patient::class);
        
        // Clear users
        $this->documentManager->getSchemaManager()->dropDocumentCollection(User::class);
        
        // Clear audit logs
        $this->documentManager->getSchemaManager()->dropDocumentCollection(\App\Document\AuditLog::class);
    }
}
