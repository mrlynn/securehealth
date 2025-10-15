# Medical Knowledge Base Access Guide

## Issue Summary

The medical knowledge search page is working correctly, but there's a port mismatch:
- **Expected**: The application should run on `localhost:8000` (PHP built-in server)
- **Browser Cache**: Your browser might be redirecting to `localhost:8081` (Docker nginx port)

## ✅ Fixed Issues

### 1. JavaScript Errors
- ✅ Fixed illegal return statement
- ✅ Fixed missing function definitions
- ✅ Added proper authentication checks
- ✅ All search functions now work correctly

### 2. Authentication
- ✅ Updated to use correct localStorage key (`securehealth_user`)
- ✅ Changed to session-based authentication (`credentials: 'include'`)
- ✅ Created `MedicalKnowledgeVoter` for proper role-based access control

## 🚀 How to Access the Medical Knowledge Base

### Step 1: Clear Browser Cache
The browser might be caching the redirect to port 8081. To fix this:

1. **Chrome/Edge**:
   - Press `Cmd+Shift+Delete` (Mac) or `Ctrl+Shift+Delete` (Windows)
   - Select "Cached images and files"
   - Click "Clear data"

2. **Firefox**:
   - Press `Cmd+Shift+Delete` (Mac) or `Ctrl+Shift+Delete` (Windows)
   - Select "Cookies" and "Cache"
   - Click "Clear Now"

3. **Safari**:
   - Press `Cmd+Option+E` to empty caches
   - Or go to Develop > Empty Caches

### Step 2: Hard Refresh
After clearing cache, do a hard refresh:
- **Mac**: `Cmd+Shift+R`
- **Windows/Linux**: `Ctrl+Shift+R`

### Step 3: Access the Application
1. Open a **new incognito/private window**
2. Navigate to: `http://localhost:8000/login.html`
3. Login as doctor: `doctor@example.com` / `doctor`
4. Navigate to: `http://localhost:8000/medical-knowledge-search.html`

## 🔧 Alternative: Use Docker (Recommended for Production)

If you want to use port 8081 (the Docker nginx setup), run:

```bash
docker-compose up
```

Then access:
- Application: `http://localhost:8081`
- Medical Knowledge: `http://localhost:8081/medical-knowledge-search.html`

## 📝 Current Server Status

The PHP built-in server is running on port 8000:
```bash
php -S localhost:8000 -t public public/router.php
```

To stop it:
```bash
pkill -f "php -S"
```

## 🔐 Access Control Matrix

| Feature | Doctor | Nurse | Admin |
|---------|--------|-------|-------|
| Semantic Search | ✅ | ❌ | ✅ |
| Clinical Decision Support | ✅ | ❌ | ❌ |
| Drug Interactions | ✅ | ✅ | ❌ |
| Treatment Guidelines | ✅ | ❌ | ❌ |
| Diagnostic Criteria | ✅ | ❌ | ❌ |
| View Knowledge | ✅ | ✅ | ❌ |
| Create Knowledge | ✅ | ❌ | ✅ |
| Import Knowledge | ❌ | ❌ | ✅ |
| View Statistics | ✅ | ❌ | ✅ |

## 🐛 Troubleshooting

### Error: "500 Internal Server Error"
- **Cause**: API endpoint issue or authentication problem
- **Fix**: Check server logs and ensure you're logged in

### Error: "Unexpected token '<', \"<!-- The c\"... is not valid JSON"
- **Cause**: Browser is getting HTML error page instead of JSON
- **Fix**: Check API endpoint URL and ensure server is running

### Error: "Access denied"
- **Cause**: User doesn't have required role
- **Fix**: Login as a doctor or admin user

### Error: "searchDrugInteractions is not defined"
- **Cause**: JavaScript execution stopped due to authentication check
- **Fix**: This has been fixed - clear browser cache

## 📊 Sample Data

The medical knowledge base has been seeded with 50 sample entries covering:
- Clinical guidelines
- Drug databases
- Research papers
- Treatment protocols
- Diagnostic criteria

To reseed:
```bash
php bin/console app:seed-medical-knowledge --count=50
```

## 🔗 Related Documentation

- [Medical Knowledge Base Implementation](docs/MEDICAL_KNOWLEDGE_BASE.md)
- [API Documentation](docs/api/index.md)
- [Security Overview](docs/SECURITY.md)

