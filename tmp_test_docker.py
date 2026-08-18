#!/usr/bin/env python3
"""اختبار دورة كاملة داخل container nova513 على port 8082"""
import requests, subprocess, sys, time

BASE = "http://localhost:8082/api/v1"
EMAIL = "docktest@example.com"

def sub(sql):
    r = subprocess.run(["sudo","docker","exec","nova513","mysql","-h127.0.0.1",
                        "-unova_user","-pnova2026","nova","-e",sql],
                       capture_output=True, text=True)
    return r.stdout

# 1. تفعيل الإعدادات
sub("UPDATE app_settings SET setting_value='1' WHERE setting_key IN ('auth_email_registration','otp_email_enabled','auth_email_login','auth_username_login');")
sub("DELETE FROM email_verification_codes WHERE email='"+EMAIL+"'; DELETE FROM users WHERE email='"+EMAIL+"';")

# 2. register-email
r = requests.post(f"{BASE}/auth/register-email", json={"email": EMAIL, "name": "اختبار دوكر",
    "phone": "", "device_uuid": "d9", "app_version": "3.6.0", "platform": "web",
    "os_name": "Web", "os_version": "browser"})
print("REG:", r.status_code, r.text[:150])

# 3. verify
r = requests.post(f"{BASE}/auth/verify-email-otp", json={"email": EMAIL, "otp": "123456"})
print("VERIFY:", r.status_code, r.text[:250])
d = r.json().get("data", {})
token = d.get("token")
user = d.get("user", {})
print("  user id:", user.get("id"), "email_verified:", user.get("email_verified"))

if token:
    # 4. set password + login-email
    r = requests.post(f"{BASE}/auth/set-password", headers={"Authorization": f"Bearer {token}"},
                      json={"new_password": "password123"})
    print("SET-PW:", r.status_code, r.text[:100])
    r = requests.post(f"{BASE}/auth/login-email", json={"email": EMAIL, "password": "password123",
        "device_uuid": "d10", "app_version": "3.6.0", "platform": "web", "os_name": "Web", "os_version": "browser"})
    print("LOGIN-EMAIL:", r.status_code, r.text[:150])

    # 5. login-username
    pw = subprocess.run(["php","-r", 'echo password_hash("password123", PASSWORD_BCRYPT);'],
                        capture_output=True, text=True).stdout.strip()
    uid = user.get("id")
    sub(f"UPDATE users SET password_hash='{pw}', username='dockuser' WHERE id={uid};")
    time.sleep(1)
    r = requests.post(f"{BASE}/auth/login-username", json={"username": "dockuser", "password": "password123",
        "device_uuid": "d11", "app_version": "3.6.0", "platform": "web", "os_name": "Web", "os_version": "browser"})
    print("LOGIN-USERNAME:", r.status_code, r.text[:150])

    # 6. admin login + صفحة auth-settings
    r = requests.post(f"{BASE}/admin/otp/login", json={"email": "admin@nova-messenger.com", "password": "Admin@1234"})
    aj = r.json()
    at = aj.get("data", {}).get("token")
    if at:
        r = requests.get(f"{BASE}/admin/auth/settings", headers={"Authorization": f"Bearer {at}"})
        print("ADMIN-AUTH-SETTINGS:", r.status_code, r.text[:200])

# 7. منع التسجيل
sub("UPDATE app_settings SET setting_value='0' WHERE setting_key='auth_email_registration';")
r = requests.post(f"{BASE}/auth/register-email", json={"email": "blocked2@example.com", "name": "x"})
print("BLOCKED-REG:", r.status_code, r.text[:150])
sub("UPDATE app_settings SET setting_value='1' WHERE setting_key='auth_email_registration';")

# 8. health + config
r = requests.get(f"{BASE}/auth/config")
print("CONFIG:", r.status_code, r.text[:150])

print("DOCKER TEST DONE")
