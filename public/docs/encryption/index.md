# MongoDB Queryable Encryption

MongoDB 8.2's Queryable Encryption (QE) is a groundbreaking feature that allows you to search encrypted data without decrypting it on the server. This guide explains how SecureHealth implements and leverages this feature.

## Overview

Traditionally, developers had to choose between security and functionality when handling sensitive data like PHI. With MongoDB Queryable Encryption, you can:

- **Search encrypted data** without ever decrypting it on the server
- **Choose encryption types** based on your query needs
- **Keep your encryption keys** completely separate from your data
- **Maintain performance** with encrypted indexes

## Contents

- [Encryption Basics](basics) - Core concepts of MongoDB Queryable Encryption
- [Encryption Types](types) - Different encryption algorithms and their use cases
- [Implementing Encryption](implementation) - How to implement encryption in your code
- [Key Management](key-management) - Managing encryption keys securely
- [Performance Considerations](performance) - Optimizing performance with encryption

## Encryption Types

MongoDB Queryable Encryption provides three encryption algorithms, each with different capabilities:

### 1. Deterministic Encryption

- **Algorithm**: `AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic`
- **Capability**: Same plaintext produces the same ciphertext, allowing for equality searches
- **Use Case**: Names, email addresses, phone numbers
- **Trade-off**: Vulnerable to frequency analysis

### 2. Random Encryption

- **Algorithm**: `AEAD_AES_256_CBC_HMAC_SHA_512-Random`
- **Capability**: Maximum security, different ciphertext each time
- **Use Case**: SSN, diagnosis, medical notes
- **Trade-off**: Cannot query these fields

### 3. Range Encryption

- **Algorithm**: `range`
- **Capability**: Enables range queries on encrypted data
- **Use Case**: Dates, ages, numeric values
- **Trade-off**: More complex configuration

## MongoDB 8.2 Enhancements

MongoDB 8.2 introduces several improvements to Queryable Encryption:

- Enhanced range query performance
- Better key management capabilities
- Reduced performance overhead
- Available on all paid MongoDB Atlas tiers
- More flexible encryption configuration options