# SecureHealth: HIPAA-Compliant Medical Records API

A HIPAA-compliant patient records API built with PHP/Symfony and MongoDB Queryable Encryption.

## Features

- **MongoDB Queryable Encryption**: Fully encrypted data storage with searchable capabilities
- **Role-Based Access Control**: Doctors, nurses, and receptionists with appropriate permissions
- **HIPAA-Compliant Audit Logging**: Comprehensive activity tracking for compliance
- **API Authentication**: Secure JSON authentication for all endpoints
- **Patient Records Management**: Complete CRUD operations with encryption

## Prerequisites

- PHP 8.2+
- Docker and Docker Compose
- Basic understanding of Symfony 6.4+
- Basic knowledge of MongoDB

## Getting Started

### 1. Clone the Repository

```bash
git clone <repository-url>
cd hipaa
```

### 2. Start Docker Environment

```bash
docker-compose up -d
```

This will start:
- PHP service with Symfony application
- MongoDB with encryption enabled
- Nginx web server

### 3. Install Dependencies

```bash
docker-compose exec php composer install
```

### 4. Access the API

The API is available at:

```
http://localhost:8080/api
```

## API Authentication

The API uses JSON authentication. Available test users:

| Role | Email | Password |
|------|-------|----------|
| Doctor | doctor@example.com | doctor |
| Nurse | nurse@example.com | nurse |
| Receptionist | receptionist@example.com | receptionist |

### Login Example

```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"_username":"doctor@example.com","_password":"doctor"}'
```

## API Endpoints

### Patients

| Method | Endpoint | Description | Required Role |
|--------|----------|-------------|--------------|
| GET | `/api/patients` | List all patients | Any authenticated |
| GET | `/api/patients/{id}` | Get a specific patient | Any authenticated |
| POST | `/api/patients` | Create a new patient | Doctor |
| PUT | `/api/patients/{id}` | Update a patient | Doctor |
| DELETE | `/api/patients/{id}` | Delete a patient | Doctor |
| GET | `/api/patients/search` | Search patients | Any authenticated |

### Patient Creation Example

```bash
curl -X POST http://localhost:8080/api/patients \
  -H "Content-Type: application/json" \
  -d '{
    "firstName": "John",
    "lastName": "Doe",
    "birthDate": "1980-01-01",
    "ssn": "123-45-6789",
    "diagnosis": "Hypertension",
    "contactPhone": "555-123-4567",
    "contactEmail": "john.doe@example.com"
  }'
```

### Audit Logs

| Method | Endpoint | Description | Required Role |
|--------|----------|-------------|--------------|
| GET | `/api/audit-logs` | List all audit logs | Doctor |
| GET | `/api/audit-logs/patient/{id}` | Get logs for a patient | Doctor |
| GET | `/api/audit-logs/user/{username}` | Get logs for a user | Doctor or self |

## Role-Based Data Access

Different roles have access to different patient data:

- **Doctors**: Full access to all patient data including sensitive medical information
- **Nurses**: Access to patient contact info, medications, and allergies (no SSN or diagnosis)
- **Receptionists**: Access to basic patient info only (name, contact details, status)

## MongoDB Encryption Details

The system uses MongoDB's Queryable Encryption with three levels of protection:

1. **Deterministic Encryption**: For searchable fields like lastName (enables exact matches)
2. **Range Encryption**: For fields like birthDate (enables range queries)
3. **Standard Encryption**: For highly sensitive data like SSN and diagnosis (no search)

## HIPAA Compliance Features

- Comprehensive audit logging of all data access
- IP address tracking for security monitoring
- Role-based data access controls
- Encrypted data storage and transmission
- Automatic activity logging

## Running Tests

```bash
docker-compose exec php bin/phpunit
```

## Production Deployment Considerations

For a production deployment, make sure to:

1. Use strong, randomly generated encryption keys stored in a secure key management system
2. Enable TLS/SSL for all connections
3. Configure proper firewall rules for MongoDB access
4. Implement regular backup procedures for the database
5. Set up monitoring and alerting for suspicious activities
6. Perform regular security audits