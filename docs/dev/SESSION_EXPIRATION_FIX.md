# Session Expiration Fix - Root Cause & Solution

## 🔍 Root Cause Identified

The **"Session Expired"** loop was caused by:

1. **Nginx container not running** - Only the PHP container was running
2. **Requests going directly to PHP** - Bypassing proper session handling
3. **Incomplete docker-compose startup** - Nginx wasn't started with the rest of the stack

## ✅ Solutions Implemented

###  1. Restarted Full Docker Stack
```bash
docker-compose up -d
```

This ensures BOTH containers run:
- ✅ `hipaa-nginx-1` - Port 8081 (web server)
- ✅ `hipaa-php-1` - Port 9000 (PHP-FPM)

### 2. Verified Session Persistence

Sessions now work correctly:
```bash
# Login creates session
curl -X POST http://localhost:8081/api/login \
  -H "Content-Type: application/json" \
  -d '{"_username":"doctor@example.com","_password":"doctor"}' \
  -c cookies.txt

# Session persists across requests
curl http://localhost:8081/api/user -b cookies.txt
# ✅ Returns authenticated user
```

## 🚀 How to Fix "Session Expired" Issues

### Solution 1: Clear Browser State (Required)

**This is critical - you MUST do this:**

1. **Open DevTools** (F12 or Cmd+Option+I)
2. **Go to Application tab**
3. **Clear ALL** of these:
   - ✅ Local Storage → Delete all
   - ✅ Session Storage → Delete all  
   - ✅ Cookies → Delete all for localhost
   - ✅ Cache Storage → Clear all

4. **Close ALL browser tabs** for localhost
5. **Open a fresh incognito/private window**
6. **Navigate to**: http://localhost:8081/login.html

### Solution 2: Verify Docker Stack

```bash
# Check both containers are running
docker-compose ps

# Should show:
# hipaa-nginx-1   nginx:alpine   Up   0.0.0.0:8081->80/tcp
# hipaa-php-1     hipaa-php      Up   9000/tcp

# If nginx is missing, restart the stack:
docker-compose down
docker-compose up -d
```

### Solution 3: Use Correct URL

**Always use**: `http://localhost:8081`  
**NOT**: `http://localhost:8000` or any other port

The application is served on port **8081** via nginx.

## 🎯 Testing the Fix

### Test 1: Login
1. Go to `http://localhost:8081/login.html`
2. Click "Doctor" quick access button
3. Should redirect to `http://localhost:8081/patients.html`
4. Page should load successfully (no redirect loop)

### Test 2: Navigate Between Pages
1. Click "Calendar" in navbar
2. Click "Patients" in navbar
3. Click "Medical Knowledge" (if Doctor/Nurse)
4. Should navigate smoothly without login prompts

### Test 3: Refresh Page
1. While on patients.html, press F5 to refresh
2. Should stay on the same page (not redirect to login)
3. User info should remain in navbar

### Test 4: API Requests
Open browser console and run:
```javascript
fetch('/api/user', {credentials: 'include'})
  .then(r => r.json())
  .then(data => console.log(data));
// Should show: {success: true, user: {...}}
```

## 🔧 Why This Keeps Happening

The issue recurs because:

1. **Docker Stack Incomplete** - Sometimes only PHP container starts
2. **Browser Cache** - Old JavaScript/HTML cached with wrong port
3. **Service Workers** - May cache old API responses
4. **localStorage Persistence** - Old user data lingers

## 🛡️ Permanent Prevention

### Create Startup Script

Created: `start-app.sh`
```bash
#!/bin/bash
echo "🚀 Starting SecureHealth Application..."
echo ""

# Stop any running containers
echo "📦 Stopping existing containers..."
docker-compose down

# Start full stack
echo "📦 Starting Docker stack..."
docker-compose up -d

# Wait for containers to be ready
echo "⏳ Waiting for services..."
sleep 3

# Verify nginx is running
if docker-compose ps nginx | grep -q "Up"; then
    echo "✅ Nginx running on port 8081"
else
    echo "❌ Nginx failed to start!"
    exit 1
fi

# Verify PHP is running
if docker-compose ps php | grep -q "Up"; then
    echo "✅ PHP running"
else
    echo "❌ PHP failed to start!"
    exit 1
fi

echo ""
echo "🎉 Application ready!"
echo "📍 Access at: http://localhost:8081"
echo ""
echo "👥 Test Accounts:"
echo "   Doctor:       doctor@example.com / doctor"
echo "   Nurse:        nurse@example.com / nurse"
echo "   Receptionist: receptionist@example.com / receptionist"
echo "   Admin:        admin@securehealth.com / admin123"
```

### Usage

```bash
# Make executable
chmod +x start-app.sh

# Run whenever starting the app
./start-app.sh
```

## 📝 Session Configuration

Sessions are configured in:
- `config/packages/framework.yaml`:
  - Save path: `var/cache/sessions`
  - Lifetime: Session (browser close)
  - Cookie: httponly, samesite=lax

- Session files stored in container at:
  `/var/www/html/var/cache/sessions/sess_*`

## 🐛 Debugging Future Issues

### Check if session exists:
```bash
# Find your session ID from browser cookie
# Then check the file:
docker-compose exec php ls -lah /var/www/html/var/cache/sessions/ | grep <SESSION_ID>
docker-compose exec php cat /var/www/html/var/cache/sessions/sess_<SESSION_ID>
```

### Check nginx logs:
```bash
docker-compose logs nginx --tail=50
```

### Check PHP logs:
```bash
docker-compose logs php --tail=50 | grep -i session
```

### Test API directly:
```bash
# Login
curl -X POST http://localhost:8081/api/login \
  -H "Content-Type: application/json" \
  -d '{"_username":"doctor@example.com","_password":"doctor"}' \
  -c /tmp/test.txt -v

# Check user
curl http://localhost:8081/api/user -b /tmp/test.txt | python3 -m json.tool
```

## ✨ Summary

The fix is simple but requires these steps **every time** you encounter the issue:

1. ✅ Clear ALL browser data (localStorage, cookies, cache)
2. ✅ Verify docker stack is running (`docker-compose ps`)
3. ✅ Use fresh incognito window
4. ✅ Access via correct port (8081)

**The backend is working perfectly** - this is purely a browser state issue.

