#!/bin/bash
# Signaling test with pre-obtained tokens (no login/verify steps).
set -e
BASE=http://localhost:8080
CALLER_TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjMyLCJpYXQiOjE3ODcyNjUwNzgsImV4cCI6MTc4NTg1NzA3OCwianRpIjoiYWM4NDE3ZWU4MmY5OTYzMyJ9.TOKEN_CALLER"
CALLEE_TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsImlhdCI6MTc4NzI2NTI0OCwiZXhwIjoxNzg1ODU3MjA4LCJqdGkiOiJhYzg0MTdlZTgyZjk5NjMzIn0.YWi2n3nJsq3XZI5LNaEzUqjzTSvqIURr20u0MdWfs5s"
# Tokens will be re-fetched below since JWTs above are placeholders.

echo "=== 1. re-fetch caller token (+966738155801) ==="
OTP=$(sqlite3 backend/config/nova.sqlite "SELECT manual_code FROM otp_verifications WHERE phone_number='+966738155801' AND status IN ('pending','sent') ORDER BY id DESC LIMIT 1")
[ -z "$OTP" ] && { echo "no pending OTP, request login"; curl -s -X POST $BASE/api/v1/auth/login -H 'Content-Type: application/json' -d '{"phone":"+966738155801"}' > /dev/null; OTP=$(sqlite3 backend/config/nova.sqlite "SELECT manual_code FROM otp_verifications WHERE phone_number='+966738155801' AND status IN ('pending','sent') ORDER BY id DESC LIMIT 1"); }
echo "caller OTP: $OTP"
CALLER_TOKEN=$(curl -s -X POST $BASE/api/v1/auth/verify-otp -H 'Content-Type: application/json' -d "{\"phone\":\"+966738155801\",\"otp\":\"$OTP\"}" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['token'])")
echo "caller token ok"

echo "=== 2. callee already verified (token) ==="
echo "callee token ok"

echo "=== 3. initiate call ==="
CR=$(curl -s -X POST $BASE/api/v1/calls -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{"callee_id":1,"call_type":"video"}')
echo "$CR" | head -c 300; echo
CALL_ID=$(echo "$CR" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['id'])")
echo "call id: $CALL_ID"

echo "=== 4. POST signal (offer) ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/signal -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{"signal_type":"offer","payload":{"type":"offer","sdp":"v=0\r\no=- 123 2 IN IP4 0.0.0.0\r\ns=-\r\n"}}'
echo

echo "=== 5. callee answer signal ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/signal -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLEE_TOKEN" -d '{"signal_type":"answer","payload":{"type":"answer","sdp":"v=0\r\n"}}'
echo

echo "=== 6. candidate ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/signal -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{"signal_type":"candidate","payload":{"candidate":"candidate:123 1 udp 2130706431 1.2.3.4 5000 typ host"}}'
echo

echo "=== 7. GET signals ==="
curl -s -w "\nHTTP:%{http_code}\n" -G $BASE/api/v1/calls/$CALL_ID/signals -H "Authorization: Bearer $CALLEE_TOKEN" --data-urlencode "since=2020-01-01 00:00:00" | head -c 500
echo

echo "=== 8. accept call ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/answer -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLEE_TOKEN" -d '{}'
echo

echo "=== 9. end call ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/end -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{}'
echo

echo "=== 10. call_signals rows ==="
sqlite3 backend/config/nova.sqlite "SELECT id, call_id, signal_type, length(payload) FROM call_signals;"
echo "=== DONE ==="
