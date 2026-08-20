#!/usr/bin/env python3
"""فحص محتوى main.dart.js المنشور بعد فك unicode escapes للتأكد من وجود المنطق الجديد."""
import codecs
import re

PATH = '/home/ubuntu/nova_new/web_app/main.dart.js'
t = open(PATH, encoding='utf-8', errors='ignore').read()
dec = codecs.decode(t, 'unicode_escape', errors='ignore')

checks = {
    # زر تحقق البريد
    'verify_btn_label': 'تحقق' in dec,
    'send_code_to_email_msg': 'تم إرسال رمز التحقق إلى بريدك' in dec,
    'email_field_label': 'البريد الإلكتروني' in dec,
    # منطق الالتزام بالإعدادات
    'phoneLogin': 'phoneLogin' in dec,
    'phoneRegistration': 'phoneRegistration' in dec,
    'emailLogin': 'emailLogin' in dec,
    'emailRegistration': 'emailRegistration' in dec,
    'usernameLogin': 'usernameLogin' in dec,
    'loginEnabled': 'loginEnabled' in dec,
    'admin_disabled_msg': 'خدمة تسجيل الدخول متوقفة مؤقتًا' in dec,
    'email_otp_config_check': 'emailOtpEnabled' in dec,
    'not_enabled_msg': 'غير مفعّل من لوحة الإدارة' in dec,
    'tabs': all(w in dec for w in ['هاتف', 'بريد', 'اسم مستخدم']),
    'nova_href_debug': '[NovaWeb] href' in dec,
    # لا رابط manus القديم
    'no_manus_link': 'manus.computer' not in dec,
    'origin_fallback': 'origin' in dec,
}
for k, v in checks.items():
    status = '✓' if v else '✗'
    print(f'{status} {k}')

# البحث عن السياق حول كلمة التحقق للتأكد من أنها زر داخل حقل البريد
for m in re.finditer('تحقق', dec):
    ctx = dec[max(0, m.start()-60):m.start()+60].replace('\n', ' ')
    print('CTX:', ctx)
    if m.start() > 100000:
        break
