# System Patterns

## Architecture Overview
SecureHealth follows a traditional MVC pattern with Symfony framework, enhanced with MongoDB for flexible data storage and queryable encryption.

```
┌─────────────────────────────────────────────────────────────┐
│                         Frontend                             │
│  (HTML/JS/Bootstrap - Static & Twig Templates)              │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│                    Symfony Application                       │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Controllers (API & Web)                                 │ │
│  │  - PatientController, AppointmentController, etc.      │ │
│  └─────────────┬──────────────────────────────────────────┘ │
│                │                                              │
│  ┌─────────────▼─────────────┐  ┌────────────────────────┐  │
│  │   Security Voters          │  │   Services             │  │
│  │  - PatientVoter            │  │  - AuditLogService     │  │
│  │  - MedicalKnowledgeVoter   │  │  - EncryptionService   │  │
│  └─────────────┬──────────────┘  └────────┬───────────────┘  │
│                │                           │                  │
│  ┌─────────────▼───────────────────────────▼───────────────┐ │
│  │         Doctrine MongoDB ODM                            │ │
│  │  - Document Models (Patient, User, etc.)                │ │
│  │  - Repositories                                          │ │
│  └─────────────┬─────────────────────────────────────────┬─┘ │
└────────────────┼─────────────────────────────────────────┼───┘
                 │                                         │
        ┌────────▼────────┐                   ┌───────────▼──────┐
        │  MongoDB Atlas   │                   │  Local Encryption│
        │  (Encrypted DB)  │                   │  Key Management  │
        └──────────────────┘                   └──────────────────┘
```

## Key Design Patterns

### 1. Security Voter Pattern
All authorization decisions go through Symfony Security Voters, which provide fine-grained access control:

**PatientVoter** - Controls access to patient data:
- `PATIENT_VIEW`, `PATIENT_CREATE`, `PATIENT_EDIT`, `PATIENT_DELETE`
- `PATIENT_VIEW_DIAGNOSIS`, `PATIENT_EDIT_DIAGNOSIS`
- `PATIENT_VIEW_MEDICATIONS`, `PATIENT_EDIT_MEDICATIONS`
- `PATIENT_VIEW_SSN`, `PATIENT_VIEW_INSURANCE`, `PATIENT_EDIT_INSURANCE`
- `PATIENT_VIEW_NOTES`, `PATIENT_ADD_NOTE`, `PATIENT_EDIT_NOTES`, `PATIENT_DELETE_NOTE`

**MedicalKnowledgeVoter** - Controls access to medical knowledge:
- `MEDICAL_KNOWLEDGE_VIEW`, `MEDICAL_KNOWLEDGE_SEARCH`
- `MEDICAL_KNOWLEDGE_CLINICAL_DECISION_SUPPORT`
- `MEDICAL_KNOWLEDGE_DRUG_INTERACTIONS`
- `MEDICAL_KNOWLEDGE_TREATMENT_GUIDELINES`
- `MEDICAL_KNOWLEDGE_DIAGNOSTIC_CRITERIA`
- `MEDICAL_KNOWLEDGE_CREATE`, `MEDICAL_KNOWLEDGE_EDIT`, `MEDICAL_KNOWLEDGE_DELETE`

### 2. Audit Logging Pattern
All PHI access is automatically logged through the `AuditLogService`:
```php
// Automatic logging in voters
$this->auditLogService->log($user, 'security_access', [
    'attribute' => $attribute,
    'patientId' => $subject->getId(),
    'granted' => $granted
]);
```

### 3. Dual Navigation System
Two parallel navigation systems for different contexts:

**JavaScript Navbar** (`public/assets/js/navbar.js`):
- Used by static HTML pages
- Client-side role detection from localStorage
- Dynamic rendering based on user role
- Bootstrap 5 compatible

**Twig Navbar** (`templates/includes/navbar.html.twig`):
- Used by Symfony-rendered pages
- Server-side role detection via Symfony Security
- Twig template syntax with `is_granted()` checks
- Bootstrap 4 compatible (legacy)

### 4. MongoDB Queryable Encryption
Client-Side Field-Level Encryption (CSFLE) for PHI:
```javascript
{
  ssn: { encrypt: true, type: 'equality' },
  diagnosis: { encrypt: true, type: 'equality' },
  medications: { encrypt: true, type: 'equality' },
  bloodType: { encrypt: true, type: 'equality' }
}
```

### 5. Role Hierarchy
Symfony's role hierarchy provides permission inheritance:
```yaml
role_hierarchy:
    ROLE_DOCTOR: [ROLE_NURSE, ROLE_RECEPTIONIST]
    ROLE_NURSE: [ROLE_RECEPTIONIST]
    ROLE_PATIENT: [ROLE_USER]
```

### 6. API Design Pattern
RESTful API controllers under `/api/*`:
- `/api/patients/*` - Patient CRUD operations
- `/api/appointments/*` - Appointment management
- `/api/conversations/*` - Staff messaging
- `/api/medical-knowledge/*` - Medical knowledge search
- `/api/audit-logs/*` - Audit log viewing

### 7. Document-Based Data Model
MongoDB documents instead of relational tables:
- Flexible schema for healthcare data
- Embedded sub-documents (notes, appointments)
- Easy encryption integration
- Better performance for complex queries

## Navigation Architecture

### Role-Based Menu Structure
Each role sees a customized navigation menu:

**ROLE_ADMIN**
- Home, Calendar, Documentation, Help
- Patients → View All, Add New
- Admin → Dashboard, Demo Data, Medical Knowledge, Encryption Search, User Management

**ROLE_DOCTOR**
- Home, Calendar, Documentation, Help
- Patients → View All, Add New, Manage Patient Notes
- Clinical Tools → Medical Knowledge, Clinical Decision Support, Drug Interactions, Treatment Guidelines, Diagnostic Criteria, Audit Logs
- Messages (with unread badge)

**ROLE_NURSE**
- Home, Calendar, Documentation, Help
- Patients → View All, Add New, View Patient Notes
- Medical Tools → Drug Interactions, Medical Knowledge (View)
- Messages (with unread badge)

**ROLE_RECEPTIONIST**
- Home, Calendar, Documentation, Help
- Patients → View All, Add New, Scheduling
- Scheduling (top-level)

### Navigation Consistency
Both navigation systems maintain:
- Consistent visual hierarchy
- Font Awesome icons for all menu items
- Active state highlighting
- Responsive dropdown menus
- Role-appropriate labeling

## Component Relationships

### Patient Management Flow
```
User → Controller → Voter (check permissions) → Service (business logic) 
  → Repository → MongoDB (encrypted storage) → Audit Log
```

### Authentication Flow
```
Login Form → AuthController → Symfony Security 
  → UserProvider → MongoDB → Session Creation 
  → Role Assignment → Navigation Rendering
```

### Medical Knowledge Search
```
Search Query → MedicalKnowledgeController → Voter (check permissions)
  → MedicalKnowledgeService → MongoDB Vector Search
  → Results Filtering (by role) → JSON Response
```

