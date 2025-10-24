#!/bin/bash
# Test suite for SecureHealth features
# Run this before and after every change

echo "🧪 SecureHealth Feature Test Suite"
echo "=================================="

# Function to test API endpoint
test_api() {
    local name="$1"
    local method="$2"
    local url="$3"
    local data="$4"
    local expected="$5"
    
    echo -n "Testing $name... "
    
    # Extract cookie value from session file
    cookie_value=$(cat /tmp/test-session.txt | tail -1 | awk '{print $NF}')
    cookie_header="PHPSESSID=$cookie_value"
    
    if [ "$method" = "POST" ]; then
        result=$(curl -s -X POST "$url" \
            -H "Content-Type: application/json" \
            -H "Cookie: $cookie_header" \
            -d "$data")
    else
        result=$(curl -s "$url" -H "Cookie: $cookie_header")
    fi
    
    if echo "$result" | grep -q "$expected"; then
        echo "✅ PASS"
        return 0
    else
        echo "❌ FAIL"
        echo "   Response: $result"
        return 1
    fi
}

# Clean up any existing session
rm -f /tmp/test-session.txt

# Login first to get session
echo "🔐 Logging in..."
login_result=$(curl -s -X POST http://localhost:8081/api/login \
    -H "Content-Type: application/json" \
    -c /tmp/test-session.txt \
    -d '{"_username":"doctor@example.com","_password":"doctor"}')

if echo "$login_result" | grep -q "success"; then
    echo "✅ Login successful"
else
    echo "❌ Login failed - cannot continue tests"
    echo "   Response: $login_result"
    exit 1
fi

echo ""
echo "🧪 Running feature tests..."
echo ""

# Test all features
failed_tests=0

test_api "Session persistence" "GET" "http://localhost:8081/api/user" "" "success" || ((failed_tests++))
test_api "Patient list" "GET" "http://localhost:8081/api/patients" "" "success" || ((failed_tests++))
test_api "Add note" "POST" "http://localhost:8081/api/patients/507f1f77bcf86cd799439011/notes" '{"content":"Test note"}' "success" || ((failed_tests++))

echo ""
echo "🎯 Test Results:"
if [ $failed_tests -eq 0 ]; then
    echo "✅ ALL TESTS PASSED - Safe to proceed!"
    exit 0
else
    echo "❌ $failed_tests TEST(S) FAILED - DO NOT PROCEED!"
    echo "💡 Fix the failing tests before making any changes"
    exit 1
fi
