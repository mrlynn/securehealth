# SecureHealth - HIPAA-Compliant Medical Records System

## Project Overview
SecureHealth is a HIPAA-compliant medical records management system built with Symfony (PHP) and MongoDB, featuring advanced queryable encryption for protecting Protected Health Information (PHI).

## Core Requirements
1. **HIPAA Compliance**: Full compliance with HIPAA security and privacy rules
2. **Role-Based Access Control**: Strict permissions based on healthcare roles
3. **Data Encryption**: MongoDB Queryable Encryption for PHI at rest and in transit
4. **Audit Logging**: Comprehensive audit trails for all PHI access
5. **Medical Knowledge Base**: Searchable medical knowledge with clinical decision support

## Technology Stack
- **Backend**: Symfony 6.x (PHP 8.2+)
- **Database**: MongoDB Atlas with Queryable Encryption
- **Frontend**: HTML5, JavaScript (vanilla), Bootstrap 5
- **Authentication**: Symfony Security with session-based auth
- **Encryption**: MongoDB CSFLE (Client-Side Field-Level Encryption)

## Healthcare Roles
1. **ROLE_ADMIN**: System administration, audit logs, demo data, encryption management
2. **ROLE_DOCTOR**: Full patient access, clinical tools, medical knowledge, audit logs
3. **ROLE_NURSE**: Limited patient access, drug interactions, view notes
4. **ROLE_RECEPTIONIST**: Basic patient info, scheduling, insurance management
5. **ROLE_PATIENT**: Patient portal, view own records, messaging with staff

## Key Features
- Patient management with encrypted PHI
- Appointment scheduling and calendar
- Staff messaging system (doctors/nurses)
- Medical knowledge search with clinical decision support
- Audit logging and compliance reporting
- Queryable encryption demonstrations
- Patient portal for self-service

## Project Goals
- Demonstrate HIPAA-compliant architecture with MongoDB
- Showcase queryable encryption capabilities
- Provide role-based access control example
- Create educational resource for healthcare IT

