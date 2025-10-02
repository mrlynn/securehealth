# Encryption Basics

This page covers the fundamental concepts of MongoDB Queryable Encryption as implemented in SecureHealth.

## What is Client-Side Field Level Encryption (CSFLE)?

MongoDB's Queryable Encryption is built on Client-Side Field Level Encryption (CSFLE), which means:

- Encryption happens in the application (client-side), not on the server
- Individual fields are encrypted, not the entire document
- The MongoDB server never sees unencrypted sensitive data
- Even database administrators cannot view unencrypted sensitive data

## Key Concepts

### 1. Customer Master Key (CMK)

The Customer Master Key (CMK) is the root key that protects your Data Encryption Keys. In SecureHealth:

- For development, a local key file is used
- For production, a cloud key management service (AWS KMS, Azure Key Vault, or Google Cloud KMS) should be used
- The CMK should never be stored in your application code or database

```bash
# Generate a 96-byte random key for development
openssl rand -base64 96 > docker/encryption.key
```

### 2. Data Encryption Keys (DEK)

Data Encryption Keys (DEKs) are the keys that actually encrypt your data:

- DEKs are stored in a special collection called the Key Vault
- DEKs are themselves encrypted by the CMK
- Different fields or groups of fields may use different DEKs
- In SecureHealth, we use a naming convention of `{documentType}_{fieldName}_key` for DEKs

### 3. Encryption Algorithms

MongoDB supports multiple encryption algorithms with different capabilities:

- **Deterministic Encryption**: Enables equality searches
- **Random Encryption**: Maximum security, no search capability
- **Range Encryption**: Enables range queries on encrypted data

### 4. Key Vault

The Key Vault is a special MongoDB collection that stores your Data Encryption Keys:

- By convention, it's named `encryption.__keyVault`
- Keys in the vault are themselves encrypted by the CMK
- Only authorized users should have access to the key vault

## How Queryable Encryption Works

Here's a simplified explanation of how MongoDB Queryable Encryption works:

1. **Key Generation**: When you first initialize the system, a Data Encryption Key is created and stored in the Key Vault, protected by your CMK.

2. **Encryption**: When saving patient data, sensitive fields are encrypted using the appropriate DEK and algorithm before they leave the application.

3. **Storage**: MongoDB stores the encrypted data. It looks like gibberish to anyone who can access the raw database.

4. **Querying**: When you need to find a patient:
   - Your search term is encrypted using the same DEK and algorithm
   - MongoDB searches for the encrypted term in the encrypted data
   - This works because the same plaintext always produces the same ciphertext (for deterministic encryption)

5. **Retrieval and Decryption**: When retrieving data:
   - MongoDB returns the encrypted document
   - The application decrypts it using the DEK
   - Only then can the plaintext data be used

## Security Benefits

MongoDB Queryable Encryption provides several security benefits:

- **Zero-knowledge security**: The database server never sees unencrypted data
- **Protection from insider threats**: Even database administrators can't access sensitive data
- **Data breach protection**: Stolen data remains encrypted and unusable
- **Compliance assistance**: Helps meet HIPAA requirements for data protection

## Code Example

Here's a simplified example of how encryption works in SecureHealth:

```php
// Encrypt a patient's last name for storage
$encryptedLastName = $encryptionService->encrypt(
    'patient',
    'lastName',
    'Smith'
);

// Store the encrypted value in MongoDB
$collection->insertOne([
    'lastName' => $encryptedLastName,
    // other fields...
]);

// Later, search for patients with last name "Smith"
$encryptedSearchTerm = $encryptionService->encrypt(
    'patient',
    'lastName',
    'Smith'
);

// MongoDB can find the match without decrypting
$result = $collection->findOne([
    'lastName' => $encryptedSearchTerm
]);

// Decrypt the result
$patient = Patient::fromDocument((array)$result, $encryptionService);
```

## Next Steps

Now that you understand the basics of MongoDB Queryable Encryption, explore:

- [Encryption Types](types) - Learn more about the different encryption algorithms
- [Implementing Encryption](implementation) - See how to implement encryption in your code
- [Key Management](key-management) - Learn best practices for managing encryption keys