# Role-Based Access Control Test Cases

## Overview

This document defines the test cases for validating the Role-Based Access Control (RBAC) implementation in the SecureHealth application. These tests ensure that users can only access patient data appropriate to their role, in compliance with HIPAA's minimum necessary access principle.

## Test Environment Prerequisites

- Test database with sample patient records
- Test users for each role:
  - Doctor: doctor@example.com / doctor
  - Nurse: nurse@example.com / nurse
  - Receptionist: receptionist@example.com / receptionist
  - Admin: admin@example.com / admin

## Patient Data Fields Access Matrix

| Field             | Doctor | Nurse | Receptionist |
|-------------------|--------|-------|--------------|
| firstName         | ✅     | ✅    | ✅           |
| lastName          | ✅     | ✅    | ✅           |
| email             | ✅     | ✅    | ✅           |
| phoneNumber       | ✅     | ✅    | ✅           |
| birthDate         | ✅     | ✅    | ✅           |
| ssn               | ✅     | ❌    | ❌           |
| diagnosis         | ✅     | ✅    | ❌           |
| medications       | ✅     | ✅    | ❌           |
| insuranceDetails  | ✅     | ✅    | ✅           |
| notes             | ✅     | ✅    | ❌           |
| createdAt         | ✅     | ✅    | ✅           |
| updatedAt         | ✅     | ✅    | ✅           |
| primaryDoctorId   | ✅     | ✅    | ❌           |

## Patient CRUD Operations Access Matrix

| Operation | Doctor | Nurse | Receptionist |
|-----------|--------|-------|--------------|
| View      | ✅     | ✅    | ✅           |
| Create    | ✅     | ✅    | ✅           |
| Update    | ✅     | ✅    | Partial      |
| Delete    | ✅     | ❌    | ❌           |

## Test Cases

### 1. Authentication Tests

#### TC-AUTH-001: Valid Login

**Description:** Verify that valid users can authenticate successfully

**Steps:**
1. Attempt to log in with valid credentials for each role
2. Verify that a valid token is returned
3. Verify that the token contains the correct role information

**Expected Results:**
- All valid users can log in successfully
- Each user receives a token with appropriate role claims

#### TC-AUTH-002: Invalid Login

**Description:** Verify that invalid credentials are rejected

**Steps:**
1. Attempt to log in with invalid username
2. Attempt to log in with invalid password
3. Attempt to log in with empty credentials

**Expected Results:**
- All invalid login attempts are rejected
- Appropriate error messages are returned
- No tokens are issued for invalid credentials

#### TC-AUTH-003: Token Validation

**Description:** Verify that API requests require valid authentication tokens

**Steps:**
1. Make API requests without authentication token
2. Make API requests with expired token
3. Make API requests with invalid token

**Expected Results:**
- All requests without valid tokens are rejected
- Appropriate 401/403 status codes are returned

### 2. Patient Data Access Tests

#### TC-ACCESS-001: Doctor Patient Data Access

**Description:** Verify that doctors can access all patient data fields

**Steps:**
1. Authenticate as a doctor
2. Retrieve a patient record
3. Verify all fields are accessible

**Expected Results:**
- All patient data fields are visible
- SSN and other sensitive fields are accessible
- No data is filtered out

#### TC-ACCESS-002: Nurse Patient Data Access

**Description:** Verify that nurses have appropriate access to patient data

**Steps:**
1. Authenticate as a nurse
2. Retrieve the same patient record used in TC-ACCESS-001
3. Verify field visibility matches the access matrix

**Expected Results:**
- Basic demographic data is visible
- Medical data (diagnosis, medications) is visible
- SSN is not visible
- Fields are filtered according to the access matrix

#### TC-ACCESS-003: Receptionist Patient Data Access

**Description:** Verify that receptionists have limited access to patient data

**Steps:**
1. Authenticate as a receptionist
2. Retrieve the same patient record used in TC-ACCESS-001
3. Verify field visibility matches the access matrix

**Expected Results:**
- Only basic demographic and insurance data is visible
- Medical data (diagnosis, medications) is not visible
- SSN is not visible
- Fields are filtered according to the access matrix

### 3. Patient Management Operation Tests

#### TC-OP-001: Doctor CRUD Operations

**Description:** Verify that doctors can perform all CRUD operations on patients

**Steps:**
1. Authenticate as a doctor
2. Create a new patient with all fields
3. Retrieve the created patient
4. Update the patient's information
5. Delete the patient

**Expected Results:**
- All operations succeed
- Patient can be created with all fields
- All fields can be updated
- Patient can be deleted

#### TC-OP-002: Nurse CRUD Operations

**Description:** Verify that nurses can create and update patients but not delete them

**Steps:**
1. Authenticate as a nurse
2. Create a new patient with appropriate fields
3. Retrieve the created patient
4. Update the patient's allowed fields
5. Attempt to update the patient's SSN
6. Attempt to delete the patient

**Expected Results:**
- Patient creation succeeds
- Patient retrieval succeeds with appropriate field filtering
- Updates to allowed fields succeed
- Update to SSN is rejected
- Delete operation is rejected with 403 Forbidden

#### TC-OP-003: Receptionist CRUD Operations

**Description:** Verify that receptionists can create and partially update patients

**Steps:**
1. Authenticate as a receptionist
2. Create a new patient with demographic and insurance fields
3. Retrieve the created patient
4. Update the patient's insurance information
5. Attempt to update the patient's diagnosis
6. Attempt to delete the patient

**Expected Results:**
- Patient creation succeeds
- Patient retrieval succeeds with appropriate field filtering
- Updates to insurance information succeed
- Update to diagnosis is rejected
- Delete operation is rejected with 403 Forbidden

### 4. Audit Log Access Tests

#### TC-AUDIT-001: Doctor Audit Log Access

**Description:** Verify that doctors can access all audit logs

**Steps:**
1. Authenticate as a doctor
2. Request audit logs for all patients
3. Request audit logs for a specific patient
4. Request audit logs for various users

**Expected Results:**
- All audit log requests succeed
- Doctor can view logs for any patient or user

#### TC-AUDIT-002: Nurse Audit Log Access

**Description:** Verify that nurses have limited audit log access

**Steps:**
1. Authenticate as a nurse
2. Attempt to access audit logs for all patients
3. Attempt to access audit logs for a specific patient
4. Attempt to access audit logs for self and other users

**Expected Results:**
- Request for all patient logs is rejected
- Request for specific patient logs is rejected
- Request for self logs succeeds
- Request for other users' logs is rejected

#### TC-AUDIT-003: Receptionist Audit Log Access

**Description:** Verify that receptionists have minimal audit log access

**Steps:**
1. Authenticate as a receptionist
2. Attempt to access audit logs for all patients
3. Attempt to access audit logs for a specific patient
4. Attempt to access audit logs for self and other users

**Expected Results:**
- Request for all patient logs is rejected
- Request for specific patient logs is rejected
- Request for self logs succeeds
- Request for other users' logs is rejected

### 5. Import/Export Operation Tests

#### TC-IMPEXP-001: Doctor Import/Export Operations

**Description:** Verify that doctors can import and export patient data

**Steps:**
1. Authenticate as a doctor
2. Import patient data from CSV/JSON
3. Export patient data to external system
4. Access import/export configurations

**Expected Results:**
- All import/export operations succeed
- Doctor can access all import/export features

#### TC-IMPEXP-002: Nurse Import/Export Operations

**Description:** Verify that nurses have limited import/export capabilities

**Steps:**
1. Authenticate as a nurse
2. Attempt to import patient data
3. Attempt to export patient data
4. Attempt to access import/export configurations

**Expected Results:**
- Import attempts are rejected
- Export attempts are rejected
- Configuration access is rejected

#### TC-IMPEXP-003: Receptionist Import/Export Operations

**Description:** Verify that receptionists cannot import or export patient data

**Steps:**
1. Authenticate as a receptionist
2. Attempt to import patient data
3. Attempt to export patient data
4. Attempt to access import/export configurations

**Expected Results:**
- All import/export operations are rejected

### 6. External Integration Tests

#### TC-INT-001: Doctor Integration Operations

**Description:** Verify that doctors can access external system integrations

**Steps:**
1. Authenticate as a doctor
2. List available external systems
3. Import a patient from external system
4. Export a patient to external system

**Expected Results:**
- All integration operations succeed
- Doctor can access all external system features

#### TC-INT-002: Nurse Integration Operations

**Description:** Verify that nurses have limited external system access

**Steps:**
1. Authenticate as a nurse
2. Attempt to list external systems
3. Attempt to import/export with external systems

**Expected Results:**
- External system access is rejected
- Import/export operations are rejected

#### TC-INT-003: Receptionist Integration Operations

**Description:** Verify that receptionists cannot access external systems

**Steps:**
1. Authenticate as a receptionist
2. Attempt to list external systems
3. Attempt to import/export with external systems

**Expected Results:**
- All external system operations are rejected

### 7. Cross-Role Access Tests

#### TC-CROSS-001: Role Elevation Attempt

**Description:** Verify that users cannot elevate their privileges

**Steps:**
1. Authenticate as a nurse
2. Modify API requests to attempt to access doctor-level data
3. Modify request parameters to bypass role checks

**Expected Results:**
- All privilege elevation attempts are rejected
- Server-side role enforcement prevents access

#### TC-CROSS-002: Token Manipulation

**Description:** Verify that token manipulation cannot bypass access controls

**Steps:**
1. Authenticate as a receptionist
2. Modify the JWT token to claim doctor role
3. Attempt to access doctor-only endpoints

**Expected Results:**
- Modified tokens are rejected as invalid
- Access is denied to protected resources

### 8. Special Case Tests

#### TC-SPECIAL-001: Emergency Access

**Description:** Verify emergency access provisions (if implemented)

**Steps:**
1. Authenticate as a nurse
2. Attempt emergency access to restricted data
3. Verify audit logging of emergency access

**Expected Results:**
- Emergency access is granted if properly requested
- All emergency access is thoroughly logged
- Normal access controls resume after emergency

#### TC-SPECIAL-002: Temporary Access Elevation

**Description:** Verify temporary role elevation (if implemented)

**Steps:**
1. Authenticate as a receptionist
2. Request temporary access elevation with approval
3. Verify access during and after elevation period

**Expected Results:**
- Temporary elevation works if properly approved
- Elevated access expires automatically
- All elevation events are thoroughly logged

## Test Execution Procedure

1. **Preparation**
   - Create test users for each role
   - Prepare test patient data
   - Set up API testing environment (Postman, curl, etc.)

2. **Execution**
   - Execute each test case
   - Document actual results
   - Compare with expected results

3. **Reporting**
   - Document pass/fail status
   - Note any discrepancies
   - Provide evidence for each test case

## Acceptance Criteria

The RBAC implementation will be considered acceptable when:

1. All users can only access data appropriate to their role
2. Role-based field filtering works correctly
3. Operation permissions are enforced for all roles
4. No privilege escalation is possible
5. All access decisions are properly logged
6. HIPAA minimum necessary principle is enforced