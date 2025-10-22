# 🐳 Railway Dockerfile Deployment Guide

Your Railway configuration is using Dockerfile-based deployment. Here's how to configure it properly for SecureHealth.

## 🔧 Current Railway Configuration

Your `railway.json` shows:
```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile"
  },
  "deploy": {
    "runtime": "V2",
    "numReplicas": 1,
    "sleepApplication": false,
    "useLegacyStacker": false,
    "multiRegionConfig": {
      "us-east4-eqdc4a": {
        "numReplicas": 1
      }
    },
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

## ✅ Updated Dockerfile for Railway

The Dockerfile has been updated to work with Railway's Dockerfile deployment:

```dockerfile
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libssl-dev \
    pkg-config \
    libcrypto++-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Install MongoDB PHP extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log \
    && chmod -R 755 var \
    && chmod -R 755 public

# Expose port (Railway will set PORT environment variable)
EXPOSE 8080

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

## 🔑 Environment Variables for Railway

In your Railway dashboard, go to **Variables** tab and add:

### **Required Variables:**
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

# Railway Configuration (automatically set by Railway)
PORT=8080
```

### **Generate Secure Secrets:**
```bash
# Generate 32-character secrets
openssl rand -hex 16

# Generate 16-character secrets
openssl rand -hex 8
```

## 🚀 Deployment Steps

### **1. Update Railway Configuration**
Your current `railway.json` is good, but you can add health check configuration:

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile"
  },
  "deploy": {
    "runtime": "V2",
    "numReplicas": 1,
    "sleepApplication": false,
    "useLegacyStacker": false,
    "multiRegionConfig": {
      "us-east4-eqdc4a": {
        "numReplicas": 1
      }
    },
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10,
    "healthcheckPath": "/api/health",
    "healthcheckTimeout": 100
  }
}
```

### **2. Set Environment Variables**
1. Go to Railway dashboard
2. Click on your project
3. Go to **Variables** tab
4. Add all the environment variables listed above
5. Use your actual MongoDB Atlas connection string
6. Generate secure secrets for APP_SECRET, JWT_SECRET_KEY, and JWT_PASSPHRASE

### **3. Deploy**
1. Push your code to GitHub
2. Railway will automatically build and deploy
3. Monitor the build logs in Railway dashboard

## 🔍 Verification

### **Health Check**
After deployment, test the health endpoint:
```bash
curl https://your-app.railway.app/api/health
```

Expected response:
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01 12:00:00",
  "service": "MongoDB Queryable Encryption Demo",
  "version": "1.0.0"
}
```

### **Application Access**
- **Frontend**: `https://your-app.railway.app`
- **API**: `https://your-app.railway.app/api`
- **Documentation**: `https://your-app.railway.app/documentation.html`

## 🚨 Troubleshooting

### **Build Issues**
- Check build logs in Railway dashboard
- Ensure Dockerfile is in the root directory
- Verify all dependencies are available

### **Runtime Issues**
- Check application logs in Railway dashboard
- Verify all environment variables are set
- Test MongoDB connection string

### **Port Issues**
- Ensure Dockerfile exposes port 8080
- Verify CMD uses `0.0.0.0:8080`
- Check Railway's PORT environment variable

## 📊 Monitoring

### **Railway Dashboard**
- Monitor CPU and memory usage
- Check deployment logs
- View build and runtime logs

### **Application Logs**
- Access logs through Railway dashboard
- Check for PHP errors
- Monitor MongoDB connection status

## 🔄 Updates

### **Code Updates**
1. Push changes to GitHub
2. Railway automatically rebuilds and redeploys
3. Monitor deployment status

### **Environment Variable Updates**
1. Update variables in Railway dashboard
2. Redeploy the application
3. Verify changes are applied

## 🎯 Key Differences from Nixpacks

### **Dockerfile Deployment:**
- Uses custom Dockerfile
- More control over build process
- Better for complex applications
- Requires Docker knowledge

### **Nixpacks Deployment:**
- Automatic build detection
- Simpler setup
- Less control over build process
- Good for simple applications

## 📚 Additional Resources

- [Railway Dockerfile Documentation](https://docs.railway.app/deploy/dockerfile)
- [PHP on Railway](https://docs.railway.app/guides/php)
- [Environment Variables](https://docs.railway.app/deploy/environment-variables)

---

**🎉 Your SecureHealth application should now deploy successfully on Railway using Dockerfile!**

The key changes made:
1. ✅ Updated Dockerfile to use PHP CLI instead of PHP-FPM
2. ✅ Added proper port configuration (8080)
3. ✅ Optimized for Railway's Dockerfile deployment
4. ✅ Added health check configuration
5. ✅ Updated environment variables template
