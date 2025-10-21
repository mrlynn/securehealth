#!/bin/bash

# Test script for SecureHealth Railway deployment
# Run this script to verify the deployment is working

echo "🧪 Testing SecureHealth Railway Deployment"
echo "=========================================="

BASE_URL="https://securehealth.dev"

# Test 1: Health endpoint
echo ""
echo "1. Testing health endpoint..."
curl -s -o /dev/null -w "Health endpoint: %{http_code}\n" "$BASE_URL/api/health"

# Test 2: Login page accessibility
echo ""
echo "2. Testing login page accessibility..."
curl -s -o /dev/null -w "Login page: %{http_code}\n" "$BASE_URL/login.html"

# Test 3: API login with correct credentials
echo ""
echo "3. Testing API login..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"doctor@example.com","password":"doctor"}')

echo "Login response: $LOGIN_RESPONSE"

# Check if login was successful
if echo "$LOGIN_RESPONSE" | grep -q '"success":true'; then
    echo "✅ Login test PASSED"
else
    echo "❌ Login test FAILED"
fi

# Test 4: API login with incorrect credentials
echo ""
echo "4. Testing API login with incorrect credentials..."
FAILED_LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"doctor@example.com","password":"wrongpassword"}')

echo "Failed login response: $FAILED_LOGIN_RESPONSE"

# Check if failed login was handled correctly
if echo "$FAILED_LOGIN_RESPONSE" | grep -q '"success":false'; then
    echo "✅ Failed login test PASSED"
else
    echo "❌ Failed login test FAILED"
fi

# Test 5: Static file serving
echo ""
echo "5. Testing static file serving..."
curl -s -o /dev/null -w "Static files: %{http_code}\n" "$BASE_URL/assets/css/bootstrap.min.css"

echo ""
echo "🎯 Deployment test completed!"
echo "If all tests show 200 status codes and login works, deployment is successful."
