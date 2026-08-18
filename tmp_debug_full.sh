#!/bin/bash
# اختبار كامل داخل container nova513 — register ثم فحص DB ثم verify مباشرة
set -e
EMAIL="docktest@example.com"

# تنظيف
sudo docker exec nova513 mysql -h127.0.0.1 -unova_user -pnova2026 nova -e "DELETE FROM email_verification_codes WHERE email='$EMAIL'; DELETE FROM users WHERE email='$EMAIL';" 2>/dev/null
sudo docker exec nova513 mysql -h127.0.0.1 -unova_user -pnova2026 nova -e "UPDATE app_settings SET setting_value='1' WHERE setting_key IN ('auth_email_registration','otp_email_enabled','auth_email_login','auth_username_login');" 2>/dev/null

# register
curl -s -X POST http://127.0.0.1:8088/api/v1/auth/register-email -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"name\":\"اختبار دوكر\",\"phone\":\"\",\"device_uuid\":\"d60\",\"app_version\":\"3.6.0\",\"platform\":\"web\",\"os_name\":\"Web\",\"os_version\":\"browser\"}" > /tmp/reg.json
echo "REG: $(cat /tmp/reg.json | head -c 200)"
echo

# فحص حالة الرمز في DB قبل verify
sudo docker exec nova513 mysql -h127.0.0.1 -unova_user -pnova2026 nova -e "SELECT id,email,status,delivery_mode,manual_code_hash IS NOT NULL AS has_manual FROM email_verification_codes WHERE email='$EMAIL';" 2>/dev/null

# verify مباشرة
echo "--- VERIFY ---"
curl -s -m 15 -X POST http://127.0.0.1:8088/api/v1/auth/verify-email-otp -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"otp\":\"123456\"}" -o /tmp/ver.json -w 'HTTP:%{http_code}\n'
cat /tmp/ver.json | head -c 400
echo
