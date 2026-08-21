#!/bin/bash
# Full signaling test: login two users, initiate call, signal, fetch signals.
set -e
BASE=http://localhost:8080

echo "=== 1. login caller +966738155801 ==="
OTP=$(sqlite3 backend/config/nova.sqlite "SELECT manual_code FROM otp_verifications WHERE phone_number LIKE '%738155801' AND status IN ('pending','sent') ORDER BY id DESC LIMIT 1")
if [ -z "$OTP" ]; then
  curl -s -X POST $BASE/api/v1/auth/login -H 'Content-Type: application/json' -d '{"phone":"+966738155801"}' > /dev/null
  OTP=$(sqlite3 backend/config/nova.sqlite "SELECT manual_code FROM otp_verifications WHERE phone_number LIKE '%738155801' AND status IN ('pending','sent') ORDER BY id DESC LIMIT 1")
fi
echo "caller OTP: $OTP"
R=$(curl -s -X POST $BASE/api/v1/auth/verify-otp -H 'Content-Type: application/json' -d "{\"phone\":\"+966738155801\",\"otp\":\"$OTP\"}")
CALLER_TOKEN=$(echo "$R" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['token'])")
echo "caller token ok"

echo "=== 2. login callee +966501234567 ==="
OTP2=$(sqlite3 backend/config/nova.sqlite "SELECT manual_code FROM otp_verifications WHERE phone_number LIKE '%501234567' AND status IN ('pending','sent') ORDER BY id DESC LIMIT 1")
if [ -z "$OTP2" ]; then
  curl -s -X POST $BASE/api/v1/auth/login -H 'Content-Type: application/json' -d '{"phone":"+966501234567"}' > /dev/null
  OTP2=$(sqlite3 backend/config/nova.sqlite "SELECT manual_code FROM otp_verifications WHERE phone_number LIKE '%501234567' AND status IN ('pending','sent') ORDER BY id DESC LIMIT 1")
fi
echo "callee OTP: $OTP2"
R2=$(curl -s -X POST $BASE/api/v1/auth/verify-otp -H 'Content-Type: application/json' -d "{\"phone\":\"+966501234567\",\"otp\":\"$OTP2\"}")
CALLEE_TOKEN=$(echo "$R2" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['token'])")
echo "callee token ok"

echo "=== 3. initiate call (caller -> callee) ==="
CR=$(curl -s -X POST $BASE/api/v1/calls -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{"receiver_id":1,"call_type":"video"}')
echo "$CR" | head -c 300; echo
CALL_ID=$(echo "$CR" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['id'])")
echo "call id: $CALL_ID"

echo "=== 4. POST signal (offer) — was failing 500 ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/signal -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{"signal_type":"offer","payload":{"type":"offer","sdp":"v=0\r\no=- 123 2 IN IP4 0.0.0.0\r\ns=-\r\n"}}'
echo

echo "=== 5. callee sends answer ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/signal -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLEE_TOKEN" -d '{"signal_type":"answer","payload":{"type":"answer","sdp":"v=0\r\n"}}'
echo

echo "=== 6. candidate from caller ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/signal -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{"signal_type":"candidate","payload":{"candidate":"candidate:123 1 udp 2130706431 1.2.3.4 5000 typ host"}}'
echo

echo "=== 7. GET signals?since= (was failing 500) ==="
curl -s -w "\nHTTP:%{http_code}\n" -G $BASE/api/v1/calls/$CALL_ID/signals -H "Authorization: Bearer $CALLEE_TOKEN" --data-urlencode "since=2020-01-01 00:00:00" | head -c 500
echo

echo "=== 8. callee answers call ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/answer -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLEE_TOKEN" -d '{}'
echo

echo "=== 9. end call ==="
curl -s -w "\nHTTP:%{http_code}\n" -X POST $BASE/api/v1/calls/$CALL_ID/end -H 'Content-Type: application/json' -H "Authorization: Bearer $CALLER_TOKEN" -d '{}'
echo

echo "=== 10. call_signals DB rows ==="
sqlite3 backend/config/nova.sqlite "SELECT id, call_id, signal_type, length(payload) FROM call_signals;"
echo "=== DONE ==="
