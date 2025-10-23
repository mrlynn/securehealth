#!/bin/bash

# Colors
GREEN="\033[0;32m"
RED="\033[0;31m"
YELLOW="\033[0;33m"
BLUE="\033[0;34m"
RESET="\033[0m"

# Log in to get a session cookie
echo -e "${YELLOW}Attempting to log in to get a session cookie...${RESET}"

LOGIN_RESPONSE=$(curl -s -c cookies.txt -X POST http://localhost:8081/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin@example.com","password":"password123"}')

if [[ "$LOGIN_RESPONSE" == *"\"token\""* ]]; then
  echo -e "${GREEN}Login successful!${RESET}"

  # Extract session ID
  PHPSESSID=$(grep PHPSESSID cookies.txt | awk '{print $7}')
  echo "Session ID: $PHPSESSID"
else
  echo -e "${RED}Login failed! Trying with a test session ID.${RESET}"
  PHPSESSID="test-session"
fi

# First try using the debug endpoint (should work without authentication)
echo -e "\n${YELLOW}Testing chatbot using debug endpoint...${RESET}"
DEBUG_RESPONSE=$(curl -s "http://localhost:8081/debug/chatbot?q=what%20is%20hipaa%3F")

if [[ "$DEBUG_RESPONSE" == *"\"success\":true"* ]]; then
  echo -e "${GREEN}✓ Debug endpoint successful!${RESET}"
  echo -e "${BLUE}Here's the response:${RESET}"
  echo "$DEBUG_RESPONSE" | grep -o '"response":"[^"]*"' | sed 's/"response":"//;s/"$//'
  echo -e "\n${YELLOW}Debug endpoint returned full data:${RESET}"
  echo "$DEBUG_RESPONSE" | python -m json.tool
else
  echo -e "${RED}✗ Debug endpoint returned an error or unexpected response${RESET}"
  echo "Response: $DEBUG_RESPONSE"
fi

# Now test the actual API endpoint
echo -e "\n${YELLOW}Testing chatbot API endpoint with authentication...${RESET}"
API_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=$PHPSESSID" \
  -d '{"query":"what is hipaa?"}' \
  http://localhost:8081/api/chatbot/query)

if [[ "$API_RESPONSE" == *"\"success\":true"* ]]; then
  echo -e "${GREEN}✓ Chatbot API query successful!${RESET}"
  echo -e "${BLUE}Here's the response:${RESET}"
  echo "$API_RESPONSE" | grep -o '"response":"[^"]*"' | sed 's/"response":"//;s/"$//'
elif [[ "$API_RESPONSE" == *"\"success\":false"* ]]; then
  echo -e "${RED}✗ Chatbot API returned an error${RESET}"
  echo -e "${YELLOW}Error details:${RESET}"
  echo "$API_RESPONSE" | python -m json.tool
else
  echo -e "${RED}✗ Unexpected API response format${RESET}"
  echo "Response: $API_RESPONSE"
fi

# Clean up
rm -f cookies.txt

echo -e "\n${YELLOW}Testing complete!${RESET}"