# 🚂 Railway Deployment Checklist

Use this checklist to ensure your SecureHealth application deploys successfully on Railway.

## ✅ Pre-Deployment Checklist

### **1. Repository Setup**
- [ ] Code is pushed to GitHub
- [ ] All sensitive data removed from code
- [ ] `.env` files are in `.gitignore`
- [ ] `railway.json` and `nixpacks.toml` are in repository

### **2. MongoDB Atlas Setup**
- [ ] MongoDB Atlas cluster created
- [ ] Database user created with appropriate permissions
- [ ] Network access configured (allow all IPs for development)
- [ ] Connection string obtained and tested

### **3. Environment Variables Prepared**
- [ ] `MONGODB_URI` - Your MongoDB Atlas connection string
- [ ] `MONGODB_URL` - Same as MONGODB_URI
- [ ] `MONGODB_DB` - Database name (e.g., "securehealth")
- [ ] `MONGODB_DISABLED` - Set to "false"
- [ ] `MONGODB_KEY_VAULT_NAMESPACE` - Set to "encryption.__keyVault"
- [ ] `APP_ENV` - Set to "prod"
- [ ] `APP_SECRET` - 32-character random string
- [ ] `SYMFONY_ENV` - Set to "prod"
- [ ] `SYMFONY_DEBUG` - Set to "false"
- [ ] `JWT_SECRET_KEY` - 32-character random string
- [ ] `JWT_PASSPHRASE` - 16-character random string
- [ ] `MONGODB_ENCRYPTION_KEY_PATH` - Set to "/app/docker/encryption.key"

## 🚀 Railway Deployment Steps

### **Step 1: Create Railway Project**
- [ ] Go to [Railway.app](https://railway.app)
- [ ] Click "New Project"
- [ ] Select "Deploy from GitHub repo"
- [ ] Choose your SecureHealth repository
- [ ] Wait for initial deployment to complete

### **Step 2: Configure Environment Variables**
- [ ] Go to "Variables" tab in Railway dashboard
- [ ] Add all environment variables from checklist above
- [ ] Use `railway-env-template.txt` as reference
- [ ] Generate secure secrets using:
  ```bash
  # For 32-character secrets
  openssl rand -hex 16
  
  # For 16-character secrets  
  openssl rand -hex 8
  ```

### **Step 3: Build Configuration**
- [ ] Go to "Build" tab
- [ ] Verify build command: `composer install --no-dev --optimize-autoloader`
- [ ] Verify start command: `php -S 0.0.0.0:$PORT -t public`
- [ ] Ensure PHP version is 8.2 or higher

### **Step 4: Networking Configuration**
- [ ] Go to "Networking" tab
- [ ] Verify port is set to 8080
- [ ] Add custom domain if desired
- [ ] Configure DNS records for custom domain

### **Step 5: Deploy Settings**
- [ ] Go to "Deploy" tab
- [ ] Set production branch (usually "main")
- [ ] Enable auto-deploy
- [ ] Set health check path to "/api/health"

## 🔍 Post-Deployment Verification

### **Application Health Check**
- [ ] Visit `https://your-app.railway.app/api/health`
- [ ] Should return: `{"status":"healthy","timestamp":"...","service":"MongoDB Queryable Encryption Demo","version":"1.0.0"}`
- [ ] If not working, check Railway logs

### **Database Connection Test**
- [ ] Try to access the application frontend
- [ ] Check if patient data loads (if you have test data)
- [ ] Verify MongoDB connection in Railway logs

### **Security Verification**
- [ ] Ensure `APP_ENV=prod` and `SYMFONY_DEBUG=false`
- [ ] Verify HTTPS is working (Railway provides this automatically)
- [ ] Check that sensitive data is not exposed in logs

## 🚨 Troubleshooting Common Issues

### **Build Failures**
- [ ] Check build logs in Railway dashboard
- [ ] Verify `composer.json` is in root directory
- [ ] Ensure all dependencies are available
- [ ] Check PHP version compatibility

### **Runtime Errors**
- [ ] Check application logs in Railway dashboard
- [ ] Verify all environment variables are set correctly
- [ ] Test MongoDB connection string locally
- [ ] Ensure port configuration is correct

### **Database Connection Issues**
- [ ] Verify MongoDB Atlas cluster is running
- [ ] Check database user permissions
- [ ] Verify network access settings
- [ ] Test connection string format

### **Health Check Failures**
- [ ] Verify health check endpoint exists: `/api/health`
- [ ] Check if application is listening on correct port
- [ ] Ensure no firewall blocking requests
- [ ] Verify start command is correct

## 📊 Monitoring Setup

### **Railway Dashboard**
- [ ] Monitor CPU and memory usage
- [ ] Check deployment logs regularly
- [ ] Set up alerts for high resource usage
- [ ] Monitor deployment success/failure rates

### **Application Monitoring**
- [ ] Test all API endpoints
- [ ] Verify encryption/decryption functionality
- [ ] Check audit logging is working
- [ ] Monitor response times

## 🔄 Maintenance Tasks

### **Regular Updates**
- [ ] Keep dependencies updated
- [ ] Monitor security advisories
- [ ] Rotate secrets periodically
- [ ] Backup MongoDB data regularly

### **Performance Optimization**
- [ ] Monitor resource usage
- [ ] Optimize database queries
- [ ] Implement caching if needed
- [ ] Scale resources as needed

## 📞 Support Resources

### **Railway Support**
- [Railway Documentation](https://docs.railway.app/)
- [Railway Discord](https://discord.gg/railway)
- [Railway GitHub](https://github.com/railwayapp)

### **Application Support**
- Check `RAILWAY-DEPLOYMENT.md` for detailed instructions
- Review application logs for specific errors
- Test locally with `docker-compose up` to isolate issues

---

## 🎯 Quick Start Commands

```bash
# Generate secrets for Railway
openssl rand -hex 16  # For APP_SECRET and JWT_SECRET_KEY
openssl rand -hex 8   # For JWT_PASSPHRASE

# Test health endpoint locally
curl http://localhost:8081/api/health

# Test health endpoint on Railway
curl https://your-app.railway.app/api/health
```

**🎉 Once all items are checked, your SecureHealth application should be running successfully on Railway!**
