#!/bin/bash

# Test Authentication Flow Script
# This script tests the complete authentication flow to ensure it works

set -e

BASE_URL="http://localhost:8081"
COOKIE_FILE="test-cookies.txt"

echo "🔍 Testing Authentication Flow"
echo "================================"

# Clean up any existing cookies
rm -f "$COOKIE_FILE"

echo "1. Testing health endpoint..."
HEALTH_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null "$BASE_URL/api/health")
if [ "$HEALTH_RESPONSE" = "200" ]; then
    echo "✅ Health endpoint working"
else
    echo "❌ Health endpoint failed: $HEALTH_RESPONSE"
    exit 1
fi

echo "2. Testing login..."
LOGIN_RESPONSE=$(curl -s -c "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    -d '{"_username":"receptionist@example.com","_password":"receptionist"}' \
    "$BASE_URL/api/login")

LOGIN_STATUS=$(curl -s -w "%{http_code}" -o /dev/null -c "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    -d '{"_username":"receptionist@example.com","_password":"receptionist"}' \
    "$BASE_URL/api/login")

if [ "$LOGIN_STATUS" = "200" ]; then
    echo "✅ Login successful"
    echo "Response: $LOGIN_RESPONSE"
else
    echo "❌ Login failed: $LOGIN_STATUS"
    echo "Response: $LOGIN_RESPONSE"
    exit 1
fi

echo "3. Testing patients API..."
PATIENTS_RESPONSE=$(curl -s -b "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    "$BASE_URL/api/patients")

PATIENTS_STATUS=$(curl -s -w "%{http_code}" -o /dev/null -b "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    "$BASE_URL/api/patients")

if [ "$PATIENTS_STATUS" = "200" ]; then
    echo "✅ Patients API working"
    PATIENT_COUNT=$(echo "$PATIENTS_RESPONSE" | jq '. | length' 2>/dev/null || echo "unknown")
    echo "Found $PATIENT_COUNT patients"
else
    echo "❌ Patients API failed: $PATIENTS_STATUS"
    echo "Response: $PATIENTS_RESPONSE"
    exit 1
fi

echo "4. Testing session persistence..."
# Test again to ensure session persists
PATIENTS_STATUS2=$(curl -s -w "%{http_code}" -o /dev/null -b "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    "$BASE_URL/api/patients")

if [ "$PATIENTS_STATUS2" = "200" ]; then
    echo "✅ Session persistence working"
else
    echo "❌ Session persistence failed: $PATIENTS_STATUS2"
    exit 1
fi

echo ""
echo "🎉 All tests passed! Authentication flow is working correctly."
echo ""

# Clean up
rm -f "$COOKIE_FILE"

echo "To test in browser:"
echo "1. Open http://localhost:8081/login.html"
echo "2. Login with: receptionist@example.com / receptionist"
echo "3. Should redirect to patients page with data"
