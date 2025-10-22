# Installation Guide

This guide will walk you through setting up SecureHealth in your development environment. Follow these steps to get the system up and running.

## Prerequisites

Before you begin, make sure you have the following installed:

- **Docker Desktop 4.15+**
- **Git**
- **MongoDB Atlas account** (free tier is fine for development)
- **A code editor** (VS Code, PhpStorm, etc.)

## Step 1: Clone the Repository

```bash
git clone https://github.com/mrlynn/securehealth.git
cd securehealth
```

## Step 2: Set Up MongoDB Atlas

1. **Create a MongoDB Atlas Cluster**
   - Log into [cloud.mongodb.com](https://cloud.mongodb.com)
   - Create a new cluster (M0 tier is fine for development)
   - Choose the region closest to you

2. **Configure Database Access**
   - Create a new database user with password authentication
   - Give the user read/write access to any database

3. **Configure Network Access**
   - Add your IP address to the IP access list
   - For development, you can allow access from anywhere (0.0.0.0/0)

4. **Enable Queryable Encryption**
   - Go to your cluster's "Security" settings
   - Navigate to "Advanced Settings"
   - Enable "Queryable Encryption"

5. **Get Connection String**
   - Click "Connect" on your cluster
   - Select "Connect your application"
   - Copy the connection string

## Step 3: Generate Encryption Key

```bash
# Generate a 96-byte random key
openssl rand -base64 96 > docker/encryption.key

# Ensure the key is not checked into Git
echo "docker/encryption.key" >> .gitignore
```

## Step 4: Configure Environment Variables

Copy the example environment file and update it with your settings:

```bash
cp .env.example .env
```

Edit the `.env` file and update the following variables:

```
# MongoDB Connection
MONGODB_URL=mongodb+srv://username:password@your-cluster.mongodb.net/?retryWrites=true&w=majority
MONGODB_DB=securehealth
MONGODB_KEY_VAULT_NAMESPACE=encryption.__keyVault

# Encryption key path
MONGODB_ENCRYPTION_KEY_PATH=/var/www/html/docker/encryption.key

# App settings
APP_ENV=dev
APP_SECRET=your-app-secret
JWT_SECRET_KEY=your-jwt-secret
```

## Step 5: Start Docker Environment

```bash
# Build and start the containers
docker-compose up -d

# Install PHP dependencies
docker-compose exec php composer install
```

## Step 6: Initialize the Database

```bash
# Create database schema
docker-compose exec php bin/console doctrine:mongodb:schema:create

# Load fixture data (optional)
docker-compose exec php bin/console doctrine:mongodb:fixtures:load
```

## Step 7: Verify Installation

1. **Check API Status**
   - Open your browser and navigate to http://localhost:8081/api/health
   - You should see a JSON response with status "ok"

2. **Test Authentication**
   ```bash
   # Try logging in with a test user
   curl -X POST http://localhost:8081/api/login \
     -H "Content-Type: application/json" \
     -d '{"_username":"doctor@example.com","_password":"doctor"}'
   ```
   - You should receive a JWT token in the response

## Step 8: Access the Application

- The API is available at http://localhost:8081/api
- The web interface is available at http://localhost:8081
- The documentation is available at http://localhost:8081/docs

## Test Users

The system is pre-configured with the following test users:

| Role | Username | Password |
|------|----------|----------|
| Doctor | doctor@example.com | doctor |
| Nurse | nurse@example.com | nurse |
| Receptionist | receptionist@example.com | receptionist |

## Troubleshooting

### MongoDB Connection Issues

If you have trouble connecting to MongoDB:

1. Verify your IP is whitelisted in MongoDB Atlas
2. Check that your connection string is correct
3. Ensure MongoDB is accessible from your Docker containers

### Docker Issues

If you encounter Docker-related problems:

1. Make sure Docker is running
2. Try restarting the containers: `docker-compose restart`
3. Rebuild the containers: `docker-compose up -d --build`

### Encryption Issues

If encryption doesn't work correctly:

1. Verify the encryption key file exists
2. Check that the key path in `.env` is correct
3. Ensure the key has the right format

## Next Steps

Once you have SecureHealth installed and running, proceed to the [Quick Start](quick-start) guide to learn how to use and extend the application.