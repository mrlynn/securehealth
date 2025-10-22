# 🚂 Railway Deployment Guide for SecureHealth

This guide will help you deploy your SecureHealth application to Railway with proper configuration.

## 📋 Prerequisites

- Railway account (free tier available)
- GitHub repository with your SecureHealth code
- MongoDB Atlas account (for database)
- Domain name (optional, for custom domain)

## 🚀 Step-by-Step Deployment

### 1. **Connect Your Repository**

1. Go to [Railway.app](https://railway.app)
2. Click "New Project"
3. Select "Deploy from GitHub repo"
4. Choose your SecureHealth repository
5. Railway will automatically detect it's a PHP application

### 2. **Environment Variables Configuration**

In Railway, go to the **Variables** tab and add these environment variables:

#### **Required Variables:**

```bash
# MongoDB Configuration
MONGODB_URI=mongodb+srv://username:password@cluster.mongodb.net/database
MONGODB_URL=mongodb+srv://username:password@cluster.mongodb.net/database
MONGODB_DB=securehealth
MONGODB_DISABLED=false
MONGODB_KEY_VAULT_NAMESPACE=encryption.__keyVault

# Application Configuration
APP_ENV=prod
APP_SECRET=your-32-character-secret-key-here
SYMFONY_ENV=prod
SYMFONY_DEBUG=false

# Security Configuration
JWT_SECRET_KEY=your-32-character-jwt-secret-here
JWT_PASSPHRASE=your-16-character-passphrase-here

# Encryption Configuration
MONGODB_ENCRYPTION_KEY_PATH=/app/docker/encryption.key

# Railway Configuration
PORT=8080
```

#### **How to Generate Secure Secrets:**

```bash
# Generate APP_SECRET (32 characters)
openssl rand -hex 16

# Generate JWT_SECRET_KEY (32 characters)
openssl rand -hex 16

# Generate JWT_PASSPHRASE (16 characters)
openssl rand -hex 8
```

### 3. **Build Configuration**

In the **Build** section, configure:

#### **Build Command:**
```bash
composer install --no-dev --optimize-autoloader
```

#### **Start Command:**
```bash
php -S 0.0.0.0:$PORT -t public
```

#### **Build Settings:**
- **Node Version**: Not needed (PHP application)
- **PHP Version**: 8.2 or higher
- **Composer**: Enabled automatically

### 4. **Networking Configuration**

In the **Networking** section:

#### **Port Configuration:**
- **Port**: `8080` (Railway will set the PORT environment variable)
- **Protocol**: HTTP

#### **Custom Domain (Optional):**
- Add your custom domain (e.g., `securehealth.dev`)
- Railway will provide DNS instructions
- Update your domain's DNS records as instructed

### 5. **Deployment Settings**

In the **Deploy** section:

#### **Branch:**
- **Production Branch**: `main` (or your default branch)
- **Auto Deploy**: Enabled

#### **Health Check:**
- **Path**: `/api/health`
- **Port**: `8080`

## 🔧 Railway-Specific Configuration

### **railway.json** (Optional)

Create a `railway.json` file in your project root:

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "NIXPACKS"
  },
  "deploy": {
    "startCommand": "php -S 0.0.0.0:$PORT -t public",
    "healthcheckPath": "/api/health",
    "healthcheckTimeout": 100,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

### **nixpacks.toml** (Optional)

Create a `nixpacks.toml` file for custom build configuration:

```toml
[phases.setup]
nixPkgs = ["php82", "composer"]

[phases.install]
cmds = ["composer install --no-dev --optimize-autoloader"]

[phases.build]
cmds = ["echo 'Build phase complete'"]

[start]
cmd = "php -S 0.0.0.0:$PORT -t public"
```

## 🗄️ Database Setup

### **MongoDB Atlas Configuration:**

1. **Create MongoDB Atlas Cluster:**
   - Go to [MongoDB Atlas](https://www.mongodb.com/atlas)
   - Create a new cluster
   - Choose a cloud provider and region
   - Select M0 (free tier) for development

2. **Configure Database Access:**
   - Create a database user
   - Set up network access (allow all IPs for development)
   - Get your connection string

3. **Update Environment Variables:**
   - Use your MongoDB Atlas connection string
   - Format: `mongodb+srv://username:password@cluster.mongodb.net/database`

## 🔐 Security Considerations

### **Production Security:**

1. **Environment Variables:**
   - Never commit secrets to git
   - Use Railway's environment variable management
   - Rotate secrets regularly

2. **MongoDB Security:**
   - Use strong passwords
   - Enable IP whitelisting for production
   - Enable MongoDB Atlas encryption at rest

3. **Application Security:**
   - Set `APP_ENV=prod`
   - Set `SYMFONY_DEBUG=false`
   - Use HTTPS (Railway provides this automatically)

## 🚨 Troubleshooting

### **Common Issues:**

#### **Build Failures:**
```bash
# Check build logs in Railway dashboard
# Common fixes:
# 1. Ensure composer.json is in root directory
# 2. Check PHP version compatibility
# 3. Verify all dependencies are available
```

#### **Runtime Errors:**
```bash
# Check application logs in Railway dashboard
# Common fixes:
# 1. Verify all environment variables are set
# 2. Check MongoDB connection string
# 3. Ensure port configuration is correct
```

#### **Database Connection Issues:**
```bash
# Verify MongoDB Atlas configuration:
# 1. Check connection string format
# 2. Verify database user permissions
# 3. Check network access settings
```

### **Debugging Commands:**

```bash
# Check environment variables
echo $MONGODB_URI

# Test database connection
php -r "echo 'Testing MongoDB connection...';"

# Check application status
curl https://your-app.railway.app/api/health
```

## 📊 Monitoring and Logs

### **Railway Dashboard:**
- **Metrics**: CPU, Memory, Network usage
- **Logs**: Application and build logs
- **Deployments**: Deployment history and status

### **Health Checks:**
- **Endpoint**: `/api/health`
- **Expected Response**: `{"status":"ok","timestamp":"...","api_version":"1.0.0"}`

## 🔄 CI/CD Integration

### **Automatic Deployments:**
- Railway automatically deploys on git push
- Configure branch protection rules
- Set up staging environment for testing

### **Environment Promotion:**
- Use Railway's environment promotion feature
- Test in staging before production
- Use feature flags for gradual rollouts

## 💰 Cost Optimization

### **Free Tier Limits:**
- 500 hours of usage per month
- 1GB RAM limit
- 1GB disk space
- 1 concurrent deployment

### **Upgrade Considerations:**
- Monitor usage in Railway dashboard
- Upgrade when approaching limits
- Consider reserved instances for production

## 📚 Additional Resources

- [Railway Documentation](https://docs.railway.app/)
- [MongoDB Atlas Documentation](https://docs.atlas.mongodb.com/)
- [Symfony Deployment Guide](https://symfony.com/doc/current/deployment.html)
- [PHP on Railway](https://docs.railway.app/guides/php)

## 🆘 Support

If you encounter issues:

1. **Check Railway Logs**: Dashboard → Your Project → Logs
2. **Verify Environment Variables**: Dashboard → Your Project → Variables
3. **Test Locally**: Ensure app works with `docker-compose up`
4. **Check MongoDB Atlas**: Verify cluster status and connectivity

---

**🎉 Your SecureHealth application should now be running on Railway!**

Access your application at: `https://your-app-name.railway.app`
