#!/bin/bash
# OTP system end-to-end test
set -e
BASE="http://localhost:8080/api/v1"
echo "== 1) admin login"
RESP=$(curl -s -X POST "$BASE/admin/otp/login" -H "Content-Type: application/json" -d '{"email":"admin@nova-messenger.com","password":"Admin@1234"}')
TOKEN=$(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")
echo "TOKEN OK: ${TOKEN:0:20}..."

echo "== 2) settings GET"
curl -s "$BASE/admin/otp/settings" -H "Authorization: Bearer $TOKEN" | head -c 500
echo

echo "== 3) settings UPDATE"
curl -s -X POST "$BASE/admin/otp/settings" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"otp_expiry_minutes":"10","otp_required":"1"}' | head -c 300
echo

echo "== 4) providers list"
curl -s "$BASE/admin/otp/providers" -H "Authorization: Bearer $TOKEN" | head -c 400
echo

echo "== 5) stats"
curl -s "$BASE/admin/otp/stats" -H "Authorization: Bearer $TOKEN" | head -c 300
echo

echo "== 6) registrations list"
curl -s "$BASE/admin/otp/registrations?page=1&per_page=5" -H "Authorization: Bearer $TOKEN" | head -c 400
echo

echo "== 7) fresh register flow"
PHONE="+966559990099"
curl -s -X POST "$BASE/auth/register" -H "Content-Type: application/json" -d "{\"phone\":\"$PHONE\",\"name\":\"اختبار OTP نهائي\"}" | head -c 300
echo
echo "== 8) verify with bypass 123456"
curl -s -X POST "$BASE/auth/verify-otp" -H "Content-Type: application/json" -d "{\"phone\":\"$PHONE\",\"otp\":\"123456\"}" | head -c 400
echo

echo "== 9) cooldown test (two resends in a row)"
curl -s -X POST "$BASE/auth/register" -H "Content-Type: application/json" -d '{"phone":"+966559990100","name":"cooldown"}' > /dev/null
sleep 1
R1=$(curl -s -X POST "$BASE/auth/resend-otp" -H "Content-Type: application/json" -d '{"phone":"+966559990100"}')
echo "R1: $R1" | head -c 200
echo
R2=$(curl -s -X POST "$BASE/auth/resend-otp" -H "Content-Type: application/json" -d '{"phone":"+966559990100"}')
echo "R2: $R2" | head -c 200
echo

echo "== 10) expired otp test (expiry=0 minutes)"
curl -s -X POST "$BASE/admin/otp/settings" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"otp_expiry_minutes":"0"}' > /dev/null
curl -s -X POST "$BASE/auth/register" -H "Content-Type: application/json" -d '{"phone":"+966559990111","name":"expiry"}' > /dev/null
sleep 2
EXP=$(curl -s -X POST "$BASE/auth/verify-otp" -H "Content-Type: application/json" -d '{"phone":"+966559990111","otp":"123456"}')
echo "EXPIRY TEST: $EXP" | head -c 200
echo
curl -s -X POST "$BASE/admin/otp/settings" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"otp_expiry_minutes":"10"}' > /dev/null
echo "ALL DONE"
