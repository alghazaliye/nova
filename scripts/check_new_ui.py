import codecs
import re

path = '/home/ubuntu/nova_new/web_app/main.dart.js'
raw = open(path, encoding='utf-8', errors='ignore').read()

def count(kw):
    n = raw.count(kw)
    if n:
        return n
    # dart2js يخزن العربية كـ \\u0627 (backslash escaped) في الملف الخام
    def deq(m):
        return m.group(1)
    t = re.sub(r'\\+u([0-9A-Fa-f]{4})', deq, raw)
    return t.count(kw)

for kw in ['تسجيل الدخول', 'إنشاء حساب', 'تحقق', 'رمز التحقق', 'phoneRegistration',
           'lastLoginBypass', 'registrationEnabled', 'loginEnabled',
           'دخول بحسابك الحالي', 'حساب جديد في ثوانٍ', 'خدمة الدخول والتسجيل متوقفة']:
    print(kw, '=>', count(kw))
