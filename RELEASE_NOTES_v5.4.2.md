# الإصدار v5.4.2 — SQLite + مزود Gmail SMTP + فورم مزودي البريد المحسّن

## التاريخ: 2026-08-19

## 1) دعم SQLite لقاعدة البيانات (Default)
- طبقة جديدة `backend/config/MysqlCompatPdo.php` تترجم استعلامات MySQL تلقائيًا إلى SQLite:
  - `INSERT ... ON DUPLICATE KEY UPDATE` → `INSERT ... ON CONFLICT DO UPDATE` (SQLite 3.24+)
  - `INSERT IGNORE` → `INSERT OR IGNORE`
  - `NOW()` → `datetime('now','localtime')`
- `backend/config/database.php` يختار المحرك عبر `DB_TYPE` (الافتراضي `sqlite`، ويمكن `mysql`).
- أُضيفت جدولا `privacy_settings` و `message_deletions` الناقصان + فهارس فريدة لـ:
  `otp_rate_limits`, `message_reads`, `conversation_members`, `user_devices`, `message_deletions`, `privacy_settings`.
- `database/schema.sqlite.sql` — سكيمتا SQLite كاملة للنشر الجديد.
- `nova.sqlite` الفعلية لا تُرفع إلى Git (محمية في `.gitignore`)؛ تُنشأ عند النشر من السكيمتا.

## 2) مزود Gmail SMTP حقيقي (مُختبر ووصل فعليًا)
- `EmailOtpService::sendSmtp()` أعيد بناؤه: اتصال `tcp://` + STARTTLS يدوي + قارئ `fgets` موثوق مع timeouts.
- مزود `Gmail SMTP` أُضيف إلى `email_providers`: smtp.gmail.com:587 / TLS / alghazaliye@gmail.com.
- اختُبر الإرسال عبر لوحة التحكم (API `test`) — نجح 5 مرات ووصلت الرسائل فعليًا إلى صندوق الوارد.

## 3) تحسين فورم مزودي البريد في لوحة التحكم (`admin/email-providers.php`)
- تقسيم الحقول إلى قسمين واضحين: SMTP و HTTP REST مع عناوين.
- زر إظهار/إخفاء كلمة المرور (العين).
- أزرار تعبئة سريعة جاهزة: Gmail / Outlook / Yahoo / Hostinger (تملأ الخادم/المنفذ/التشفير).
- تنبيهات toast أنيقة بدل `alert()`.
- شريط إحصاءات (مزودات مفعّلة / إجمالي الإرسالات الناجحة / الفاشلة).
- أزرار اختبار سريع من الجدول مع modal إدخال بريد الاستقبال.
- إصلاحات: حقل hidden `pfId` كان مفقودًا، وإعادة تعيين الحقول بعد بناء قسم النوع.

## 4) جاهزية النشر على Render.com
- `Dockerfile` جديد: PHP 8.3 + Apache + pdo_sqlite (بدون MariaDB).
- `docker/000-default.conf`: DocumentRoot على `backend/public` مع FallbackResource لـrouter.php، وaliases لـ `/admin` و `/web_app`.
- `router.php`: `PROJECT_ROOT` ديناميكي (يعمل على أي مضيف).
- أضيفت `.htaccess` في `backend/public` و `admin`.

## المتغيرات البيئية المطلوبة على Render
| المتغير | القيمة |
|---|---|
| DB_TYPE | sqlite |
| JWT_SECRET | مفتاح سري طويل (تغييره عن الافتراضي) |
| APP_ENV | production |
| OTP_PROVIDER | smtp |
| ENCRYPTION_KEY | مفتاح AES-256-GCM سري (32 بايت hex) |
| GMAIL_SMTP_USERNAME | alghazaliye@gmail.com |
| GMAIL_SMTP_PASSWORD | كلمة المرور التطبيقية |

## ملاحظات
- OTP ما زال يعمل بـ dev mode محليًا (APP_ENV=development) — على Render تُعيّن `APP_ENV=production` فيغيّر.
- لا توجد بيانات حساسة في Git: `.env` و `nova.sqlite` محميتان في `.gitignore`.
