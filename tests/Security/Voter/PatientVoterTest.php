<?php

namespace App\Tests\Security\Voter;

use App\Document\Patient;
use App\Document\User;
use App\Security\Voter\PatientVoter;
use App\Service\AuditLogService;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class PatientVoterTest extends TestCase
{
    private PatientVoter $voter;
    private AuditLogService $mockAuditLogService;
    private TokenInterface $mockToken;

    protected function setUp(): void
    {
        // Create mock audit log service
        $this->mockAuditLogService = $this->createMock(AuditLogService::class);
        
        // Create mock audit log object
        $mockAuditLog = $this->createMock(\App\Document\AuditLog::class);
        $this->mockAuditLogService->method('log')->willReturn($mockAuditLog);
        
        // Create voter with mocked dependencies
        $this->voter = new PatientVoter($this->mockAuditLogService);
        
        // Create mock token
        $this->mockToken = $this->createMock(TokenInterface::class);
    }

    /**
     * Test 1: Admin role restrictions
     */
    public function testAdminRoleRestrictions(): void
    {
        $adminUser = $this->createTestUser(['ROLE_ADMIN']);
        $patient = $this->createTestPatient();
        
        $this->mockToken->method('getUser')->willReturn($adminUser);
        
        // Admin should NOT have access to medical data
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_DIAGNOSIS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_MEDICATIONS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_SSN])
        );
        
        // Admin should have access to basic patient info
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::CREATE])
        );
    }

    /**
     * Test 2: Doctor full access capabilities
     */
    public function testDoctorFullAccessCapabilities(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $patient = $this->createTestPatient();
        
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        // Doctor should have full access to all patient data
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_DIAGNOSIS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_MEDICATIONS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_SSN])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::EDIT])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::EDIT_DIAGNOSIS])
        );
    }

    /**
     * Test 3: Nurse limited access restrictions
     */
    public function testNurseLimitedAccessRestrictions(): void
    {
        $nurseUser = $this->createTestUser(['ROLE_NURSE']);
        $patient = $this->createTestPatient();
        
        $this->mockToken->method('getUser')->willReturn($nurseUser);
        
        // Nurse should have access to basic patient info
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW])
        );
        
        // Nurse should NOT have access to SSN
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_SSN])
        );
        
        // Nurse should have access to diagnosis and medications
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_DIAGNOSIS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_MEDICATIONS])
        );
    }

    /**
     * Test 4: Receptionist demographic-only access
     */
    public function testReceptionistDemographicOnlyAccess(): void
    {
        $receptionistUser = $this->createTestUser(['ROLE_RECEPTIONIST']);
        $patient = $this->createTestPatient();
        
        $this->mockToken->method('getUser')->willReturn($receptionistUser);
        
        // Receptionist should have access to basic patient info
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW])
        );
        
        // Receptionist should NOT have access to medical data
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_DIAGNOSIS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_MEDICATIONS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_SSN])
        );
    }

    /**
     * Test 5: Patient self-access limitations
     */
    public function testPatientSelfAccessLimitations(): void
    {
        $patientUser = $this->createTestUser(['ROLE_PATIENT']);
        $patient = $this->createTestPatient();
        
        // Set patient ID to match user
        $patientUser->setPatientId($patient->getId());
        
        $this->mockToken->method('getUser')->willReturn($patientUser);
        
        // Patient should have access to their own basic info
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::PATIENT_VIEW_OWN])
        );
        
        // Patient should NOT have access to medical data
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_DIAGNOSIS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_MEDICATIONS])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_SSN])
        );
    }

    /**
     * Test 6: Role hierarchy inheritance
     */
    public function testRoleHierarchyInheritance(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $nurseUser = $this->createTestUser(['ROLE_NURSE']);
        $patient = $this->createTestPatient();
        
        // Test doctor inherits nurse permissions
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW])
        );
        
        // Test nurse inherits receptionist permissions
        $this->mockToken->method('getUser')->willReturn($nurseUser);
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW])
        );
    }

    /**
     * Test 7: Unauthenticated user access
     */
    public function testUnauthenticatedUserAccess(): void
    {
        $patient = $this->createTestPatient();
        
        // No user (unauthenticated)
        $this->mockToken->method('getUser')->willReturn(null);
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW])
        );
        
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::CREATE])
        );
    }

    /**
     * Test 8: Audit logging integration
     */
    public function testAuditLoggingIntegration(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $patient = $this->createTestPatient();
        
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        // Verify audit log service is called
        $this->mockAuditLogService->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo($doctorUser),
                $this->equalTo('security_access'),
                $this->isType('array')
            );
        
        $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW]);
    }

    /**
     * Test 9: Edge cases and error handling
     */
    public function testEdgeCasesAndErrorHandling(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        // Test with null subject - VIEW should be allowed for listing
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, null, [PatientVoter::VIEW])
        );
        
        // Test with non-Patient subject - VIEW should be allowed for listing
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, 'not-a-patient', [PatientVoter::VIEW])
        );
        
        // Test with unsupported attribute
        $patient = $this->createTestPatient();
        $this->assertEquals(
            PatientVoter::ACCESS_ABSTAIN,
            $this->voter->vote($this->mockToken, $patient, ['UNSUPPORTED_ATTRIBUTE'])
        );
    }

    /**
     * Test 10: Multiple role combinations
     */
    public function testMultipleRoleCombinations(): void
    {
        $patient = $this->createTestPatient();
        
        // Test user with multiple roles
        $multiRoleUser = $this->createTestUser(['ROLE_DOCTOR', 'ROLE_ADMIN']);
        $this->mockToken->method('getUser')->willReturn($multiRoleUser);
        
        // Should have admin permissions (admin overrides doctor for security)
        $this->assertEquals(
            PatientVoter::ACCESS_DENIED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::VIEW_DIAGNOSIS])
        );
        
        // Test user with patient role only
        $patientOnlyUser = $this->createTestUser(['ROLE_PATIENT']);
        $patientOnlyUser->setPatientId('507f1f77bcf86cd799439011'); // Set as string
        $this->mockToken->method('getUser')->willReturn($patientOnlyUser);
        
        $this->assertEquals(
            PatientVoter::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken, $patient, [PatientVoter::PATIENT_VIEW_OWN])
        );
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
        
        return $user;
    }

    /**
     * Helper method to create a test patient
     */
    private function createTestPatient(): Patient
    {
        $patient = new Patient();
        $patient->setId(new ObjectId('507f1f77bcf86cd799439011'));
        $patient->setFirstName('John');
        $patient->setLastName('Doe');
        $patient->setEmail('john.doe@example.com');
        $patient->setPhoneNumber('555-123-4567');
        $patient->setBirthDate(new UTCDateTime(new \DateTime('1990-01-01')));
        $patient->setSsn('123-45-6789');
        $patient->setDiagnosis(['Hypertension']);
        $patient->setMedications(['Lisinopril 10mg']);
        $patient->setInsuranceDetails(['Blue Cross Blue Shield']);
        
        return $patient;
    }
}