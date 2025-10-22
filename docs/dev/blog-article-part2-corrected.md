# Technical Deep Dive: MongoDB Queryable Encryption Implementation

In this follow-up article, we'll explore the more technical aspects of implementing MongoDB Queryable Encryption in our HIPAA-compliant medical records system. While the previous article gave an overview of the architecture, this piece will focus on the specific encryption implementation, schema design decisions, and performance optimizations.

## Understanding MongoDB Queryable Encryption

MongoDB's Queryable Encryption (QE) is built on a concept called "structured encryption," which allows for specific types of operations on encrypted data without requiring decryption. This is fundamentally different from traditional encryption approaches in several ways:

### Encryption Types in Detail

Our system utilizes two encryption types, each with specific characteristics:

#### 1. Deterministic Encryption

Deterministic encryption always produces the same ciphertext for a given plaintext value. This allows for equality operations (`$eq`) directly on encrypted data.

**Use cases in our system:**
- Patient identifiers
- Last name and first name fields
- Phone numbers
- Email addresses
- Birth dates (for demo purposes, though range encryption would be more suitable in production)

**Technical implementation:**

```php
// Configuration in MongoDBEncryptionService.php
class MongoDBEncryptionService
{
    // Real encryption algorithms
    const ALGORITHM_DETERMINISTIC = 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic';
    
    private function configureEncryptedFieldsDefinitions(): void
    {
        // Patient document fields with deterministic encryption
        $this->encryptedFields['patient'] = [
            // Deterministic encryption for searchable fields (exact match)
            'lastName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'firstName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'email' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            'phoneNumber' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            
            // For demo purposes, use deterministic instead of range
            'birthDate' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
            
            // Other encryption types...
        ];
    }
}
```

**Security considerations:** While deterministic encryption allows equality queries, it can be susceptible to frequency analysis if the data set has very uneven distribution. We've mitigated this by using deterministic encryption only for fields with high cardinality.

#### 2. Standard Encryption

Standard encryption (also called Random encryption) provides the highest level of security but doesn't support any queries on the encrypted data.

**Use cases in our system:**
- Social Security Numbers (SSN)
- Diagnostic notes and diagnosis information
- Treatment plans and medications
- Insurance details
- Medical notes

**Technical implementation:**

```php
// Configuration in MongoDBEncryptionService.php
class MongoDBEncryptionService
{
    // Real encryption algorithm for standard encryption
    const ALGORITHM_RANDOM = 'AEAD_AES_256_CBC_HMAC_SHA_512-Random';
    
    private function configureEncryptedFieldsDefinitions(): void
    {
        $this->encryptedFields['patient'] = [
            // Other encryption types...
            
            // Standard encryption for highly sensitive data (no query)
            'ssn' => ['algorithm' => self::ALGORITHM_RANDOM],
            'diagnosis' => ['algorithm' => self::ALGORITHM_RANDOM],
            'medications' => ['algorithm' => self::ALGORITHM_RANDOM],
            'insuranceDetails' => ['algorithm' => self::ALGORITHM_RANDOM],
            'notes' => ['algorithm' => self::ALGORITHM_RANDOM],
        ];
    }
}
```

### Range Encryption: Planned Enhancement

While our demo implementation currently uses deterministic encryption for date fields (as noted in the code comments), MongoDB Queryable Encryption does support range encryption for date and numeric fields to enable range queries.

The code includes support for range encryption, though it's not currently used for any fields:

```php
// For range encryption support in MongoDBEncryptionService.php
const ALGORITHM_RANGE = 'range'; // Use 'range' for MongoDB Atlas

// For range encryption, add additional options
if ($algorithm === self::ALGORITHM_RANGE) {
    // For MongoDB Atlas, contentionFactor is required
    $encryptOptions['rangeOptions'] = [
        'min' => null,           // Optional min bound, or null for automatic
        'max' => null,           // Optional max bound, or null for automatic
        'contentionFactor' => 10 // Required for range algorithm
        // Sparsity and precision are not used in this version
    ];
}
```

In a production implementation, range encryption would be more appropriate for date fields like birthDate to enable age-based queries and date range searches.

## Key Management System

A critical component of our encryption strategy is secure key management. Our system implements a key management approach with:

```php
class MongoDBEncryptionService
{
    /**
     * Get or create a data encryption key
     *
     * @param string $keyAltName The alternate name for the key
     * @return Binary The data encryption key UUID
     */
    public function getOrCreateDataKey(string $keyAltName = 'default_encryption_key'): Binary
    {
        // Check if the key already exists
        $existingKey = $this->keyVaultCollection->findOne(['keyAltNames' => $keyAltName]);
        
        if ($existingKey) {
            return $existingKey->_id;
        }
        
        try {
            // Create a new data encryption key
            $dataKeyOptions = [
                'keyAltNames' => [$keyAltName]
            ];
            
            // Create a new key using the local KMS provider
            $keyId = $this->clientEncryption->createDataKey('local', $dataKeyOptions);
            $this->logger->info('Created new encryption data key: ' . $keyAltName);
            return $keyId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create encryption key: ' . $e->getMessage());
            throw $e;
        }
    }
}
```

Our implementation currently uses a local key management system, with the encryption master key stored securely in the application environment. In production environments, this could be upgraded to use a cloud key management service like AWS KMS, Azure Key Vault, or Google Cloud KMS.

## Patient Document Schema in Detail

When designing the schema for our medical records system, we carefully considered which fields to encrypt and how to structure the data for optimal performance.

### Patient Document Schema

```php
class Patient
{
    /**
     * Patient ID
     * @Assert\NotBlank(message="ID is required")
     */
    private ?ObjectId $id = null;
    
    /**
     * Patient's last name - deterministically encrypted (searchable)
     * @Assert\NotBlank(message="Last name is required")
     * @Assert\Length(
     *     min=2, 
     *     max=50, 
     *     minMessage="Last name must be at least {{ limit }} characters long",
     *     maxMessage="Last name cannot be longer than {{ limit }} characters"
     * )
     */
    private string $lastName;

    /**
     * Patient's first name - deterministically encrypted (searchable)
     * @Assert\NotBlank(message="First name is required")
     * @Assert\Length(
     *     min=2, 
     *     max=50, 
     *     minMessage="First name must be at least {{ limit }} characters long",
     *     maxMessage="First name cannot be longer than {{ limit }} characters"
     * )
     */
    private string $firstName;

    /**
     * Patient email - deterministically encrypted (searchable)
     * @Assert\Email(
     *     message="The email {{ value }} is not a valid email address"
     * )
     * @Assert\NotBlank(message="Email is required")
     */
    private string $email;
    
    /**
     * Patient's birth date - deterministically encrypted (for demo purposes)
     * @Assert\NotBlank(message="Birth date is required")
     */
    private UTCDateTime $birthDate;

    /**
     * Social Security Number - strongly encrypted (no search)
     * @Assert\Regex(
     *     pattern="/^\d{3}-\d{2}-\d{4}$/",
     *     message="SSN must be in format XXX-XX-XXXX"
     * )
     */
    private ?string $ssn = null;

    /**
     * Medical diagnosis - strongly encrypted (no search)
     */
    private ?array $diagnosis = [];
    
    // ... other fields
}
```

### Document-to-BSON Conversion with Encryption

The `toDocument` method in our Patient class demonstrates how we encrypt data before storing it in MongoDB:

```php
/**
 * Convert Patient to BSON document
 */
public function toDocument(MongoDBEncryptionService $encryptionService): array
{
    $document = [
        'firstName' => $encryptionService->encrypt('patient', 'firstName', $this->firstName),
        'lastName' => $encryptionService->encrypt('patient', 'lastName', $this->lastName),
        'email' => $encryptionService->encrypt('patient', 'email', $this->email),
        'birthDate' => $encryptionService->encrypt('patient', 'birthDate', $this->birthDate),
        'createdAt' => $this->createdAt,
        'updatedAt' => $this->updatedAt ?? new UTCDateTime()
    ];

    // Optional fields
    if ($this->id !== null) {
        $document['_id'] = $this->id;
    }

    if ($this->phoneNumber !== null) {
        $document['phoneNumber'] = $encryptionService->encrypt('patient', 'phoneNumber', $this->phoneNumber);
    }

    if ($this->ssn !== null) {
        $document['ssn'] = $encryptionService->encrypt('patient', 'ssn', $this->ssn);
    }

    if ($this->diagnosis !== null && count($this->diagnosis) > 0) {
        $document['diagnosis'] = $encryptionService->encrypt('patient', 'diagnosis', $this->diagnosis);
    }

    if ($this->medications !== null && count($this->medications) > 0) {
        $document['medications'] = $encryptionService->encrypt('patient', 'medications', $this->medications);
    }

    if ($this->insuranceDetails !== null) {
        $document['insuranceDetails'] = $encryptionService->encrypt('patient', 'insuranceDetails', $this->insuranceDetails);
    }

    if ($this->notes !== null) {
        $document['notes'] = $encryptionService->encrypt('patient', 'notes', $this->notes);
    }

    if ($this->primaryDoctorId !== null) {
        $document['primaryDoctorId'] = $this->primaryDoctorId;
    }

    return $document;
}
```

Similarly, the `fromDocument` method handles decryption when reading from MongoDB:

```php
/**
 * Convert BSON document to Patient
 */
public static function fromDocument(array $document, MongoDBEncryptionService $encryptionService): self
{
    $patient = new self();

    if (isset($document['_id'])) {
        $patient->setId($document['_id']);
    }

    // Process each field with potential decryption
    if (isset($document['firstName'])) {
        $firstName = $encryptionService->decrypt($document['firstName']);
        $patient->setFirstName($firstName);
    }

    if (isset($document['lastName'])) {
        $lastName = $encryptionService->decrypt($document['lastName']);
        $patient->setLastName($lastName);
    }

    // ... other field decryption

    return $patient;
}
```

## Symfony 7 Integration with MongoDB Atlas

Integrating MongoDB Queryable Encryption with Symfony 7 involved creating a connection factory to handle the encrypted MongoDB client:

### Factory for Creating Encrypted Connections

```php
// src/Factory/MongoDBConnectionFactory.php
namespace App\Factory;

use App\Service\MongoDBEncryptionService;
use MongoDB\Client;

class MongoDBConnectionFactory
{
    private MongoDBEncryptionService $encryptionService;
    
    public function __construct(MongoDBEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }
    
    /**
     * Create a MongoDB client with encryption capabilities
     */
    public function createEncryptedClient(string $mongoUrl): Client
    {
        $options = [
            // Real MongoDB Enterprise/Atlas encryption configuration
            'autoEncryption' => $this->encryptionService->getEncryptionOptions(),
            'driver' => [
                'ssl' => true
            ]
        ];
        
        // Use Atlas with proper encryption configuration
        return new Client($mongoUrl, [], $options);
    }
    
    /**
     * Create a standard MongoDB client (without encryption)
     */
    public function createClient(string $mongoUrl): Client
    {
        return new Client($mongoUrl);
    }
}
```

This simplified factory allows us to create MongoDB clients with the correct encryption options for Atlas connectivity.

## Performance Optimizations

Queryable Encryption introduces performance overhead, which we've addressed through several optimization techniques:

### 1. Strategic Data Partitioning

We've implemented selective encryption to minimize the performance impact:

```php
private function configureEncryptedFieldsDefinitions(): void
{
    // Patient document fields
    $this->encryptedFields['patient'] = [
        // Only encrypt fields that contain PHI or PII
        'lastName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'firstName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'email' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'phoneNumber' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'birthDate' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'ssn' => ['algorithm' => self::ALGORITHM_RANDOM],
        'diagnosis' => ['algorithm' => self::ALGORITHM_RANDOM],
        'medications' => ['algorithm' => self::ALGORITHM_RANDOM],
        'insuranceDetails' => ['algorithm' => self::ALGORITHM_RANDOM],
        'notes' => ['algorithm' => self::ALGORITHM_RANDOM],
    ];
    
    // Non-PHI fields don't need encryption
    // Metadata, timestamps, and IDs are not encrypted
}
```

By not encrypting non-sensitive fields, we maintain better performance where encryption isn't necessary.

### 2. Query Optimization

We've carefully designed queries to minimize the need for decryption:

```php
// In PatientRepository.php

/**
 * Find patients by last name with deterministic encryption
 */
public function findByLastName(string $lastName, MongoDBEncryptionService $encryptionService)
{
    // Encrypt the search value using the same algorithm used for storage
    $encryptedLastName = $encryptionService->encrypt('patient', 'lastName', $lastName);
    
    // Query using the encrypted value
    $filter = ['lastName' => $encryptedLastName];
    $collection = $this->getCollection();
    $cursor = $collection->find($filter);
    
    // Convert results to Patient objects
    $patients = [];
    foreach ($cursor as $document) {
        $patients[] = Patient::fromDocument((array)$document, $encryptionService);
    }
    
    return $patients;
}

/**
 * Count patients by criteria
 */
public function countByCriteria(array $criteria): int
{
    $collection = $this->getCollection();
    return $collection->countDocuments($criteria);
}
```

## Audit Logging for HIPAA Compliance

Our comprehensive audit logging system tracks all interactions with PHI:

### Audit Log Schema

```php
// src/Document/AuditLog.php
class AuditLog
{
    /**
     * @var ObjectId
     */
    private $id;
    
    /**
     * @var string
     */
    private $username;
    
    /**
     * @var string
     */
    private $actionType; // VIEW, CREATE, UPDATE, DELETE, LOGIN, LOGOUT
    
    /**
     * @var string
     */
    private $entityType; // patient, user, etc.
    
    /**
     * @var string|null
     */
    private $entityId;
    
    /**
     * @var string
     */
    private $description;
    
    /**
     * @var string|null
     */
    private $ipAddress;
    
    /**
     * @var string|null
     */
    private $userAgent;
    
    /**
     * @var UTCDateTime
     */
    private $timestamp;
    
    /**
     * @var array|null
     */
    private $metadata;
    
    // ... getters and setters
}
```

### Logging Patient Access

We implement specialized methods for tracking patient data access:

```php
/**
 * Log a patient data access event (HIPAA-compliant)
 *
 * @param UserInterface $user User accessing the data
 * @param string $accessType Type of access (VIEW, CREATE, EDIT, DELETE)
 * @param string $patientId Patient ID being accessed
 * @param array $data Additional data about the access
 * @return AuditLog
 */
public function logPatientAccess(
    UserInterface $user,
    string $accessType,
    string $patientId,
    array $data = []
): AuditLog {
    $data['entityId'] = $patientId;
    $data['entityType'] = 'Patient';
    $data['description'] = $data['description'] ?? "Patient data {$accessType}";
    
    return $this->log($user, 'PATIENT_' . $accessType, $data);
}
```

## Security Best Practices

Beyond encryption, our system implements several additional security measures:

### 1. Defense in Depth

Multiple layers of security controls:

1. Web Application Firewall for HTTP traffic filtering
2. Network Security Groups limiting access to MongoDB Atlas
3. Application-level authentication with Symfony Security
4. Role-based authorization with Security Voters
5. Field-level encryption with MongoDB QE
6. Comprehensive audit logging

### 2. Principle of Least Privilege

Our implementation of role-based access control follows the principle of least privilege:

```php
/**
 * Convert object to an array with role-based access control
 */
public function toArray($userOrRole = null): array
{
    $data = [
        'id' => (string)$this->getId(),
        'firstName' => $this->getFirstName(),
        'lastName' => $this->getLastName(),
        'email' => $this->getEmail(),
        'phoneNumber' => $this->getPhoneNumber(),
        'birthDate' => $this->getBirthDate() ? $this->getBirthDate()->toDateTime()->format('Y-m-d') : null,
        'createdAt' => $this->getCreatedAt() ? $this->getCreatedAt()->toDateTime()->format('Y-m-d H:i:s') : null
    ];

    // Get roles either from user object or directly from string
    $roles = [];
    if ($userOrRole instanceof UserInterface) {
        $roles = $userOrRole->getRoles();
    } elseif (is_string($userOrRole)) {
        $roles = [$userOrRole];
    }
    
    // Doctors can see all patient data
    if (in_array('ROLE_DOCTOR', $roles)) {
        $data['ssn'] = $this->getSsn();
        $data['diagnosis'] = $this->getDiagnosis();
        $data['medications'] = $this->getMedications();
        $data['insuranceDetails'] = $this->getInsuranceDetails();
        $data['notes'] = $this->getNotes();
        $data['primaryDoctorId'] = $this->getPrimaryDoctorId() ? (string)$this->getPrimaryDoctorId() : null;
    }
    // Nurses can see diagnosis and medications but not SSN
    elseif (in_array('ROLE_NURSE', $roles)) {
        $data['diagnosis'] = $this->getDiagnosis();
        $data['medications'] = $this->getMedications();
        $data['notes'] = $this->getNotes();
    }
    // Receptionists can see insurance details but no medical data
    elseif (in_array('ROLE_RECEPTIONIST', $roles)) {
        $data['insuranceDetails'] = $this->getInsuranceDetails();
    }

    return $data;
}
```

### 3. Input Validation and Sanitization

We use Symfony's Validator component for comprehensive input validation:

```php
/**
 * Patient's last name - deterministically encrypted (searchable)
 * @Assert\NotBlank(message="Last name is required")
 * @Assert\Length(
 *     min=2, 
 *     max=50, 
 *     minMessage="Last name must be at least {{ limit }} characters long",
 *     maxMessage="Last name cannot be longer than {{ limit }} characters"
 * )
 */
private string $lastName;

/**
 * Social Security Number - strongly encrypted (no search)
 * @Assert\Regex(
 *     pattern="/^\d{3}-\d{2}-\d{4}$/",
 *     message="SSN must be in format XXX-XX-XXXX"
 * )
 */
private ?string $ssn = null;
```

## Deployment Considerations for MongoDB Atlas

Deploying a HIPAA-compliant application with MongoDB Atlas requires special configuration:

### MongoDB Atlas Setup

When using MongoDB Atlas for HIPAA-compliant applications:

1. Enable Client-Side Field Level Encryption in Atlas
2. Configure network access to restrict IP ranges
3. Enable advanced security features:
   - IP access lists
   - VPC peering if applicable
   - Database auditing
   - LDAP authentication
4. Set appropriate backup policies for compliance

### Encryption Key Security

For production deployments, the encryption master key should be stored securely outside the Docker environment, ideally in a dedicated key management system like:

1. AWS Key Management Service (KMS)
2. Azure Key Vault 
3. Google Cloud KMS
4. HashiCorp Vault

## Future Enhancements

Several enhancements could be made to this implementation in the future:

### 1. Implementing Range Encryption

While our demo uses deterministic encryption for date fields, implementing proper range encryption would improve security while maintaining query capabilities:

```php
// Future implementation
$this->encryptedFields['patient'] = [
    // Existing fields...
    
    // Use actual range encryption for dates
    'birthDate' => ['algorithm' => self::ALGORITHM_RANGE],
    'appointmentDate' => ['algorithm' => self::ALGORITHM_RANGE],
    'lastVisitDate' => ['algorithm' => self::ALGORITHM_RANGE],
];
```

### 2. Enhanced In-Memory Caching

Adding a memory-based caching system would further improve performance for frequently accessed data while maintaining HIPAA compliance.

### 3. Enhanced Key Management

Implementing automated key rotation would further enhance security:

```php
// Planned key rotation implementation
public function rotateEncryptionKeys(): void
{
    // 1. Generate new master key
    $newMasterKey = $this->generateNewMasterKey();
    
    // 2. Re-encrypt data encryption keys with new master key
    $this->reencryptDataKeys($newMasterKey);
    
    // 3. Update key references in the key vault
    $this->updateKeyVaultReferences($newMasterKey);
    
    // 4. Schedule old key for deletion after grace period
    $this->scheduleKeyDeletion($this->currentMasterKey, '+30 days');
    
    // 5. Set new master key as current
    $this->currentMasterKey = $newMasterKey;
}
```

## Conclusion

MongoDB's Queryable Encryption provides a powerful foundation for building HIPAA-compliant healthcare applications. By combining it with Symfony's robust security features and careful system design, we've created a system that protects sensitive patient data while maintaining application functionality and performance.

The technical details outlined in this article demonstrate that it's possible to build highly secure medical records systems without compromising on usability or developer experience. While implementing such a system requires careful planning and specialized knowledge, the benefits in terms of security, compliance, and patient trust are well worth the investment.

## Further Technical Reading

- [MongoDB Client-Side Field Level Encryption Guide](https://www.mongodb.com/docs/manual/core/security-client-side-encryption/)
- [AWS KMS for Key Management](https://docs.aws.amazon.com/kms/latest/developerguide/overview.html)
- [Symfony Security Component Deep Dive](https://symfony.com/doc/current/security.html)
- [NIST Encryption Guidelines](https://csrc.nist.gov/publications/detail/sp/800-175b/rev-1/final)