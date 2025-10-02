# API Reference

SecureHealth provides a comprehensive API for interacting with patient records and system features. This section documents all available API endpoints, their parameters, and response formats.

## API Basics

All API requests should:

- Use HTTPS for secure communication
- Include authentication via JWT token in the `Authorization` header
- Accept and return JSON data with appropriate content types

## Authentication

SecureHealth uses JSON Web Tokens (JWT) for API authentication:

```bash
# Login example
curl -X POST http://localhost:8081/api/login \
  -H "Content-Type: application/json" \
  -d '{"_username":"doctor@example.com","_password":"doctor"}'
```

The response will include a token that must be included in subsequent requests:

```bash
# Authenticated request example
curl -X GET http://localhost:8081/api/patients \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Available Endpoints

### Patient Endpoints

- [Patient List](patient#list) - Get a list of all patients
- [Patient Details](patient#details) - Get details for a specific patient
- [Create Patient](patient#create) - Create a new patient record
- [Update Patient](patient#update) - Update an existing patient
- [Delete Patient](patient#delete) - Delete a patient record
- [Patient Search](patient#search) - Search for patients

### Audit Log Endpoints

- [Audit Log List](audit-log#list) - Get a list of audit logs
- [Patient Audit Logs](audit-log#patient) - Get audit logs for a specific patient
- [User Audit Logs](audit-log#user) - Get audit logs for a specific user

### Integration Endpoints

- [Available Systems](integration#systems) - List available external systems
- [Import Patient](integration#import) - Import patient from external system
- [Export Patient](integration#export) - Export patient to external system

### Health Check Endpoints

- [System Health](health#check) - Check system health status
- [MongoDB Status](health#mongodb) - Check MongoDB connection status

## Role-Based Access

API access is restricted based on user roles. Each endpoint documents which roles have access to the functionality.

## Error Handling

The API uses standard HTTP status codes:

- `200 OK` - Request succeeded
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Authentication failure
- `403 Forbidden` - Permission denied
- `404 Not Found` - Resource not found
- `500 Server Error` - Server-side error

Error responses include a JSON object with an `error` field describing the issue.