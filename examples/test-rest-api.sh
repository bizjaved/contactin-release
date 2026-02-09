#!/bin/bash
#
# Secure ContactUs Hub - REST API Test Script
# 
# This script demonstrates how to test the REST API endpoint
# from an external source using cURL.
#
# Usage: ./test-rest-api.sh [name] [email] [message]
# Example: ./test-rest-api.sh "John Doe" "john@example.com" "Hello!"

# Configuration
API_URL="http://wpdev.local/wp-json/securech/v1/submit"

# Parameters with defaults
NAME="${1:-John Doe}"
EMAIL="${2:-john@example.com}"
MESSAGE="${3:-Test message from external script}"
SUBJECT="${4:-External Test Submission}"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "REST API Test - Secure ContactUs Hub"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Endpoint: $API_URL"
echo "Name: $NAME"
echo "Email: $EMAIL"
echo "Message: $MESSAGE"
echo "Subject: $SUBJECT"
echo ""

# Make the API request
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"$NAME\",
    \"email\": \"$EMAIL\",
    \"message\": \"$MESSAGE\",
    \"subject\": \"$SUBJECT\"
  }")

echo "Response:"
echo "$RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
