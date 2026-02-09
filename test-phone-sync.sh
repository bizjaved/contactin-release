#!/bin/bash
#
# Test Phone Field Sync to Salesforce
#
# This script tests that phone field is correctly mapped and synced.
#
# Usage: ./test-phone-sync.sh [name] [email] [phone] [message] [subject]
# Example: ./test-phone-sync.sh "John Doe" "john@example.com" "+1-555-1234" "Test message" "Phone Test"

API_URL="http://wpdev.local/wp-json/securech/v1/submit"

# Parameters with defaults (including phone)
NAME="${1:-John Doe}"
EMAIL="${2:-john@example.com}"
PHONE="${3:-+1-555-1234}"
MESSAGE="${4:-Test message with phone}"
SUBJECT="${5:-Phone Field Test}"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Phone Field Sync Test"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Endpoint: $API_URL"
echo "Name: $NAME"
echo "Email: $EMAIL"
echo "Phone: $PHONE"
echo "Message: $MESSAGE"
echo "Subject: $SUBJECT"
echo ""

# Make the API request
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"$NAME\",
    \"email\": \"$EMAIL\",
    \"phone\": \"$PHONE\",
    \"message\": \"$MESSAGE\",
    \"subject\": \"$SUBJECT\"
  }")

echo "Response:"
echo "$RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"
echo ""
echo "After submission, check:"
echo "1. Contact Inbox → Messages → Look for the new message"
echo "2. Message Details → Check CRM Sync Status should show 'Synced'"
echo "3. Salesforce Contacts → Search for $EMAIL"
echo "4. Contact Details → Phone field should show: $PHONE"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
