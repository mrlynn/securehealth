#!/bin/bash

# SecureHealth Environment Setup Script
# This script helps you securely configure your environment variables

set -e

echo "🔐 SecureHealth Environment Setup"
echo "=================================="
echo ""

# Check if .env already exists
if [ -f ".env" ]; then
    echo "⚠️  .env file already exists!"
    read -p "Do you want to overwrite it? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Setup cancelled."
        exit 0
    fi
fi

# Copy .env.example to .env
echo "📋 Creating .env file from template..."
cp .env.example .env

echo ""
echo "🔧 Please configure your environment variables:"
echo ""

# MongoDB Configuration
echo "📊 MongoDB Configuration:"
read -p "MongoDB URI (mongodb+srv://username:password@cluster.mongodb.net/database): " mongodb_uri
read -p "MongoDB Database Name [securehealth]: " mongodb_db
mongodb_db=${mongodb_db:-securehealth}

# Application Configuration
echo ""
echo "⚙️  Application Configuration:"
read -p "Application Environment [dev]: " app_env
app_env=${app_env:-dev}

read -p "App Secret (generate a random string): " app_secret
if [ -z "$app_secret" ]; then
    app_secret=$(openssl rand -hex 32)
    echo "Generated random app secret: $app_secret"
fi

# JWT Configuration
echo ""
echo "🔑 JWT Configuration:"
read -p "JWT Secret Key (generate a random string): " jwt_secret
if [ -z "$jwt_secret" ]; then
    jwt_secret=$(openssl rand -hex 32)
    echo "Generated random JWT secret: $jwt_secret"
fi

read -p "JWT Passphrase (generate a random string): " jwt_passphrase
if [ -z "$jwt_passphrase" ]; then
    jwt_passphrase=$(openssl rand -hex 16)
    echo "Generated random JWT passphrase: $jwt_passphrase"
fi

# Update .env file
echo ""
echo "💾 Updating .env file..."

# Use sed to replace values in .env file
if [ ! -z "$mongodb_uri" ]; then
    sed -i.bak "s|MONGODB_URI=mongodb+srv://username:password@cluster.mongodb.net/database|MONGODB_URI=$mongodb_uri|g" .env
    sed -i.bak "s|MONGODB_URL=mongodb+srv://username:password@cluster.mongodb.net/database|MONGODB_URL=$mongodb_uri|g" .env
fi

sed -i.bak "s|MONGODB_DB=securehealth|MONGODB_DB=$mongodb_db|g" .env
sed -i.bak "s|APP_ENV=dev|APP_ENV=$app_env|g" .env
sed -i.bak "s|APP_SECRET=your-app-secret-key-here|APP_SECRET=$app_secret|g" .env
sed -i.bak "s|JWT_SECRET_KEY=your-jwt-secret-key-here|JWT_SECRET_KEY=$jwt_secret|g" .env
sed -i.bak "s|JWT_PASSPHRASE=your-jwt-passphrase-here|JWT_PASSPHRASE=$jwt_passphrase|g" .env

# Clean up backup files
rm -f .env.bak

echo ""
echo "✅ Environment configuration complete!"
echo ""
echo "🔒 Security Notes:"
echo "   - Your .env file contains sensitive information"
echo "   - It's already ignored by git (.gitignore)"
echo "   - Never commit .env files to version control"
echo "   - Share .env.example instead of .env"
echo ""
echo "🚀 Next steps:"
echo "   1. Review your .env file: cat .env"
echo "   2. Start the application: docker-compose up -d"
echo "   3. Access the app: http://localhost:8081"
echo ""
echo "📚 For more information, see the documentation:"
echo "   http://localhost:8081/documentation.html"
