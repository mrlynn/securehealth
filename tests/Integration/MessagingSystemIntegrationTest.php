<?php

namespace App\Tests\Integration;

use App\Document\Conversation;
use App\Document\Message;
use App\Document\Patient;
use App\Document\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;

class MessagingSystemIntegrationTest extends WebTestCase
{
    private $client;
    private DocumentManager $documentManager;
    private ConversationRepository $conversationRepository;
    private MessageRepository $messageRepository;
    private PatientRepository $patientRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        
        $this->documentManager = $container->get('doctrine_mongodb.odm.document_manager');
        $this->conversationRepository = $container->get(ConversationRepository::class);
        $this->messageRepository = $container->get(MessageRepository::class);
        $this->patientRepository = $container->get(PatientRepository::class);
        
        // Clear test data
        $this->clearTestData();
    }

    protected function tearDown(): void
    {
        $this->clearTestData();
    }

    /**
     * Test 1: Complete Staff Messaging Workflow
     */
    public function testCompleteStaffMessagingWorkflow(): void
    {
        // Step 1: Create users
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR'], 'doctor@example.com');
        $nurseUser = $this->createTestUser(['ROLE_NURSE'], 'nurse@example.com');
        
        // Step 2: Create a patient
        $patient = $this->createTestPatient();
        
        // Step 3: Doctor starts conversation with nurse
        $this->loginUser($doctorUser);
        
        $conversationData = [
            'patientId' => (string)$patient->getId(),
            'subject' => 'Patient Consultation Discussion',
            'participants' => ['ROLE_NURSE'],
            'initialMessage' => 'Hi, I need to discuss the treatment plan for this patient.'
        ];
        
        $this->client->request('POST', '/api/conversations', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($conversationData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        
        $conversationId = $responseData['data']['id'];
        $this->assertNotNull($conversationId);
        
        // Step 4: Nurse replies to conversation
        $this->loginUser($nurseUser);
        
        $messageData = [
            'conversationId' => $conversationId,
            'body' => 'I agree with the treatment plan. The patient has been responding well to the medication.',
            'subject' => 'Re: Patient Consultation Discussion'
        ];
        
        $this->client->request('POST', '/api/messages', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($messageData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $messageId = $responseData['data']['id'];
        
        // Step 5: Doctor views conversation
        $this->loginUser($doctorUser);
        
        $this->client->request('GET', "/api/conversations/{$conversationId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']['messages']));
        
        // Step 6: Doctor sends follow-up message
        $followUpMessageData = [
            'conversationId' => $conversationId,
            'body' => 'Great! Let\'s schedule a follow-up appointment for next week.',
            'subject' => 'Re: Patient Consultation Discussion'
        ];
        
        $this->client->request('POST', '/api/messages', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($followUpMessageData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        // Step 7: Check unread message count
        $this->loginUser($nurseUser);
        
        $this->client->request('GET', '/api/messages/inbox/unread-count');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, $responseData['data']['unreadCount']);
        
        // Step 8: Nurse marks message as read
        $this->client->request('PUT', "/api/messages/{$messageId}/read");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Clean up
        $this->client->request('DELETE', "/api/conversations/{$conversationId}");
    }

    /**
     * Test 2: Patient Portal Messaging Workflow
     */
    public function testPatientPortalMessagingWorkflow(): void
    {
        // Step 1: Create patient and patient user
        $patient = $this->createTestPatient();
        $patientUser = $this->createTestUser(['ROLE_PATIENT'], 'patient@example.com');
        $patientUser->setPatientId($patient->getId());
        $this->documentManager->persist($patientUser);
        $this->documentManager->flush();
        
        // Step 2: Create doctor user
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR'], 'doctor@example.com');
        
        // Step 3: Patient sends message to doctor
        $this->loginUser($patientUser);
        
        $patientMessageData = [
            'patientId' => (string)$patient->getId(),
            'recipientRoles' => ['ROLE_DOCTOR'],
            'subject' => 'Question about my medication',
            'body' => 'I have been experiencing some side effects from my medication. Should I continue taking it?',
            'direction' => 'patient_to_staff'
        ];
        
        $this->client->request('POST', '/api/patient-portal/messages', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($patientMessageData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $messageId = $responseData['data']['id'];
        
        // Step 4: Doctor views patient messages
        $this->loginUser($doctorUser);
        
        $this->client->request('GET', '/api/messages/inbox');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Step 5: Doctor replies to patient
        $doctorReplyData = [
            'conversationId' => $responseData['data'][0]['conversationId'],
            'body' => 'Please continue taking your medication as prescribed. The side effects should subside within a week. If they persist, please contact us immediately.',
            'subject' => 'Re: Question about my medication'
        ];
        
        $this->client->request('POST', '/api/messages', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($doctorReplyData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        // Step 6: Patient views their messages
        $this->loginUser($patientUser);
        
        $this->client->request('GET', '/api/patient-portal/messages');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Clean up
        $this->loginUser($doctorUser);
        $this->client->request('DELETE', "/api/messages/{$messageId}");
    }

    /**
     * Test 3: Role-Based Access Control in Messaging
     */
    public function testRoleBasedAccessControlInMessaging(): void
    {
        $patient = $this->createTestPatient();
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR'], 'doctor@example.com');
        $nurseUser = $this->createTestUser(['ROLE_NURSE'], 'nurse@example.com');
        $receptionistUser = $this->createTestUser(['ROLE_RECEPTIONIST'], 'receptionist@example.com');
        $adminUser = $this->createTestUser(['ROLE_ADMIN'], 'admin@example.com');
        
        // Test Doctor Access
        $this->loginUser($doctorUser);
        
        $conversationData = [
            'patientId' => (string)$patient->getId(),
            'subject' => 'Doctor Test Conversation',
            'participants' => ['ROLE_NURSE'],
            'initialMessage' => 'Doctor can create conversations'
        ];
        
        $this->client->request('POST', '/api/conversations', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($conversationData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $conversationId = $responseData['data']['id'];
        
        // Test Nurse Access
        $this->loginUser($nurseUser);
        
        // Nurse can view conversations
        $this->client->request('GET', '/api/conversations');
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Nurse can send messages
        $messageData = [
            'conversationId' => $conversationId,
            'body' => 'Nurse can send messages',
            'subject' => 'Re: Doctor Test Conversation'
        ];
        
        $this->client->request('POST', '/api/messages', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($messageData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        // Test Receptionist Access
        $this->loginUser($receptionistUser);
        
        // Receptionist cannot access staff messaging
        $this->client->request('GET', '/api/conversations');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        $this->client->request('GET', '/api/messages/inbox');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Test Admin Access
        $this->loginUser($adminUser);
        
        // Admin cannot access staff messaging
        $this->client->request('GET', '/api/conversations');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        // Clean up
        $this->loginUser($doctorUser);
        $this->client->request('DELETE', "/api/conversations/{$conversationId}");
    }

    /**
     * Test 4: Message Threading and Conversation Management
     */
    public function testMessageThreadingAndConversationManagement(): void
    {
        $patient = $this->createTestPatient();
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR'], 'doctor@example.com');
        $nurseUser = $this->createTestUser(['ROLE_NURSE'], 'nurse@example.com');
        
        // Create conversation
        $this->loginUser($doctorUser);
        
        $conversationData = [
            'patientId' => (string)$patient->getId(),
            'subject' => 'Threading Test Conversation',
            'participants' => ['ROLE_NURSE'],
            'initialMessage' => 'Initial message in conversation'
        ];
        
        $this->client->request('POST', '/api/conversations', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($conversationData));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $conversationId = $responseData['data']['id'];
        
        // Send multiple messages to test threading
        $messages = [
            'First reply message',
            'Second reply message',
            'Third reply message'
        ];
        
        $this->loginUser($nurseUser);
        foreach ($messages as $messageBody) {
            $messageData = [
                'conversationId' => $conversationId,
                'body' => $messageBody,
                'subject' => 'Re: Threading Test Conversation'
            ];
            
            $this->client->request('POST', '/api/messages', [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode($messageData));
            
            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        }
        
        // Verify conversation has all messages
        $this->client->request('GET', "/api/conversations/{$conversationId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(4, count($responseData['data']['messages'])); // Initial + 3 replies
        
        // Test conversation status updates
        $statusUpdateData = [
            'status' => 'resolved',
            'lastMessagePreview' => 'Conversation resolved'
        ];
        
        $this->client->request('PUT', "/api/conversations/{$conversationId}", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($statusUpdateData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('resolved', $responseData['data']['status']);
        
        // Clean up
        $this->client->request('DELETE', "/api/conversations/{$conversationId}");
    }

    /**
     * Test 5: Real-time Message Polling
     */
    public function testRealTimeMessagePolling(): void
    {
        $patient = $this->createTestPatient();
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR'], 'doctor@example.com');
        $nurseUser = $this->createTestUser(['ROLE_NURSE'], 'nurse@example.com');
        
        // Create conversation
        $this->loginUser($doctorUser);
        
        $conversationData = [
            'patientId' => (string)$patient->getId(),
            'subject' => 'Real-time Test Conversation',
            'participants' => ['ROLE_NURSE'],
            'initialMessage' => 'Initial message'
        ];
        
        $this->client->request('POST', '/api/conversations', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($conversationData));
        
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $conversationId = $responseData['data']['id'];
        
        // Test polling for new messages
        $this->loginUser($nurseUser);
        
        // First poll - should return initial message
        $this->client->request('GET', "/api/conversations/{$conversationId}/messages", [
            'since' => '1970-01-01T00:00:00Z'
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Send new message
        $this->loginUser($doctorUser);
        
        $newMessageData = [
            'conversationId' => $conversationId,
            'body' => 'New message for polling test',
            'subject' => 'Re: Real-time Test Conversation'
        ];
        
        $this->client->request('POST', '/api/messages', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($newMessageData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        // Poll again - should return new message
        $this->loginUser($nurseUser);
        
        $this->client->request('GET', "/api/conversations/{$conversationId}/messages", [
            'since' => date('c', time() - 60) // Last minute
        ]);
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThan(0, count($responseData['data']));
        
        // Clean up
        $this->loginUser($doctorUser);
        $this->client->request('DELETE', "/api/conversations/{$conversationId}");
    }

    /**
     * Test 6: Error Handling in Messaging System
     */
    public function testErrorHandlingInMessagingSystem(): void
    {
        $doctorUser = $this->createTestUser(['ROLE_DOCTOR'], 'doctor@example.com');
        $this->loginUser($doctorUser);
        
        // Test invalid conversation data
        $invalidConversationData = [
            'patientId' => 'invalid-id',
            'subject' => '', // Invalid: empty subject
            'participants' => [], // Invalid: empty participants
            'initialMessage' => '' // Invalid: empty message
        ];
        
        $this->client->request('POST', '/api/conversations', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($invalidConversationData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
        
        // Test accessing non-existent conversation
        $nonExistentId = new ObjectId();
        $this->client->request('GET', "/api/conversations/{$nonExistentId}");
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        
        // Test sending message to non-existent conversation
        $invalidMessageData = [
            'conversationId' => (string)$nonExistentId,
            'body' => 'Test message',
            'subject' => 'Test subject'
        ];
        
        $this->client->request('POST', '/api/messages', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($invalidMessageData));
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        
        // Test unauthorized access
        $unauthorizedUser = $this->createTestUser(['ROLE_PATIENT'], 'patient@example.com');
        $this->loginUser($unauthorizedUser);
        
        $this->client->request('GET', '/api/conversations');
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Helper method to create a test user
     */
    private function createTestUser(array $roles, string $email): User
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
     * Helper method to create a test patient
     */
    private function createTestPatient(): Patient
    {
        $patient = new Patient();
        $patient->setFirstName('Test');
        $patient->setLastName('Patient');
        $patient->setEmail('test.patient@example.com');
        $patient->setPhoneNumber('555-000-0000');
        $patient->setBirthDate(new \DateTime('1990-01-01'));
        $patient->setSsn('000-00-0000');
        $patient->setDiagnosis('Test Diagnosis');
        $patient->setMedications(['Test Medication']);
        $patient->setInsuranceDetails('Test Insurance');
        
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
        // Clear conversations
        $this->documentManager->getSchemaManager()->dropDocumentCollection(Conversation::class);
        
        // Clear messages
        $this->documentManager->getSchemaManager()->dropDocumentCollection(Message::class);
        
        // Clear patients
        $this->documentManager->getSchemaManager()->dropDocumentCollection(Patient::class);
        
        // Clear users
        $this->documentManager->getSchemaManager()->dropDocumentCollection(User::class);
        
        // Clear audit logs
        $this->documentManager->getSchemaManager()->dropDocumentCollection(\App\Document\AuditLog::class);
    }
}
