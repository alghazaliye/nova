#!/bin/bash
# Test: login via OTP (dev code returned in otp_dev) then fetch conversation messages
cd /home/ubuntu/nova_new
PHONE="+966501239999"
OUT=$(curl -s -X POST http://localhost:8080/api/v1/auth/login -H "Content-Type: application/json" -d "{\"phone\":\"$PHONE\"}")
CODE=$(echo "$OUT" | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['otp_dev'])")
echo "login -> code: $CODE"
TOK=$(curl -s -X POST http://localhost:8080/api/v1/auth/verify-otp -H "Content-Type: application/json" -d "{\"phone\":\"$PHONE\",\"otp\":\"$CODE\"}" | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['token'])")
echo "verify -> token len: ${#TOK}"
echo "--- GET /api/v1/conversations/2/messages ---"
curl -s -m 30 "http://localhost:8080/api/v1/conversations/2/messages" -H "Authorization: Bearer $TOK" -w "\nHTTP:%{http_code}\n" | head -c 2000
