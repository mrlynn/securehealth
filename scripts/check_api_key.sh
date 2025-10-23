#!/bin/bash

# Function to check key validity
function check_key_validity() {
    local key=$1
    local response
    response=$(curl -s -o /dev/null -w "%{http_code}" https://api.openai.com/v1/models -H "Authorization: Bearer $key")

    if [ "$response" -eq 200 ]; then
        echo "✅ The API key is valid."
    else
        echo "❌ The API key is invalid or there was a problem with the request. (HTTP Code: $response)"
    fi
}

# Main script: Check if an API key was provided
if [ $# -ne 1 ]; then
    echo "Usage: $0 <API-Key>"
    exit 1
fi

API_KEY=$1

# Perform key validation
check_key_validity "$API_KEY"