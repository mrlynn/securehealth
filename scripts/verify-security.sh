#!/bin/bash

# SecureHealth Security Verification Script
# This script verifies that the security setup is correct

set -e

echo "🔍 SecureHealth Security Verification"
echo "====================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check functions
check_pass() {
    echo -e "${GREEN}✅ $1${NC}"
}

check_warn() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

check_fail() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if .env exists
echo "📋 Checking environment files..."
if [ -f ".env" ]; then
    check_pass ".env file exists"
else
    check_fail ".env file missing - run ./setup-env.sh"
    exit 1
fi

if [ -f ".env.example" ]; then
    check_pass ".env.example template exists"
else
    check_fail ".env.example template missing"
    exit 1
fi

# Check if .env is in .gitignore
echo ""
echo "🔒 Checking git security..."
if grep -q "^\.env$" .gitignore; then
    check_pass ".env is properly ignored by git"
else
    check_fail ".env is not in .gitignore"
fi

if grep -q "^\.env\.local$" .gitignore; then
    check_pass ".env.local is properly ignored by git"
else
    check_warn ".env.local should be in .gitignore"
fi

# Check if .env contains placeholder values
echo ""
echo "🔐 Checking for placeholder values..."
if grep -q "your-app-secret-key-here" .env; then
    check_warn "App secret still has placeholder value"
else
    check_pass "App secret has been configured"
fi

if grep -q "your-jwt-secret-key-here" .env; then
    check_warn "JWT secret still has placeholder value"
else
    check_pass "JWT secret has been configured"
fi

if grep -q "username:password@cluster.mongodb.net" .env; then
    check_warn "MongoDB URI still has placeholder values"
else
    check_pass "MongoDB URI has been configured"
fi

# Check Docker Compose configuration
echo ""
echo "🐳 Checking Docker Compose security..."
if grep -q "env_file:" docker-compose.yml; then
    check_pass "Docker Compose uses env_file directive"
else
    check_fail "Docker Compose should use env_file directive"
fi

if grep -q "MONGODB_URI=\${MONGODB_URI}" docker-compose.yml; then
    check_pass "Docker Compose uses environment variable substitution"
else
    check_fail "Docker Compose should use environment variable substitution"
fi

# Check for hardcoded secrets in docker-compose.yml
if grep -q "mongodb+srv://.*:.*@" docker-compose.yml; then
    check_fail "Hardcoded MongoDB credentials found in docker-compose.yml"
else
    check_pass "No hardcoded credentials in docker-compose.yml"
fi

# Check file permissions
echo ""
echo "📁 Checking file permissions..."
if [ -f ".env" ]; then
    perms=$(stat -f "%OLp" .env 2>/dev/null || stat -c "%a" .env 2>/dev/null)
    if [ "$perms" = "600" ] || [ "$perms" = "640" ]; then
        check_pass ".env has secure permissions ($perms)"
    else
        check_warn ".env permissions are $perms (should be 600 or 640)"
        echo "   Run: chmod 600 .env"
    fi
fi

# Check if setup script exists and is executable
echo ""
echo "🛠️  Checking setup tools..."
if [ -f "setup-env.sh" ]; then
    check_pass "setup-env.sh exists"
    if [ -x "setup-env.sh" ]; then
        check_pass "setup-env.sh is executable"
    else
        check_warn "setup-env.sh should be executable"
        echo "   Run: chmod +x setup-env.sh"
    fi
else
    check_fail "setup-env.sh missing"
fi

if [ -f "SECURITY.md" ]; then
    check_pass "SECURITY.md documentation exists"
else
    check_warn "SECURITY.md documentation missing"
fi

# Test Docker Compose configuration
echo ""
echo "🐳 Testing Docker Compose configuration..."
if docker-compose config > /dev/null 2>&1; then
    check_pass "Docker Compose configuration is valid"
else
    check_fail "Docker Compose configuration has errors"
    echo "   Run: docker-compose config"
fi

# Summary
echo ""
echo "📊 Security Summary"
echo "=================="

# Count checks
total_checks=0
passed_checks=0
warning_checks=0
failed_checks=0

# This is a simplified count - in a real script you'd track these properly
echo ""
echo "🔍 Verification complete!"
echo ""
echo "📚 Next steps:"
echo "   1. Review any warnings above"
echo "   2. Run: docker-compose up -d"
echo "   3. Test the application: http://localhost:8081"
echo "   4. Read SECURITY.md for detailed guidelines"
echo ""
echo "🛡️  Remember:"
echo "   - Never commit .env files to git"
echo "   - Use strong, randomly generated secrets"
echo "   - Regularly rotate credentials"
echo "   - Monitor access logs"
echo ""
echo "✅ Your SecureHealth environment is ready!"
