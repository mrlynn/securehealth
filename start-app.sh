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
echo ""
echo "💡 If you experience 'Session Expired' issues:"
echo "   1. Clear browser localStorage, cookies, and cache"
echo "   2. Use a fresh incognito/private window"
echo "   3. Make sure you're accessing http://localhost:8081"

