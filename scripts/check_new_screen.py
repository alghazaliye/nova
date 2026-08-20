#!/usr/bin/env python3
"""فحص وجود نصوص شاشة الدخول الجديدة في main.dart.js المنشور مع فك escapes."""
import re

raw = open('/home/ubuntu/nova_new/web_app/main.dart.js', encoding='utf-8', errors='ignore').read()

def decode_escapes(s):
    # فك \\uXXXX و \uXXXX المزدوجة
    def fix(m):
        hexs = m.group(1)
        try:
            return chr(int(hexs, 16))
        except Exception:
            return m.group(0)
    # أولاً: تسلسلات \\uXXXX (escaped backslash) — نجرب كلا الشكلين
    out = re.sub(r'\\{2}u([0-9a-fA-F]{4})', fix, s)
    out = re.sub(r'\\u([0-9a-fA-F]{4})', fix, out)
    return out

decoded = decode_escapes(raw)

tests = [
    'تسجيل الدخول', 'إنشاء حساب', 'تحقق', 'الدخول بحسابك الحالي',
    'حساب جديد في نوفا', 'مرحباً بك', 'رقم الهاتف',
    'phone_login', 'email_login', 'username_login', 'phone_registration',
    'email_registration',
]
for t in tests:
    print(f'{t}: {"FOUND" if t in decoded else "missing"} | raw: {"FOUND" if t in raw else "missing"}')
