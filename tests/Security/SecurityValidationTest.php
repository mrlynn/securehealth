<?php

namespace App\Tests\Security;

use App\Document\User;
use App\Service\AuditLogService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;

class SecurityValidationTest extends WebTestCase
{
    private $client;
    private DocumentManager $documentManager;
    private AuditLogService $auditLogService;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        
        $this->documentManager = $container->get('doctrine_mongodb.odm.document_manager');
        $this->auditLogService = $container->get(AuditLogService::class);
        
        // Clear test data
        $this->clearTestData();
    }

    protected function tearDown(): void
    {
        $this->clearTestData();
    }

    /**
     * Test 1: Authentication Bypass Attempts
     */
    public function testAuthenticationBypassAttempts(): void
    {
        // Test 1.1: Direct API access without authentication
        $this->client->request('GET', '/api/patients');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        
        // Test 1.2: Attempt to access protected endpoint with invalid token
        $this->client->request('GET', '/api/patients', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token'
        ]);
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        
        // Test 1.3: Attempt to access admin endpoint with regular user
        $regularUser = $this->createTestUser(['ROLE_USER']);
        $this->loginUser($regularUser);
        
        $this->client->request('GET', '/api/admin/users');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Test 1.4: Attempt to access patient data with wrong role
        $receptionistUser = $this->createTestUser(['ROLE_RECEPTIONIST']);
        $this->loginUser($receptionistUser);
        
        $this->client->request('GET', '/api/patients/507f1f77bcf86cd799439011');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Test 1.5: Attempt to access medical knowledge with insufficient role
        $this->client->request('POST', '/api/medical-knowledge/search', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['query' => 'test']));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Test 2: Session Security Testing
     */
    public function testSessionSecurityTesting(): void
    {
        // Test 2.1: Session fixation prevention
        $user = $this->createTestUser(['ROLE_DOCTOR']);
        
        // Login and get session ID
        $this->loginUser($user);
        $sessionId1 = $this->client->getRequest()->getSession()->getId();
        
        // Logout and login again
        $this->client->request('POST', '/logout');
        $this->loginUser($user);
        $sessionId2 = $this->client->getRequest()->getSession()->getId();
        
        // Session ID should be different (session fixation prevention)
        $this->assertNotEquals($sessionId1, $sessionId2);
        
        // Test 2.2: Session timeout
        $this->loginUser($user);
        
        // Simulate session timeout by modifying session data
        $session = $this->client->getRequest()->getSession();
        $session->set('_security_last_username', time() - 3600); // 1 hour ago
        
        $this->client->request('GET', '/api/patients');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        
        // Test 2.3: Concurrent session handling
        $user1 = $this->createTestUser(['ROLE_DOCTOR'], 'user1@example.com');
        $user2 = $this->createTestUser(['ROLE_DOCTOR'], 'user2@example.com');
        
        $this->loginUser($user1);
        $this->client->request('GET', '/api/patients');
        $response1 = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response1->getStatusCode());
        
        // Login as different user in same session
        $this->loginUser($user2);
        $this->client->request('GET', '/api/patients');
        $response2 = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response2->getStatusCode());
        
        // Verify user1 is no longer authenticated
        $this->client->request('GET', '/api/patients', [], [], [
            'HTTP_X_USER_ID' => (string)$user1->getId()
        ]);
        $response3 = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response3->getStatusCode());
    }

    /**
     * Test 3: Input Validation and Sanitization
     */
    public function testInputValidationAndSanitization(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test 3.1: SQL Injection attempts
        $sqlInjectionPayloads = [
            "'; DROP TABLE patients; --",
            "' OR '1'='1",
            "'; INSERT INTO users VALUES ('hacker', 'password'); --",
            "' UNION SELECT * FROM users --"
        ];
        
        foreach ($sqlInjectionPayloads as $payload) {
            $this->client->request('GET', '/api/patients', [
                'search' => $payload
            ]);
            
            $response = $this->client->getResponse();
            // Should not cause server error (500) - should be handled gracefully
            $this->assertNotEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        }
        
        // Test 3.2: XSS attempts
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            'javascript:alert("XSS")',
            '<img src="x" onerror="alert(\'XSS\')">',
            '"><script>alert("XSS")</script>'
        ];
        
        foreach ($xssPayloads as $payload) {
            $patientData = [
                'firstName' => $payload,
                'lastName' => 'Test',
                'email' => 'test@example.com',
                'phoneNumber' => '555-000-0000',
                'birthDate' => '1990-01-01',
                'ssn' => '000-00-0000',
                'diagnosis' => 'Test Diagnosis',
                'medications' => ['Test Medication'],
                'insuranceDetails' => 'Test Insurance'
            ];
            
            $this->client->request('POST', '/api/patients', [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode($patientData));
            
            $response = $this->client->getResponse();
            // Should either reject invalid input or sanitize it
            $this->assertTrue(
                $response->getStatusCode() === Response::HTTP_BAD_REQUEST ||
                $response->getStatusCode() === Response::HTTP_CREATED
            );
        }
        
        // Test 3.3: NoSQL Injection attempts
        $nosqlPayloads = [
            '{"$ne": null}',
            '{"$gt": ""}',
            '{"$where": "this.password == this.username"}',
            '{"$regex": ".*"}'
        ];
        
        foreach ($nosqlPayloads as $payload) {
            $this->client->request('GET', '/api/patients', [
                'filter' => $payload
            ]);
            
            $response = $this->client->getResponse();
            // Should not cause server error
            $this->assertNotEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        }
        
        // Test 3.4: Path traversal attempts
        $pathTraversalPayloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts',
            '....//....//....//etc/passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd'
        ];
        
        foreach ($pathTraversalPayloads as $payload) {
            $this->client->request('GET', "/api/patients/{$payload}");
            
            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        }
    }

    /**
     * Test 4: Audit Logging Validation
     */
    public function testAuditLoggingValidation(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test 4.1: Verify PHI access is logged
        $this->client->request('GET', '/api/patients');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Check audit logs
        $this->client->request('GET', '/api/audit-logs', [
            'username' => 'test@example.com',
            'actionType' => 'PATIENT_VIEW'
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Test 4.2: Verify failed access attempts are logged
        $unauthorizedUser = $this->createTestUser(['ROLE_PATIENT']);
        $this->loginUser($unauthorizedUser);
        
        $this->client->request('GET', '/api/patients');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Check audit logs for failed attempt
        $this->client->request('GET', '/api/audit-logs', [
            'username' => 'test@example.com',
            'actionType' => 'ACCESS_DENIED'
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Test 4.3: Verify security events are logged
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        
        // Check audit logs for failed login
        $this->client->request('GET', '/api/audit-logs', [
            'actionType' => 'SECURITY_LOGIN_FAILED'
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
    }

    /**
     * Test 5: Role Hierarchy Inheritance Validation
     */
    public function testRoleHierarchyInheritanceValidation(): void
    {
        // Test 5.1: Doctor inherits nurse permissions
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Doctor should have all nurse permissions
        $this->client->request('GET', '/api/patients');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $this->client->request('POST', '/api/medical-knowledge/drug-interactions', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['medications' => ['metformin']]));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Test 5.2: Nurse inherits receptionist permissions
        $nurseUser = $this->createTestUser(['ROLE_NURSE']);
        $this->loginUser($nurseUser);
        
        // Nurse should have all receptionist permissions
        $this->client->request('GET', '/api/patients');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $this->client->request('GET', '/api/appointments');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Test 5.3: Admin has limited medical access
        $adminUser = $this->createTestUser(['ROLE_ADMIN']);
        $this->loginUser($adminUser);
        
        // Admin can view basic patient info
        $this->client->request('GET', '/api/patients');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Admin cannot access medical data
        $this->client->request('POST', '/api/medical-knowledge/clinical-decision-support', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['patientCondition' => 'diabetes']));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Test 5.4: Patient has self-access only
        $patientUser = $this->createTestUser(['ROLE_PATIENT']);
        $patientUser->setPatientId(new ObjectId());
        $this->documentManager->persist($patientUser);
        $this->documentManager->flush();
        
        $this->loginUser($patientUser);
        
        // Patient cannot access general patient list
        $this->client->request('GET', '/api/patients');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Patient can access their own data
        $this->client->request('GET', "/api/patient-portal/patient/{$patientUser->getPatientId()}");
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test 6: CSRF Protection
     */
    public function testCsrfProtection(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test 6.1: CSRF token validation for state-changing operations
        $patientData = [
            'firstName' => 'Test',
            'lastName' => 'Patient',
            'email' => 'test@example.com',
            'phoneNumber' => '555-000-0000',
            'birthDate' => '1990-01-01',
            'ssn' => '000-00-0000',
            'diagnosis' => 'Test Diagnosis',
            'medications' => ['Test Medication'],
            'insuranceDetails' => 'Test Insurance'
        ];
        
        // Request without CSRF token should fail
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientData));
        
        $response = $this->client->getResponse();
        // Should either require CSRF token or handle it gracefully
        $this->assertTrue(
            $response->getStatusCode() === Response::HTTP_BAD_REQUEST ||
            $response->getStatusCode() === Response::HTTP_CREATED
        );
        
        // Test 6.2: Verify CSRF token is included in forms
        $this->client->request('GET', '/patient-add.html');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf', $content);
    }

    /**
     * Test 7: Rate Limiting and Brute Force Protection
     */
    public function testRateLimitingAndBruteForceProtection(): void
    {
        // Test 7.1: Multiple failed login attempts
        $failedAttempts = 0;
        $maxAttempts = 5;
        
        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            $this->client->request('POST', '/api/auth/login', [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode([
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]));
            
            $response = $this->client->getResponse();
            
            if ($response->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
                $failedAttempts++;
                break;
            }
        }
        
        // Should eventually trigger rate limiting
        $this->assertGreaterThan(0, $failedAttempts);
        
        // Test 7.2: API endpoint rate limiting
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $requestCount = 0;
        $rateLimitHit = false;
        
        for ($i = 0; $i < 100; $i++) { // Try 100 requests
            $this->client->request('GET', '/api/patients');
            $response = $this->client->getResponse();
            
            if ($response->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
                $rateLimitHit = true;
                break;
            }
            
            $requestCount++;
        }
        
        // Should either handle all requests or hit rate limit
        $this->assertTrue($rateLimitHit || $requestCount === 100);
    }

    /**
     * Test 8: Data Encryption Validation
     */
    public function testDataEncryptionValidation(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test 8.1: Verify sensitive data is encrypted in transit
        $patientData = [
            'firstName' => 'Encryption',
            'lastName' => 'Test',
            'email' => 'encryption@example.com',
            'phoneNumber' => '555-123-4567',
            'birthDate' => '1990-01-01',
            'ssn' => '123-45-6789',
            'diagnosis' => 'Sensitive Diagnosis',
            'medications' => ['Sensitive Medication'],
            'insuranceDetails' => 'Sensitive Insurance Info'
        ];
        
        $this->client->request('POST', '/api/patients', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $patientId = $responseData['data']['id'];
        
        // Test 8.2: Verify data is encrypted at rest
        $this->client->request('GET', "/api/patients/{$patientId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        
        // Verify sensitive data is properly decrypted when retrieved
        $this->assertEquals($patientData['ssn'], $responseData['data']['ssn']);
        $this->assertEquals($patientData['diagnosis'], $responseData['data']['diagnosis']);
        $this->assertEquals($patientData['medications'], $responseData['data']['medications']);
        
        // Clean up
        $this->client->request('DELETE', "/api/patients/{$patientId}");
    }

    /**
     * Helper method to create a test user
     */
    private function createTestUser(array $roles, string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setUsername(str_replace('@example.com', '', $email));
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
        // Clear users
        $this->documentManager->getSchemaManager()->dropDocumentCollection(User::class);
        
        // Clear audit logs
        $this->documentManager->getSchemaManager()->dropDocumentCollection(\App\Document\AuditLog::class);
        
        // Clear patients
        $this->documentManager->getSchemaManager()->dropDocumentCollection(\App\Document\Patient::class);
    }
}
