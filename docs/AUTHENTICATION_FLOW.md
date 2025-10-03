# Authentication Flow Documentation

## Overview
This document describes the complete authentication flow for the SecureHealth application to prevent recurring authentication issues.

## Components

### 1. Backend Authentication
- **Login Endpoint**: `/api/login`
- **Session Management**: Symfony sessions with PHPSESSID cookie
- **Security**: Role-based access control (RBAC)

### 2. Frontend Authentication
- **Login Page**: `login.html`
- **Session Storage**: `localStorage` for user data
- **Cookie Handling**: `credentials: 'include'` for session cookies

## Authentication Flow

### Step 1: User Login
```javascript
// Frontend login request
fetch('/api/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    credentials: 'include',  // CRITICAL: Include session cookies
    body: JSON.stringify({
        _username: email,
        _password: password
    })
})
```

### Step 2: Backend Response
```json
{
    "success": true,
    "user": {
        "email": "receptionist@example.com",
        "username": "Receptionist Davis",
        "roles": ["ROLE_RECEPTIONIST", "ROLE_USER"],
        "isAdmin": false
    }
}
```

### Step 3: Frontend Session Storage
```javascript
// Store user data in localStorage
localStorage.setItem('securehealth_user', JSON.stringify(data.user));

// Session cookie is automatically set by browser
// PHPSESSID=abc123... (httponly, samesite=lax)
```

### Step 4: API Requests
```javascript
// All subsequent API requests must include credentials
fetch('/api/patients', {
    headers: {
        'Content-Type': 'application/json'
    },
    credentials: 'include'  // CRITICAL: Include session cookies
})
```

## Common Issues and Solutions

### Issue 1: "No patients found or invalid data format"
**Cause**: Session cookie not being sent with API requests
**Solution**: Ensure `credentials: 'include'` is set on all fetch requests

### Issue 2: 401 Unauthorized
**Cause**: Session expired or not set
**Solution**: Check PHPSESSID cookie and session persistence

### Issue 3: Environment variable errors
**Cause**: Missing MongoDB environment variables
**Solution**: Set required environment variables in Railway

## Testing

### Automated Testing
```bash
# Run the authentication flow test
./scripts/test-auth-flow.sh
```

### Manual Testing
1. Open `http://localhost:8081/debug-auth.html`
2. Run "Full Flow Test"
3. Check browser console for errors

### Browser Testing
1. Open `http://localhost:8081/login.html`
2. Login with: `receptionist@example.com` / `receptionist`
3. Should redirect to patients page with data

## Environment Variables

### Required for Production (Railway)
- `MONGODB_URI`: MongoDB Atlas connection string
- `MONGODB_DB`: Database name
- `MONGODB_KEY_VAULT_NAMESPACE`: Key vault namespace
- `MONGODB_ENCRYPTION_KEY_PATH`: Path to encryption key file

### Local Development
- Uses `.env` file (if exists)
- Falls back to default values

## Session Configuration

### Symfony Session Settings
```yaml
# config/packages/framework.yaml
session:
    handler_id: null
    cookie_secure: auto
    cookie_samesite: lax
    storage_factory_id: session.storage.factory.native
    save_path: '%kernel.project_dir%/var/cache/sessions'
    cookie_httponly: true
    cookie_lifetime: 0
    gc_maxlifetime: 1440
```

### Security Configuration
```yaml
# config/packages/security.yaml
firewalls:
    main:
        pattern: ^/
        lazy: false
        provider: users_in_memory
        custom_authenticators:
            - App\Security\SessionAuthenticator
        logout:
            path: app_logout
            target: app_login
```

## Troubleshooting

### 1. Check Session Cookie
```javascript
// In browser console
console.log(document.cookie);
// Should show: PHPSESSID=abc123...
```

### 2. Check localStorage
```javascript
// In browser console
console.log(localStorage.getItem('securehealth_user'));
// Should show user object
```

### 3. Check API Response
```javascript
// In browser console
fetch('/api/patients', {credentials: 'include'})
    .then(r => r.json())
    .then(console.log);
// Should show array of patients
```

### 4. Check Server Logs
```bash
# Check Symfony logs
tail -f var/log/dev.log

# Check Docker logs
docker-compose logs -f php
```

## Prevention Checklist

Before making changes that could affect authentication:

- [ ] Run `./scripts/test-auth-flow.sh`
- [ ] Test in browser with `debug-auth.html`
- [ ] Verify session cookies are set
- [ ] Check that `credentials: 'include'` is used
- [ ] Verify environment variables are set
- [ ] Test both local and remote environments

## Quick Fixes

### If authentication stops working:

1. **Check environment variables**:
   ```bash
   # Local
   cat .env
   
   # Remote (Railway)
   # Check Railway dashboard for env vars
   ```

2. **Clear browser data**:
   - Clear cookies for localhost:8081
   - Clear localStorage
   - Hard refresh (Ctrl+Shift+R)

3. **Restart services**:
   ```bash
   docker-compose restart
   ```

4. **Run tests**:
   ```bash
   ./scripts/test-auth-flow.sh
   ```

## Key Files

- `public/login.html` - Login page
- `public/patients.html` - Patients page
- `public/debug-auth.html` - Debug tool
- `src/Controller/Api/AuthController.php` - Login endpoint
- `src/Controller/Api/PatientController.php` - Patients endpoint
- `src/Security/SessionAuthenticator.php` - Session authentication
- `config/packages/security.yaml` - Security configuration
- `config/packages/framework.yaml` - Session configuration
- `scripts/test-auth-flow.sh` - Automated tests
