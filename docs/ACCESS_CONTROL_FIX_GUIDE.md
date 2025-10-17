# Access Control Fix Guide

## Problem Identified

The static HTML pages have **inconsistent authentication checking**, leading to authentication errors:

1. Some pages only check `localStorage` (client-side)
2. Some pages call different API endpoints for validation
3. Server-side sessions aren't consistently verified
4. No `/api/user` endpoint existed for session verification
5. Access control rules were missing for auth endpoints

## Solution Implemented

### 1. New API Endpoints

Created `/src/Controller/Api/AuthController.php` with three new endpoints:

**GET `/api/user`** - Get current authenticated user
```javascript
// Returns current user data from server-side session
{
  "success": true,
  "user": {
    "username": "doctor@example.com",
    "roles": ["ROLE_DOCTOR", "ROLE_NURSE", "ROLE_RECEPTIONIST", "ROLE_USER"],
    "isDoctor": true,
    ...
  }
}
```

**GET `/api/verify-access/{page}`** - Verify page access
```javascript
// Checks if current user can access a specific page
// Returns 200 if access granted, 401/403 if denied
```

**POST `/api/check-permission`** - Check role permissions
```javascript
// Checks if user has required roles
// Body: { "roles": ["ROLE_DOCTOR"] }
```

### 2. Authentication Guard Utility

Created `/public/assets/js/auth-guard.js` - A standardized authentication library that all pages should use:

```javascript
// Example usage in any page:
await requireAuth(); // Ensure user is logged in
await requireRole('ROLE_DOCTOR'); // Ensure user has specific role
await requirePageAccess('calendar'); // Verify page access
```

Key features:
- ✅ Verifies server-side session
- ✅ Syncs with localStorage
- ✅ Provides easy role checking
- ✅ Automatic redirect on auth failure
- ✅ Consistent error handling

### 3. Access Audit Tool

Created `/public/assets/js/access-audit.js` - Diagnostic tool for troubleshooting:

```javascript
// Run in browser console:
await runAccessAudit();

// Checks:
// - localStorage state
// - Server session validity
// - API endpoint access
// - Page access permissions
// - Returns detailed diagnostic report
```

### 4. Updated Security Configuration

Updated `/config/packages/security.yaml`:
```yaml
access_control:
    - { path: ^/api/user, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/api/verify-access, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/api/check-permission, roles: IS_AUTHENTICATED_FULLY }
```

## How to Fix Each Page

### Pattern 1: Simple Authentication Check (Most Pages)

**Old pattern:**
```html
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const user = window.secureHealthNavbar.getCurrentUser();
    if (!user) {
      window.location.href = '/login.html';
      return;
    }
  });
</script>
```

**New pattern:**
```html
<!-- Add auth-guard before other scripts -->
<script src="/assets/js/auth-guard.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', async function() {
    // Verify authentication with server
    const isAuth = await requireAuth();
    if (!isAuth) return; // Already redirected
    
    // Now safe to initialize page
    initializePage();
  });
</script>
```

### Pattern 2: Role-Based Access (Admin, Doctor-only pages)

**Old pattern:**
```html
<script>
  const user = JSON.parse(localStorage.getItem('securehealth_user'));
  if (!user.roles.includes('ROLE_DOCTOR')) {
    alert('Access denied');
    window.location.href = '/patients.html';
  }
</script>
```

**New pattern:**
```html
<script src="/assets/js/auth-guard.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', async function() {
    // Verify role with server
    const hasAccess = await requireRole('ROLE_DOCTOR');
    if (!hasAccess) return; // Already redirected
    
    // Now safe to initialize page
    initializePage();
  });
</script>
```

### Pattern 3: Page-Specific Access Verification

**New pattern for maximum security:**
```html
<script src="/assets/js/auth-guard.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', async function() {
    // Verify this specific page access
    const hasAccess = await requirePageAccess('medical-knowledge-search');
    if (!hasAccess) return; // Already redirected
    
    // Now safe to initialize page
    initializePage();
  });
</script>
```

## Pages That Need Updating

Run this audit to find pages with issues:

### High Priority (Core Pages)
- [ ] `/public/calendar.html` - All authenticated users
- [ ] `/public/patients.html` - Healthcare staff
- [ ] `/public/patient-add.html` - Healthcare staff  
- [ ] `/public/patient-detail.html` - Healthcare staff
- [ ] `/public/patient-edit.html` - Doctors/Nurses
- [ ] `/public/scheduling.html` - All staff
- [ ] `/public/medical-knowledge-search.html` - Doctors/Nurses/Admins
- [ ] `/public/patient-notes-demo.html` - Doctors/Nurses
- [ ] `/public/admin.html` - Admins/Doctors
- [ ] `/public/admin-demo-data.html` - Admins only
- [ ] `/public/queryable-encryption-search.html` - Admins only

### Medium Priority (Staff Pages)
- [ ] `/public/staff/messages` (if exists as HTML)

### Lower Priority (Debug/Test Pages)
- Debug pages can remain as-is or be removed

## Step-by-Step Fix Process

### For Each Page:

1. **Add the auth-guard script** (before other scripts):
```html
<script src="/assets/js/auth-guard.js"></script>
```

2. **Identify required access level:**
   - All authenticated: `await requireAuth()`
   - Specific role: `await requireRole(['ROLE_DOCTOR', 'ROLE_ADMIN'])`
   - Page-specific: `await requirePageAccess('page-name')`

3. **Update DOMContentLoaded handler:**
```html
<script>
  document.addEventListener('DOMContentLoaded', async function() {
    // Add ONE of these based on page requirements:
    
    // Option A: Just authentication
    const isAuth = await requireAuth();
    if (!isAuth) return;
    
    // Option B: Specific role(s)
    const hasRole = await requireRole(['ROLE_DOCTOR']);
    if (!hasRole) return;
    
    // Option C: Page-specific verification
    const hasAccess = await requirePageAccess('calendar');
    if (!hasAccess) return;
    
    // Continue with page initialization
    initYourPage();
  });
</script>
```

4. **Remove old authentication code:**
   - Remove localStorage checks
   - Remove manual `/api/user` calls
   - Remove custom authentication functions

5. **Test the page:**
   - Open browser console
   - Run `await runAccessAudit()`
   - Verify page access works
   - Try accessing with different roles

## Testing Your Fixes

### 1. Run Access Audit

In browser console on any page:
```javascript
await runAccessAudit()
```

Look for:
- ✅ Server session valid
- ✅ Page access granted
- ❌ Authentication errors
- ⚠️  Permission warnings

### 2. Test Each Role

Login as each role and verify:
- **Admin**: Can access admin pages, demo data, encryption search
- **Doctor**: Can access all clinical tools, audit logs, patient notes
- **Nurse**: Can access medical tools, drug interactions, patient data (limited)
- **Receptionist**: Can access scheduling, basic patient info

### 3. Test Unauthorized Access

Try accessing:
- Admin page as Doctor ✓ (should work - doctors can view audit logs)
- Admin demo data as Doctor ✗ (should deny)
- Medical knowledge as Receptionist ✗ (should deny)  
- Patient notes as Receptionist ✗ (should deny)

## Quick Fix Script

For bulk updating pages, use this template:

```javascript
// Save as fix-page-auth.js
const fs = require('fs');

function fixPageAuth(filePath, accessLevel) {
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Add auth-guard script if not present
    if (!content.includes('auth-guard.js')) {
        content = content.replace(
            '<script src="/assets/js/navbar.js"></script>',
            '<script src="/assets/js/auth-guard.js"></script>\n  <script src="/assets/js/navbar.js"></script>'
        );
    }
    
    // Add access check
    const accessCheck = `
    // Verify access
    const hasAccess = await ${accessLevel};
    if (!hasAccess) return;
    `;
    
    content = content.replace(
        "document.addEventListener('DOMContentLoaded', function() {",
        `document.addEventListener('DOMContentLoaded', async function() {${accessCheck}`
    );
    
    fs.writeFileSync(filePath, content);
    console.log(`✅ Fixed: ${filePath}`);
}

// Usage:
fixPageAuth('public/calendar.html', 'requireAuth()');
fixPageAuth('public/admin.html', "requireRole(['ROLE_ADMIN', 'ROLE_DOCTOR'])");
```

## Troubleshooting

### "Not authenticated" on all pages

**Cause**: Session not being created or sent

**Fix**:
1. Clear all cookies and localStorage
2. Log out completely
3. Log back in
4. Check browser console for cookie errors

### "Access denied" on pages you should access

**Cause**: Role hierarchy not working or wrong roles

**Fix**:
1. Run `await runAccessAudit()` to see your current roles
2. Check `config/packages/security.yaml` role_hierarchy
3. Verify user roles in database
4. Check page permission requirements in `AuthController.php`

### API calls return 401 even when logged in

**Cause**: Session cookies not being sent

**Fix**:
1. Ensure `credentials: 'include'` in all fetch calls
2. Check browser security settings
3. Verify same-origin policy compliance
4. Check CORS configuration

### Pages load but data doesn't

**Cause**: API authentication succeeds but authorization fails

**Fix**:
1. Check the specific API endpoint access rules in `security.yaml`
2. Verify voter permissions for the feature
3. Check console for 403 errors
4. Run access audit to see which APIs are blocked

## Verification Checklist

After fixing all pages:

- [ ] Run access audit - all checks pass
- [ ] Test login/logout flow
- [ ] Test all pages as Admin
- [ ] Test all pages as Doctor
- [ ] Test all pages as Nurse
- [ ] Test all pages as Receptionist
- [ ] Verify unauthorized access is blocked
- [ ] Check browser console - no auth errors
- [ ] Test API calls from each page
- [ ] Verify navbar shows correct items per role

## Summary

**What we fixed:**
1. ✅ Created `/api/user` endpoint for session verification
2. ✅ Created `auth-guard.js` for consistent authentication
3. ✅ Created `access-audit.js` for diagnostics
4. ✅ Updated security.yaml with auth endpoint rules
5. ✅ Defined page access requirements

**What you need to do:**
1. 🔧 Update each HTML page to use `auth-guard.js`
2. 🔧 Replace old auth code with new patterns
3. 🔧 Test each page with access audit tool
4. 🔧 Verify all roles work correctly

**Result:**
- Consistent authentication across all pages
- Server-side session verification
- Clear error messages
- Easy troubleshooting with audit tool
- HIPAA-compliant access control

