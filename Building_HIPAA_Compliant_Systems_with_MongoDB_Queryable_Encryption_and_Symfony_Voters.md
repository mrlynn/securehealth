# Building HIPAA-Compliant Systems with MongoDB 8.2 Queryable Encryption and Symfony Voters

*Stop reading this article. Nobody reads tutorials anymore.*

Seriously, just go check out the live demo at [https://securehealth.dev](https://securehealth.dev), poke around the interactive documentation at [https://docs.securehealth.dev](https://docs.securehealth.dev), and if you're the type who likes to see how the sausage is made, the GitHub repo is at [https://github.com/mrlynn/securehealth](https://github.com/mrlynn/securehealth) or [https://securehealth.dev/github.html](https://securehealth.dev/github.html).

*Still here? Fine, I'll write this thing.*

Look, I get it. You're probably using AI to help you build stuff now (I mean, you're reading this, so you're definitely using AI). And that's cool - I do too. But here's the thing about HIPAA compliance: it's not just about checking boxes or following some tutorial. It's about actually understanding what you're building and why it matters.

I've spent way too many years wrestling with the nightmare that is building secure, encrypted data systems. You know the drill - you either encrypt everything and lose the ability to search your data, or you keep it unencrypted for searchability and pray the compliance auditors don't notice. It's like choosing between having a functional app and not getting sued.

MongoDB 8.2's Queryable Encryption changed everything for me. Combined with Symfony's voter system, I finally found a path that doesn't make me want to throw my laptop out the window. This article is basically me documenting how I built **SecureHealth** - a production-ready HIPAA-compliant medical records system that actually works.

## The Problem: Why Healthcare Data is a Pain in the Ass

Here's the thing about healthcare data - it's like the worst kind of puzzle. You need to encrypt everything because HIPAA will destroy you if you don't, but you also need to be able to search through it because doctors aren't going to memorize patient IDs. It's a classic catch-22.

Traditional approaches basically forced you to pick your poison:

- **Encrypt everything**: Great for security, terrible for functionality. Good luck finding that patient named "Smith" when everything is encrypted.
- **Keep it unencrypted**: Fast searches, but you might as well just hand your database to the first hacker who asks nicely.
- **Complex workarounds**: Usually involve multiple databases, sync issues, and the kind of code that makes you question your life choices.

MongoDB 8.2's Queryable Encryption is basically the "have your cake and eat it too" solution. You get encryption AND searchability. It's like magic, but with more documentation.

Throw in Symfony's voter system for access control, and suddenly you have something that's both secure and actually usable. Revolutionary, I know.

The **SecureHealth** system I built demonstrates all of this in practice. It's not just a toy example - it's a real application that handles real patient data with real security requirements. Go check it out at [securehealth.dev](https://securehealth.dev) if you want to see it in action.

## How This Magic Actually Works

Alright, let's get into the weeds. If you're going to build something like this, you need to understand what's happening under the hood. Don't worry, I'll try to make it less painful than reading MongoDB's documentation.

### The Encryption Schema: Where the Magic Happens

Queryable Encryption works through something called an `encryptedFieldsMap`. Think of it as a blueprint that tells MongoDB "encrypt this field this way, that field that way." It's defined in your app and enforced by the MongoDB driver, which is pretty neat because you don't have to remember to encrypt things manually.

```php
// Complete encryptedFieldsMap configuration
$encryptedFieldsMap = [
    'securehealth.patients' => [
        'fields' => [
            [
                'path' => 'firstName',
                'bsonType' => 'string',
                'keyId' => $firstNameKeyId,
                'queries' => [
                    [
                        'queryType' => 'equality'
                    ]
                ]
            ],
            [
                'path' => 'lastName', 
                'bsonType' => 'string',
                'keyId' => $lastNameKeyId,
                'queries' => [
                    [
                        'queryType' => 'equality'
                    ]
                ]
            ],
            [
                'path' => 'birthDate',
                'bsonType' => 'date',
                'keyId' => $birthDateKeyId,
                'queries' => [
                    [
                        'queryType' => 'range',
                        'min' => 0,
                        'max' => 1
                    ]
                ]
            ],
            [
                'path' => 'ssn',
                'bsonType' => 'string',
                'keyId' => $ssnKeyId
                // No queries array = random encryption (no search capability)
            ]
        ]
    ]
];
```

### The Driver Does the Heavy Lifting

Here's the beautiful part - once you set this up, the MongoDB driver handles all the encryption/decryption automatically. You just write normal queries, and it figures out what needs to be encrypted. It's like having a really smart intern who never asks questions.

```php
use MongoDB\Client;
use MongoDB\Driver\ClientEncryption;

class MongoDBEncryptionService
{
    private Client $client;
    private ClientEncryption $clientEncryption;
    private array $encryptedFieldsMap;
    
    public function __construct(string $connectionString, string $keyVaultNamespace)
    {
        // Set up encryption options
        $encryptionOptions = [
            'keyVaultNamespace' => $keyVaultNamespace,
            'kmsProviders' => [
                'local' => [
                    'key' => new Binary($this->masterKey, Binary::TYPE_GENERIC)
                ]
            ],
            'encryptedFieldsMap' => $this->encryptedFieldsMap,
            'bypassAutoEncryption' => false, // Enable automatic encryption
            'bypassQueryAnalysis' => false
        ];
        
        // Create client with encryption
        $this->client = new Client($connectionString, [], $encryptionOptions);
        
        // Set up client encryption for key management
        $this->clientEncryption = new ClientEncryption($encryptionOptions);
    }
    
    public function createDataKey(string $keyAltName): Binary
    {
        return $this->clientEncryption->createDataKey('local', [
            'keyAltName' => $keyAltName
        ]);
    }
}
```

### What You Can and Can't Do (The Fine Print)

Look, I'm not going to sugarcoat this. Queryable Encryption is awesome, but it's not magic. There are some limitations that will make you want to throw things.

#### What Actually Works:
1. **Equality Queries**: `{ firstName: "John" }` - This works because it uses deterministic encryption (same input = same encrypted output)
2. **Range Queries**: `{ birthDate: { $gte: new Date("1990-01-01") } }` - This works because it uses range encryption
3. **Regex Queries** (New in 8.2): `{ lastName: { $regex: "^Smith" } }` - This is in preview, so use at your own risk

#### What Will Make You Cry:
- **No Aggregation Pipeline Support**: Want to use `$match` or `$lookup` on encrypted fields? Nope. Sorry.
- **Limited Update Operations**: No `$inc`, `$mul`, or `$push` on encrypted fields. You'll have to fetch, decrypt, modify, encrypt, save.
- **No Collection Migrations**: Once you encrypt a collection, you're stuck with that name forever.
- **Schema Evolution is a Nightmare**: Want to rename an encrypted field? Good luck with that.
- **Performance Hit**: Encrypted indexes are slower and take up more space. That's just how it is.

### The Performance Reality Check

Here's the thing - encryption isn't free. It costs you in performance and storage. If you're building a system that needs to handle thousands of queries per second, you're going to feel this.

**Storage overhead is real:**
- Deterministic encryption: ~2x the original size
- Random encryption: ~3-4x the original size  
- Range encryption: ~2-3x the original size

So if you have 1GB of patient data, plan for 2-4GB of storage. Your AWS bill will thank you for planning ahead.

```php
// Performance monitoring for encrypted operations
class EncryptionPerformanceMonitor
{
    public function logEncryptionOperation(string $operation, float $duration, int $dataSize): void
    {
        $this->logger->info('Encryption operation', [
            'operation' => $operation,
            'duration_ms' => $duration * 1000,
            'data_size_bytes' => $dataSize,
            'overhead_factor' => $this->calculateOverheadFactor($dataSize)
        ]);
    }
    
    private function calculateOverheadFactor(int $dataSize): float
    {
        // Encrypted data typically requires 2-4x storage space
        // Deterministic encryption: ~2x overhead
        // Random encryption: ~3-4x overhead
        // Range encryption: ~2-3x overhead
        return $this->encryptedSize / $dataSize;
    }
}
```

## How This Whole Thing Actually Works

### The Architecture (Or: Why This Isn't Just Another Database)

The cool thing about Queryable Encryption is that it's not just "encrypt everything and hope for the best." It's actually a pretty sophisticated system that works like this:

1. **Your App Encrypts Everything**: Before data leaves your application, it gets encrypted based on your schema
2. **The Driver is the Bouncer**: The MongoDB driver makes sure everything follows the rules and handles all the key management stuff
3. **MongoDB Does the Heavy Lifting**: The server processes your encrypted queries without ever seeing the actual data
4. **Automatic Decryption**: When results come back, they get decrypted automatically on your end

It's like having a really smart middleman who speaks both "encrypted" and "human" and translates between them seamlessly.

### Symfony Voters: The Security Bouncer

Now, encryption is great and all, but you also need to control WHO can see WHAT. That's where Symfony's voter system comes in. Think of voters as really smart bouncers who decide whether someone gets into the VIP section.

Here's how the whole authorization dance works:

```
User logs in → Gets assigned roles → Voters check permissions → Access granted/denied
```

The process is pretty straightforward:

1. **User logs in**: They get a security token (like a wristband at a club)
2. **Role assignment**: The token says what they are (ROLE_DOCTOR, ROLE_NURSE, etc.)
3. **Voter evaluation**: Each voter looks at the request and says "yea" or "nay"
4. **Final decision**: Based on what the voters say, access is granted or denied

The beautiful thing about voters is that they're not just checking roles - they can look at context, relationships, time of day, whatever you want. It's like having a bouncer who knows not just your ID, but also whether you're friends with the patient and what time it is.

### Real-World Complexity: Multi-Tenant and Context

Here's where things get interesting. In the real world, you're not just dealing with "is this person a doctor?" You're dealing with "is this doctor allowed to see THIS specific patient's data?" 

Real HIPAA systems are messy because healthcare is messy. You might have:

```php
// Extended voter for multi-tenant healthcare systems
class PatientVoter extends Voter
{
    private function checkPermission(string $attribute, array $roles, $subject, UserInterface $user): bool
    {
        // Check role-based permissions first
        $roleGranted = $this->checkRolePermissions($attribute, $roles);
        
        if (!$roleGranted) {
            return false;
        }
        
        // Additional context-specific checks
        if ($subject instanceof Patient) {
            // Check if user belongs to same healthcare organization
            if (!$this->checkOrganizationAccess($user, $subject)) {
                return false;
            }
            
            // Check if user has specific patient relationship
            if (!$this->checkPatientRelationship($user, $subject)) {
                return false;
            }
            
            // Check time-based access restrictions
            if (!$this->checkTimeBasedAccess($user, $attribute)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function checkOrganizationAccess(UserInterface $user, Patient $patient): bool
    {
        // Ensure user and patient belong to same healthcare organization
        return $user->getOrganizationId() === $patient->getOrganizationId();
    }
    
    private function checkPatientRelationship(UserInterface $user, Patient $patient): bool
    {
        // Check if user has specific relationship to patient
        // (e.g., primary care physician, assigned nurse, etc.)
        return $this->patientRelationshipService->hasRelationship($user, $patient);
    }
}
```

## Full Request Lifecycle: Encryption + RBAC Integration

Understanding how encryption and RBAC work together in a complete request lifecycle is crucial for building robust HIPAA-compliant systems.

### Complete Request Flow

Here's how a typical request flows through our system:

```mermaid
sequenceDiagram
    participant Client
    participant Controller
    participant Voter
    participant Repository
    participant MongoDB
    participant EncryptionService
    participant AuditLog

    Client->>Controller: GET /api/patients/507f1f77bcf86cd799439011
    Controller->>Voter: Check PATIENT_VIEW permission
    Voter->>AuditLog: Log access attempt
    Voter-->>Controller: Permission granted/denied
    Controller->>Repository: Find patient by ID
    Repository->>MongoDB: Query encrypted collection
    MongoDB-->>Repository: Return encrypted document
    Repository->>EncryptionService: Decrypt fields based on permissions
    EncryptionService-->>Repository: Return decrypted data
    Repository-->>Controller: Return patient object
    Controller->>Voter: Check field-level permissions (SSN, diagnosis)
    Voter-->>Controller: Field access decisions
    Controller-->>Client: Return filtered JSON response
    Controller->>AuditLog: Log successful access
```

### Implementation: Field-Level Access Control

The key to HIPAA compliance is ensuring that only fields the user is permitted to view are decrypted and returned:

```php
<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Repository\PatientRepository;
use App\Security\Voter\PatientVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PatientController extends AbstractController
{
    public function __construct(
        private PatientRepository $patientRepository
    ) {}

    #[Route('/api/patients/{id}', methods: ['GET'])]
    public function getPatient(string $id): JsonResponse
    {
        $patient = $this->patientRepository->find($id);
        
        if (!$patient) {
            return new JsonResponse(['error' => 'Patient not found'], 404);
        }

        // Check if user can view this patient at all
        $this->denyAccessUnlessGranted(PatientVoter::VIEW, $patient);

        // Build response with field-level permission checks
        $response = [
            'id' => $patient->getId(),
            'firstName' => $patient->getFirstName(),
            'lastName' => $patient->getLastName(),
            'email' => $patient->getEmail(),
            'phoneNumber' => $patient->getPhoneNumber(),
            'birthDate' => $patient->getBirthDate(),
        ];

        // Only include sensitive data if user has specific permissions
        if ($this->isGranted(PatientVoter::VIEW_SSN, $patient)) {
            $response['ssn'] = $patient->getSsn();
        }

        if ($this->isGranted(PatientVoter::VIEW_DIAGNOSIS, $patient)) {
            $response['diagnosis'] = $patient->getDiagnosis();
        }

        if ($this->isGranted(PatientVoter::VIEW_MEDICATIONS, $patient)) {
            $response['medications'] = $patient->getMedications();
        }

        if ($this->isGranted(PatientVoter::VIEW_INSURANCE, $patient)) {
            $response['insuranceDetails'] = $patient->getInsuranceDetails();
        }

        return new JsonResponse($response);
    }

    #[Route('/api/patients/search', methods: ['POST'])]
    public function searchPatients(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Check if user can search patients
        $this->denyAccessUnlessGranted(PatientVoter::VIEW, new Patient());

        // Perform encrypted search
        $patients = $this->patientRepository->search($data['criteria']);
        
        // Filter results based on user permissions
        $filteredPatients = [];
        foreach ($patients as $patient) {
            if ($this->isGranted(PatientVoter::VIEW, $patient)) {
                $filteredPatients[] = $this->buildPatientResponse($patient);
            }
        }

        return new JsonResponse(['patients' => $filteredPatients]);
    }

    private function buildPatientResponse(Patient $patient): array
    {
        // Same field-level permission logic as getPatient
        $response = [
            'id' => $patient->getId(),
            'firstName' => $patient->getFirstName(),
            'lastName' => $patient->getLastName(),
            'email' => $patient->getEmail(),
        ];

        // Add sensitive fields only if permitted
        if ($this->isGranted(PatientVoter::VIEW_SSN, $patient)) {
            $response['ssn'] = $patient->getSsn();
        }

        return $response;
    }
}
```

### Encryption at Rest, in Transit, and in Use

Our architecture ensures encryption protection at all three levels:

#### 1. Encryption at Rest
- **MongoDB Queryable Encryption**: All PHI fields encrypted before storage
- **Key Management**: Encryption keys stored separately from data
- **Database Level**: MongoDB Atlas provides additional encryption layers

#### 2. Encryption in Transit
- **TLS/SSL**: All connections encrypted with TLS 1.2+
- **API Security**: HTTPS-only endpoints
- **Internal Communication**: Service-to-service communication encrypted

#### 3. Encryption in Use
- **Client-Side Decryption**: Data only decrypted on authorized client
- **Memory Protection**: Sensitive data cleared from memory after use
- **Field-Level Control**: Only authorized fields decrypted per user permissions

## The HIPAA Compliance Reality Check

Alright, let's talk about the elephant in the room - HIPAA compliance. This is where most developers' eyes glaze over, but it's actually not that bad once you understand what you're dealing with.

The good news is that our architecture handles most of the heavy lifting. Let me show you how we map specific HIPAA requirements to actual code:

### Access Control: Who Gets to See What

**The Rule**: Only people who are supposed to see patient data can see patient data. Shocking, I know.

**How We Handle It**: This is where our voter system really shines. We don't just check "are you a doctor?" - we check "are you THIS doctor, looking at THIS patient's data, at THIS time?"
```php
// Role hierarchy ensures proper access levels
role_hierarchy:
    ROLE_DOCTOR: [ROLE_NURSE, ROLE_RECEPTIONIST, ROLE_ADMIN]
    ROLE_NURSE: [ROLE_RECEPTIONIST]

// Voters enforce fine-grained permissions
class PatientVoter extends Voter
{
    private function checkPermission(string $attribute, array $roles, $subject, UserInterface $user): bool
    {
        // Multi-layered access control
        return $this->checkRolePermissions($attribute, $roles) &&
               $this->checkOrganizationAccess($user, $subject) &&
               $this->checkPatientRelationship($user, $subject);
    }
}
```

### Audit Controls: Big Brother is Watching (And That's Good)

**The Rule**: You need to log everything. EVERYTHING. Who accessed what, when, from where, and whether they were allowed to.

**Why This Matters**: If there's ever a breach or audit, you need to be able to show exactly what happened. It's like having security cameras everywhere, but for your data.

**How We Handle It**: Every single access attempt gets logged, whether it succeeds or fails. We track who, what, when, where, and why.
```php
class AuditLogService
{
    public function log(User $user, string $action, array $details = []): AuditLog
    {
        $auditLog = new AuditLog();
        $auditLog->setUserId($user->getId());
        $auditLog->setUsername($user->getUsername());
        $auditLog->setAction($action);
        $auditLog->setDetails($details);
        $auditLog->setTimestamp(new \DateTime());
        $auditLog->setIpAddress($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $auditLog->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $auditLog->setSessionId(session_id());
        
        // Log both successful and failed access attempts
        $this->documentManager->persist($auditLog);
        $this->documentManager->flush();
        
        return $auditLog;
    }
    
    public function generateComplianceReport(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->documentManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder()
            ->field('timestamp')->gte($startDate)
            ->field('timestamp')->lte($endDate)
            ->field('action')->in(['security_access', 'data_access', 'data_modification'])
            ->getQuery()
            ->execute()
            ->toArray();
    }
}
```

### Data Integrity: Making Sure Nothing Gets Messed Up

**The Rule**: Patient data can't be altered or destroyed without proper authorization and tracking.

**Why This Matters**: Imagine if someone could just go in and change a patient's diagnosis or delete their medical history. That would be... bad.

**How We Handle It**: Audit logs are immutable (can't be changed), and we have integrity checks to make sure encrypted data stays encrypted.
```php
// Immutable audit logs
#[ODM\Document(collection: 'audit_logs')]
class AuditLog
{
    #[ODM\Id]
    private $id;
    
    #[ODM\Field(type: 'string')]
    private $userId;
    
    #[ODM\Field(type: 'string')]
    private $action;
    
    #[ODM\Field(type: 'hash')]
    private $details;
    
    #[ODM\Field(type: 'date')]
    private $timestamp;
    
    // No setters for modification - logs are immutable
    // Only getters to ensure data integrity
}

// Data integrity checks
class DataIntegrityService
{
    public function verifyEncryptionIntegrity(Patient $patient): bool
    {
        $encryptedFields = ['ssn', 'diagnosis', 'medications'];
        
        foreach ($encryptedFields as $field) {
            $value = $patient->{'get' . ucfirst($field)}();
            if (!$this->isEncrypted($value)) {
                $this->logger->error('Unencrypted sensitive data detected', [
                    'patientId' => $patient->getId(),
                    'field' => $field
                ]);
                return false;
            }
        }
        
        return true;
    }
    
    private function isEncrypted($value): bool
    {
        return $value instanceof \MongoDB\BSON\Binary;
    }
}
```

### Authentication: Proving You Are Who You Say You Are

**The Rule**: You need to verify that people are actually who they claim to be before letting them near patient data.

**Why This Matters**: You can't just let anyone with a username and password access sensitive medical information. That's how breaches happen.

**How We Handle It**: Multi-factor authentication for sensitive operations, plus we log all authentication attempts (successful and failed).
```php
// Multi-factor authentication support
class AuthenticationService
{
    public function authenticateUser(string $username, string $password, ?string $mfaToken = null): User
    {
        $user = $this->userRepository->findByUsername($username);
        
        if (!$user || !password_verify($password, $user->getPassword())) {
            throw new AuthenticationException('Invalid credentials');
        }
        
        // Require MFA for sensitive operations
        if ($this->requiresMFA($user) && !$this->verifyMFA($user, $mfaToken)) {
            throw new AuthenticationException('MFA required');
        }
        
        // Log successful authentication
        $this->auditLogService->log($user, 'authentication_success', [
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
        
        return $user;
    }
    
    private function requiresMFA(User $user): bool
    {
        // Require MFA for admin users and doctors
        return in_array('ROLE_ADMIN', $user->getRoles()) || 
               in_array('ROLE_DOCTOR', $user->getRoles());
    }
}
```

### Transmission Security: Keeping Data Safe in Transit

**The Rule**: Any data being sent over the internet needs to be encrypted so hackers can't intercept it.

**Why This Matters**: Patient data traveling between your app and the database (or between your app and users) needs to be protected. No exceptions.

**How We Handle It**: Everything uses HTTPS/TLS, and we force secure connections in production.
```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            pattern: ^/
            stateless: false
            provider: app_user_provider
            custom_authenticators:
                - App\Security\JsonLoginAuthenticator
                - App\Security\SessionAuthenticator
            # Force HTTPS in production
            access_denied_handler: App\Security\AccessDeniedHandler
            # Session security
            session_fixation_strategy: migrate
            invalidate_session: true
```

## Let's Actually Build This Thing

Alright, enough theory. Let's get our hands dirty and build something that actually works. I'm going to show you how I structured the encryption service and voter system in the SecureHealth application.

If you want to see this stuff in action, go check out the live system at [securehealth.dev](https://securehealth.dev) and the documentation at [docs.securehealth.dev](https://docs.securehealth.dev). But if you're still reading this article instead of just looking at the working code, I'll walk you through it.

### The Encryption Service: The Heart of the System

The `MongoDBEncryptionService` is where all the magic happens. It's the thing that handles encryption and decryption while providing a clean interface for the rest of your app. Think of it as the translator between your app and MongoDB's encrypted world.

```php
<?php

namespace App\Service;

use MongoDB\Client;
use MongoDB\Driver\ClientEncryption;
use MongoDB\BSON\Binary;
use Psr\Log\LoggerInterface;

class MongoDBEncryptionService
{
    // Encryption algorithms for MongoDB Atlas
    const ALGORITHM_DETERMINISTIC = 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic';
    const ALGORITHM_RANDOM = 'AEAD_AES_256_CBC_HMAC_SHA_512-Random';
    const ALGORITHM_RANGE = 'range';
    
    private $client;
    private $clientEncryption;
    private $keyVaultNamespace;
    private $masterKey;
    private $encryptedFields = [];
    
    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger,
        ?Client $mongoClient = null
    ) {
        $this->logger = $logger;
        
        // Set up key vault namespace
        $this->keyVaultNamespace = $_ENV['MONGODB_KEY_VAULT_NAMESPACE'] ?? 'encryption.__keyVault';
        
        // Load master encryption key
        $keyFile = $_ENV['MONGODB_ENCRYPTION_KEY_PATH'] ?? 'docker/encryption.key';
        if (!file_exists($keyFile)) {
            $this->logger->warning('Encryption key file not found, generating new key: ' . $keyFile);
            file_put_contents($keyFile, random_bytes(96));
        }
        $this->masterKey = file_get_contents($keyFile);
        
        $this->configureEncryptedFieldsDefinitions();
    }
    
    private function configureEncryptedFieldsDefinitions(): void
    {
        $this->encryptedFields = [
            'patient' => [
                // Deterministic encryption for searchable fields
                'firstName' => [
                    'algorithm' => self::ALGORITHM_DETERMINISTIC,
                    'bsonType' => 'string'
                ],
                'lastName' => [
                    'algorithm' => self::ALGORITHM_DETERMINISTIC,
                    'bsonType' => 'string'
                ],
                'email' => [
                    'algorithm' => self::ALGORITHM_DETERMINISTIC,
                    'bsonType' => 'string'
                ],
                'phoneNumber' => [
                    'algorithm' => self::ALGORITHM_DETERMINISTIC,
                    'bsonType' => 'string'
                ],
                // Range encryption for date fields
                'birthDate' => [
                    'algorithm' => self::ALGORITHM_RANGE,
                    'bsonType' => 'string'
                ],
                // Random encryption for highly sensitive data
                'ssn' => [
                    'algorithm' => self::ALGORITHM_RANDOM,
                    'bsonType' => 'string'
                ],
                'diagnosis' => [
                    'algorithm' => self::ALGORITHM_RANDOM,
                    'bsonType' => 'array'
                ],
                'medications' => [
                    'algorithm' => self::ALGORITHM_RANDOM,
                    'bsonType' => 'array'
                ],
                'insuranceDetails' => [
                    'algorithm' => self::ALGORITHM_RANDOM,
                    'bsonType' => 'object'
                ]
            ]
        ];
    }
}
```

### The Patient Document: Where Encryption Meets Doctrine

Now here's where things get interesting. The Patient document shows how to integrate encryption seamlessly with Doctrine MongoDB ODM. The key is using lifecycle callbacks to automatically decrypt fields when they're loaded from the database.

This is pretty slick - you just define your document normally, and the encryption/decryption happens automatically behind the scenes.

```php
<?php

namespace App\Document;

use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;

#[ODM\Document(collection: 'patients')]
#[ODM\HasLifecycleCallbacks]
class Patient
{
    private static ?MongoDBEncryptionService $encryptionService = null;

    /**
     * Set the encryption service for decryption
     */
    public static function setEncryptionService(MongoDBEncryptionService $encryptionService): void
    {
        self::$encryptionService = $encryptionService;
    }

    /**
     * Post-load callback to decrypt encrypted fields
     */
    #[ODM\PostLoad]
    public function postLoad(): void
    {
        if (self::$encryptionService) {
            $this->decryptFields();
        }
    }

    /**
     * Decrypt all encrypted fields
     */
    private function decryptFields(): void
    {
        try {
            // Decrypt string fields
            if (is_string($this->firstName)) {
                $this->firstName = self::$encryptionService->decrypt('patient', 'firstName', $this->firstName);
            }
            if (is_string($this->lastName)) {
                $this->lastName = self::$encryptionService->decrypt('patient', 'lastName', $this->lastName);
            }
            if (is_string($this->email)) {
                $this->email = self::$encryptionService->decrypt('patient', 'email', $this->email);
            }
            if (is_string($this->phoneNumber)) {
                $this->phoneNumber = self::$encryptionService->decrypt('patient', 'phoneNumber', $this->phoneNumber);
            }
            if (is_string($this->ssn)) {
                $this->ssn = self::$encryptionService->decrypt('patient', 'ssn', $this->ssn);
            }
            if (is_string($this->birthDate)) {
                $this->birthDate = self::$encryptionService->decrypt('patient', 'birthDate', $this->birthDate);
            }
        } catch (\Exception $e) {
            // If decryption fails, keep the original values
            // This prevents the application from crashing if encryption keys are wrong
        }
    }

    #[ODM\Id]
    private $id = null;
    
    /**
     * Patient's last name - deterministically encrypted (searchable)
     */
    #[ODM\Field(type: 'raw')]
    private $lastName;

    /**
     * Patient's first name - deterministically encrypted (searchable)
     */
    #[ODM\Field(type: 'raw')]
    private $firstName;

    /**
     * Patient email - deterministically encrypted (searchable)
     */
    #[ODM\Field(type: 'raw')]
    private $email;
    
    /**
     * Patient phone number - deterministically encrypted (searchable)
     */
    #[ODM\Field(type: 'raw', nullable: true)]
    private $phoneNumber = null;

    /**
     * Patient's birth date - range encrypted (supports range queries)
     */
    #[ODM\Field(type: 'string')]
    private $birthDate;

    /**
     * Social Security Number - strongly encrypted (no search)
     */
    #[ODM\Field(type: 'raw', nullable: true)]
    private $ssn = null;

    /**
     * Medical diagnosis - strongly encrypted (no search)
     */
    #[ODM\Field(type: 'collection')]
    private ?array $diagnosis = [];

    /**
     * Medications - strongly encrypted (no search)
     */
    #[ODM\Field(type: 'collection')]
    private ?array $medications = [];
    
    /**
     * Insurance details - strongly encrypted (no search)
     */
    #[ODM\Field(type: 'hash', nullable: true)]
    private ?array $insuranceDetails = null;

    // ... getters and setters
}
```

## Fine-Grained Access Control: The Voter System

This is where the magic really happens for HIPAA compliance. Instead of simple role checks, voters let you make context-aware authorization decisions. It's like having a really smart security guard who knows not just who you are, but also what you're trying to do and whether you should be allowed to do it.

### The Patient Voter: Your Security Bouncer

The `PatientVoter` is where we implement fine-grained permissions for different types of patient data. This isn't just "are you a doctor?" - it's "are you THIS doctor, looking at THIS patient's SSN, at THIS time?"

The key insight here is that different types of data need different levels of protection. A patient's name? Most healthcare staff can see that. Their SSN? Only doctors. Their diagnosis? Doctors and nurses, but not admins.

```php
<?php

namespace App\Security\Voter;

use App\Document\Patient;
use App\Service\AuditLogService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class PatientVoter extends Voter
{
    // Define constants for patient-related permissions
    public const VIEW = 'PATIENT_VIEW';
    public const CREATE = 'PATIENT_CREATE';
    public const EDIT = 'PATIENT_EDIT';
    public const DELETE = 'PATIENT_DELETE';
    public const VIEW_DIAGNOSIS = 'PATIENT_VIEW_DIAGNOSIS';
    public const EDIT_DIAGNOSIS = 'PATIENT_EDIT_DIAGNOSIS';
    public const VIEW_MEDICATIONS = 'PATIENT_VIEW_MEDICATIONS';
    public const EDIT_MEDICATIONS = 'PATIENT_EDIT_MEDICATIONS';
    public const VIEW_SSN = 'PATIENT_VIEW_SSN';
    public const VIEW_INSURANCE = 'PATIENT_VIEW_INSURANCE';
    public const EDIT_INSURANCE = 'PATIENT_EDIT_INSURANCE';
    public const VIEW_NOTES = 'PATIENT_VIEW_NOTES';
    public const EDIT_NOTES = 'PATIENT_EDIT_NOTES';
    public const ADD_NOTE = 'PATIENT_ADD_NOTE';
    public const UPDATE_NOTE = 'PATIENT_UPDATE_NOTE';
    public const DELETE_NOTE = 'PATIENT_DELETE_NOTE';

    private AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Only vote on Patient objects and supported attributes
        if (!$subject instanceof Patient) {
            return false;
        }

        $supportedAttributes = [
            self::VIEW, self::CREATE, self::EDIT, self::DELETE,
            self::VIEW_DIAGNOSIS, self::EDIT_DIAGNOSIS,
            self::VIEW_MEDICATIONS, self::EDIT_MEDICATIONS,
            self::VIEW_SSN, self::VIEW_INSURANCE, self::EDIT_INSURANCE,
            self::VIEW_NOTES, self::EDIT_NOTES, self::ADD_NOTE,
            self::UPDATE_NOTE, self::DELETE_NOTE
        ];

        return in_array($attribute, $supportedAttributes);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        // User must be logged in
        if (!$user instanceof UserInterface) {
            return false;
        }

        $roles = $user->getRoles();

        // Audit the access attempt - regardless of permission result
        if ($subject instanceof Patient) {
            $this->auditLogService->log(
                $user,
                'security_access',
                [
                    'attribute' => $attribute,
                    'patientId' => $subject->getId() ? (string)$subject->getId() : null,
                    'granted' => null // Will update this after determining permission
                ]
            );
        }

        // Check permission based on role and attribute
        $granted = $this->checkPermission($attribute, $roles, $subject, $user);

        // Update audit log with result
        if ($subject instanceof Patient) {
            $this->auditLogService->updateLastLog([
                'granted' => $granted
            ]);
        }

        return $granted;
    }

    private function checkPermission(string $attribute, array $roles, $subject, UserInterface $user): bool
    {
        // ROLE_ADMIN has limited access - can view basic patient info but NOT medical data
        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->checkAdminPermissions($attribute);
        }

        // Handle specific permissions
        switch ($attribute) {
            case self::CREATE:
                // All authenticated healthcare staff can create patients
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles) || 
                       in_array('ROLE_RECEPTIONIST', $roles);

            case self::VIEW:
                // All authenticated healthcare staff can view basic patient info
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles) || 
                       in_array('ROLE_RECEPTIONIST', $roles);

            case self::EDIT:
                // Only doctors and nurses can edit patient basic info
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);
                       
            case self::DELETE:
                // Only doctors can delete patients
                return in_array('ROLE_DOCTOR', $roles);

            case self::VIEW_DIAGNOSIS:
            case self::VIEW_MEDICATIONS:
                // Only doctors and nurses can view medical data
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);

            case self::EDIT_DIAGNOSIS:
            case self::EDIT_MEDICATIONS:
                // Only doctors can edit medical data
                return in_array('ROLE_DOCTOR', $roles);

            case self::VIEW_SSN:
                // Only doctors can view SSN
                return in_array('ROLE_DOCTOR', $roles);

            case self::VIEW_INSURANCE:
            case self::EDIT_INSURANCE:
                // Only doctors and nurses can view/edit insurance
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);

            case self::VIEW_NOTES:
                // Doctors can view all notes, nurses can view notes
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);

            case self::ADD_NOTE:
            case self::EDIT_NOTES:
            case self::UPDATE_NOTE:
                // Only doctors can add/edit notes
                return in_array('ROLE_DOCTOR', $roles);

            case self::DELETE_NOTE:
                // Only doctors can delete notes
                return in_array('ROLE_DOCTOR', $roles);

            default:
                return false;
        }
    }

    private function checkAdminPermissions(string $attribute): bool
    {
        // Admins have very limited access to patient data
        // They can only view basic patient info for system administration
        return in_array($attribute, [
            self::VIEW, // Basic patient info only
        ]);
    }
}
```

### The Medical Knowledge Voter: Clinical Decision Support

For medical knowledge access, we need another voter that controls access to clinical decision support tools. This is where things get really interesting because you're dealing with different types of medical information that have different access requirements.

Drug interaction checking? Doctors and nurses can do that. Treatment guidelines? Only doctors. Diagnostic criteria? Definitely doctors only.

```php
<?php

namespace App\Security\Voter;

use App\Document\MedicalKnowledge;
use App\Service\AuditLogService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class MedicalKnowledgeVoter extends Voter
{
    // Define constants for medical knowledge permissions
    public const VIEW = 'MEDICAL_KNOWLEDGE_VIEW';
    public const SEARCH = 'MEDICAL_KNOWLEDGE_SEARCH';
    public const CREATE = 'MEDICAL_KNOWLEDGE_CREATE';
    public const EDIT = 'MEDICAL_KNOWLEDGE_EDIT';
    public const DELETE = 'MEDICAL_KNOWLEDGE_DELETE';
    public const CLINICAL_DECISION_SUPPORT = 'MEDICAL_KNOWLEDGE_CLINICAL_DECISION_SUPPORT';
    public const DRUG_INTERACTIONS = 'MEDICAL_KNOWLEDGE_DRUG_INTERACTIONS';
    public const TREATMENT_GUIDELINES = 'MEDICAL_KNOWLEDGE_TREATMENT_GUIDELINES';
    public const DIAGNOSTIC_CRITERIA = 'MEDICAL_KNOWLEDGE_DIAGNOSTIC_CRITERIA';

    private AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        $supportedAttributes = [
            self::VIEW, self::SEARCH, self::CREATE, self::EDIT, self::DELETE,
            self::CLINICAL_DECISION_SUPPORT, self::DRUG_INTERACTIONS,
            self::TREATMENT_GUIDELINES, self::DIAGNOSTIC_CRITERIA
        ];

        return in_array($attribute, $supportedAttributes);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof UserInterface) {
            return false;
        }

        $roles = $user->getRoles();

        // Audit the access attempt
        $this->auditLogService->log(
            $user,
            'MEDICAL_KNOWLEDGE_ACCESS',
            [
                'attribute' => $attribute,
                'subjectId' => $subject instanceof MedicalKnowledge ? (string)$subject->getId() : null,
                'granted' => null
            ]
        );

        $granted = $this->checkPermission($attribute, $roles, $subject, $user);

        // Update audit log with result
        $this->auditLogService->updateLastLog([
            'granted' => $granted
        ]);

        return $granted;
    }

    private function checkPermission(string $attribute, array $roles, $subject, UserInterface $user): bool
    {
        switch ($attribute) {
            case self::VIEW:
            case self::SEARCH:
                // All healthcare staff can view and search medical knowledge
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles) || 
                       in_array('ROLE_ADMIN', $roles);

            case self::CLINICAL_DECISION_SUPPORT:
            case self::TREATMENT_GUIDELINES:
            case self::DIAGNOSTIC_CRITERIA:
                // Only doctors can access advanced clinical tools
                return in_array('ROLE_DOCTOR', $roles);

            case self::DRUG_INTERACTIONS:
                // Doctors and nurses can check drug interactions
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);

            case self::CREATE:
            case self::EDIT:
                // Only doctors and admins can create/edit medical knowledge
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_ADMIN', $roles);

            case self::DELETE:
                // Only admins can delete medical knowledge
                return in_array('ROLE_ADMIN', $roles);

            default:
                return false;
        }
    }
}
```

## Security Configuration: Tying It All Together

The security configuration is where everything comes together. This is where you define your role hierarchies and access control rules. It's like the rulebook that tells your application who can do what.

The key thing to understand here is the role hierarchy. Doctors inherit all the permissions of nurses, nurses inherit all the permissions of receptionists, and so on. This makes sense in a healthcare context - if you're a doctor, you probably need to do everything a nurse can do, plus more.

```yaml
# config/packages/security.yaml
security:
    role_hierarchy:
        # Doctor has all permissions
        ROLE_DOCTOR: [ROLE_NURSE, ROLE_RECEPTIONIST, ROLE_ADMIN]
        # Nurse has more permissions than receptionist
        ROLE_NURSE: [ROLE_RECEPTIONIST]
        # Patients have their own separate role hierarchy
        ROLE_PATIENT: [ROLE_USER]

    access_control:
        # Create operations allowed for healthcare staff
        - { path: ^/api/patients$, methods: [POST], roles: [ROLE_DOCTOR, ROLE_NURSE, ROLE_RECEPTIONIST] }
        - { path: '^/api/patients/[a-f0-9]{24}$', methods: [PUT, DELETE], roles: ROLE_DOCTOR }
        # Read operations allowed for healthcare staff
        - { path: ^/api/patients, methods: [GET], roles: [ROLE_DOCTOR, ROLE_NURSE, ROLE_RECEPTIONIST] }
        # Medical Knowledge API access control
        - { path: ^/api/medical-knowledge/search, roles: [ROLE_DOCTOR, ROLE_ADMIN] }
        - { path: ^/api/medical-knowledge/clinical-decision-support, roles: ROLE_DOCTOR }
        - { path: ^/api/medical-knowledge/drug-interactions, roles: [ROLE_DOCTOR, ROLE_NURSE, ROLE_ADMIN] }
        # Audit logs API access control
        - { path: ^/api/audit-logs, roles: [ROLE_DOCTOR, ROLE_ADMIN] }
```

## Controller Implementation: Putting It All Together

Now here's where you see how all this stuff actually works in practice. Controllers demonstrate how to use voters for authorization decisions. The key thing to understand is that you're not just checking "can this user view patients?" - you're checking "can this user view THIS specific patient's SSN?"

This is where field-level access control really shines. You build your response based on what the user is actually allowed to see, not just what they're allowed to access.

```php
<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PatientController extends AbstractController
{
    public function __construct(
        private PatientRepository $patientRepository
    ) {}

    #[Route('/api/patients/{id}', methods: ['GET'])]
    public function getPatient(string $id): JsonResponse
    {
        $patient = $this->patientRepository->find($id);
        
        if (!$patient) {
            return new JsonResponse(['error' => 'Patient not found'], 404);
        }

        // Check if user can view this patient
        $this->denyAccessUnlessGranted(PatientVoter::VIEW, $patient);

        // Return patient data (encryption/decryption handled automatically)
        return new JsonResponse([
            'id' => $patient->getId(),
            'firstName' => $patient->getFirstName(),
            'lastName' => $patient->getLastName(),
            'email' => $patient->getEmail(),
            'phoneNumber' => $patient->getPhoneNumber(),
            'birthDate' => $patient->getBirthDate(),
            // Only include sensitive data if user has permission
            'ssn' => $this->isGranted(PatientVoter::VIEW_SSN, $patient) ? $patient->getSsn() : null,
            'diagnosis' => $this->isGranted(PatientVoter::VIEW_DIAGNOSIS, $patient) ? $patient->getDiagnosis() : null,
            'medications' => $this->isGranted(PatientVoter::VIEW_MEDICATIONS, $patient) ? $patient->getMedications() : null,
        ]);
    }

    #[Route('/api/patients/{id}/diagnosis', methods: ['PUT'])]
    public function updateDiagnosis(string $id, Request $request): JsonResponse
    {
        $patient = $this->patientRepository->find($id);
        
        if (!$patient) {
            return new JsonResponse(['error' => 'Patient not found'], 404);
        }

        // Check if user can edit diagnosis
        $this->denyAccessUnlessGranted(PatientVoter::EDIT_DIAGNOSIS, $patient);

        $data = json_decode($request->getContent(), true);
        $patient->setDiagnosis($data['diagnosis']);
        
        $this->patientRepository->save($patient);

        return new JsonResponse(['message' => 'Diagnosis updated successfully']);
    }
}
```

## Working with AI Assistants: Making Your Code AI-Friendly

Look, I know you're probably using AI to help you write code. I do too. And that's fine - AI is great at helping with boilerplate and understanding patterns. But if you want AI to actually help you build secure, HIPAA-compliant systems, you need to structure your code in ways that make it easy for AI to understand.

Here are some patterns I've found that work really well with AI assistants:

### 1. Use Descriptive Permission Constants

Don't just call everything `VIEW` or `EDIT`. Use names that actually describe what the permission does. AI can understand `VIEW_SENSITIVE_DATA` much better than `VIEW_SSN`.

```php
class PatientVoter extends Voter
{
    // Use descriptive constant names that AI can understand
    public const VIEW_BASIC_INFO = 'PATIENT_VIEW_BASIC_INFO';
    public const VIEW_MEDICAL_DATA = 'PATIENT_VIEW_MEDICAL_DATA';
    public const VIEW_SENSITIVE_DATA = 'PATIENT_VIEW_SENSITIVE_DATA';
    public const EDIT_MEDICAL_RECORDS = 'PATIENT_EDIT_MEDICAL_RECORDS';
    public const MANAGE_PATIENT_NOTES = 'PATIENT_MANAGE_NOTES';
}
```

### 2. Write Documentation That Actually Explains Things

Include detailed docblocks that explain the business logic, not just the technical implementation. AI needs to understand WHY you're doing something, not just HOW.

```php
/**
 * Determines if a user can view a specific type of patient data.
 * 
 * HIPAA Compliance Rules:
 * - Basic info (name, contact): All healthcare staff
 * - Medical data (diagnosis, medications): Doctors and nurses only
 * - Sensitive data (SSN): Doctors only
 * - Patient notes: Doctors can manage, nurses can view
 * 
 * @param string $attribute The permission being checked
 * @param array $roles User's assigned roles
 * @param Patient $patient The patient record being accessed
 * @param UserInterface $user The authenticated user
 * @return bool Whether access is granted
 */
private function checkPermission(string $attribute, array $roles, $subject, UserInterface $user): bool
{
    // Implementation here...
}
```

### 3. Write Error Messages That Help AI Understand What Went Wrong

Provide clear error messages that help AI understand what went wrong and how to fix it. Don't just say "Access denied" - explain WHY access was denied and what the user needs to do differently.

```php
if (!$this->isGranted(PatientVoter::VIEW_SENSITIVE_DATA, $patient)) {
    throw new AccessDeniedException(
        'Access denied: User role does not have permission to view sensitive patient data. ' .
        'Only doctors can access SSN and insurance information.'
    );
}
```

## Audit Logging: Because HIPAA Requires It

HIPAA requires comprehensive audit logging, and honestly, it's a good idea even if it wasn't required. You need to know who accessed what, when, and whether they were supposed to.

Here's how I implemented automatic audit logging in voters. The key insight is that you want to log EVERYTHING - successful access, failed access, and everything in between. When there's a breach or audit, you need to be able to show exactly what happened.

```php
<?php

namespace App\Service;

use App\Document\User;
use App\Document\AuditLog;
use Doctrine\ODM\MongoDB\DocumentManager;

class AuditLogService
{
    public function __construct(
        private DocumentManager $documentManager
    ) {}

    public function log(User $user, string $action, array $details = []): AuditLog
    {
        $auditLog = new AuditLog();
        $auditLog->setUserId($user->getId());
        $auditLog->setUsername($user->getUsername());
        $auditLog->setAction($action);
        $auditLog->setDetails($details);
        $auditLog->setTimestamp(new \DateTime());
        $auditLog->setIpAddress($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $auditLog->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        $this->documentManager->persist($auditLog);
        $this->documentManager->flush();

        return $auditLog;
    }

    public function updateLastLog(array $updates): void
    {
        $lastLog = $this->documentManager
            ->getRepository(AuditLog::class)
            ->findOneBy([], ['timestamp' => 'DESC']);

        if ($lastLog) {
            foreach ($updates as $key => $value) {
                $lastLog->setDetail($key, $value);
            }
            $this->documentManager->flush();
        }
    }
}
```

## Testing: Because Security Systems Need Testing

Look, I know testing isn't the most exciting part of building applications, but when you're dealing with HIPAA compliance and encryption, you can't afford to get it wrong. Comprehensive testing is essential.

Here's how I structure tests for both encryption and RBAC systems. The key is to test not just that things work, but that they work correctly under different scenarios.

### Encryption Testing: Making Sure Your Data Stays Encrypted

You need to test that your encryption actually works. This means testing that the same input produces the same encrypted output (for deterministic encryption), that different inputs produce different outputs (for random encryption), and that you can actually decrypt things when you need to.

```php
<?php

namespace App\Tests\Service;

use App\Service\MongoDBEncryptionService;
use MongoDB\BSON\Binary;
use PHPUnit\Framework\TestCase;

class MongoDBEncryptionServiceTest extends TestCase
{
    private MongoDBEncryptionService $encryptionService;

    protected function setUp(): void
    {
        $this->encryptionService = new MongoDBEncryptionService(
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testDeterministicEncryption(): void
    {
        $plaintext = "John Doe";
        $encrypted1 = $this->encryptionService->encrypt('patient', 'firstName', $plaintext);
        $encrypted2 = $this->encryptionService->encrypt('patient', 'firstName', $plaintext);
        
        // Deterministic encryption should produce same ciphertext
        $this->assertEquals($encrypted1, $encrypted2);
        
        // Verify decryption works
        $decrypted = $this->encryptionService->decrypt('patient', 'firstName', $encrypted1);
        $this->assertEquals($plaintext, $decrypted);
    }

    public function testRandomEncryption(): void
    {
        $plaintext = "123-45-6789";
        $encrypted1 = $this->encryptionService->encrypt('patient', 'ssn', $plaintext);
        $encrypted2 = $this->encryptionService->encrypt('patient', 'ssn', $plaintext);
        
        // Random encryption should produce different ciphertext
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // Verify decryption works
        $decrypted1 = $this->encryptionService->decrypt('patient', 'ssn', $encrypted1);
        $decrypted2 = $this->encryptionService->decrypt('patient', 'ssn', $encrypted2);
        $this->assertEquals($plaintext, $decrypted1);
        $this->assertEquals($plaintext, $decrypted2);
    }

    public function testEncryptedQuery(): void
    {
        // Test that encrypted equality queries work
        $patient = new Patient();
        $patient->setFirstName("Alice");
        
        $this->patientRepository->save($patient);
        
        // Query should work with plaintext
        $found = $this->patientRepository->findBy(['firstName' => 'Alice']);
        $this->assertCount(1, $found);
        $this->assertEquals('Alice', $found[0]->getFirstName());
    }

    public function testEncryptionIntegrity(): void
    {
        $patient = new Patient();
        $patient->setSsn("123-45-6789");
        
        $this->patientRepository->save($patient);
        
        // Verify SSN is encrypted in database
        $rawDocument = $this->patientRepository->getCollection()->findOne(['_id' => $patient->getId()]);
        $this->assertInstanceOf(Binary::class, $rawDocument['ssn']);
        
        // Verify decryption works
        $this->assertEquals("123-45-6789", $patient->getSsn());
    }
}
```

### RBAC Testing: Making Sure People Can Only See What They Should

Testing access control is just as important as testing encryption. You need to verify that doctors can see what doctors should see, nurses can see what nurses should see, and admins can't see things they shouldn't see.

The key here is to test different scenarios with different user roles and make sure the permissions work as expected.

```php
<?php

namespace App\Tests\Security;

use App\Document\Patient;
use App\Document\User;
use App\Security\Voter\PatientVoter;
use App\Service\AuditLogService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PatientVoterTest extends TestCase
{
    private PatientVoter $voter;
    private AuditLogService $auditLogService;

    protected function setUp(): void
    {
        $this->auditLogService = $this->createMock(AuditLogService::class);
        $this->voter = new PatientVoter($this->auditLogService);
    }

    public function testDoctorCanViewSensitiveData(): void
    {
        $patient = new Patient();
        $patient->setId('507f1f77bcf86cd799439011');
        
        $user = new User();
        $user->setRoles(['ROLE_DOCTOR']);
        
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_DOCTOR']);

        $result = $this->voter->vote($token, $patient, [PatientVoter::VIEW_SSN]);
        
        $this->assertEquals(1, $result); // VoterInterface::ACCESS_GRANTED
    }

    public function testNurseCannotViewSensitiveData(): void
    {
        $patient = new Patient();
        $patient->setId('507f1f77bcf86cd799439011');
        
        $user = new User();
        $user->setRoles(['ROLE_NURSE']);
        
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_NURSE']);

        $result = $this->voter->vote($token, $patient, [PatientVoter::VIEW_SSN]);
        
        $this->assertEquals(-1, $result); // VoterInterface::ACCESS_DENIED
    }

    public function testAdminCannotViewMedicalData(): void
    {
        $patient = new Patient();
        $patient->setId('507f1f77bcf86cd799439011');
        
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN']);

        $result = $this->voter->vote($token, $patient, [PatientVoter::VIEW_DIAGNOSIS]);
        
        $this->assertEquals(-1, $result); // VoterInterface::ACCESS_DENIED
    }

    public function testMultiTenantAccessControl(): void
    {
        $patient = new Patient();
        $patient->setId('507f1f77bcf86cd799439011');
        $patient->setOrganizationId('org-123');
        
        $user = new User();
        $user->setRoles(['ROLE_DOCTOR']);
        $user->setOrganizationId('org-456'); // Different organization
        
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_DOCTOR']);

        $result = $this->voter->vote($token, $patient, [PatientVoter::VIEW]);
        
        $this->assertEquals(-1, $result); // ACCESS_DENIED due to organization mismatch
    }
}
```

### Integration Testing: Making Sure Everything Works Together

Integration tests are where you test that all the pieces work together correctly. This is where you verify that the encryption, access control, and audit logging all work together as expected.

The key thing to test here is that different user roles get different responses from the same API endpoints. A doctor should see more data than a nurse, and a nurse should see more data than an admin.

```php
<?php

namespace App\Tests\Integration;

use App\Controller\Api\PatientController;
use App\Document\Patient;
use App\Document\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PatientControllerIntegrationTest extends WebTestCase
{
    public function testPatientAccessWithDifferentRoles(): void
    {
        $client = static::createClient();
        
        // Test as doctor
        $this->loginAs($client, 'doctor@securehealth.com', 'password');
        
        $client->request('GET', '/api/patients/507f1f77bcf86cd799439011');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('ssn', $response); // Doctor can see SSN
        $this->assertArrayHasKey('diagnosis', $response); // Doctor can see diagnosis
        
        // Test as nurse
        $this->loginAs($client, 'nurse@securehealth.com', 'password');
        
        $client->request('GET', '/api/patients/507f1f77bcf86cd799439011');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('ssn', $response); // Nurse cannot see SSN
        $this->assertArrayHasKey('diagnosis', $response); // Nurse can see diagnosis
        
        // Test as admin
        $this->loginAs($client, 'admin@securehealth.com', 'password');
        
        $client->request('GET', '/api/patients/507f1f77bcf86cd799439011');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('ssn', $response); // Admin cannot see SSN
        $this->assertArrayNotHasKey('diagnosis', $response); // Admin cannot see diagnosis
    }

    private function loginAs($client, string $username, string $password): void
    {
        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => $username,
            'password' => $password
        ]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
```

### Compliance Testing: Making Sure You're Actually HIPAA Compliant

Finally, you need to test that your system is actually HIPAA compliant. This means testing that all sensitive fields are encrypted, that audit logs are complete and immutable, and that access control works as expected.

This is where you verify that your system meets the actual HIPAA requirements, not just that it works correctly.

```php
<?php

namespace App\Tests\Compliance;

use App\Service\ComplianceValidator;
use PHPUnit\Framework\TestCase;

class ComplianceValidatorTest extends TestCase
{
    private ComplianceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ComplianceValidator();
    }

    public function testAllSensitiveFieldsEncrypted(): void
    {
        $patient = $this->createPatientWithSensitiveData();
        
        $isCompliant = $this->validator->validateEncryptionCompliance($patient);
        
        $this->assertTrue($isCompliant);
    }

    public function testAuditLogCompleteness(): void
    {
        $auditLogs = $this->createSampleAuditLogs();
        
        $complianceReport = $this->validator->validateAuditCompliance($auditLogs);
        
        $this->assertTrue($complianceReport['hasRequiredFields']);
        $this->assertTrue($complianceReport['hasProperRetention']);
        $this->assertTrue($complianceReport['hasImmutableLogs']);
    }

    public function testAccessControlCompliance(): void
    {
        $accessAttempts = $this->createSampleAccessAttempts();
        
        $complianceReport = $this->validator->validateAccessControlCompliance($accessAttempts);
        
        $this->assertTrue($complianceReport['properRoleHierarchy']);
        $this->assertTrue($complianceReport['fieldLevelAccess']);
        $this->assertTrue($complianceReport['multiTenantIsolation']);
    }
}
```

## Deployment: The Real World

Alright, so you've built this thing and it works on your local machine. Great. Now you need to deploy it to production, and that's where things get interesting.

When you're deploying HIPAA-compliant systems, you can't just throw them on any old server and hope for the best. You need to think about environment variables, key management, and all the other stuff that makes production deployments fun.

### Environment Variables: The Configuration Nightmare

You're going to need a lot of environment variables. MongoDB connection strings, encryption keys, audit log retention periods - the list goes on. Make sure you document all of them and use a proper secrets management system.

```bash
# MongoDB Configuration
MONGODB_URI=mongodb+srv://username:password@cluster.mongodb.net/
MONGODB_DB=securehealth
MONGODB_KEY_VAULT_NAMESPACE=encryption.__keyVault
MONGODB_ENCRYPTION_KEY_PATH=/secure/path/to/encryption.key

# Security Configuration
APP_SECRET=your-app-secret-key
APP_ENV=prod

# Audit Configuration
AUDIT_LOG_RETENTION_DAYS=2555  # 7 years for HIPAA compliance
```

### Key Management: The Security Nightmare

For production deployments, you can't just store your encryption keys in a file on the server. You need a proper Key Management Service (KMS). AWS KMS, Azure Key Vault, Google Cloud KMS - pick one and use it.

The key insight here is that your encryption keys are more valuable than your data. If someone gets your keys, they can decrypt everything. So protect them accordingly.

```php
// Production key management
$kmsProviders = [
    'aws' => [
        'accessKeyId' => $_ENV['AWS_ACCESS_KEY_ID'],
        'secretAccessKey' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        'sessionToken' => $_ENV['AWS_SESSION_TOKEN'] ?? null,
    ],
    'azure' => [
        'tenantId' => $_ENV['AZURE_TENANT_ID'],
        'clientId' => $_ENV['AZURE_CLIENT_ID'],
        'clientSecret' => $_ENV['AZURE_CLIENT_SECRET'],
    ],
    'gcp' => [
        'email' => $_ENV['GCP_EMAIL'],
        'privateKey' => $_ENV['GCP_PRIVATE_KEY'],
    ],
];
```

## Operational Considerations: The Day-to-Day Reality

Building HIPAA-compliant systems is one thing. Running them day-to-day is another thing entirely. You need to think about key management, data retention, performance monitoring, and disaster recovery.

This is where most developers' eyes glaze over, but it's actually pretty important. If you don't plan for these things upfront, you're going to have a bad time later.

### Key Management and Rotation: The Never-Ending Story

Proper key management is critical for long-term security. You can't just create encryption keys once and forget about them. You need to rotate them regularly, and you need to plan for what happens when keys get compromised.

The key insight here is that key rotation is a process, not a one-time event. You need to schedule it, test it, and make sure it doesn't break your application.

```php
<?php

namespace App\Service;

use MongoDB\Driver\ClientEncryption;
use MongoDB\BSON\Binary;

class KeyManagementService
{
    private ClientEncryption $clientEncryption;
    private array $keyRotationSchedule = [];

    public function __construct(ClientEncryption $clientEncryption)
    {
        $this->clientEncryption = $clientEncryption;
    }

    public function rotateDataKey(string $keyAltName): Binary
    {
        // Create new data key
        $newKey = $this->clientEncryption->createDataKey('local', [
            'keyAltName' => $keyAltName . '_v' . time()
        ]);

        // Schedule re-encryption of existing data
        $this->scheduleReEncryption($keyAltName, $newKey);

        return $newKey;
    }

    private function scheduleReEncryption(string $oldKeyAltName, Binary $newKey): void
    {
        // This would typically be handled by a background job
        // that re-encrypts all data with the new key
        $this->keyRotationSchedule[] = [
            'oldKey' => $oldKeyAltName,
            'newKey' => $newKey,
            'scheduledAt' => new \DateTime(),
            'status' => 'pending'
        ];
    }

    public function getKeyRotationStatus(): array
    {
        return $this->keyRotationSchedule;
    }
}
```

### Data Retention and Archival: The Storage Nightmare

HIPAA requires specific data retention policies. You need to keep audit logs for 7 years, and you need to handle patient data deletion requests properly.

The key insight here is that "deletion" in HIPAA terms usually means "anonymization," not actual deletion. You need to keep the data for compliance purposes, but you need to remove the identifying information.

```php
<?php

namespace App\Service;

use App\Document\Patient;
use App\Document\AuditLog;
use Doctrine\ODM\MongoDB\DocumentManager;

class DataRetentionService
{
    private DocumentManager $documentManager;
    private int $auditLogRetentionDays = 2555; // 7 years
    private int $patientDataRetentionDays = 2555; // 7 years

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function archiveOldAuditLogs(): int
    {
        $cutoffDate = new \DateTime("-{$this->auditLogRetentionDays} days");
        
        $logsToArchive = $this->documentManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder()
            ->field('timestamp')->lt($cutoffDate)
            ->getQuery()
            ->execute();

        $archivedCount = 0;
        foreach ($logsToArchive as $log) {
            $this->archiveAuditLog($log);
            $this->documentManager->remove($log);
            $archivedCount++;
        }

        $this->documentManager->flush();
        return $archivedCount;
    }

    private function archiveAuditLog(AuditLog $log): void
    {
        // Move to cold storage or compressed archive
        $archiveData = [
            'id' => $log->getId(),
            'userId' => $log->getUserId(),
            'action' => $log->getAction(),
            'details' => $log->getDetails(),
            'timestamp' => $log->getTimestamp(),
            'archivedAt' => new \DateTime()
        ];

        // Store in archival system (S3, Glacier, etc.)
        $this->archivalStorage->store($archiveData);
    }

    public function handlePatientDataDeletion(string $patientId): void
    {
        $patient = $this->documentManager->find(Patient::class, $patientId);
        
        if (!$patient) {
            return;
        }

        // Log the deletion request
        $this->auditLogService->log(
            $this->getCurrentUser(),
            'patient_data_deletion_request',
            ['patientId' => $patientId]
        );

        // Anonymize instead of delete (HIPAA requirement)
        $this->anonymizePatientData($patient);
        
        $this->documentManager->flush();
    }

    private function anonymizePatientData(Patient $patient): void
    {
        $patient->setFirstName('ANONYMIZED');
        $patient->setLastName('ANONYMIZED');
        $patient->setEmail('anonymized@example.com');
        $patient->setSsn('000-00-0000');
        $patient->setDiagnosis([]);
        $patient->setMedications([]);
        $patient->setAnonymizedAt(new \DateTime());
    }
}
```

### Performance Monitoring: The Speed Reality Check

Monitoring encrypted operations is crucial for maintaining system performance. Encryption adds overhead, and you need to know when that overhead is becoming a problem.

The key insight here is that you need to monitor not just the overall performance of your application, but specifically the performance of encrypted operations. They're going to be slower than unencrypted operations, and you need to plan for that.

```php
<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class EncryptionPerformanceMonitor
{
    private LoggerInterface $logger;
    private Stopwatch $stopwatch;
    private array $performanceMetrics = [];

    public function __construct(LoggerInterface $logger, Stopwatch $stopwatch)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    public function monitorEncryptionOperation(string $operation, callable $callback)
    {
        $this->stopwatch->start($operation);
        
        try {
            $result = $callback();
            
            $event = $this->stopwatch->stop($operation);
            $this->recordMetrics($operation, $event->getDuration(), true);
            
            return $result;
        } catch (\Exception $e) {
            $event = $this->stopwatch->stop($operation);
            $this->recordMetrics($operation, $event->getDuration(), false);
            
            throw $e;
        }
    }

    private function recordMetrics(string $operation, int $duration, bool $success): void
    {
        $this->performanceMetrics[] = [
            'operation' => $operation,
            'duration_ms' => $duration,
            'success' => $success,
            'timestamp' => new \DateTime()
        ];

        // Log performance issues
        if ($duration > 1000) { // More than 1 second
            $this->logger->warning('Slow encryption operation', [
                'operation' => $operation,
                'duration_ms' => $duration
            ]);
        }

        // Alert on failures
        if (!$success) {
            $this->logger->error('Encryption operation failed', [
                'operation' => $operation,
                'duration_ms' => $duration
            ]);
        }
    }

    public function getPerformanceReport(): array
    {
        $report = [];
        
        foreach ($this->performanceMetrics as $metric) {
            $operation = $metric['operation'];
            
            if (!isset($report[$operation])) {
                $report[$operation] = [
                    'count' => 0,
                    'total_duration' => 0,
                    'success_count' => 0,
                    'failure_count' => 0,
                    'avg_duration' => 0,
                    'max_duration' => 0
                ];
            }
            
            $report[$operation]['count']++;
            $report[$operation]['total_duration'] += $metric['duration_ms'];
            $report[$operation]['max_duration'] = max($report[$operation]['max_duration'], $metric['duration_ms']);
            
            if ($metric['success']) {
                $report[$operation]['success_count']++;
            } else {
                $report[$operation]['failure_count']++;
            }
        }

        // Calculate averages
        foreach ($report as $operation => &$stats) {
            $stats['avg_duration'] = $stats['total_duration'] / $stats['count'];
            $stats['success_rate'] = $stats['success_count'] / $stats['count'];
        }

        return $report;
    }
}
```

### Disaster Recovery: The Backup Nightmare

Planning for disaster recovery with encrypted data requires special considerations. You can't just backup your data - you need to backup your encryption keys too, and you need to make sure they're stored separately from your data.

The key insight here is that encrypted data without encryption keys is useless. So your backup strategy needs to include both data and keys, and they need to be stored in different places.

```php
<?php

namespace App\Service;

class DisasterRecoveryService
{
    private MongoDBEncryptionService $encryptionService;
    private array $backupProviders = [];

    public function __construct(MongoDBEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    public function createEncryptedBackup(): array
    {
        $backupId = uniqid('backup_');
        $backupData = [
            'backup_id' => $backupId,
            'created_at' => new \DateTime(),
            'encryption_keys' => $this->exportEncryptionKeys(),
            'data' => $this->exportEncryptedData(),
            'metadata' => $this->exportMetadata()
        ];

        // Store backup in multiple locations
        foreach ($this->backupProviders as $provider) {
            $provider->store($backupId, $backupData);
        }

        return [
            'backup_id' => $backupId,
            'status' => 'completed',
            'locations' => array_keys($this->backupProviders)
        ];
    }

    private function exportEncryptionKeys(): array
    {
        // Export key vault data (encrypted with master key)
        return $this->encryptionService->exportKeyVault();
    }

    private function exportEncryptedData(): array
    {
        // Export all encrypted collections
        $collections = ['patients', 'audit_logs', 'medical_knowledge'];
        $data = [];

        foreach ($collections as $collection) {
            $data[$collection] = $this->documentManager
                ->getDocumentCollection($collection)
                ->find()
                ->toArray();
        }

        return $data;
    }

    public function restoreFromBackup(string $backupId): bool
    {
        try {
            // Retrieve backup from primary location
            $backupData = $this->backupProviders['primary']->retrieve($backupId);
            
            // Restore encryption keys first
            $this->encryptionService->importKeyVault($backupData['encryption_keys']);
            
            // Restore data
            foreach ($backupData['data'] as $collection => $documents) {
                $this->documentManager
                    ->getDocumentCollection($collection)
                    ->insertMany($documents);
            }
            
            // Verify integrity
            $this->verifyBackupIntegrity($backupData);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Backup restoration failed', [
                'backup_id' => $backupId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    private function verifyBackupIntegrity(array $backupData): void
    {
        // Verify that encrypted data can be decrypted
        foreach ($backupData['data']['patients'] as $patientDoc) {
            $patient = $this->documentManager->getUnitOfWork()->getDocumentById($patientDoc['_id']);
            $this->encryptionService->verifyDecryption($patient);
        }
    }
}
```

## Scalability and Performance: The Growth Reality

As your HIPAA-compliant system grows, performance considerations become critical. Encrypted data is slower to query, takes up more space, and requires more careful indexing.

This is where you need to think about indexing strategies, sharding, and all the other stuff that makes MongoDB scale. The key insight is that encrypted indexes are different from regular indexes, and you need to plan for that.

### Indexing Strategy for Encrypted Data: The Performance Reality

You need to create indexes for your encrypted data, but encrypted indexes are slower and take up more space than regular indexes. You need to be strategic about which fields you index and how you structure your queries.

The key insight here is that you should only index fields that you actually query on. Don't just index everything because you can - index what you need, and make sure your queries use those indexes.

```php
<?php

namespace App\Service;

class EncryptedIndexingService
{
    private MongoDBEncryptionService $encryptionService;

    public function createEncryptedIndexes(): void
    {
        // Create equality indexes for deterministically encrypted fields
        $this->createEqualityIndexes();
        
        // Create range indexes for range-encrypted fields
        $this->createRangeIndexes();
        
        // Create compound indexes for common query patterns
        $this->createCompoundIndexes();
    }

    private function createEqualityIndexes(): void
    {
        $equalityFields = ['firstName', 'lastName', 'email'];
        
        foreach ($equalityFields as $field) {
            $this->documentManager
                ->getDocumentCollection('patients')
                ->createIndex([$field => 1], [
                    'name' => "idx_{$field}_equality",
                    'background' => true
                ]);
        }
    }

    private function createRangeIndexes(): void
    {
        // Range indexes for date fields
        $this->documentManager
            ->getDocumentCollection('patients')
            ->createIndex(['birthDate' => 1], [
                'name' => 'idx_birthdate_range',
                'background' => true
            ]);
    }

    private function createCompoundIndexes(): void
    {
        // Compound index for common search patterns
        $this->documentManager
            ->getDocumentCollection('patients')
            ->createIndex([
                'firstName' => 1,
                'lastName' => 1,
                'organizationId' => 1
            ], [
                'name' => 'idx_patient_search',
                'background' => true
            ]);
    }

    public function optimizeQueryPerformance(string $query): array
    {
        $explainResult = $this->documentManager
            ->getDocumentCollection('patients')
            ->find($query)
            ->explain();

        return [
            'execution_time_ms' => $explainResult['executionTimeMillis'],
            'index_used' => $explainResult['indexName'] ?? 'none',
            'documents_examined' => $explainResult['totalDocsExamined'],
            'documents_returned' => $explainResult['totalDocsReturned'],
            'optimization_suggestions' => $this->generateOptimizationSuggestions($explainResult)
        ];
    }

    private function generateOptimizationSuggestions(array $explainResult): array
    {
        $suggestions = [];
        
        if ($explainResult['totalDocsExamined'] > $explainResult['totalDocsReturned'] * 10) {
            $suggestions[] = 'Consider adding an index to reduce document examination';
        }
        
        if ($explainResult['executionTimeMillis'] > 100) {
            $suggestions[] = 'Query execution time is high, consider query optimization';
        }
        
        if (!isset($explainResult['indexName'])) {
            $suggestions[] = 'Query is not using an index, consider adding appropriate indexes';
        }
        
        return $suggestions;
    }
}
```

### Sharding Considerations: The Scale Reality

For large-scale deployments, sharding encrypted data requires careful planning. You can't just shard on any field - you need to think about data locality and query patterns.

The key insight here is that you should shard on fields that are not encrypted, or on fields that use deterministic encryption. Random encryption makes sharding much more difficult because you can't predict where data will be stored.

```php
<?php

namespace App\Service;

class EncryptedShardingService
{
    public function configureSharding(): void
    {
        // Enable sharding on the database
        $this->mongodbClient->admin->command(['enableSharding' => 'securehealth']);
        
        // Shard patients collection by organization (ensures data locality)
        $this->mongodbClient->admin->command([
            'shardCollection' => 'securehealth.patients',
            'key' => ['organizationId' => 1, '_id' => 1]
        ]);
        
        // Shard audit logs by date (for time-based queries)
        $this->mongodbClient->admin->command([
            'shardCollection' => 'securehealth.audit_logs',
            'key' => ['timestamp' => 1, '_id' => 1]
        ]);
    }

    public function optimizeShardDistribution(): array
    {
        $shardStats = [];
        
        foreach ($this->getShardList() as $shard) {
            $stats = $this->mongodbClient->admin->command([
                'shardDataDistribution' => $shard
            ]);
            
            $shardStats[$shard] = [
                'document_count' => $stats['count'],
                'data_size_mb' => $stats['size'] / 1024 / 1024,
                'chunk_count' => $stats['chunks'],
                'balance_score' => $this->calculateBalanceScore($stats)
            ];
        }
        
        return $shardStats;
    }

    private function calculateBalanceScore(array $stats): float
    {
        // Calculate how well-balanced this shard is
        $expectedSize = $this->getExpectedShardSize();
        $actualSize = $stats['size'];
        
        return min(1.0, $expectedSize / $actualSize);
    }
}
```

## Future Enhancements: What's Coming Next

MongoDB Queryable Encryption continues to evolve, and there are some exciting features coming down the pipeline. Here's what to look forward to:

### Substring/Prefix/Suffix Search: The Regex Reality

MongoDB 8.2 introduces support for substring searches on encrypted data. This is huge because it means you can do things like "find all patients whose last name starts with 'Smith'" without having to decrypt everything first.

This is still in preview, so use it at your own risk, but it's a game-changer for search functionality.

```php
// Future implementation for substring search
$encryptedFieldsMap = [
    'securehealth.patients' => [
        'fields' => [
            [
                'path' => 'lastName',
                'bsonType' => 'string',
                'keyId' => $lastNameKeyId,
                'queries' => [
                    [
                        'queryType' => 'equality'
                    ],
                    [
                        'queryType' => 'substring' // New in 8.2
                    ]
                ]
            ]
        ]
    ]
];

// This will enable queries like:
// db.patients.find({ lastName: { $regex: "^Smith" } })
// db.patients.find({ lastName: { $regex: "son$" } })
// db.patients.find({ lastName: { $regex: "mit" } })
```

### AI/ML on Encrypted Data: The Future Reality

Future MongoDB versions may support AI/ML operations on encrypted data. This would be huge because it means you could run machine learning algorithms on patient data without ever decrypting it.

This is still hypothetical, but it's the kind of thing that could revolutionize healthcare data analysis while maintaining privacy.

```php
// Hypothetical future implementation
class EncryptedMLService
{
    public function analyzeEncryptedPatientData(array $patientIds): array
    {
        // This would run ML algorithms on encrypted data
        // without decrypting it on the server
        return $this->mongodbClient
            ->selectDatabase('securehealth')
            ->selectCollection('patients')
            ->aggregate([
                ['$match' => ['_id' => ['$in' => $patientIds]]],
                ['$mlPredict' => [
                    'model' => 'patient_risk_assessment',
                    'inputFields' => ['encrypted_diagnosis', 'encrypted_medications'],
                    'outputField' => 'risk_score'
                ]]
            ])
            ->toArray();
    }
}
```

### Enhanced Key Management: The Security Reality

Future enhancements may include better key management features like HSM integration, key escrow services, and cross-region key replication.

The key insight here is that key management is going to get easier and more secure over time. But for now, you need to plan for the current limitations.

- **Hardware Security Module (HSM) Integration**: Direct integration with HSMs for key management
- **Key Escrow Services**: Automated key escrow for compliance requirements
- **Cross-Region Key Replication**: Automatic key replication across regions for disaster recovery

### Performance Optimizations: The Speed Reality

Upcoming performance improvements include better compression for encrypted indexes, multi-threaded encryption for large datasets, and cache-friendly encryption algorithms.

The key insight here is that encryption performance is going to get better over time. But for now, you need to plan for the current overhead.

- **Encrypted Index Compression**: Better compression for encrypted indexes
- **Parallel Encryption**: Multi-threaded encryption for large datasets
- **Cache-Friendly Encryption**: Optimized encryption algorithms for better cache utilization

## Conclusion: What You've Actually Learned

Alright, so you've made it this far. Congratulations! You now know how to build HIPAA-compliant systems with MongoDB 8.2 Queryable Encryption and Symfony voters. That's actually pretty cool.

The combination of these technologies lets you:

- **Protect sensitive data** with field-level encryption while maintaining search capabilities
- **Implement fine-grained access control** through sophisticated voter systems  
- **Maintain compliance** with comprehensive audit logging and data integrity measures
- **Scale effectively** with proper indexing and sharding strategies
- **Plan for the future** with upcoming features and enhancements

### Key Takeaways (The Stuff You Actually Need to Remember)

1. **Start with Security by Design**: Don't retrofit security into existing systems. It's a nightmare.
2. **Embrace the Principle of Least Privilege**: Use voters for fine-grained permissions. Not everyone needs to see everything.
3. **Audit Everything**: Comprehensive logging is essential for compliance. You need to know who did what, when.
4. **Test Thoroughly**: Security systems require extensive testing. Don't just test that things work - test that they work correctly.
5. **Plan for Operations**: Consider key management, performance monitoring, and disaster recovery. These things matter.
6. **Stay Current**: MongoDB Queryable Encryption continues to evolve. Keep up with the changes.

### The Live Implementation: Where You Can See This Stuff Actually Working

To see these concepts in action, check out the live implementation:

- **[SecureHealth Application](https://securehealth.dev)** - A fully functional HIPAA-compliant medical records system demonstrating MongoDB Queryable Encryption and Symfony voters
- **[SecureHealth Documentation](https://docs.securehealth.dev)** - Comprehensive technical documentation covering implementation details, API references, and operational procedures

The SecureHealth application serves as a practical reference implementation of all the concepts discussed in this article. You can:

- **Explore the live system** with different user roles (admin, doctor, nurse, receptionist)
- **Test encrypted search capabilities** on patient data
- **Examine audit logging** in real-time
- **Review the source code** and implementation patterns
- **Access detailed documentation** for each component

### Resources and References (The Stuff You'll Actually Need)

- [MongoDB Queryable Encryption Documentation](https://docs.mongodb.com/manual/core/queryable-encryption/)
- [Symfony Security Voters Documentation](https://symfony.com/doc/current/security/voters.html)
- [HIPAA Security Rule (45 CFR §164.312)](https://www.hhs.gov/hipaa/for-professionals/security/laws-regulations/index.html)
- [MongoDB Atlas Queryable Encryption](https://www.mongodb.com/docs/atlas/database-encryption-at-rest/)
- [Symfony Security Best Practices](https://symfony.com/doc/current/security.html)

### The Final Reality Check

Remember: HIPAA compliance is not just about technology. It's about implementing proper processes, training your team, and maintaining a security-first mindset throughout the development lifecycle. The technical implementation is just one piece of the puzzle, but it's a crucial foundation for everything else.

As you build your HIPAA-compliant systems, consider how AI assistants can help with code generation, testing, and maintenance. Structure your code with clear naming conventions, comprehensive documentation, and logical organization to make it easier for AI tools to understand and work with your implementation.

The future of healthcare application development is bright, with powerful tools like MongoDB Queryable Encryption and Symfony voters making it possible to build secure, compliant, and functional systems that protect patient data while enabling healthcare professionals to do their jobs effectively.

*Now go build something awesome. And remember - if you're still reading this instead of just looking at the working code, you're probably overthinking it.*

