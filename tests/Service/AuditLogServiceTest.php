<?php

namespace App\Tests\Service;

use App\Document\AuditLog;
use App\Service\AuditLogService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogServiceTest extends KernelTestCase
{
    private AuditLogService $auditLogService;
    private $documentManager;
    
    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $this->documentManager = $container->get('doctrine_mongodb.odm.document_manager');
        
        // Clear audit logs collection before tests
        $this->documentManager->getSchemaManager()->dropDocumentCollection(AuditLog::class);
        
        // Create a mock request stack with a request
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        
        $requestStack = new RequestStack();
        $requestStack->push($request);
        
        $this->auditLogService = new AuditLogService($this->documentManager, $requestStack);
    }
    
    public function testLogEvent(): void
    {
        $log = $this->auditLogService->logEvent(
            'test.user',
            'TEST_EVENT',
            'This is a test event',
            '123',
            'TestEntity',
            ['key' => 'value']
        );
        
        $this->assertNotNull($log->getId());
        $this->assertEquals('test.user', $log->getUsername());
        $this->assertEquals('TEST_EVENT', $log->getActionType());
        $this->assertEquals('This is a test event', $log->getDescription());
        $this->assertEquals('123', $log->getEntityId());
        $this->assertEquals('TestEntity', $log->getEntityType());
        $this->assertEquals(['key' => 'value'], $log->getMetadata());
        $this->assertEquals('127.0.0.1', $log->getIpAddress());
        
        // Verify it's in the database
        $storedLog = $this->documentManager->getRepository(AuditLog::class)->findOneBy([
            'username' => 'test.user',
            'actionType' => 'TEST_EVENT'
        ]);
        
        $this->assertNotNull($storedLog);
        $this->assertEquals('This is a test event', $storedLog->getDescription());
    }
    
    public function testLogSecurityEvent(): void
    {
        $log = $this->auditLogService->logSecurityEvent(
            'test.user',
            'LOGIN',
            'User logged in',
            ['ip' => '127.0.0.1']
        );
        
        $this->assertEquals('SECURITY_LOGIN', $log->getActionType());
        $this->assertEquals('Security', $log->getEntityType());
        
        // Verify it's in the database
        $storedLog = $this->documentManager->getRepository(AuditLog::class)->findOneBy([
            'actionType' => 'SECURITY_LOGIN'
        ]);
        
        $this->assertNotNull($storedLog);
    }
    
    public function testLogPatientEvent(): void
    {
        $log = $this->auditLogService->logPatientEvent(
            'test.user',
            'CREATE',
            'Created patient record',
            '123',
            ['firstName' => 'John', 'lastName' => 'Doe']
        );
        
        $this->assertEquals('PATIENT_CREATE', $log->getActionType());
        $this->assertEquals('Patient', $log->getEntityType());
        $this->assertEquals('123', $log->getEntityId());
        
        // Verify it's in the database
        $storedLog = $this->documentManager->getRepository(AuditLog::class)->findOneBy([
            'actionType' => 'PATIENT_CREATE',
            'entityId' => '123'
        ]);
        
        $this->assertNotNull($storedLog);
    }
    
    public function testLogDataAccess(): void
    {
        $log = $this->auditLogService->logDataAccess(
            'test.user',
            'Patient',
            '123',
            'READ',
            'Read patient data',
            ['fields' => ['name', 'ssn']]
        );
        
        $this->assertEquals('ACCESS_READ', $log->getActionType());
        $this->assertEquals('Patient', $log->getEntityType());
        $this->assertEquals('123', $log->getEntityId());
        
        // Verify it's in the database
        $storedLog = $this->documentManager->getRepository(AuditLog::class)->findOneBy([
            'actionType' => 'ACCESS_READ',
            'entityId' => '123'
        ]);
        
        $this->assertNotNull($storedLog);
    }
    
    public function testGetLogsForEntity(): void
    {
        // Create multiple logs for the same entity
        for ($i = 0; $i < 5; $i++) {
            $this->auditLogService->logEvent(
                'test.user',
                "TEST_EVENT_$i",
                "Test event $i",
                '123',
                'TestEntity'
            );
        }
        
        // Create a log for a different entity
        $this->auditLogService->logEvent(
            'test.user',
            'TEST_EVENT_OTHER',
            'Test event other',
            '456',
            'TestEntity'
        );
        
        $logs = $this->auditLogService->getLogsForEntity('TestEntity', '123');
        
        $this->assertCount(5, $logs);
        
        // Verify they're all for entity id 123
        foreach ($logs as $log) {
            $this->assertEquals('123', $log->getEntityId());
        }
    }
    
    public function testGetLogsForUser(): void
    {
        // Create logs for test.user
        for ($i = 0; $i < 3; $i++) {
            $this->auditLogService->logEvent(
                'test.user',
                "TEST_EVENT_$i",
                "Test event $i"
            );
        }
        
        // Create logs for another.user
        for ($i = 0; $i < 2; $i++) {
            $this->auditLogService->logEvent(
                'another.user',
                "TEST_EVENT_$i",
                "Test event $i"
            );
        }
        
        $logs = $this->auditLogService->getLogsForUser('test.user');
        
        $this->assertCount(3, $logs);
        
        // Verify they're all for test.user
        foreach ($logs as $log) {
            $this->assertEquals('test.user', $log->getUsername());
        }
    }
}