<?php

namespace App\Tests\Security\Voter;

use App\Document\MedicalKnowledge;
use App\Document\User;
use App\Security\Voter\MedicalKnowledgeVoter;
use App\Service\AuditLogService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MedicalKnowledgeVoterTest extends TestCase
{
    private MedicalKnowledgeVoter $voter;
    private AuditLogService $mockAuditLogService;
    private TokenInterface $mockToken;

    protected function setUp(): void
    {
        // Create mock audit log service
        $this->mockAuditLogService = $this->createMock(AuditLogService::class);
        
        // Create mock audit log object
        $mockAuditLog = $this->createMock(\App\Document\AuditLog::class);
        $this->mockAuditLogService->method('log')->willReturn($mockAuditLog);
        $this->mockAuditLogService->method('updateLastLog')->willReturn($mockAuditLog);
        
        // Create mock token
        $this->mockToken = $this->createMock(TokenInterface::class);
        
        // Create voter instance
        $this->voter = new MedicalKnowledgeVoter($this->mockAuditLogService);
    }

    /**
     * Test 1: Doctor Full Access Capabilities
     */
    public function testDoctorFullAccessCapabilities(): void
    {
        $doctorUser = $this->createUser(['ROLE_DOCTOR']);
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        // Doctor CAN search medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        
        // Doctor CAN access clinical decision support
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        
        // Doctor CAN check drug interactions
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        
        // Doctor CAN access treatment guidelines
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::TREATMENT_GUIDELINES]));
        
        // Doctor CAN access diagnostic criteria
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA]));
        
        // Doctor CAN view medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        
        // Doctor CAN view statistics
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::VIEW_STATS]));
        
        // Doctor CAN create medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CREATE]));
        
        // Doctor CAN edit medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::EDIT]));
        
        // Doctor CANNOT delete medical knowledge (only admin)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        
        // Doctor CANNOT import medical knowledge (only admin)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
    }

    /**
     * Test 2: Admin Access Capabilities
     */
    public function testAdminAccessCapabilities(): void
    {
        $adminUser = $this->createUser(['ROLE_ADMIN']);
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        $this->mockToken->method('getUser')->willReturn($adminUser);
        
        // Admin CAN search medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        
        // Admin CANNOT access clinical decision support (only doctors)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        
        // Admin CANNOT check drug interactions (only doctors and nurses)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        
        // Admin CANNOT access treatment guidelines (only doctors)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::TREATMENT_GUIDELINES]));
        
        // Admin CANNOT access diagnostic criteria (only doctors)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA]));
        
        // Admin CANNOT view medical knowledge (only doctors and nurses)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        
        // Admin CAN view statistics
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::VIEW_STATS]));
        
        // Admin CAN create medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CREATE]));
        
        // Admin CAN edit medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::EDIT]));
        
        // Admin CAN delete medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        
        // Admin CAN import medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
    }

    /**
     * Test 3: Nurse Limited Access
     */
    public function testNurseLimitedAccess(): void
    {
        $nurseUser = $this->createUser(['ROLE_NURSE']);
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        $this->mockToken->method('getUser')->willReturn($nurseUser);
        
        // Nurse CANNOT search medical knowledge (only doctors and admins)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        
        // Nurse CANNOT access clinical decision support (only doctors)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        
        // Nurse CAN check drug interactions
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        
        // Nurse CANNOT access treatment guidelines (only doctors)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::TREATMENT_GUIDELINES]));
        
        // Nurse CANNOT access diagnostic criteria (only doctors)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA]));
        
        // Nurse CAN view medical knowledge
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        
        // Nurse CANNOT view statistics (only doctors and admins)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::VIEW_STATS]));
        
        // Nurse CANNOT create medical knowledge (only doctors and admins)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CREATE]));
        
        // Nurse CANNOT edit medical knowledge (only doctors and admins)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::EDIT]));
        
        // Nurse CANNOT delete medical knowledge (only admin)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        
        // Nurse CANNOT import medical knowledge (only admin)
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
    }

    /**
     * Test 4: Receptionist No Access
     */
    public function testReceptionistNoAccess(): void
    {
        $receptionistUser = $this->createUser(['ROLE_RECEPTIONIST']);
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        $this->mockToken->method('getUser')->willReturn($receptionistUser);
        
        // Receptionist CANNOT access any medical knowledge features
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::TREATMENT_GUIDELINES]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::VIEW_STATS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CREATE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::EDIT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
    }

    /**
     * Test 5: Patient No Access
     */
    public function testPatientNoAccess(): void
    {
        $patientUser = $this->createUser(['ROLE_PATIENT']);
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        $this->mockToken->method('getUser')->willReturn($patientUser);
        
        // Patient CANNOT access any medical knowledge features
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::TREATMENT_GUIDELINES]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::VIEW_STATS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CREATE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::EDIT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
    }

    /**
     * Test 6: Unauthenticated User Access
     */
    public function testUnauthenticatedUserAccess(): void
    {
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        // Test with no user
        $this->mockToken->method('getUser')->willReturn(null);
        
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::TREATMENT_GUIDELINES]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::VIEW_STATS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CREATE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::EDIT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
        
        // Test with non-UserInterface user
        $nonUserInterface = $this->createMock(UserInterface::class);
        $this->mockToken->method('getUser')->willReturn($nonUserInterface);
        
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
    }

    /**
     * Test 7: Audit Logging Integration
     */
    public function testAuditLoggingIntegration(): void
    {
        $doctorUser = $this->createUser(['ROLE_DOCTOR']);
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        // Verify audit log service is called
        $this->mockAuditLogService->expects($this->once())
            ->method('log')
            ->with(
                $doctorUser,
                'MEDICAL_KNOWLEDGE_ACCESS',
                $this->callback(function($data) use ($medicalKnowledge) {
                    return $data['attribute'] === MedicalKnowledgeVoter::VIEW &&
                           $data['subjectId'] === (string)$medicalKnowledge->getId() &&
                           $data['granted'] === null;
                })
            );
        
        $this->mockAuditLogService->expects($this->once())
            ->method('updateLastLog')
            ->with(['granted' => true]);
        
        $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]);
    }

    /**
     * Test 9: Edge Cases and Error Handling
     */
    public function testEdgeCasesAndErrorHandling(): void
    {
        $doctorUser = $this->createUser(['ROLE_DOCTOR']);
        
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        // Test with medical knowledge that has no ID
        $medicalKnowledgeWithoutId = $this->createMedicalKnowledge();
        $medicalKnowledgeWithoutId->setId(null);
        
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledgeWithoutId, [MedicalKnowledgeVoter::VIEW]));
        
        // Test with null subject for attributes that don't require it
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        
        // Test with null subject for attributes that require it
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::VIEW]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::EDIT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DELETE]));
    }

    /**
     * Test 10: Multiple Role Combinations
     */
    public function testMultipleRoleCombinations(): void
    {
        // Test user with multiple roles
        $multiRoleUser = $this->createUser(['ROLE_DOCTOR', 'ROLE_ADMIN']);
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        $this->mockToken->method('getUser')->willReturn($multiRoleUser);
        
        // Should have doctor permissions
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        
        // Should also have admin permissions
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
        
        // Test user with nurse and admin roles
        $nurseAdminUser = $this->createUser(['ROLE_NURSE', 'ROLE_ADMIN']);
        $this->mockToken->method('getUser')->willReturn($nurseAdminUser);
        
        // Should have nurse permissions
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::DRUG_INTERACTIONS]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::VIEW]));
        
        // Should also have admin permissions
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::SEARCH]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $medicalKnowledge, [MedicalKnowledgeVoter::DELETE]));
        $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, null, [MedicalKnowledgeVoter::IMPORT]));
    }

    /**
     * Test 11: Role Hierarchy Validation
     */
    public function testRoleHierarchyValidation(): void
    {
        $medicalKnowledge = $this->createMedicalKnowledge();
        
        // Test that ROLE_DOCTOR has all necessary permissions
        $doctorUser = $this->createUser(['ROLE_DOCTOR']);
        $this->mockToken->method('getUser')->willReturn($doctorUser);
        
        $doctorPermissions = [
            MedicalKnowledgeVoter::SEARCH,
            MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT,
            MedicalKnowledgeVoter::DRUG_INTERACTIONS,
            MedicalKnowledgeVoter::TREATMENT_GUIDELINES,
            MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA,
            MedicalKnowledgeVoter::VIEW,
            MedicalKnowledgeVoter::VIEW_STATS,
            MedicalKnowledgeVoter::CREATE,
            MedicalKnowledgeVoter::EDIT
        ];
        
        foreach ($doctorPermissions as $permission) {
            $subject = in_array($permission, [MedicalKnowledgeVoter::VIEW, MedicalKnowledgeVoter::EDIT]) ? $medicalKnowledge : null;
            $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $subject, [$permission]), "Doctor should have permission: $permission");
        }
        
        // Test that ROLE_NURSE has limited permissions
        $nurseUser = $this->createUser(['ROLE_NURSE']);
        $this->mockToken->method('getUser')->willReturn($nurseUser);
        
        $nursePermissions = [
            MedicalKnowledgeVoter::DRUG_INTERACTIONS,
            MedicalKnowledgeVoter::VIEW
        ];
        
        foreach ($nursePermissions as $permission) {
            $subject = $permission === MedicalKnowledgeVoter::VIEW ? $medicalKnowledge : null;
            $this->assertEquals(MedicalKnowledgeVoter::ACCESS_GRANTED, $this->voter->vote($this->mockToken, $subject, [$permission]), "Nurse should have permission: $permission");
        }
        
        $nurseDeniedPermissions = [
            MedicalKnowledgeVoter::SEARCH,
            MedicalKnowledgeVoter::CLINICAL_DECISION_SUPPORT,
            MedicalKnowledgeVoter::TREATMENT_GUIDELINES,
            MedicalKnowledgeVoter::DIAGNOSTIC_CRITERIA,
            MedicalKnowledgeVoter::VIEW_STATS,
            MedicalKnowledgeVoter::CREATE,
            MedicalKnowledgeVoter::EDIT,
            MedicalKnowledgeVoter::DELETE,
            MedicalKnowledgeVoter::IMPORT
        ];
        
        foreach ($nurseDeniedPermissions as $permission) {
            $subject = in_array($permission, [MedicalKnowledgeVoter::VIEW, MedicalKnowledgeVoter::EDIT, MedicalKnowledgeVoter::DELETE]) ? $medicalKnowledge : null;
            $this->assertEquals(MedicalKnowledgeVoter::ACCESS_DENIED, $this->voter->vote($this->mockToken, $subject, [$permission]), "Nurse should not have permission: $permission");
        }
    }

    /**
     * Helper method to create a user with specific roles
     */
    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password');
        $user->setRoles($roles);
        
        return $user;
    }

    /**
     * Helper method to create medical knowledge
     */
    private function createMedicalKnowledge(): MedicalKnowledge
    {
        $medicalKnowledge = new MedicalKnowledge();
        $medicalKnowledge->setId(new ObjectId());
        $medicalKnowledge->setTitle('Test Medical Knowledge');
        $medicalKnowledge->setContent('This is test medical knowledge content');
        $medicalKnowledge->setSummary('Test summary');
        $medicalKnowledge->setSource('Test Source');
        $medicalKnowledge->setConfidenceLevel(8);
        $medicalKnowledge->setEvidenceLevel(4);
        $medicalKnowledge->setTags(['test', 'medical']);
        $medicalKnowledge->setSpecialties(['cardiology']);
        $medicalKnowledge->setRelatedConditions(['hypertension']);
        $medicalKnowledge->setRelatedMedications(['lisinopril']);
        $medicalKnowledge->setRelatedProcedures(['blood pressure measurement']);
        $medicalKnowledge->setRequiresReview(false);
        $medicalKnowledge->setIsActive(true);
        $medicalKnowledge->setCreatedAt(new UTCDateTime());
        $medicalKnowledge->setCreatedBy(new ObjectId());
        
        return $medicalKnowledge;
    }
}
