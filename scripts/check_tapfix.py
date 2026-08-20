#!/usr/bin/env python3
'''
فحص النسخة المنشورة: فك escapes والبحث عن نصوص الشاشة الجديدة.
'''
import sys

def decode_escapes(s: str) -> str:
    out = []
    i, n = 0, len(s)
    while i < n:
        if s[i] == '\\' and i + 5 < n and s[i + 1] == 'u':
            h = s[i + 2:i + 6]
            if all(c in '0123456789ABCDEFabcdef' for c in h):
                out.append(chr(int(h, 16)))
                i += 6
                continue
        out.append(s[i])
        i += 1
    return ''.join(out)

path = sys.argv[1] if len(sys.argv) > 1 else 'web_app/main.dart.js'
raw = open(path, encoding='utf-8', errors='ignore').read()
t = decode_escapes(raw)
kws = [
    'لا تتوفر حاليًا أي طريقة للدخول',
    'التسجيل غير متاح حاليًا',
    'تسجيل الدخول',
    'إنشاء حساب',
    'دخول بحسابك الحالي',
    'حساب جديد في ثوانٍ',
    'تحقق',
]
for kw in kws:
    print(f'{kw} => {t.count(kw)}')
