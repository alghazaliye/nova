# حالة JWT_SECRET على Render (2026-08-20 23:06)

المفاجأة الإيجابية: **JWT_SECRET مثبت بالفعل كمتغير ثابت على Render** وقيمته `nova-prod-secret-2026-9702924b74e9a6aa` (ثابت، ليس REPLACE_ME).

هذا يعني أن سبب خطأ 401 الشامل الذي واجهه المستخدم ليس تغيير JWT_SECRET، بل فقدان قاعدة البيانات نفسها (جدول users/registrations فارغ — الرقمان +966738155861 و+966770105284 لم يعودا موجودين).

المتغيرات البيئية الموجودة على Render: APP_ENV, DB_TYPE, ENCRYPTION_KEY, GMAIL_SMTP_PASSWORD, GMAIL_SMTP_USERNAME, JWT_SECRET (ثابت ✓), OTP_ENCRYPTION_KEY, OTP_PROVIDER.

## الاستنتاج المتبقي
بما أن JWT_SECRET ثابت، فإن 401 عند المستخدم = token قديم يشير إلى مستخدم/جلسة محذوفة من DB الجديدة. الحل: إعادة إنشاء الحسابين ثم إعادة تسجيل الدخول.

## الخطة المتبقية (الخيار 1 المعتمد من المستخدم)
1. سكربت حماية DB في Dockerfile: على الـstartup، إذا /data/nova_data/nova.sqlite غير موجود أنشئه من schema، وإذا موجود انسخه إلى backend/config/nova.sqlite (حماية من الفقدان أثناء حياة الحاوية الحالية محدودة — لا تحل مشكلة deploys لكن تحافظ بين restarts)
2. الرفع: commit + push → Render auto-deploy
3. إعادة إنشاء الحسابين عبر register + OTP admin + verify
4. التحقق من الرسائل والآخر ظهور
