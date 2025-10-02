<?php

namespace App\Tests\Controller\Api;

use App\Document\Patient;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PatientControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private DocumentManager $documentManager;
    
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->documentManager = static::getContainer()->get('doctrine_mongodb.odm.document_manager');
        
        // Clear the database before each test
        $this->documentManager->getSchemaManager()->dropDocumentCollection(Patient::class);
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
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
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
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
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
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }
    
    private function createTestPatient(): string
    {
        // Create a test patient
        $this->loginAsDoctor();
        
        $this->client->request(
            'POST',
            '/api/patients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John',
                'lastName' => 'Doe',
                'birthDate' => '1980-01-01',
                'ssn' => '123-45-6789',
                'diagnosis' => 'Hypertension',
                'contactPhone' => '555-123-4567',
                'contactEmail' => 'john.doe@example.com',
                'medications' => ['Lisinopril', 'Aspirin'],
                'allergies' => ['Penicillin']
            ])
        );
        
        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        
        // Get patient ID from response
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        return $responseData['id'];
    }
    
    public function testCreatePatient(): void
    {
        $this->loginAsDoctor();
        
        $this->client->request(
            'POST',
            '/api/patients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John',
                'lastName' => 'Doe',
                'birthDate' => '1980-01-01',
                'ssn' => '123-45-6789',
                'diagnosis' => 'Hypertension',
                'contactPhone' => '555-123-4567',
                'contactEmail' => 'john.doe@example.com'
            ])
        );
        
        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
    }
    
    public function testCreatePatientUnauthorizedAsNurse(): void
    {
        $this->loginAsNurse();
        
        $this->client->request(
            'POST',
            '/api/patients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John',
                'lastName' => 'Doe',
                'birthDate' => '1980-01-01',
                'ssn' => '123-45-6789',
                'diagnosis' => 'Hypertension'
            ])
        );
        
        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
    
    public function testGetPatient(): void
    {
        $patientId = $this->createTestPatient();
        
        $this->loginAsDoctor();
        
        $this->client->request('GET', '/api/patients/' . $patientId);
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertEquals('John', $responseData['firstName']);
        $this->assertEquals('Doe', $responseData['lastName']);
        $this->assertArrayHasKey('socialSecurityNumber', $responseData);
        $this->assertArrayHasKey('primaryDiagnosis', $responseData);
    }
    
    public function testGetPatientAsNurse(): void
    {
        $patientId = $this->createTestPatient();
        
        $this->loginAsNurse();
        
        $this->client->request('GET', '/api/patients/' . $patientId);
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertEquals('John', $responseData['firstName']);
        $this->assertEquals('Doe', $responseData['lastName']);
        $this->assertArrayHasKey('medications', $responseData);
        $this->assertArrayHasKey('allergies', $responseData);
        
        // Nurse should not see sensitive information
        $this->assertArrayNotHasKey('socialSecurityNumber', $responseData);
        $this->assertArrayNotHasKey('primaryDiagnosis', $responseData);
    }
    
    public function testUpdatePatient(): void
    {
        $patientId = $this->createTestPatient();
        
        $this->loginAsDoctor();
        
        $this->client->request(
            'PUT',
            '/api/patients/' . $patientId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Jane',
                'diagnosis' => 'Diabetes'
            ])
        );
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Verify changes
        $this->client->request('GET', '/api/patients/' . $patientId);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Jane', $responseData['firstName']);
        $this->assertEquals('Diabetes', $responseData['primaryDiagnosis']);
    }
    
    public function testUpdatePatientUnauthorizedAsNurse(): void
    {
        $patientId = $this->createTestPatient();
        
        $this->loginAsNurse();
        
        $this->client->request(
            'PUT',
            '/api/patients/' . $patientId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Jane',
                'diagnosis' => 'Diabetes'
            ])
        );
        
        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
    
    public function testDeletePatient(): void
    {
        $patientId = $this->createTestPatient();
        
        $this->loginAsDoctor();
        
        $this->client->request('DELETE', '/api/patients/' . $patientId);
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        // Verify patient is gone
        $this->client->request('GET', '/api/patients/' . $patientId);
        
        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }
    
    public function testDeletePatientUnauthorizedAsNurse(): void
    {
        $patientId = $this->createTestPatient();
        
        $this->loginAsNurse();
        
        $this->client->request('DELETE', '/api/patients/' . $patientId);
        
        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
    
    public function testSearchPatients(): void
    {
        // Create multiple test patients
        $this->loginAsDoctor();
        
        // Create patient 1
        $this->client->request(
            'POST',
            '/api/patients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John',
                'lastName' => 'Smith',
                'birthDate' => '1970-05-15',
                'ssn' => '123-45-6789',
                'diagnosis' => 'Hypertension'
            ])
        );
        
        // Create patient 2
        $this->client->request(
            'POST',
            '/api/patients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'birthDate' => '1985-10-20',
                'ssn' => '987-65-4321',
                'diagnosis' => 'Diabetes'
            ])
        );
        
        // Create patient 3
        $this->client->request(
            'POST',
            '/api/patients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Robert',
                'lastName' => 'Jones',
                'birthDate' => '1990-03-10',
                'ssn' => '456-78-9012',
                'diagnosis' => 'Asthma'
            ])
        );
        
        // Test search by last name
        $this->client->request('GET', '/api/patients/search?lastName=Smith');
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $responseData);
        
        // Test search by min age
        $this->client->request('GET', '/api/patients/search?minAge=40');
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(1, count($responseData));
    }
}