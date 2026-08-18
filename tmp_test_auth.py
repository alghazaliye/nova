#!/usr/bin/env python3
"""اختبار دورة كاملة: register-email -> verify-email-otp داخل container nova512"""
import requests, json, subprocess, sys

BASE = "http://localhost:8081/api/v1"
EMAIL = "qt@example.com"

# 1. حذف سجلات قديمة داخل container
r = subprocess.run(
    ["sudo","docker","exec","nova512","mysql","-h127.0.0.1","-unova_user","-prender2026","nova",
     "-e", f"DELETE FROM email_verification_codes WHERE email='{EMAIL}'; SELECT id FROM users WHERE email='{EMAIL}';"],
    capture_output=True, text=True)
print("DB cleanup:", r.stdout.strip() or r.stderr.strip())

# 2. register-email
r = requests.post(f"{BASE}/auth/register-email", json={
    "email": EMAIL, "name": "اختبار كيو تي", "phone": "", "device_uuid": "uqt",
    "app_version": "3.6.0", "platform": "web", "os_name": "Web", "os_version": "browser"})
print("REG:", r.status_code, r.text[:200])

# 3. verify-email-otp
r = requests.post(f"{BASE}/auth/verify-email-otp", json={"email": EMAIL, "otp": "123456"})
print("VERIFY:", r.status_code, r.text[:300])

if r.status_code == 200:
    token = r.json()["data"]["token"]
    uid = r.json()["data"]["user"].get("id")
    print("TOKEN ok, userId=", uid)

    # 4. set-password
    r = requests.post(f"{BASE}/auth/set-password", headers={"Authorization": f"Bearer {token}"},
                      json={"new_password": "password123"})
    print("SET-PW:", r.status_code, r.text[:150])

    # 5. logout
    requests.post(f"{BASE}/auth/logout", headers={"Authorization": f"Bearer {token}"})

    # 6. login-email
    r = requests.post(f"{BASE}/auth/login-email", json={
        "email": EMAIL, "password": "password123", "device_uuid": "uqt2",
        "app_version": "3.6.0", "platform": "web", "os_name": "Web", "os_version": "browser"})
    print("LOGIN-EMAIL:", r.status_code, r.text[:200])

    # 7. login-username (بعد تعيين username)
    subprocess.run(["sudo","docker","exec","nova512","mysql","-h127.0.0.1","-unova_user","-prender2026","nova",
                    "-e", f"UPDATE users SET username='qtuser' WHERE email='{EMAIL}';"],
                   capture_output=True)
    r = requests.post(f"{BASE}/auth/login-username", json={
        "username": "qtuser", "password": "password123", "device_uuid": "uqt3",
        "app_version": "3.6.0", "platform": "web", "os_name": "Web", "os_version": "browser"})
    print("LOGIN-USERNAME:", r.status_code, r.text[:200])
else:
    # فحص حالة DB
    r = subprocess.run(["sudo","docker","exec","nova512","mysql","-h127.0.0.1","-unova_user","-prender2026","nova",
                        "-e", f"SELECT email,status,delivery_mode,resends FROM email_verification_codes WHERE email='{EMAIL}'; SELECT id,email FROM users WHERE email='{EMAIL}';"],
                       capture_output=True, text=True)
    print("DB STATE:", r.stdout)
    sys.exit(1)
print("DONE")
