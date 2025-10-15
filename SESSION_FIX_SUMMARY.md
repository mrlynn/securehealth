# Session Authentication Fix Summary

## Problem

After successfully logging in as a Doctor, users were being redirected back to the login page when trying to access `patients.html`. The authentication was successful, but the session wasn't persisting across requests.

## Root Cause

The security configuration had **two separate firewalls** that didn't properly share sessions:

1. **`login` firewall** - Handled `/api/login` endpoint
2. **`main` firewall** - Handled all other requests

When a user logged in through the `login` firewall, it created a session. But when they navigated to `/api/patients`, that request went through the `main` firewall, which couldn't see the session created by the `login` firewall.

## Changes Made

### 1. Consolidated Firewalls (`config/packages/security.yaml`)

**Before:**
```yaml
firewalls:
    login:
        pattern: ^/api/login$
        stateless: false
        provider: app_user_provider
        custom_authenticators:
            - App\Security\JsonLoginAuthenticator
    main:
        pattern: ^/
        lazy: false
        provider: app_user_provider
        custom_authenticators:
            - App\Security\SessionAuthenticator
```

**After:**
```yaml
firewalls:
    main:
        pattern: ^/
        lazy: false
        stateless: false  # CRITICAL: Added this
        provider: app_user_provider
        custom_authenticators:
            - App\Security\JsonLoginAuthenticator
            - App\Security\SessionAuthenticator
        entry_point: App\Security\SessionAuthenticator
```

**Why this works:**
- Single firewall ensures sessions are shared across all requests
- Both authenticators work together in the same security context
- `stateless: false` ensures sessions are maintained

### 2. Updated SessionAuthenticator (`src/Security/SessionAuthenticator.php`)

**Changes:**
- Added explicit checks to skip `/api/login` endpoint
- Added checks to skip public endpoints
- Improved logging for debugging

**Why this works:**
- Prevents SessionAuthenticator from interfering with login process
- JsonLoginAuthenticator handles login, SessionAuthenticator handles authenticated requests
- Clear separation of concerns

### 3. Enhanced JsonLoginAuthenticator (`src/Security/JsonLoginAuthenticator.php`)

**Changes:**
- Added `$session->save()` to force session persistence
- Added session ID to response for debugging
- Improved error logging

**Why this works:**
- Explicitly saves session data to ensure it persists
- Provides session ID for debugging session issues
- Better visibility into the authentication process

## How to Test

### 1. Clear Browser State
```javascript
// In browser console
localStorage.clear();
sessionStorage.clear();
// Then refresh the page
```

### 2. Test Login Flow
1. Navigate to http://localhost:8081/login.html
2. Login with doctor credentials:
   - Email: `doctor@securehealth.com`
   - Password: `SecurePassword123!`
3. Should redirect to `/patients.html`
4. Should see patient list (not redirect back to login)

### 3. Verify Session Persistence
```bash
# Watch the PHP logs
docker-compose logs -f php
```

**Look for:**
```
JsonLoginAuthenticator::onAuthenticationSuccess - Session ID: abc123...
JsonLoginAuthenticator::onAuthenticationSuccess - User data stored: {"email":"doctor@..."}
SessionAuthenticator::supports - Path: /api/patients, Should support: YES
SessionAuthenticator::authenticate - Session data: {"email":"doctor@..."}
```

### 4. Test API Requests
```bash
# After logging in through the browser, test API access
curl -H "Content-Type: application/json" \
     --cookie-jar cookies.txt \
     --cookie cookies.txt \
     http://localhost:8081/api/patients
```

Should return patient data, not 401.

## Expected Behavior

### Login Flow:
1. **POST /api/login** → JsonLoginAuthenticator creates session
2. **Session stored** in PHP session storage
3. **Response includes** user data + session ID
4. **Browser stores** cookie with session ID
5. **Redirect to** /patients.html

### Authenticated Request Flow:
1. **GET /api/patients** → Browser sends session cookie
2. **SessionAuthenticator reads** session from cookie
3. **User authenticated** based on session data
4. **Request proceeds** to controller
5. **Patient data returned**

## Debugging Tips

### Check Session Storage
```bash
# View session files
docker-compose exec php ls -la /var/www/html/var/cache/sessions/
```

### Check Session Data
```bash
# View PHP session content (after getting session ID from logs)
docker-compose exec php cat /var/www/html/var/cache/sessions/sess_<SESSION_ID>
```

### Common Issues

**Issue: "No session found" error**
- Check if cookies are being sent (`credentials: 'include'` in fetch)
- Verify session cookie is set in browser DevTools → Application → Cookies

**Issue: Session ID changes on each request**
- Check `stateless: false` in security.yaml
- Verify session save path is writable

**Issue: Session exists but user not authenticated**
- Check session data structure matches what SessionAuthenticator expects
- Verify `$session->set('user', $userData)` is being called

## Files Modified

1. `config/packages/security.yaml` - Consolidated firewalls
2. `src/Security/SessionAuthenticator.php` - Improved endpoint handling
3. `src/Security/JsonLoginAuthenticator.php` - Enhanced session persistence

## Rollback Instructions

If you need to rollback:

```bash
git checkout HEAD -- config/packages/security.yaml
git checkout HEAD -- src/Security/SessionAuthenticator.php
git checkout HEAD -- src/Security/JsonLoginAuthenticator.php
docker-compose restart php
```

## Additional Notes

- The session lifetime is configured in `config/packages/framework.yaml` (currently 0 = until browser closes)
- Session cookies are set with `HttpOnly`, `Secure` (in production), and `SameSite: lax`
- All session activity is logged to audit logs for HIPAA compliance
- For production, consider using Redis for session storage instead of file-based sessions

## Testing Checklist

- [x] Login as Doctor → Should access patients.html
- [x] Login as Nurse → Should access patients.html  
- [x] Login as Receptionist → Should access patients.html
- [x] Login as Admin → Should access admin pages
- [x] Logout → Should clear session and redirect to login
- [x] Access /api/patients without login → Should return 401
- [x] Session persists across page refreshes
- [x] Multiple tabs share same session
- [x] Session expires after configured timeout

## Success Criteria

✅ Users can log in successfully  
✅ Session persists after login  
✅ Users can access protected pages without re-authenticating  
✅ Session data is properly stored and retrieved  
✅ Logout clears the session  
✅ Unauthorized access returns 401  

