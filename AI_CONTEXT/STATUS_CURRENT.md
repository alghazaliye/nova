# الحالة الحالية — 2026-08-19 (مرحلة Render.com)

## مكتمل ومؤكَّد:
1. v5.1.0 مرفوع على GitHub (commit a21cf28 + tag v5.1.0 + release + APK): https://github.com/alghazaliye/nova/releases/tag/v5.1.0
2. Docker image nova-messenger:511 تعمل كاملًا ✅:
   - مزود test enabled + 8 صلاحيات otp + 14 إعداد otp في DB
   - API verify/register يعمل، admin 200، web_app 200، API 200
   - ملاحظة exec: يجب استخدام mysql -h127.0.0.1 داخل container (skip-name-resolve)
3. OTP system كامل يعمل محليًا على 8080

## المتبقي:
1. **Render.com** (جارٍ الآن):
   - الحساب الجديد: أنشئ في https://dashboard.render.com/register بـ alghazaliye@gmail.com / Aa738155861 — المستخدم يتولى المتصفح لإكمال hCaptcha (رسالة "Please try again" ظاهرة)
   - بعد التسجيل يتم النقر على Create Account ثم إنشاء Web Service
   - خيار نشر: "New +" → Web Service → GitHub alghazaliye/nova → Docker (Render يقرأ Dockerfile) → port 8080
   - Env vars: MYSQL_DATABASE=nova, MYSQL_USER=nova_user, MYSQL_PASSWORD=render2026, JWT_SECRET=nova-render-secret-key-2026-xyz, OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456, APP_URL=<render url>
   - Instance type: Starter (free tier مجاني لكن ينام بعد 15 دقيقة)
   - **تحذير مهم**: Render free-tier لا يدعم Docker images الكبيرة 1.2GB (حد ~1GB أو وقت build 20 دقيقة) — إذا فشل: استخدام GitHub deploy mode (Render يقرأ Dockerfile) بدل image، أو رفع image على ghcr.io.
   - **الأفضل**: نشر عبر GitHub repo (Render يبني الـimage بنفسه) — اختر "GitHub" source + Docker environment.
2. بعد النشر: إرسال الروابط للمستخدم (API + admin + web_app)

## الخطة القادمة (بعد Render) — من NEW_REQUESTS.md:
1. **Offline-First Flutter** (pasted_content_2.txt — 961 سطر): Drift+SQLite local DB, Outbox queue, Sync engine (incremental sync), media storage, network detection, idempotency — يعمل بدون إنترنت
2. **Auth Phone+Email** (pasted_content_3.txt — 712 سطر): طرق تسجيل/دخول مستقلة ON/OFF من لوحة التحكم، Email OTP مع SMTP/REST مزودو email جدد (types: smtp, http_rest)، RBAC, Audit
   - settings keys: auth_phone_registration, auth_email_registration, auth_phone_login, auth_email_login, auth_username_login + otp_phone_*/otp_email_*
   - API: GET للإعدادات العامة، register/login يرفضون حسب الإعدادات (backend-level وليس UI فقط)

## معلومات تشغيلية:
- DB محلي: nova@127.0.0.1:3306 nova_user/nova2026
- الخادم المحلي: cd /home/ubuntu/nova_new && nohup php -S 0.0.0.0:8080 backend/public/router.php
- Docker test: sudo docker run -d --name nova-test -e MYSQL_DATABASE=nova -e MYSQL_USER=nova_user -e MYSQL_PASSWORD=nova2026 -e JWT_SECRET=nova-dev-secret-key-2026-xyz -e OTP_BYPASS=123456 -e OTP_PROVIDER=test -e OTP_TEST_CODE=123456 -p 9090:8080 nova-messenger:511
- Admin: admin@nova-messenger.com / Admin@1234 على /admin/
- GitHub: alghazaliye/nova
