# حالة المهمة — محدث (آخر تحديث: بعد إصلاح reports + رفع GitHub)

## ما أُنجز:
- **ReportsController.php**: إصلاح الجذر — mismatch في عدد placeholders بين dupSql وparams (كان 3 placeholders و4 params)، + علامات "" المزدوجة + NOW() غير مدعومة. السكربت الآن: SELECT منفصل (3 أو 3 params حسب وجود msgId)، INSERT داخل transaction، status='pending' (single quotes)، datetime('now','localtime').
- **database.php**: PRAGMA read_uncommitted=1
- **index.php**: trace في global handler عند development
- **Response.php**: trace عند code>=500
- **commit 86ada88** مرفوع على GitHub main (من 66fd8ee)

## اختبارات محلية: 18/18 PASS
- /tmp/test_bundle_final.py: 18/18 (سبب reports الآن timestamp لتجنب duplicate عبر runs)
- /tmp/test_report_msg.py: message_id report 201, dup 409, no-message 201, list OK
- flutter analyze: 0 errors (1 error قديم في nova_web_state_web.dart dart:js_util — web-only معروف)

## اختبار Render (المشكلة الحالية):
- **Render DB تُمسح مع كل deploy** + sessions table تُفقد → التوكنات القديمة تُرفض "الجلسة غير موجودة أو منتهية" (AuthMiddleware يفحص sessions.revoked_at + exists)
- **fresh_session في render_smoke_bundle.py يعتمد على OTP registrations** — لكن بعد deploy الـregistrations تُمسح أيضًا! وget_render_tokens يعمل لأنه:
  - +966738155861: login مباشر يعمل (auth/login لا يعتمد على registrations؟ بل يعمل عبر OTP جديد)
  - +966770105284: register → 409 (مسجل) → OTP من registrations → verify
- المشكلة المكتشفة حديثًا: بعد تشغيل get_render_tokens (verified بنجاح)، التوكنات المحفوظة في /tmp/render_accounts.json تُرفض فورًا بـ"يجب تسجيل الدخول أولاً" حتى عبر curl مباشرة!
  - السبب المحتمل: auth/login أو verify-otp يعيدان توكن لكن session لا تُنشأ؟ أو JWT verify يفشل؟
  - يجب فحص: هل register بعد 409 يُعيد OTP جديد (cooldown)? هل verify يعيد success=True؟
- render_smoke_bundle: 21/23 — الفشلان typing seen by other وonline after heartbeat — كلاهما بسبب "تم إلغاء" لتوكن t2
- السكربت محسّن الآن: probe للتوكن المحفوظ وإعادة الإنشاء إن رُفض، واستخدام OTP جديد في offline-after-logout

## الخطوة التالية (الأساسية):
فحص لماذا توكنات get_render_tokens تُرفض فورًا على Render:
1. فحص response verify-otp كامل (status code + body)
2. فحص جدول sessions بعد verify
3. فحص AdminAuthController: ربما admin login/OTP logic مختلف الآن
4. ملاحظة: "يجب تسجيل الدخول أولاً" = لا Bearer header! قد تكون مشكلة في السكربت curl (الـheader الثاني يُلغي الأول؟ لا). في curl أعطيته هدرين — الثاني يُلغي الأول! إعادة الاختبار بهدر واحد فقط.

## أوامر جاهزة:
- local server: php -S 0.0.0.0:8080 backend/public/router.php (من /home/ubuntu/nova_new) — ما زال يعمل
- اختبار محلي: python3 /tmp/test_bundle_final.py (18/18)
- إنتاج: python3 /tmp/get_render_tokens.py ثم python3 /tmp/render_smoke_bundle.py (يجب 23/23)
- Admin: admin@nova-messenger.com / 738155861
- JWT_SECRET Render: nova-prod-secret-2026-9702924b74e9a6aa
