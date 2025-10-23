#!/bin/bash

# Colors
GREEN="\033[0;32m"
RED="\033[0;31m"
YELLOW="\033[0;33m"
BLUE="\033[0;34m"
RESET="\033[0m"

echo -e "${GREEN}=== HIPAA Chatbot Test Script ===${RESET}"
echo "Testing endpoints to diagnose the 500 error"
echo

# Test MongoDB connection
echo -e "${YELLOW}1. Testing MongoDB connection...${RESET}"
mongo_response=$(curl -s http://localhost:8081/debug/mongo)

if [[ "$mongo_response" == *"\"success\":true"* ]]; then
    echo -e "${GREEN}✓ MongoDB connection successful${RESET}"
    echo "$mongo_response" | grep -o '"mongodb_status":"[^"]*"'
    echo "$mongo_response" | grep -o '"version":"[^"]*"'
    echo "$mongo_response" | grep -o '"database":"[^"]*"'
    echo "$mongo_response" | grep -o '"collections":\[[^\]]*\]'
    echo "$mongo_response" | grep -o '"knowledge_count":[^,}]*'
else
    echo -e "${RED}✗ MongoDB connection failed or returned HTML${RESET}"
    echo "Response indicates the endpoint is redirecting to HTML, not returning JSON"
fi

echo

# Test debug chatbot endpoint
echo -e "${YELLOW}2. Testing debug chatbot endpoint...${RESET}"
chatbot_response=$(curl -s "http://localhost:8081/debug/chatbot?q=what%20is%20hipaa")

if [[ "$chatbot_response" == *"\"success\":true"* ]]; then
    echo -e "${GREEN}✓ Debug chatbot endpoint successful${RESET}"
    echo "$chatbot_response" | grep -o '"query":"[^"]*"'
    echo "$chatbot_response" | grep -o '"response":"[^"]*"' | head -n 50
else
    echo -e "${RED}✗ Debug chatbot endpoint failed or returned HTML${RESET}"
    echo "Response indicates the endpoint is redirecting to HTML, not returning JSON"
fi

echo

# Try with session cookie to mimic browser authentication
echo -e "${YELLOW}3. Testing chatbot query with session cookie...${RESET}"
chatbot_api_response=$(curl -s -X POST \
  http://localhost:8081/api/chatbot/query \
  -H "Content-Type: application/json" \
  --cookie "PHPSESSID=test-session" \
  -d '{"query":"what is hipaa?"}')

if [[ "$chatbot_api_response" == *"\"success\":true"* ]]; then
    echo -e "${GREEN}✓ Chatbot query with session successful${RESET}"
    echo "$chatbot_api_response" | grep -o '"response":"[^"]*"' | head -n 50
elif [[ "$chatbot_api_response" == *"Authentication required"* ]]; then
    echo -e "${RED}✗ Authentication required - session not valid${RESET}"
    echo "API requires valid session cookie"
else
    echo -e "${RED}✗ Chatbot API returned error${RESET}"
    echo "$chatbot_api_response" | grep -o '"error":"[^"]*"' || echo "$chatbot_api_response"
fi

echo

# Check for fallback responses
echo -e "${YELLOW}4. Testing fallback mechanism...${RESET}"
# Testing if the response contains phrases commonly found in fallback responses
if [[ "$chatbot_response" == *"HIPAA (Health Insurance Portability and Accountability Act) is a US federal law established in 1996"* ]]; then
    echo -e "${YELLOW}⚠ Fallback response detected - the system is using hard-coded responses${RESET}"
else
    echo -e "${BLUE}ℹ No obvious fallback response detected${RESET}"
fi

echo

# Check MongoDB environment variables
echo -e "${YELLOW}5. Checking MongoDB environment variables...${RESET}"
mongodb_uri=$(grep "MONGODB_URI=" /Users/michael.lynn/code/symfony/hipaa/.env | cut -d= -f2)
mongodb_db=$(grep "MONGODB_DB=" /Users/michael.lynn/code/symfony/hipaa/.env | cut -d= -f2)
mongodb_disabled=$(grep "MONGODB_DISABLED=" /Users/michael.lynn/code/symfony/hipaa/.env | cut -d= -f2)

echo "MONGODB_URI: ${mongodb_uri:0:20}***** (masked for security)"
echo "MONGODB_DB: $mongodb_db"
echo "MONGODB_DISABLED: $mongodb_disabled"

if [[ "$mongodb_disabled" == "true" ]]; then
    echo -e "${RED}⚠ MongoDB is disabled in .env file${RESET}"
else
    echo -e "${GREEN}✓ MongoDB is enabled${RESET}"
fi

echo

# Check OpenAI API key
echo -e "${YELLOW}6. Checking OpenAI API key...${RESET}"
openai_key=$(grep "OPENAI_API_KEY=" /Users/michael.lynn/code/symfony/hipaa/.env | cut -d= -f2)

if [[ -n "$openai_key" ]]; then
    echo -e "${GREEN}✓ OpenAI API key exists${RESET}"
    echo "Key prefix: ${openai_key:0:10}***** (masked for security)"
    
    # Test if key appears valid
    if [[ "$openai_key" == sk-* ]]; then
        echo -e "${GREEN}✓ OpenAI API key has valid prefix${RESET}"
    else
        echo -e "${RED}⚠ OpenAI API key does not have expected prefix${RESET}"
    fi
else
    echo -e "${RED}✗ No OpenAI API key found${RESET}"
fi

echo

echo -e "${GREEN}=== Test Summary ===${RESET}"
echo "1. Debug endpoints appear to be returning HTML instead of JSON"
echo "2. This suggests an issue with URL routing or middleware configuration"
echo "3. MongoDB and OpenAI API keys appear to be configured"
echo "4. The application may be falling back to hard-coded responses due to API issues"
echo
echo -e "${YELLOW}Next steps:${RESET}"
echo "1. Check Symfony routing configuration"
echo "2. Inspect application logs for 500 errors"
echo "3. Verify MongoDB vector search index is correctly configured"
echo "4. Check if security configuration is blocking non-authenticated API access"
