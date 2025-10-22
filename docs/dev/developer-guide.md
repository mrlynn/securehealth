# SecureHealth Developer Guide

This guide provides detailed information for developers working on the SecureHealth project.

## Architecture Overview

SecureHealth is built using:

- **Symfony 6.4**: PHP framework for the application backend
- **MongoDB 7.0 Enterprise**: Database with Queryable Encryption support
- **Docker**: Containerization for development and deployment

### Directory Structure

```
/
├── config/                  # Symfony configuration
│   ├── packages/            # Bundle configurations
│   └── services.yaml        # Service definitions
├── docker/                  # Docker configuration files
│   ├── encryption.key       # MongoDB encryption master key
│   ├── init-encryption.js   # MongoDB initialization script
│   └── nginx.conf           # Nginx web server config
├── public/                  # Web server document root
│   └── index.php            # Front controller
├── src/                     # Application source code
│   ├── Controller/          # API controllers
│   ├── Document/            # MongoDB document models
│   ├── Factory/             # Service factories
│   ├── Repository/          # Document repositories
│   ├── Security/            # Security classes and voters
│   ├── Service/             # Application services
│   └── Kernel.php           # Symfony kernel
├── tests/                   # PHPUnit tests
├── .env                     # Environment variables
├── composer.json            # PHP dependencies
├── docker-compose.yml       # Docker services definition
├── Dockerfile               # PHP service definition
└── phpunit.xml.dist         # PHPUnit configuration
```

## MongoDB Queryable Encryption

### Key Concepts

1. **Client-Side Field Level Encryption (CSFLE)**: Data is encrypted before it reaches the database
2. **Key Vault**: Stores encryption keys used for data fields
3. **Encryption Algorithms**:
   - **Deterministic**: Same plaintext always results in same ciphertext (searchable)
   - **Random**: Same plaintext results in different ciphertext (more secure, not searchable)
   - **Range**: Enables range queries on encrypted data

### Implementation

The `MongoDBEncryptionService` handles encryption configuration:

- Creates and manages data keys in the key vault
- Configures encrypted fields for patient documents
- Provides encryption/decryption methods for application use

### Adding New Encrypted Fields

To add a new encrypted field to a document:

1. Update the `Patient` document class with the new field
2. Add the field configuration in `MongoDBEncryptionService::configureEncryptedFields()`
3. Choose the appropriate encryption algorithm based on query needs

Example:

```php
// In MongoDBEncryptionService::configureEncryptedFields()
[
    'path' => 'newField',
    'bsonType' => 'string',
    'keyId' => $this->getOrCreateDataKey('newField'),
    'algorithm' => ClientEncryption::ALGORITHM_INDEXED  // For searchable fields
]
```

## Role-Based Access Control

### User Roles

- **ROLE_DOCTOR**: Full access to all patient data and operations
- **ROLE_NURSE**: Read-only access to non-sensitive patient data
- **ROLE_RECEPTIONIST**: Read-only access to basic patient info

### Security Components

1. **PatientVoter**: Handles access decisions for patient resources
2. **Security Configuration**: Defines firewall and access control rules
3. **Role-Based Data Filtering**: `Patient::toArray()` filters data based on user role

### Extending Roles

To add a new role:

1. Add the role to `security.yaml` under `providers.users_in_memory.memory.users`
2. Update `role_hierarchy` if needed
3. Update `PatientVoter` to include rules for the new role
4. Update `Patient::toArray()` to filter data appropriately for the new role

## HIPAA Audit Logging

### Logging Components

1. **AuditLog**: Document class for storing audit events
2. **AuditLogService**: Service for logging events
3. **AuditLogSubscriber**: Event subscriber for automatic logging

### Log Types

- **Security Events**: Logins, logouts, access attempts
- **Patient Events**: CRUD operations on patient records
- **Data Access Events**: Read operations for sensitive data

### Extending Logging

To log custom events:

```php
$this->auditLogService->logEvent(
    $username,
    'CUSTOM_EVENT_TYPE',
    'Description of what happened',
    $entityId,       // Optional
    $entityType,     // Optional
    [               // Optional metadata
        'key1' => 'value1',
        'key2' => 'value2'
    ]
);
```

## Testing

### Test Types

1. **Functional Tests**: API endpoint testing with authentication
2. **Service Tests**: Test service functionality in isolation

### Running Tests

```bash
# Run all tests
docker-compose exec php bin/phpunit

# Run specific test file
docker-compose exec php bin/phpunit tests/Controller/Api/PatientControllerTest.php

# Run specific test method
docker-compose exec php bin/phpunit --filter testCreatePatient
```

## API Authentication

The application uses Symfony Security's JSON login mechanism. 

Authentication flow:

1. Client sends POST request to `/api/login` with username and password
2. System validates credentials and creates a session
3. Subsequent requests use session cookie for authentication
4. Logout is performed at `/api/logout`

## Troubleshooting

### Common Issues

#### MongoDB Connection Problems

**Problem**: Error connecting to MongoDB

**Solution**:
1. Check if the MongoDB container is running: `docker-compose ps`
2. Verify the connection string in `.env` file
3. Check MongoDB logs: `docker-compose logs mongodb`

#### Encryption Key Issues

**Problem**: Errors about encryption keys

**Solution**:
1. Ensure `docker/encryption.key` file exists and has proper permissions
2. Check if the key vault collection has been initialized
3. Try regenerating the encryption key: `openssl rand -base64 96 > docker/encryption.key`

#### Access Denied Errors

**Problem**: Access denied when accessing API endpoints

**Solution**:
1. Ensure you're logged in with a user that has sufficient permissions
2. Check `security.yaml` for access control rules
3. Review `PatientVoter` for specific access rules

## Best Practices

1. **Always use Patient::toArray()** for returning patient data to clients
2. **Log all sensitive data access** using AuditLogService
3. **Use authorization voters** for access control decisions
4. **Write tests** for all new functionality
5. **Follow HIPAA requirements** for any new features