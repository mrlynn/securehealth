<?php

namespace App\Tests\Controller\Api;

use App\Document\Appointment;
use App\Document\Patient;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AppointmentControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private DocumentManager $documentManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->documentManager = $this->client->getContainer()->get('doctrine_mongodb.odm.document_manager');

        $schemaManager = $this->documentManager->getSchemaManager();
        $schemaManager->dropDocumentCollection(Appointment::class);
        $schemaManager->dropDocumentCollection(Patient::class);
    }

    private function loginAsDoctor(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                '_username' => 'doctor@example.com',
                '_password' => 'doctor',
            ])
        );

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    private function loginAsReceptionist(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                '_username' => 'receptionist@example.com',
                '_password' => 'receptionist',
            ])
        );

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    private function loginAsNurse(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                '_username' => 'nurse@example.com',
                '_password' => 'nurse',
            ])
        );

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    private function createTestPatient(): string
    {
        $this->loginAsDoctor();

        $payload = [
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'email' => 'alice.smith@example.com',
            'phoneNumber' => '555-000-0001',
            'birthDate' => '1990-01-01',
            'ssn' => '111-22-3333',
            'diagnosis' => ['Hypertension'],
            'medications' => ['Medication A'],
            'insuranceDetails' => ['provider' => 'Test Insurance'],
        ];

        $this->client->request(
            'POST',
            '/api/patients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);

        return $responseData['id'];
    }

    public function testReceptionistCanCreateAndListAppointments(): void
    {
        $patientId = $this->createTestPatient();
        $this->loginAsReceptionist();

        $scheduledAt = '2030-05-01T09:30';

        $this->client->request(
            'POST',
            '/api/appointments',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'patientId' => $patientId,
                'scheduledAt' => $scheduledAt,
                'notes' => 'Initial consultation',
            ])
        );

        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $appointmentData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($patientId, $appointmentData['patientId']);
        $this->assertSame('receptionist@example.com', $appointmentData['createdBy']);

        $this->client->request('GET', '/api/appointments');
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $listPayload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('appointments', $listPayload);
        $this->assertCount(1, $listPayload['appointments']);
        $this->assertSame($patientId, $listPayload['appointments'][0]['patientId']);
    }

    public function testNurseCannotAccessSchedulingEndpoints(): void
    {
        $patientId = $this->createTestPatient();
        $this->loginAsNurse();

        $this->client->request(
            'POST',
            '/api/appointments',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'patientId' => $patientId,
                'scheduledAt' => '2030-01-01T08:00',
            ])
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/appointments');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
