# Adding a New Role to SecureHealth

This guide explains the step-by-step process for adding a new role to the SecureHealth application.

## Overview

The role system in SecureHealth controls access to patient data and system features. Roles are defined in multiple places throughout the application and must be updated consistently.

## Step 1: Update Security Configuration

**File:** `config/packages/security.yaml`

### 1.1 Add Role to Access Control

Add access control rules for your new role in the `access_control` section:

```yaml
access_control:
    # Example: Allow new role to access specific endpoints
    - { path: ^/api/patients, methods: [GET], roles: [ROLE_DOCTOR, ROLE_NURSE, ROLE_RECEPTIONIST, ROLE_NEW_ROLE] }
```

### 1.2 Update Role Hierarchy (Optional)

If your role should inherit permissions from another role, add it to the `role_hierarchy` section:

```yaml
role_hierarchy:
    ROLE_DOCTOR: [ROLE_NURSE, ROLE_RECEPTIONIST, ROLE_ADMIN]
    ROLE_NURSE: [ROLE_RECEPTIONIST]
    # Add your new role here if it should inherit permissions
    ROLE_NEW_ROLE: [ROLE_RECEPTIONIST]
```

## Step 2: Update Patient Document

**File:** `src/Document/Patient.php`

### 2.1 Add Role-Based Data Access

In the `toArray()` method (around line 230-250), add a new conditional block for your role:

```php
// After existing role checks...
elseif (in_array('ROLE_NEW_ROLE', $roles)) {
    // Define what data this role can access
    $data['diagnosis'] = $this->getDiagnosis();
    $data['medications'] = $this->getMedications();
    // Add or remove fields based on role requirements
}
```

**Data Access Examples:**
- Basic info: `firstName`, `lastName`, `email`, `phoneNumber`, `birthDate`, `createdAt`
- Medical data: `ssn`, `diagnosis`, `medications`, `notes`, `notesHistory`
- Administrative: `insuranceDetails`, `primaryDoctorId`

## Step 3: Update Security Voter

**File:** `src/Security/Voter/PatientVoter.php`

### 3.1 Add Role to Permission Checks

Update the `checkPermission()` method to include your new role in permission checks:

```php
case self::VIEW:
    // Add your role to appropriate permission checks
    return in_array('ROLE_DOCTOR', $roles) || 
           in_array('ROLE_NURSE', $roles) || 
           in_array('ROLE_RECEPTIONIST', $roles) ||
           in_array('ROLE_NEW_ROLE', $roles);

case self::VIEW_DIAGNOSIS:
case self::VIEW_MEDICATIONS:
    // Control medical data access
    return in_array('ROLE_DOCTOR', $roles) || 
           in_array('ROLE_NURSE', $roles) ||
           in_array('ROLE_NEW_ROLE', $roles);
```

**Available Permission Constants:**
- `PATIENT_VIEW` - View basic patient info
- `PATIENT_CREATE` - Create new patients
- `PATIENT_EDIT` - Edit patient records
- `PATIENT_DELETE` - Delete patients
- `PATIENT_VIEW_DIAGNOSIS` - View diagnosis
- `PATIENT_EDIT_DIAGNOSIS` - Edit diagnosis
- `PATIENT_VIEW_MEDICATIONS` - View medications
- `PATIENT_EDIT_MEDICATIONS` - Edit medications
- `PATIENT_VIEW_SSN` - View SSN
- `PATIENT_VIEW_INSURANCE` - View insurance
- `PATIENT_EDIT_INSURANCE` - Edit insurance
- `PATIENT_VIEW_NOTES` - View notes
- `PATIENT_EDIT_NOTES` - Edit notes
- `PATIENT_ADD_NOTE` - Add new notes
- `PATIENT_UPDATE_NOTE` - Update notes
- `PATIENT_DELETE_NOTE` - Delete notes

## Step 4: Update PatientController Serialization

**File:** `src/Controller/Api/PatientController.php`

Add your new role to the role detection logic (around line 75-88):

```php
if ($user) {
    $userRoles = $user->getRoles();
    if (in_array('ROLE_DOCTOR', $userRoles)) {
        $userRole = 'ROLE_DOCTOR';
    } elseif (in_array('ROLE_NURSE', $userRoles)) {
        $userRole = 'ROLE_NURSE';
    } elseif (in_array('ROLE_ADMIN', $userRoles)) {
        $userRole = 'ROLE_ADMIN';
    } elseif (in_array('ROLE_RECEPTIONIST', $userRoles)) {
        $userRole = 'ROLE_RECEPTIONIST';
    } elseif (in_array('ROLE_NEW_ROLE', $userRoles)) {
        $userRole = 'ROLE_NEW_ROLE';
    }
}
```

Repeat this pattern in all methods that serialize patient data: `index()`, `show()`, `create()`, `update()`.

## Step 5: Create Default User (Optional)

**File:** `src/Command/CreateUsersCommand.php`

Add a default user for the new role in the `$users` array (around line 46-72):

```php
$users = [
    // ... existing users ...
    [
        'email' => 'newrole@example.com',
        'username' => 'New Role User',
        'password' => 'password',
        'roles' => ['ROLE_NEW_ROLE']
    ]
];
```

Run the command to create the user:
```bash
php bin/console app:create-users --force
```

## Step 6: Update Frontend (Optional)

If your new role needs specific UI features:

### 6.1 Update Navigation

**File:** `public/assets/js/navbar.js`

Add role-specific navigation items based on the user's role.

### 6.2 Update Role Documentation

**File:** `public/role-documentation.html`

Add documentation for the new role's capabilities and responsibilities.

## Key Principles

1. **Least Privilege**: Only grant access to data that the role needs to perform its duties
2. **Data Segregation**: Medical data (diagnosis, medications, notes) should be separate from administrative data (insurance, demographics)
3. **Consistency**: Update all relevant files to maintain system integrity
4. **Testing**: Test the new role with sample users to ensure proper access control

## Example: Adding ROLE_PHARMACIST

A pharmacist role that can view medications and allergies but not full diagnosis:

1. **security.yaml**: Add to access control for medication endpoints
2. **Patient.php**: 
   ```php
   elseif (in_array('ROLE_PHARMACIST', $roles)) {
       $data['medications'] = $this->getMedications();
       $data['diagnosis'] = $this->getDiagnosis(); // Limited view
   }
   ```
3. **PatientVoter.php**: Add to `VIEW_MEDICATIONS` permission
4. **PatientController.php**: Add to role detection chain
5. **CreateUsersCommand.php**: Add default pharmacist user

## Testing Checklist

- [ ] User can log in with new role
- [ ] User can access permitted endpoints
- [ ] User is denied access to restricted data
- [ ] Role hierarchy works correctly (if applicable)
- [ ] Audit logs record the role's actions
- [ ] Frontend displays appropriate UI for the role

## Additional Files to Consider

When adding a new role, you may also need to update:

### Controllers
- `src/Controller/Api/DashboardController.php` - Add role-specific dashboard data
- `src/Controller/Api/AuditLogController.php` - Control audit log access
- `src/Controller/Api/AppointmentController.php` - Appointment management permissions

### Services
- `src/Service/PatientVerificationService.php` - Role-based verification requirements
- `src/Service/RAGChatbotService.php` - AI chatbot access control

### Tests
- `tests/Security/Voter/PatientVoterTest.php` - Add tests for new role permissions
- `tests/Integration/` - Integration tests for role-specific workflows

### Frontend
- `public/assets/js/dashboard.js` - Dashboard functionality for the role
- `public/assets/js/navbar.js` - Navigation menu items
- `public/patients.html` - Patient management interface
- `public/role-documentation.html` - Role-specific documentation

## Security Considerations

1. **Always follow the principle of least privilege**
2. **Test thoroughly with sample users**
3. **Ensure audit logging captures all role-based actions**
4. **Consider HIPAA compliance implications for each role**
5. **Document the role's purpose and data access requirements**
6. **Review and update access controls regularly**

## Common Pitfalls

1. **Forgetting to update all relevant files** - The role system spans multiple files
2. **Inconsistent permission logic** - Ensure voter and controller logic match
3. **Missing frontend updates** - UI should reflect role capabilities
4. **Inadequate testing** - Test both positive and negative access scenarios
5. **Poor documentation** - Document what each role can and cannot do
