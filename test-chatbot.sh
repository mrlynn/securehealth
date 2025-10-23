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

# Test with a valid session
echo -e "\n${YELLOW}Testing chatbot with session cookie...${RESET}"
CHATBOT_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=$PHPSESSID" \
  -d '{"query":"what is hipaa?"}' \
  http://localhost:8081/api/chatbot/query)

echo "Response: $CHATBOT_RESPONSE"

if [[ "$CHATBOT_RESPONSE" == *"\"success\":true"* ]]; then
  echo -e "${GREEN}✓ Chatbot query successful!${RESET}"
  echo -e "${BLUE}Here's the response:${RESET}"
  echo "$CHATBOT_RESPONSE" | grep -o '"response":"[^"]*"' | sed 's/"response":"\(.*\)"/\1/'
elif [[ "$CHATBOT_RESPONSE" == *"\"success\":false"* ]]; then
  echo -e "${RED}✗ Chatbot returned an error${RESET}"
  echo "Error: $(echo "$CHATBOT_RESPONSE" | grep -o '"error":"[^"]*"' | sed 's/"error":"\(.*\)"/\1/')"
  
  if [[ "$CHATBOT_RESPONSE" == *"\"details\":"* ]]; then
    echo -e "${YELLOW}Error details:${RESET}"
    ERROR_FILE=$(echo "$CHATBOT_RESPONSE" | grep -o '"file":"[^"]*"' | sed 's/"file":"\(.*\)"/\1/')
    ERROR_LINE=$(echo "$CHATBOT_RESPONSE" | grep -o '"line":[0-9]*' | sed 's/"line":\(.*\)/\1/')
    echo "Location: $ERROR_FILE:$ERROR_LINE"
  fi
else
  echo -e "${RED}✗ Unexpected response format${RESET}"
fi

# Clean up
rm -f cookies.txt
