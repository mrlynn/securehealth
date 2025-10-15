# Technical Context

## Technology Stack

### Backend
- **Framework**: Symfony 6.x
- **Language**: PHP 8.2+
- **ODM**: Doctrine MongoDB ODM
- **Security**: Symfony Security Component
- **Session**: Symfony Session with MongoDB storage

### Database
- **Primary Database**: MongoDB Atlas (cloud-hosted)
- **Encryption**: MongoDB Queryable Encryption (CSFLE)
- **Collections**: 
  - `users` - User accounts and authentication
  - `patients` - Patient records (encrypted PHI)
  - `appointments` - Appointment scheduling
  - `audit_log` - HIPAA compliance audit trail
  - `medical_knowledge` - Medical reference data
  - `conversations` - Staff-patient messaging threads
  - `messages` - Individual messages

### Frontend
- **UI Framework**: Bootstrap 5.3.3 (static pages), Bootstrap 4 (Symfony pages)
- **JavaScript**: Vanilla JS (no frameworks)
- **Icons**: Font Awesome 6.4.0
- **CSS**: Custom CSS + Bootstrap themes

### Development Environment
- **Containerization**: Docker & Docker Compose
- **Web Server**: Nginx
- **PHP Container**: PHP 8.2-FPM
- **Local MongoDB**: Docker container (for development)

## Key Dependencies

### PHP/Composer
```json
{
  "symfony/framework-bundle": "^6.0",
  "symfony/security-bundle": "^6.0",
  "symfony/twig-bundle": "^6.0",
  "doctrine/mongodb-odm": "^2.5",
  "doctrine/mongodb-odm-bundle": "^4.6",
  "mongodb/mongodb": "^1.17"
}
```

### JavaScript (CDN)
- Bootstrap 5.3.3 (CSS & JS)
- Font Awesome 6.4.0
- No build process required

## File Structure

```
/hipaa/
├── bin/                    # Console commands
├── config/                 # Symfony configuration
│   ├── packages/          # Service configurations
│   └── routes/            # Route definitions
├── public/                # Web root (static files)
│   ├── assets/           # CSS/JS assets
│   │   ├── css/         # Stylesheets
│   │   └── js/          # JavaScript files
│   ├── patient-portal/  # Patient self-service portal
│   └── *.html           # Static HTML pages
├── src/
│   ├── Command/         # CLI commands
│   ├── Controller/      # Request handlers
│   │   └── Api/        # API endpoints
│   ├── Document/        # MongoDB document models
│   ├── Repository/      # Data access layer
│   ├── Security/        # Security voters, providers
│   └── Service/         # Business logic
├── templates/           # Twig templates
│   ├── includes/       # Shared components (navbar)
│   └── */              # Feature-specific templates
├── memory-bank/        # Project documentation
└── vendor/             # Composer dependencies
```

## Configuration Files

### MongoDB Connection
Environment variable: `MONGODB_URI`
```
mongodb+srv://[user]:[pass]@[cluster].mongodb.net/?retryWrites=true&w=majority
```

### Security Configuration
`config/packages/security.yaml`:
- Session-based authentication
- Role hierarchy
- Access control rules for routes
- Password hashing algorithm

### Doctrine MongoDB
`config/packages/doctrine_mongodb.yaml`:
- Connection settings
- Document mapping
- Auto-generation of document metadata

### Encryption Configuration
`config/packages/mongo_encryption.yaml`:
- Encryption keys location
- Encrypted fields configuration
- Key vault settings

## Development Setup

### Prerequisites
- PHP 8.2+
- Composer
- Docker & Docker Compose (for local development)
- MongoDB Atlas account (for production-like testing)

### Environment Variables
```bash
MONGODB_URI=mongodb+srv://...
MONGODB_DB=securehealth
APP_ENV=dev|prod
APP_SECRET=[random-secret]
ENCRYPTION_KEY_PATH=/path/to/keys
```

### Installation
```bash
composer install
php bin/console doctrine:mongodb:schema:create
php bin/console app:create-users  # Create demo users
```

### Running Locally
```bash
# With Docker
docker-compose up -d

# With Symfony CLI
symfony server:start
```

## API Endpoints

### Authentication
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `GET /api/auth/status` - Check auth status

### Patients
- `GET /api/patients` - List patients
- `GET /api/patients/{id}` - Get patient details
- `POST /api/patients` - Create patient
- `PUT /api/patients/{id}` - Update patient
- `DELETE /api/patients/{id}` - Delete patient
- `GET /api/patients/{id}/notes` - Get patient notes
- `POST /api/patients/{id}/notes` - Add patient note

### Appointments
- `GET /api/appointments` - List appointments
- `POST /api/appointments` - Create appointment
- `PUT /api/appointments/{id}` - Update appointment
- `DELETE /api/appointments/{id}` - Delete appointment

### Medical Knowledge
- `GET /api/medical-knowledge/search` - Search knowledge base
- `GET /api/medical-knowledge/{id}` - Get knowledge entry
- `POST /api/medical-knowledge` - Create entry (admin/doctor)
- `PUT /api/medical-knowledge/{id}` - Update entry
- `DELETE /api/medical-knowledge/{id}` - Delete entry (admin)

### Messaging
- `GET /api/conversations/inbox` - Get conversations
- `POST /api/conversations` - Create conversation
- `POST /api/conversations/{id}/reply` - Reply to conversation
- `GET /api/conversations/inbox/unread-count` - Unread count

### Audit Logs
- `GET /api/audit-logs` - List audit logs (doctor/admin)
- `GET /api/audit-logs/patient/{id}` - Patient-specific logs
- `GET /api/audit-logs/user/{username}` - User-specific logs

## Security Considerations

### HIPAA Compliance
1. **Encryption at Rest**: MongoDB Queryable Encryption
2. **Encryption in Transit**: TLS/SSL for all connections
3. **Access Control**: Role-based with Symfony Security
4. **Audit Logging**: All PHI access logged
5. **Session Security**: Secure session handling with HttpOnly cookies

### Authentication
- Session-based (not JWT)
- Secure password hashing (bcrypt)
- Role-based authorization
- CSRF protection on forms

### Data Protection
- Field-level encryption for PHI
- Queryable encryption for searchable fields
- Automatic key rotation capability
- Separate encryption keys per environment

## Testing

### Available Test Commands
```bash
# Run all tests
php bin/phpunit

# Test specific component
php bin/phpunit tests/Controller/

# Test authentication flow
./scripts/test-auth-flow.sh

# Verify security configuration
./scripts/verify-security.sh
```

### Test Users
Created via `php bin/console app:create-users`:
- admin@securehealth.com (ROLE_ADMIN)
- doctor@securehealth.com (ROLE_DOCTOR)
- nurse@securehealth.com (ROLE_NURSE)
- receptionist@securehealth.com (ROLE_RECEPTIONIST)

## Deployment

### Production Environment
- Platform: Railway.app (configured)
- Database: MongoDB Atlas (production cluster)
- Domain: Custom domain with SSL
- Environment: Production mode with error logging

### Deployment Files
- `railway.json` - Railway configuration
- `nixpacks.toml` - Build configuration
- `Dockerfile` - Container definition
- `.env.production` - Production environment variables

