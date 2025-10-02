# MongoDB Queryable Encryption for HIPAA Compliance

This document explains how SecureHealth implements MongoDB 8.2 Queryable Encryption to achieve HIPAA compliance for patient data.

## Overview

MongoDB Queryable Encryption (QE) allows the application to encrypt sensitive data fields in documents before storing them in the database, while still maintaining the ability to query those fields. This provides the security of encryption with the usability of a database.

## Security Model

SecureHealth uses three encryption types for different fields:

1. **Deterministic Encryption**: Enables equality matches on encrypted data
   - Used for: `lastName`, `firstName`, `email`, `phoneNumber`
   - Example query: Find patient with email = "patient@example.com"

2. **Range Encryption**: Enables range queries on encrypted data
   - Used for: `birthDate`
   - Example query: Find patients born between 1980 and 1990

3. **Standard Encryption**: Maximum security for highly sensitive data
   - Used for: `ssn`, `diagnosis`, `medications`, `insuranceDetails`, `notes`
   - Cannot be queried, but provides strongest security

## Implementation

The encryption logic is implemented in `MongoDBEncryptionService.php`. Key components:

### 1. Key Management

The system uses a master encryption key stored in `/docker/encryption.key`, which is used to encrypt/decrypt the data encryption keys stored in MongoDB.

```php
// Master key management
$keyFile = $params->get('mongodb_encryption_key_path', __DIR__ . '/../../docker/encryption.key');
$this->masterKey = file_get_contents($keyFile);
```

### 2. Key Vault Collection

Data encryption keys are stored in a separate collection, typically `encryption.__keyVault`:

```php
// Key vault setup
list($keyVaultDb, $keyVaultColl) = explode('.', $keyVaultNamespace);
$this->keyVaultCollection = $this->client->selectCollection($keyVaultDb, $keyVaultColl);
```

### 3. Encryption Configuration

Fields are configured for encryption with different algorithms:

```php
// Field encryption configuration
$this->encryptedFields['patient'] = [
    // Deterministic encryption for searchable fields
    'lastName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
    'firstName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
    // Standard encryption for highly sensitive data
    'ssn' => ['algorithm' => self::ALGORITHM_RANDOM],
    'diagnosis' => ['algorithm' => self::ALGORITHM_RANDOM],
];
```

### 4. Field-Level Encryption

Data is encrypted at the field level before being stored:

```php
// Encrypt a value
public function encrypt(string $documentType, string $fieldName, $value) {
    // Check if field should be encrypted
    if (!isset($this->encryptedFields[$documentType][$fieldName])) {
        return $value;
    }
    
    $algorithm = $this->encryptedFields[$documentType][$fieldName]['algorithm'];
    $keyAltName = 'hipaa_encryption_key';
    $dataKeyId = $this->getOrCreateDataKey($keyAltName);
    
    // Encrypt the value
    return $this->clientEncryption->encrypt($value, [
        'algorithm' => $algorithm, 
        'keyId' => $dataKeyId
    ]);
}
```

## Role-Based Access Control (RBAC)

The application implements HIPAA's "minimum necessary" rule by providing different levels of access to patient data based on user roles:

1. **Doctor**
   - Full access to all patient data
   - Can edit and delete records
   - Can see SSNs and diagnosis information

2. **Nurse**
   - Access to medical information but not SSNs
   - Cannot edit or delete records
   - Can see diagnosis and medications

3. **Receptionist**
   - Access to basic patient info and insurance details
   - Cannot see medical information or SSNs
   - Cannot edit or delete records

## Database Initialization

To initialize the database for MongoDB Queryable Encryption:

1. Run the initialization script:
   ```bash
   docker-compose exec php php bin/init-mongodb.php
   ```

This script:
- Creates the key vault collection if needed
- Sets up the patients collection
- Creates sample patient records
- Creates user accounts with different roles

## Troubleshooting

### Common Issues

1. **MongoDB Connection Errors**
   - Ensure your MongoDB Atlas connection string is correctly specified in `.env.local`
   - Check that your IP address is allowed in Atlas network settings

2. **Encryption Errors**
   - Verify that the encryption key file exists at the path specified in the environment
   - Make sure the key vault collection is properly initialized

3. **Query Issues**
   - Remember that only deterministically encrypted fields can be queried with equality
   - Range-encrypted fields support range queries only
   - Standard encrypted fields cannot be queried directly

### Viewing Raw Documents

To understand how encryption works, you can use the "X-Ray" button in the UI to see the raw encrypted document as it is stored in MongoDB. Encrypted fields appear as `BinData` type 6 objects.

## Security Best Practices

1. **Keep the master encryption key secure**
   - The `/docker/encryption.key` file should be protected and never checked into version control
   - In production, use a key management system rather than a file

2. **Minimize access to key vault**
   - The key vault collection contains encryption keys and should be highly restricted

3. **Follow the principle of least privilege**
   - Users should only have access to the data they need for their role
   - The application enforces this through the `PatientVoter` and `Patient::toArray()` methods

## Further Reading

For more information on MongoDB Queryable Encryption:
- [MongoDB Client-Side Field Level Encryption](https://www.mongodb.com/docs/manual/core/csfle/)
- [MongoDB Queryable Encryption](https://www.mongodb.com/docs/manual/core/queryable-encryption/)
- [HIPAA Security Rule](https://www.hhs.gov/hipaa/for-professionals/security/index.html)