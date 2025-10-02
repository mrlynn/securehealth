# Encryption Types

MongoDB Queryable Encryption provides three different encryption algorithms, each with different capabilities and use cases. This page explains each type and when to use it in your SecureHealth implementation.

## Deterministic Encryption

**Algorithm Name:** `AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic`

### Characteristics

- Same plaintext always produces the same ciphertext
- Enables equality searches on encrypted data
- Allows indexing for faster queries
- Less secure than random encryption

### When to Use

Use deterministic encryption for fields that:
- Need to be searchable by exact matches
- Will be used in equality filters
- Are not highly sensitive or don't have a predictable set of values

### Example Fields

- Last Name
- First Name
- Email Address
- Phone Number
- External IDs

### Code Example

```php
// In MongoDBEncryptionService.php
private function configureEncryptedFieldsDefinitions(): void
{
    $this->encryptedFields['patient'] = [
        // Deterministic encryption for searchable fields
        'lastName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'firstName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'email' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'phoneNumber' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        // ... other fields
    ];
}
```

### Security Considerations

Deterministic encryption is vulnerable to frequency analysis. If an attacker has a large set of encrypted values, they might determine the original values by analyzing patterns.

For example, if your dataset contains many patients with the last name "Smith," an attacker could potentially identify the encrypted form of "Smith" by observing which encrypted value appears most frequently.

## Random Encryption

**Algorithm Name:** `AEAD_AES_256_CBC_HMAC_SHA_512-Random`

### Characteristics

- Same plaintext produces different ciphertext each time
- Maximum security for sensitive data
- Cannot be searched or indexed
- Requires retrieving and decrypting all data to find matches

### When to Use

Use random encryption for fields that:
- Are highly sensitive
- Don't need to be searched or filtered
- Require maximum security

### Example Fields

- Social Security Number (SSN)
- Diagnosis details
- Medical notes
- Insurance details
- Treatment plans

### Code Example

```php
// In MongoDBEncryptionService.php
private function configureEncryptedFieldsDefinitions(): void
{
    $this->encryptedFields['patient'] = [
        // Random encryption for highly sensitive fields
        'ssn' => ['algorithm' => self::ALGORITHM_RANDOM],
        'diagnosis' => ['algorithm' => self::ALGORITHM_RANDOM],
        'medications' => ['algorithm' => self::ALGORITHM_RANDOM],
        'insuranceDetails' => ['algorithm' => self::ALGORITHM_RANDOM],
        'notes' => ['algorithm' => self::ALGORITHM_RANDOM],
        // ... other fields
    ];
}
```

### Security Considerations

Random encryption provides the highest level of security but with significant limitations on functionality. You cannot search or filter on these fields - you must retrieve and decrypt all documents to find matches.

## Range Encryption

**Algorithm Name:** `range`

### Characteristics

- Enables range queries on encrypted data (>, <, >=, <=)
- Works for dates, timestamps, and numeric values
- More complex configuration than other types
- Improved performance in MongoDB 8.2

### When to Use

Use range encryption for fields that:
- Need to be queried with range conditions
- Are numeric or date values
- Will be used in sorting, min/max operations

### Example Fields

- Birth Date
- Admission Date
- Age
- Height
- Weight
- Lab Results

### Code Example

```php
// In MongoDBEncryptionService.php
private function configureEncryptedFieldsDefinitions(): void
{
    $this->encryptedFields['patient'] = [
        // Range encryption for date fields
        'birthDate' => [
            'algorithm' => self::ALGORITHM_RANGE,
            'min' => new UTCDateTime(strtotime('1900-01-01') * 1000),
            'max' => new UTCDateTime(strtotime('2100-12-31') * 1000),
            'sparsity' => 2,  // Controls index precision/security balance
            'precision' => 2   // Precision for date ranges
        ],
        
        // Range encryption for numeric fields
        'ageInYears' => [
            'algorithm' => self::ALGORITHM_RANGE,
            'min' => 0,
            'max' => 120,
            'sparsity' => 1,
            'precision' => 1
        ],
        // ... other fields
    ];
}
```

### Range Query Example

```php
// Find patients born between 1980 and 1990
$cursor = $collection->find([
    'birthDate' => [
        '$gte' => $encryptionService->encrypt('patient', 'birthDate', 
            new UTCDateTime(strtotime('1980-01-01') * 1000)),
        '$lte' => $encryptionService->encrypt('patient', 'birthDate',
            new UTCDateTime(strtotime('1990-12-31') * 1000))
    ]
]);
```

### Configuration Parameters

Range encryption requires several configuration parameters:

- **min**: Minimum possible value for the field
- **max**: Maximum possible value for the field
- **sparsity**: Controls the balance between index precision and security (lower values = more precise indexes)
- **precision**: The level of precision for the encrypted values

### MongoDB 8.2 Improvements

MongoDB 8.2 brings significant improvements to range encryption:

- Better query performance
- More efficient indexing
- Reduced storage overhead
- Support for more complex range operations

## Choosing the Right Encryption Type

When deciding which encryption type to use for a field, consider:

1. **Query Requirements**: How do you need to search this field?
   - Equality searches only → Deterministic
   - Range queries → Range
   - No search needed → Random

2. **Sensitivity Level**: How sensitive is the data?
   - Highly sensitive → Random (if possible) or Range
   - Moderately sensitive → Deterministic or Range
   - Low sensitivity → Consider leaving unencrypted

3. **Performance Needs**: How performance-critical is this field?
   - Critical → Deterministic or unencrypted
   - Standard → Any encryption type
   - Non-critical → Any encryption type

4. **Data Distribution**: Is the data evenly distributed?
   - Few unique values → Random preferred over Deterministic
   - Many unique values → Deterministic is safer

## Best Practices

1. **Don't Over-Encrypt**: Only encrypt fields containing sensitive information. Non-PHI like timestamps, IDs, and metadata can stay unencrypted.

2. **Use Random for Highly Sensitive Data**: SSN, detailed medical information, and other highly sensitive data should use random encryption when possible.

3. **Use Range for Date Fields**: Date fields usually need range queries, so range encryption is the best choice.

4. **Test Performance**: Measure the performance impact of your encryption choices before deploying to production.

5. **Document Your Choices**: Maintain documentation explaining which fields use which encryption type and why.