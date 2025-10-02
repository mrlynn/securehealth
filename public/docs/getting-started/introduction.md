# Introduction to SecureHealth

SecureHealth is a HIPAA-compliant healthcare records system that demonstrates how to build secure applications using MongoDB 8.2's Queryable Encryption. This guide provides an overview of the system architecture, design principles, and core components.

## System Architecture

SecureHealth follows a modern, layered architecture:

```
┌─────────────────────────────────────┐
│    Frontend (HTML/CSS/JS + React)   │
│    Role-aware UI components         │
└──────────────┬──────────────────────┘
               │ HTTPS/JWT
               │
┌──────────────▼──────────────────────┐
│    Symfony 7.0 Backend API          │
│    • JWT Authentication             │
│    • Role-Based Access Control      │
│    • Audit Logging Service          │
│    • Encryption Service             │
└──────────────┬──────────────────────┘
               │ Encrypted TLS
               │
┌──────────────▼──────────────────────┐
│    MongoDB Atlas with Q/E           │
│    • Client-side encryption         │
│    • Key Vault Collection           │
│    • Encrypted Indexes              │
└─────────────────────────────────────┘
```

### Key Components

1. **MongoDB Encryption Service**
   - Handles all encryption/decryption operations
   - Manages encryption keys
   - Configures encryption for different field types

2. **Patient Document Model**
   - Defines the structure of patient records
   - Implements encryption for sensitive fields
   - Provides role-based data access

3. **Security Layer**
   - Role-based access control via Security Voters
   - JWT authentication
   - Field-level security based on user role

4. **Audit Logging Service**
   - Records all system activity
   - Provides HIPAA-compliant audit trails
   - Logs all data access and modifications

5. **API Controllers**
   - RESTful API for patient management
   - Enforces authorization rules
   - Integrates with audit logging

## Design Principles

SecureHealth is built around several core design principles:

### 1. Security First

Security is built into the foundation of the system, not added as an afterthought. Every design decision considers the security implications.

### 2. HIPAA Compliance by Design

The application is designed from the ground up to meet HIPAA requirements for:
- Access Controls
- Audit Controls
- Integrity Controls
- Transmission Security

### 3. Minimum Necessary Access

Following HIPAA's "minimum necessary" principle, users can only access the specific data they need to perform their jobs.

### 4. Separation of Concerns

Each component has a specific, well-defined responsibility:
- Controllers handle HTTP requests and responses
- Services implement business logic
- Repositories manage data access
- Documents define data structure
- Security voters enforce access rules

### 5. Zero-Knowledge Security

The database server never sees unencrypted PHI. All encryption and decryption happen client-side, protecting data from database administrators and potential breaches.

## Role-Based Access Control

SecureHealth implements three primary roles:

### Doctor Role
- Can access all patient data
- Can create, update, and delete patient records
- Can view all audit logs
- Has full access to sensitive medical information

### Nurse Role
- Can access patient demographic and medical data
- Cannot access SSN
- Can create and update patient records
- Cannot delete patient records
- Can view their own audit logs

### Receptionist Role
- Can access patient demographic and insurance data
- Cannot access medical information or SSN
- Can create patient records with basic information
- Can update demographic and insurance information
- Cannot delete patient records
- Can view their own audit logs

## Data Flow

Here's how data flows through the system when a user searches for a patient:

1. User submits a search query through the frontend interface
2. Frontend sends an authenticated request to the API
3. API controller validates the JWT token and checks user permissions
4. Encryption service encrypts the search term using the appropriate algorithm
5. MongoDB searches for the encrypted value in the encrypted data
6. MongoDB returns encrypted results
7. Application decrypts the data
8. Patient document filters the fields based on user role
9. Audit logging service records the data access
10. API returns the filtered data to the frontend
11. Frontend displays the data to the user

## Next Steps

Now that you understand the basic architecture, proceed to:

1. [Installation](installation) - Set up your development environment
2. [Quick Start](quick-start) - Build your first feature with SecureHealth
3. [Core Concepts](core-concepts) - Dive deeper into key concepts