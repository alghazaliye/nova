import re

path = '/home/ubuntu/nova_new/web_app/main.dart.js'
raw = open(path, encoding='utf-8', errors='ignore').read()

def decode_escapes(s):
    out = []
    i = 0
    n = len(s)
    while i < n:
        if s[i] == '\\' and i + 5 < n and s[i+1] == 'u':
            hex4 = s[i+2:i+6]
            if all(c in '0123456789ABCDEFabcdef' for c in hex4):
                out.append(chr(int(hex4, 16)))
                i += 6
                continue
        out.append(s[i])
        i += 1
    return ''.join(out)

t = decode_escapes(raw)
for kw in ['تسجيل الدخول', 'إنشاء حساب', 'تحقق', 'رمز التحقق',
           'دخول بحسابك الحالي', 'حساب جديد في ثوانٍ', 'خدمة الدخول والتسجيل متوقفة']:
    print(kw, '=>', t.count(kw))
