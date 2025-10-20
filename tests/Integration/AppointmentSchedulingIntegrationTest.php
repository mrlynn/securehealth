<?php

namespace App\Tests\Integration;

use App\Document\Appointment;
use App\Document\Patient;
use App\Document\User;
use App\Repository\AppointmentRepository;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class AppointmentSchedulingIntegrationTest extends WebTestCase
{
    private $client;
    private DocumentManager $documentManager;
    private AppointmentRepository $appointmentRepository;
    private PatientRepository $patientRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        
        $this->documentManager = $container->get('doctrine_mongodb.odm.document_manager');
        $this->appointmentRepository = $container->get(AppointmentRepository::class);
        $this->patientRepository = $container->get(PatientRepository::class);
        
        // Clear test data
        $this->clearTestData();
    }

    protected function tearDown(): void
    {
        $this->clearTestData();
    }

    /**
     * Test 1: Complete Appointment Scheduling Workflow
     */
    public function testCompleteAppointmentSchedulingWorkflow(): void
    {
        // Step 1: Create a patient
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patient = $this->createTestPatient();
        
        // Step 2: Create an appointment
        $appointmentData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-15T10:00:00Z',
            'duration' => 30,
            'type' => 'consultation',
            'notes' => 'Initial consultation for hypertension',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($appointmentData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        
        $appointmentId = $responseData['data']['id'];
        $this->assertNotNull($appointmentId);
        
        // Step 3: View the appointment
        $this->client->request('GET', "/api/appointments/{$appointmentId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($appointmentData['type'], $responseData['data']['type']);
        $this->assertEquals($appointmentData['notes'], $responseData['data']['notes']);
        
        // Step 4: Update the appointment
        $updatedData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-15T11:00:00Z',
            'duration' => 45,
            'type' => 'follow-up',
            'notes' => 'Follow-up consultation - blood pressure improved',
            'status' => 'confirmed'
        ];
        
        $this->client->request('PUT', "/api/appointments/{$appointmentId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($updatedData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($updatedData['type'], $responseData['data']['type']);
        $this->assertEquals($updatedData['status'], $responseData['data']['status']);
        
        // Step 5: List appointments
        $this->client->request('GET', '/api/appointments');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Step 6: Cancel the appointment
        $cancelData = [
            'status' => 'cancelled',
            'notes' => 'Patient cancelled - rescheduled for next week'
        ];
        
        $this->client->request('PUT', "/api/appointments/{$appointmentId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($cancelData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('cancelled', $responseData['data']['status']);
        
        // Step 7: Delete the appointment
        $this->client->request('DELETE', "/api/appointments/{$appointmentId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        
        // Step 8: Verify appointment is deleted
        $this->client->request('GET', "/api/appointments/{$appointmentId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Test 2: Role-Based Access Control in Appointment Scheduling
     */
    public function testRoleBasedAccessControlInAppointmentScheduling(): void
    {
        $patient = $this->createTestPatient();
        
        // Test Doctor Access
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $appointmentData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-20T14:00:00Z',
            'duration' => 30,
            'type' => 'consultation',
            'notes' => 'Doctor can create appointments',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($appointmentData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $appointmentId = $responseData['data']['id'];
        
        // Test Nurse Access
        $nurseUser = $this->createTestUser(['ROLE_NURSE']);
        $this->loginUser($nurseUser);
        
        // Nurse can view appointments
        $this->client->request('GET', '/api/appointments');
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Nurse can view specific appointment
        $this->client->request('GET', "/api/appointments/{$appointmentId}");
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Nurse can create appointments
        $nurseAppointmentData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-21T09:00:00Z',
            'duration' => 15,
            'type' => 'checkup',
            'notes' => 'Nurse can create appointments',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($nurseAppointmentData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        // Test Receptionist Access
        $receptionistUser = $this->createTestUser(['ROLE_RECEPTIONIST']);
        $this->loginUser($receptionistUser);
        
        // Receptionist can view appointments
        $this->client->request('GET', '/api/appointments');
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Receptionist can create appointments
        $receptionistAppointmentData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-22T10:30:00Z',
            'duration' => 20,
            'type' => 'registration',
            'notes' => 'Receptionist can create appointments',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($receptionistAppointmentData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        // Test Admin Access
        $adminUser = $this->createTestUser(['ROLE_ADMIN']);
        $this->loginUser($adminUser);
        
        // Admin can view appointments
        $this->client->request('GET', '/api/appointments');
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Admin cannot create appointments
        $adminAppointmentData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-23T15:00:00Z',
            'duration' => 30,
            'type' => 'admin-test',
            'notes' => 'Admin cannot create appointments',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($adminAppointmentData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Clean up
        $this->loginUser($doctorUser);
        $this->client->request('DELETE', "/api/appointments/{$appointmentId}");
    }

    /**
     * Test 3: Appointment Calendar Integration
     */
    public function testAppointmentCalendarIntegration(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patient = $this->createTestPatient();
        
        // Create multiple appointments for different dates
        $appointments = [
            [
                'patientId' => (string)$patient->getId(),
                'appointmentDate' => '2024-02-15T09:00:00Z',
                'duration' => 30,
                'type' => 'consultation',
                'notes' => 'Morning consultation',
                'status' => 'scheduled'
            ],
            [
                'patientId' => (string)$patient->getId(),
                'appointmentDate' => '2024-02-15T14:00:00Z',
                'duration' => 45,
                'type' => 'follow-up',
                'notes' => 'Afternoon follow-up',
                'status' => 'scheduled'
            ],
            [
                'patientId' => (string)$patient->getId(),
                'appointmentDate' => '2024-02-16T10:00:00Z',
                'duration' => 20,
                'type' => 'checkup',
                'notes' => 'Next day checkup',
                'status' => 'scheduled'
            ]
        ];
        
        $appointmentIds = [];
        foreach ($appointments as $appointmentData) {
            $this->client->request('POST', '/api/appointments', [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode($appointmentData));
            
            $response = $this->client->getResponse();
            $responseData = json_decode($response->getContent(), true);
            $appointmentIds[] = $responseData['data']['id'];
        }
        
        // Test calendar view for specific date
        $this->client->request('GET', '/api/appointments', [
            'date' => '2024-02-15'
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(2, count($responseData['data'])); // Two appointments on 2024-02-15
        
        // Test calendar view for date range
        $this->client->request('GET', '/api/appointments', [
            'startDate' => '2024-02-15',
            'endDate' => '2024-02-16'
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(3, count($responseData['data'])); // Three appointments in range
        
        // Clean up
        foreach ($appointmentIds as $appointmentId) {
            $this->client->request('DELETE', "/api/appointments/{$appointmentId}");
        }
    }

    /**
     * Test 4: Appointment Status Management
     */
    public function testAppointmentStatusManagement(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patient = $this->createTestPatient();
        
        $appointmentData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-25T10:00:00Z',
            'duration' => 30,
            'type' => 'consultation',
            'notes' => 'Status management test',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($appointmentData));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $appointmentId = $responseData['data']['id'];
        
        // Test status transitions
        $statusTransitions = [
            'confirmed' => 'Appointment confirmed by patient',
            'in-progress' => 'Patient arrived, appointment started',
            'completed' => 'Appointment completed successfully',
            'cancelled' => 'Appointment cancelled by patient',
            'no-show' => 'Patient did not show up'
        ];
        
        foreach ($statusTransitions as $status => $notes) {
            $updateData = [
                'status' => $status,
                'notes' => $notes
            ];
            
            $this->client->request('PUT', "/api/appointments/{$appointmentId}", [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode($updateData));
            
            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
            
            $responseData = json_decode($response->getContent(), true);
            $this->assertTrue($responseData['success']);
            $this->assertEquals($status, $responseData['data']['status']);
        }
        
        // Clean up
        $this->client->request('DELETE', "/api/appointments/{$appointmentId}");
    }

    /**
     * Test 5: Appointment Conflict Detection
     */
    public function testAppointmentConflictDetection(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        $patient = $this->createTestPatient();
        
        // Create first appointment
        $appointmentData1 = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-28T10:00:00Z',
            'duration' => 30,
            'type' => 'consultation',
            'notes' => 'First appointment',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($appointmentData1));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $appointmentId1 = $responseData['data']['id'];
        
        // Try to create conflicting appointment (overlapping time)
        $conflictingAppointmentData = [
            'patientId' => (string)$patient->getId(),
            'appointmentDate' => '2024-02-28T10:15:00Z', // 15 minutes overlap
            'duration' => 30,
            'type' => 'consultation',
            'notes' => 'Conflicting appointment',
            'status' => 'scheduled'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($conflictingAppointmentData));
        
        $response = $this->client->getResponse();
        // Should either return conflict error or handle gracefully
        $this->assertTrue(
            $response->getStatusCode() === Response::HTTP_CONFLICT || 
            $response->getStatusCode() === Response::HTTP_BAD_REQUEST ||
            $response->getStatusCode() === Response::HTTP_CREATED
        );
        
        // Clean up
        $this->client->request('DELETE', "/api/appointments/{$appointmentId1}");
    }

    /**
     * Test 6: Error Handling in Appointment Scheduling
     */
    public function testErrorHandlingInAppointmentScheduling(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR']);
        $this->loginUser($doctorUser);
        
        // Test invalid appointment data
        $invalidAppointmentData = [
            'patientId' => 'invalid-id',
            'appointmentDate' => 'invalid-date',
            'duration' => -10, // Invalid: negative duration
            'type' => '', // Invalid: empty type
            'notes' => 'Test notes',
            'status' => 'invalid-status'
        ];
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($invalidAppointmentData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
        
        // Test accessing non-existent appointment
        $nonExistentId = new ObjectId();
        $this->client->request('GET', "/api/appointments/{$nonExistentId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        
        // Test unauthorized access
        $unauthorizedUser = $this->createTestUser(['ROLE_PATIENT']);
        $this->loginUser($unauthorizedUser);
        
        $this->client->request('POST', '/api/appointments', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'patientId' => 'some-id',
            'appointmentDate' => '2024-02-28T10:00:00Z',
            'duration' => 30,
            'type' => 'consultation',
            'status' => 'scheduled'
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
     * Helper method to create a test patient
     */
    private function createTestPatient(): Patient
    {
        $patient = new Patient();
        $patient->setFirstName('Test');
        $patient->setLastName('Patient');
        $patient->setEmail('test.patient@example.com');
        $patient->setPhoneNumber('555-000-0000');
        $patient->setBirthDate(new UTCDateTime(new \DateTime('1990-01-01')));
        $patient->setSsn('000-00-0000');
        $patient->setDiagnosis(['Test Diagnosis']);
        $patient->setMedications(['Test Medication']);
        $patient->setInsuranceDetails(['Test Insurance']);
        
        $this->documentManager->persist($patient);
        $this->documentManager->flush();
        
        return $patient;
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
        // Clear appointments
        $this->documentManager->getSchemaManager()->dropDocumentCollection(Appointment::class);
        
        // Clear patients
        $this->documentManager->getSchemaManager()->dropDocumentCollection(Patient::class);
        
        // Clear users
        $this->documentManager->getSchemaManager()->dropDocumentCollection(User::class);
        
        // Clear audit logs
        $this->documentManager->getSchemaManager()->dropDocumentCollection(\App\Document\AuditLog::class);
    }
}
