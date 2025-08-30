#!/bin/bash

# Debug script to test different path formats for HMAC authentication

SHARED_KEY="EyliHYQqehlcVpKHrvHJbOc38oZpZhsWKFnJakqxUkzqtWJ4fIg+/VsLq3aTnrQn"
JOB_ID="debug-test-$(date +%s)"

echo "🔍 Debugging HMAC path normalization..."
echo "Job ID: $JOB_ID"
echo ""

# Test different path formats
test_path() {
    local path="$1"
    local description="$2"
    
    echo "Testing: $description"
    echo "Path: $path"
    
    local timestamp=$(date +%s)000
    local nonce=$(openssl rand -hex 8)
    local body=""
    local body_hash=$(echo -n "$body" | openssl dgst -sha256 | awk '{print $2}')
    
    # Ensure path starts with /wp-json
    if [[ ! "$path" =~ ^/wp-json ]]; then
        path="/wp-json$path"
    fi
    
    local payload="${timestamp}\n${nonce}\nGET\n${path}\n${body_hash}"
    local signature=$(echo -en "$payload" | openssl dgst -sha256 -hmac "$SHARED_KEY" -binary | base64)
    
    echo "Payload: $payload"
    echo "Signature: $signature"
    
    # Test the endpoint
    local response=$(curl -s -X GET "https://motherknitter.com$path" \
        -H "X-MIG-Timestamp: $timestamp" \
        -H "X-MIG-Nonce: $nonce" \
        -H "X-MIG-Peer: https://staging.motherknitter.com" \
        -H "X-MIG-Signature: $signature" \
        -H "Content-Type: application/json")
    
    echo "Response: $response"
    echo "---"
    echo ""
}

# Test different path formats
test_path "/migrate/v1/monitor?job_id=$JOB_ID" "Monitor with query params"
test_path "/migrate/v1/monitor" "Monitor without query params"
test_path "/migrate/v1/logs/tail?job_id=$JOB_ID" "Logs with query params"
test_path "/migrate/v1/logs/tail" "Logs without query params"
