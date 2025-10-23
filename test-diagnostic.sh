#!/bin/bash

# Diagnostic script for chatbot debugging
# This script helps test various API endpoints to diagnose issues

# Set the base URL (change if needed)
BASE_URL="http://localhost:8081"

# Function to make authenticated API calls
function call_api {
  endpoint=$1
  method=$2
  data=$3

  echo -e "\n\033[1;34m📡 Testing endpoint: $endpoint\033[0m"
  echo -e "\033[0;37mMethod: $method\033[0m"

  if [ -n "$data" ]; then
    echo -e "\033[0;37mData: $data\033[0m"
  fi

  echo -e "\033[0;36m----------------------------------------------------\033[0m"

  if [ "$method" = "GET" ]; then
    curl -s -X GET \
      -H "Content-Type: application/json" \
      -H "Cookie: $(cat .session_cookie 2>/dev/null || echo '')" \
      "$BASE_URL$endpoint" | jq . || echo "Error processing response"
  else
    curl -s -X "$method" \
      -H "Content-Type: application/json" \
      -H "Cookie: $(cat .session_cookie 2>/dev/null || echo '')" \
      -d "$data" \
      "$BASE_URL$endpoint" | jq . || echo "Error processing response"
  fi

  echo -e "\033[0;36m----------------------------------------------------\033[0m"
}

# Function to log in and save the session cookie
function login {
  echo -e "\033[1;33m🔑 Logging in as $1...\033[0m"

  response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$1\",\"password\":\"$2\"}" \
    -v "$BASE_URL/api/auth/login" 2>&1)

  cookie=$(echo "$response" | grep -i "set-cookie" | head -n 1 | sed 's/^.*set-cookie: \([^;]*\);.*$/\1/')

  if [ -n "$cookie" ]; then
    echo "$cookie" > .session_cookie
    echo -e "\033[1;32m✅ Login successful, session cookie saved\033[0m"
  else
    echo -e "\033[1;31m❌ Login failed, no cookie received\033[0m"
    echo "$response" | grep "< HTTP"
    exit 1
  fi
}

# Ensure jq is installed
if ! command -v jq &> /dev/null; then
  echo -e "\033[1;31m❌ Error: jq is not installed. Please install it first.\033[0m"
  echo "On macOS: brew install jq"
  echo "On Ubuntu/Debian: apt-get install jq"
  exit 1
fi

# Main menu
function show_menu {
  clear
  echo -e "\033[1;35m🔍 HIPAA Chatbot Diagnostic Tool\033[0m"
  echo -e "\033[0;36m----------------------------------------------------\033[0m"
  echo -e "\033[1;33m1. Login\033[0m"
  echo -e "\033[1;33m2. Run Full Diagnostic\033[0m"
  echo -e "\033[1;33m3. Test MongoDB Connection\033[0m"
  echo -e "\033[1;33m4. Test OpenAI API\033[0m"
  echo -e "\033[1;33m5. Test Vector Search\033[0m"
  echo -e "\033[1;33m6. Test Environment Variables\033[0m"
  echo -e "\033[1;33m7. Test Emergency Chatbot Endpoint\033[0m"
  echo -e "\033[1;33m8. Test HIPAA Query\033[0m"
  echo -e "\033[1;33m9. Exit\033[0m"
  echo -e "\033[0;36m----------------------------------------------------\033[0m"
  echo -ne "\033[1;37mEnter your choice [1-9]: \033[0m"
  read -r choice

  case $choice in
    1) perform_login ;;
    2) call_api "/api/diagnostic/run" "GET" ;;
    3) call_api "/api/diagnostic/mongodb" "GET" ;;
    4) call_api "/api/diagnostic/openai" "GET" ;;
    5) call_api "/api/diagnostic/vector" "GET" ;;
    6) call_api "/api/diagnostic/env" "GET" ;;
    7) call_api "/api/chatbot-emergency/query" "POST" '{"query": "what is hipaa?"}' ;;
    8) call_api "/api/chatbot/query" "POST" '{"query": "what is hipaa?"}' ;;
    9) exit 0 ;;
    *) echo -e "\033[1;31m❌ Invalid option\033[0m" ;;
  esac

  echo -e "\nPress Enter to continue..."
  read -r
  show_menu
}

# Login function for the menu
function perform_login {
  echo -ne "\033[1;37mEnter email: \033[0m"
  read -r email

  echo -ne "\033[1;37mEnter password: \033[0m"
  read -rs password
  echo

  login "$email" "$password"

  echo -e "\nPress Enter to continue..."
  read -r
}

# Start the menu
show_menu