# حالة تنفيذ مؤشر الكتابة (Typing Indicator) — 2026-08-21 02:26 (محدّث)

## نتائج Render (02:40)
- test script: /tmp/test_typing_render.py — يعمل بالكامل (register → admin code → verify → conversations POST {type:private,user_id}
- POST /conversations على Render: payload = {"type":"private","user_id":X} (وليس receiver_id!)
- conversations POST يرجع {data:{id, uuid, type, title, avatar, created_by}} — cid = d["id"]
- typing route 404 على Render حتى الرفع (متوقع)
- الحسابات الأخيرة على Render: 407 (id=15), 302 (id=16), conversation id=4 ✓
- ملاحظة: registrations القديمة (6,7) حالتها delivery_mode=auto → OTP_NOT_VIEWABLE، التسجيل الجديد manual قابل للعرض ✓

## بنية Flutter
- services/api_service.dart: static methods post/get/put/delete/uploadMultipart؛ class ApiService؛ http package
- screens/chat_screen.dart (شاشة المحادثة) — إضافة typing bar + timer
- nova_flutter في /home/ubuntu/nova_new/nova_flutter

## منجز (phase 1 شبه مكتمل)
1. database/schema.sqlite.sql: أُضيف جدول `typing_status` (conversation_id, user_id, expires_at, updated_at, UNIQUE(conv,user)) في نهاية الملف ✓
2. DB المحلية: الجدول أُنشئ فيها مباشرة ✓
3. backend/controllers/MessageController.php: أُضيفت setTyping() (سطر 806) وgetTyping() (سطر 834) — صلاحية 4 ثوانٍ، GET يرجع typing_users
4. backend/public/index.php: routeان POST/GET /conversations/{id}/typing (سطر 349-355) ✓

## المتبقي
- phase 1: اختبار endpoints محلية بـ curl (نحتاج tokens محلية — login يرجع masked بدون token؛ الحل: generate JWT يدويًا عبر JwtHelper؟ أو استخدام verify-otp كامل. الأسهل: POST /auth/login لرقم مسجل → masked، لكن masked login يرجع فارغًا! الأفضل: استخدام verify-otp مع legacy OTP 123456 للحسابات الموجودة أو استخدام حسابات Render tokens الموجودة)
  - ملاحظة: DB محلية timezone = Africa/Cairo (server)
  - accounts المحلية: user 1 (+966501234567 أحمد), user 2 (+966502345678 سارة) verified
- phase 2: Flutter — nova_flutter/lib/ (chat_screen.dart لعرض «يكتب الآن...» بدل subtitle في المحادثة، وchats_screen.dart لقائمة المحادثات) + ApiService typing methods
- phase 3: اختبار محلي
- phase 4: تسليم (الرفع فقط بأمر المستخدم)

## ملفات Render الحسابات (للاختبار على Render لاحقًا)
- /tmp/nova_tokens.json: tokens الحسابين على Render (+966738155861 id=3, +966770105284 id=4)
- Render DB جديدة (بعد rebuild) لا تحتوي typing_status إلا بعد البناء الجديد بالـschema المحدث
- رفع: git commit + push فقط عند أمر المستخدم

## أنماط مهمة
- MessageController methods الجديدة موجودة ✓
- index.php routes موجودة ✓ (local only — لم تُرفع)
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php من /home/ubuntu/nova_new يعمل ✓ health ok (Africa/Cairo)

## توضيح (ملغي): index.php على Render = المستودع 488858b (2 occurrences registrationsGetCode) — لا مشكلة في الـroutes!
- 404 لمسار /admin/otp/registrations/8/code على Render معناه: registrations id=8 غير موجود (DB جديدة، آخر id=7!) وليس route مفقود
- الحل: retry السكربت مع ids 6 و7 (المسجلة فعليًا)
- typing routes (POST/GET /conversations/{id}/typing) موجودة محليًا فقط — لن تعمل على Render حتى الرفع (488858b لا يحتويها) ✓ متوقع

## خطة التصحيح
1. اختبار typing كامل محليًا (الخادم 8080) — نحتاج tokens محلية: legacy OTP 123456 فشل (OTP_INVALID) — السبب: app_settings otp_<md5> لا يوجد للحسابات القديمة أو expires
2. حل بديل محلي: استخدام admin OTP verify guard: otp_required=0 أو رفع typing routes ثم استخدام Render GUI + verify من لوحة التحكم
3. أو: إنشاء سكربت seed يضيف otp_legacy للحسابات المحلية

## حسابات Render
- 898 (id=6), 899 (id=7) مسجلان non-verified في registrations id=6,7 — كوداتهم غير قابلة للجلب API (404)
- tokens محفوظة سابقًا في /tmp/nova_typing_tokens.json (لم يُنشأ بعد)
