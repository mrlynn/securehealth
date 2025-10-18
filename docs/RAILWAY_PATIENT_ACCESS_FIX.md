# 🚨 Railway Patient Records Access Fix

## Issue Summary
Patient records are not accessible in the deployed Railway version, likely due to deployment configuration issues.

## 🔍 Diagnostic Steps

### Step 1: Test Basic Connectivity
```bash
# Test health endpoint
curl https://your-app-name.railway.app/api/health

# Test debug endpoint (comprehensive diagnostics)
curl https://your-app-name.railway.app/api/debug/railway

# Test MongoDB connection specifically
curl https://your-app-name.railway.app/api/debug/mongodb

# Test authentication system
curl https://your-app-name.railway.app/api/debug/auth
```

### Step 2: Check Railway Environment Variables
In Railway dashboard, verify these variables are set:

**Required Variables:**
```bash
MONGODB_URI=mongodb+srv://username:password@cluster.mongodb.net/database
MONGODB_URL=mongodb+srv://username:password@cluster.mongodb.net/database
MONGODB_DB=securehealth
MONGODB_DISABLED=false
MONGODB_KEY_VAULT_NAMESPACE=encryption.__keyVault
APP_ENV=prod
APP_SECRET=your-32-character-secret-key-here
SYMFONY_ENV=prod
SYMFONY_DEBUG=false
JWT_SECRET_KEY=your-32-character-jwt-secret-here
JWT_PASSPHRASE=your-16-character-passphrase-here
MONGODB_ENCRYPTION_KEY_PATH=/app/docker/encryption.key
PORT=8080
```

### Step 3: Check Railway Logs
1. Go to Railway dashboard
2. Click on your project
3. Go to "Deployments" tab
4. Click on the latest deployment
5. Check "Build Logs" and "Deploy Logs" for errors

## 🔧 Common Fixes

### Fix 1: Environment Variables Not Set
**Problem:** Missing or incorrect environment variables
**Solution:**
1. Go to Railway dashboard → Variables tab
2. Add all required variables from the list above
3. Use the `railway-env-template.txt` file as reference
4. Redeploy the application

### Fix 2: MongoDB Connection Issues
**Problem:** Cannot connect to MongoDB Atlas
**Solution:**
1. Verify MongoDB Atlas cluster is running
2. Check network access settings (allow all IPs for development)
3. Verify database user permissions
4. Test connection string format: `mongodb+srv://username:password@cluster.mongodb.net/database`

### Fix 3: Port Configuration Issues
**Problem:** Application not listening on correct port
**Solution:**
1. Ensure `PORT=8080` is set in Railway variables
2. The application should use `0.0.0.0:$PORT` (not localhost)
3. Check that the start command is: `php -S 0.0.0.0:$PORT -t public public/router.php`

### Fix 4: Encryption Key Issues
**Problem:** MongoDB encryption not working
**Solution:**
1. Ensure `MONGODB_ENCRYPTION_KEY_PATH=/app/docker/encryption.key` is set
2. The encryption key file should be included in the Docker image
3. Check that the key file exists and is readable

### Fix 5: Session/Authentication Issues
**Problem:** Users cannot authenticate
**Solution:**
1. Check that `APP_SECRET` is set and is 32 characters long
2. Verify session configuration in `public/router.php`
3. Ensure session save path is writable: `/app/var/cache/sessions`

## 🚀 Deployment Steps

### 1. Update Configuration Files
The following files have been updated:
- `railway.json` - Added proper start command and health check timeout
- `Dockerfile` - Fixed port configuration
- `src/Controller/Api/DebugController.php` - Added comprehensive debugging endpoints

### 2. Deploy to Railway
```bash
# Commit changes
git add .
git commit -m "Fix Railway deployment configuration for patient records access"
git push origin main

# Railway will automatically deploy
```

### 3. Verify Deployment
After deployment, test these endpoints:

```bash
# 1. Health check
curl https://your-app-name.railway.app/api/health

# 2. Railway debug (comprehensive diagnostics)
curl https://your-app-name.railway.app/api/debug/railway

# 3. MongoDB connection test
curl https://your-app-name.railway.app/api/debug/mongodb

# 4. Authentication test
curl https://your-app-name.railway.app/api/debug/auth
```

## 🔍 Debugging Endpoints

### `/api/debug/railway`
Comprehensive Railway deployment diagnostics including:
- Environment variables status
- MongoDB connection status
- Encryption service status
- Database collections and counts
- Server configuration

### `/api/debug/mongodb`
MongoDB-specific connection test:
- Database name and collections
- Patient collection count
- Connection string verification

### `/api/debug/auth`
Authentication system test:
- User authentication status
- Session information
- Role-based permissions

### `/api/debug/patient-access`
Patient data access test (requires authentication):
- User information
- Patient data retrieval
- Permission verification

## 🚨 Troubleshooting Checklist

### Railway Dashboard Checks
- [ ] Environment variables are set correctly
- [ ] Build logs show no errors
- [ ] Deploy logs show successful startup
- [ ] Health check is passing
- [ ] Application is receiving traffic

### MongoDB Atlas Checks
- [ ] Cluster is running and accessible
- [ ] Database user has proper permissions
- [ ] Network access allows all IPs (for development)
- [ ] Connection string is correct format

### Application Checks
- [ ] Health endpoint returns 200 OK
- [ ] Debug endpoints return valid JSON
- [ ] MongoDB connection is successful
- [ ] Encryption service is enabled
- [ ] Session management is working

## 📞 Next Steps

1. **Test the debug endpoints** to identify the specific issue
2. **Check Railway logs** for any error messages
3. **Verify environment variables** are set correctly
4. **Test MongoDB connection** using the debug endpoint
5. **Check authentication** if patient access requires login

## 🎯 Expected Results

After applying these fixes, you should see:

1. **Health Check**: `{"status":"healthy","timestamp":"...","service":"MongoDB Queryable Encryption Demo","version":"1.0.0"}`
2. **Railway Debug**: All environment variables set, MongoDB connected, encryption enabled
3. **MongoDB Debug**: Database connected, collections listed, patient count > 0
4. **Auth Debug**: Authentication system working (when logged in)
5. **Patient Access**: Patient records accessible through the API

---

**🔧 If issues persist, check the Railway logs and use the debug endpoints to identify the specific problem.**
