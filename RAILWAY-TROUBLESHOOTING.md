# 🚨 Railway Deployment Troubleshooting

## Issue: Dockerfile Build Failure

**Error:** `ERROR: failed to build: failed to solve: failed to compute cache key: failed to calculate checksum of ref 9ns214ijsjbha93i0p7fp9tjz::nrqyli4k2qpqg4bha2cai9yf0: "/composer.json": not found`

## 🔧 Solutions Applied

### **1. Created Simplified Dockerfile**

Created `Dockerfile.railway` with a simpler approach:

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

# Copy all files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log \
    && chmod -R 755 var \
    && chmod -R 755 public

# Expose port
EXPOSE 8080

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

### **2. Updated Railway Configuration**

Updated `railway.json` to use the new Dockerfile:

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile.railway"
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

### **3. Created .dockerignore**

Added `.dockerignore` to exclude unnecessary files:

```
# Git
.git
.gitignore

# Environment files
.env
.env.local
.env.test
.env.prod

# Documentation
docs/
*.md
!README.md

# Development files
tests/
lab/
scripts/
docker-compose.yml
Dockerfile
.dockerignore

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db

# Logs
var/log/*
!var/log/.gitkeep

# Cache
var/cache/*
!var/cache/.gitkeep

# Node modules (if any)
node_modules/
npm-debug.log*

# Composer
vendor/
composer.lock

# Temporary files
*.tmp
*.temp
```

## 🚀 Next Steps

### **1. Push Changes to GitHub**
```bash
git add .
git commit -m "Fix Railway deployment with simplified Dockerfile"
git push origin main
```

### **2. Monitor Railway Deployment**
1. Go to Railway dashboard
2. Check build logs
3. Monitor deployment status

### **3. Verify Environment Variables**
Ensure all required environment variables are set in Railway:

```bash
# Required variables:
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

## 🔍 Alternative Solutions

### **Option 1: Use Nixpacks Instead**

If Dockerfile continues to cause issues, switch to Nixpacks:

1. Delete `railway.json` or change builder to `NIXPACKS`
2. Railway will automatically detect PHP and use Nixpacks
3. Add `nixpacks.toml` for custom configuration

### **Option 2: Use Railway's PHP Template**

1. Create a new Railway project
2. Select "Deploy from GitHub repo"
3. Choose "PHP" template
4. Railway will automatically configure for PHP

### **Option 3: Manual Build Commands**

Add build commands in Railway dashboard:

1. Go to **Build** tab
2. Set **Build Command**: `composer install --no-dev --optimize-autoloader`
3. Set **Start Command**: `php -S 0.0.0.0:$PORT -t public`

## 🚨 Common Issues and Solutions

### **Issue: composer.json not found**
- **Solution**: Ensure `composer.json` is in the root directory
- **Check**: Verify file exists in GitHub repository

### **Issue: MongoDB extension not found**
- **Solution**: The Dockerfile installs MongoDB extension
- **Check**: Verify `pecl install mongodb` succeeds in build logs

### **Issue: Port binding errors**
- **Solution**: Use `0.0.0.0:8080` instead of `localhost:8080`
- **Check**: Ensure `EXPOSE 8080` is in Dockerfile

### **Issue: Environment variables not loaded**
- **Solution**: Set all variables in Railway dashboard
- **Check**: Verify variable names match exactly

## 📊 Debugging Commands

### **Local Testing**
```bash
# Test Dockerfile locally
docker build -f Dockerfile.railway -t securehealth .

# Run container locally
docker run -p 8080:8080 -e MONGODB_URI="your-uri" securehealth

# Test health endpoint
curl http://localhost:8080/api/health
```

### **Railway Logs**
```bash
# Check build logs in Railway dashboard
# Look for specific error messages
# Verify all steps complete successfully
```

## 🎯 Success Indicators

### **Build Success**
- All Dockerfile steps complete without errors
- Composer install succeeds
- PHP extensions install correctly
- Container starts successfully

### **Runtime Success**
- Health check passes: `GET /api/health` returns 200
- Application responds to requests
- MongoDB connection works
- No PHP errors in logs

## 📞 Support Resources

- [Railway Documentation](https://docs.railway.app/)
- [Railway Discord](https://discord.gg/railway)
- [PHP on Railway](https://docs.railway.app/guides/php)
- [Dockerfile Best Practices](https://docs.railway.app/deploy/dockerfile)

---

**🎉 The simplified Dockerfile should resolve the build issues and allow successful deployment on Railway!**
