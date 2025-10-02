# MongoDB Queryable Encryption Guide

This guide explains how MongoDB Queryable Encryption is implemented in the SecureHealth application.

## Introduction to MongoDB Queryable Encryption

MongoDB Queryable Encryption (QE) provides the ability to encrypt sensitive data in your database while still allowing certain query operations on that encrypted data. This is a powerful feature for applications handling protected health information (PHI) or other sensitive data.

## Key Concepts

### 1. Client-Side Field Level Encryption (CSFLE)

CSFLE ensures data is encrypted before it leaves the application and is sent to the database. This means:

- The database server never sees unencrypted data
- Even database administrators cannot access plaintext data
- Breaches of the database would only expose encrypted data

### 2. Key Management

The encryption system uses two types of keys:

- **Master Key**: A top-level key used to encrypt all data keys
- **Data Keys**: Individual keys used for encrypting specific fields

### 3. Encryption Algorithms

MongoDB QE offers several encryption algorithms:

- **Deterministic Encryption**: Same input always produces the same ciphertext
  - Allows equality queries (`firstName = "John"`)
  - Less secure than random encryption
  - Used for fields where you need to search by exact match

- **Random Encryption**: Same input produces different ciphertext each time
  - Maximum security
  - No search capabilities
  - Used for highly sensitive fields like SSN

- **Range Encryption**: Preserves order relationships between values
  - Allows range queries (`birthDate > 2000-01-01`)
  - Used for date fields, numeric values where range queries are needed

## Implementation in SecureHealth

### Key Files

- `src/Service/MongoDBEncryptionService.php`: Main encryption configuration service
- `src/Factory/MongoDBConnectionFactory.php`: Creates encrypted MongoDB connections
- `docker/encryption.key`: Master encryption key (in production, use a secure KMS)

### Encryption Configuration

The `MongoDBEncryptionService` configures which fields are encrypted and how:

```php
private function configureEncryptedFields(): void
{
    $this->encryptedFieldsMap = [
        'securehealth.patients' => [
            'fields' => [
                [
                    'path' => 'lastName',
                    'bsonType' => 'string',
                    'keyId' => $this->getOrCreateDataKey('lastName'),
                    'algorithm' => ClientEncryption::ALGORITHM_INDEXED
                ],
                [
                    'path' => 'birthDate',
                    'bsonType' => 'date',
                    'keyId' => $this->getOrCreateDataKey('birthDate'),
                    'algorithm' => ClientEncryption::ALGORITHM_INDEXED
                ],
                [
                    'path' => 'socialSecurityNumber',
                    'bsonType' => 'string',
                    'keyId' => $this->getOrCreateDataKey('ssn'),
                    'algorithm' => ClientEncryption::ALGORITHM_UNINDEXED
                ],
                [
                    'path' => 'primaryDiagnosis',
                    'bsonType' => 'string',
                    'keyId' => $this->getOrCreateDataKey('diagnosis'),
                    'algorithm' => ClientEncryption::ALGORITHM_UNINDEXED
                ]
            ]
        ]
    ];
}
```

### Key Management

Data keys are stored in a special collection called the key vault:

```php
$this->keyVaultNamespace = $keyVaultDb . '.__keyVault';
$this->keyVaultCollection = $this->client->selectCollection(
    explode('.', $this->keyVaultNamespace)[0],
    explode('.', $this->keyVaultNamespace)[1]
);
```

Keys are created and retrieved as needed:

```php
private function getOrCreateDataKey(string $keyAltName): string
{
    // Check if data key exists
    $existingKey = $this->keyVaultCollection->findOne(['keyAltNames' => $keyAltName]);
    
    if ($existingKey) {
        return $existingKey->_id;
    }
    
    // Create a new data key if none exists
    $dataKeyId = $this->clientEncryption->createDataKey('local', [
        'keyAltNames' => [$keyAltName]
    ]);
    
    return $dataKeyId;
}
```

### Using Encrypted Connections

The `MongoDBConnectionFactory` creates connections with encryption enabled:

```php
public function createEncryptedClient(string $mongoUrl): Client
{
    $options = [
        'autoEncryption' => $this->encryptionService->getEncryptionOptions()
    ];
    
    return new Client($mongoUrl, [], $options);
}
```

## Querying Encrypted Data

### Equality Queries

Fields encrypted with deterministic encryption support equality queries:

```php
// Searches on lastName which uses deterministic encryption
$patients = $repository->findBy(['lastName' => 'Smith']);
```

### Range Queries

Fields encrypted with range encryption support range operations:

```php
// Find patients born before a certain date
$maxDate = new \DateTime('2000-01-01');
$patients = $repository->findBy(['birthDate' => ['$lte' => $maxDate]]);
```

### No Support for Pattern Matching

Important limitation: You cannot perform pattern matching on encrypted fields:

```php
// This will NOT work with encrypted fields
$patients = $repository->findBy(['lastName' => new \MongoDB\BSON\Regex('^Sm')]);
```

## Visual Example of Encrypted Data

When viewing documents in MongoDB Compass or other tools, encrypted fields appear as binary data:

```
{
  "_id": ObjectId("5f8a716b9d3b3e001c123456"),
  "firstName": "John",
  "lastName": BinData(6, "AQEawO1RrZ..."), // Deterministically encrypted
  "birthDate": BinData(6, "AQFS8vH2W..."), // Range encrypted
  "socialSecurityNumber": BinData(6, "ARtgJKLmN..."), // Randomly encrypted
  "status": "active" // Not encrypted
}
```

## Security Considerations

1. **Key Security**: The master encryption key is critical. In production:
   - Use a proper key management system (KMS)
   - Never store the key in the same place as the database
   - Rotate keys periodically

2. **Index Creation**: Creating indexes on encrypted fields requires special consideration:
   - Only fields with deterministic or range encryption can be indexed
   - Index definitions must match encryption configuration

3. **Performance**: Encrypted operations have some overhead:
   - CPU cost for encryption/decryption
   - Increased data size (encrypted data is larger than plaintext)
   - More complex query planning

4. **Query Limitations**: Some operations aren't possible on encrypted fields:
   - Text search
   - Regular expressions
   - Aggregation operators that examine the value semantics

## Best Practices

1. **Encrypt Only What's Necessary**: Not all fields need encryption
   - Encrypt PHI and sensitive personal information
   - Consider leaving non-sensitive fields unencrypted for better performance

2. **Choose Encryption Types Carefully**:
   - Use deterministic only when you need equality searches
   - Use range encryption only when you need range queries
   - Default to random encryption for maximum security

3. **Test Query Patterns**: Ensure your application's query patterns work with encrypted fields

4. **Monitor Performance**: Watch for query performance issues with encrypted fields

5. **Backup Key Management**: Ensure you have secure backups of encryption keys
   - If keys are lost, encrypted data cannot be recovered

## Troubleshooting

### Common Issues

#### Missing Keys

**Problem**: Error about missing data keys when trying to decrypt

**Solution**:
- Check that the key vault collection is properly initialized
- Verify that the application has access to the key vault
- Ensure keys are created before encrypting data

#### Encryption Errors

**Problem**: Errors when trying to encrypt or decrypt data

**Solution**:
- Verify MongoDB Enterprise edition is being used
- Check that the encryption library is properly installed
- Ensure the master key is accessible to the application

#### Query Not Returning Expected Results

**Problem**: Queries on encrypted fields not working as expected

**Solution**:
- Ensure you're using the correct encryption type for your query pattern
- Verify the query is formatted correctly for the encryption type
- Check that indexes are created correctly on encrypted fields

## Resources

- [MongoDB Client-Side Field Level Encryption](https://www.mongodb.com/docs/manual/core/security-client-side-encryption/)
- [MongoDB Queryable Encryption](https://www.mongodb.com/docs/manual/core/queryable-encryption/)
- [HIPAA Security Rule](https://www.hhs.gov/hipaa/for-professionals/security/index.html)