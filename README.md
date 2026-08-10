# NOVA Messenger

منصة مراسلة تتكون من PHP API، لوحة إدارة، قاعدة MariaDB، وتطبيق Android.

## متطلبات PHP

المشروع يعتمد على الامتدادات التالية، ويجب تثبيتها قبل التشغيل:

```
php-cli php-mysql php-mbstring
```

> تنبيه: بدون `php-mbstring` تظهر أخطاء قاتلة (مثل `Call to undefined function mb_substr()`) مما يمنع عرض محتوى لوحة الإدارة وصفحات الواجهة بالكامل.

## إعداد قاعدة البيانات المحلية

قاعدة المشروع الحالية:

- الاسم: `nova`
- الخادم: `127.0.0.1`
- المنفذ: `3307`
- المستخدم: `root`

استورد [database/schema.sql](database/schema.sql) ثم [database/seed.sql](database/seed.sql) عند الحاجة إلى بيانات تجريبية.

## تشغيل PHP وApache

ضع المشروع داخل `C:\xampp\htdocs` وشغّل Apache وMariaDB من XAMPP. ملف الإعداد المحلي موجود في:

```text
backend/.env
```

اختبار API:

```text
http://localhost:8080/nova-messenger/backend/public/api/v1/health
```

النتيجة الصحيحة تكون JSON وبها `"status":"ok"`.

## لوحة الإدارة

```text
http://localhost:8080/nova-messenger/admin/login.php
```

حساب التطوير بعد seed:

```text
البريد: admin@nova-messenger.com
كلمة المرور: Admin@1234
```

هذه كلمة مرور تطوير فقط ويجب تغييرها قبل النشر.

## فحص PHP

```powershell
$php = "C:\xampp\php\php.exe"
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & $php -l $_.FullName }
```

## تطبيق Android

افتح:

```text
android/NOVAMessenger
```

تمت إضافة ملفات Gradle الأساسية وملف version catalog. غيّر `API_BASE_URL` في `app/build.gradle.kts` حسب عنوان الخادم. عند استخدام Android Emulator يكون عنوان Apache على جهاز الكمبيوتر عادةً:

```text
http://10.0.2.2:8080/nova-messenger/backend/public/api/v1/
```

إشعارات Firebase تحتاج ملف `google-services.json` الخاص بمشروعك، وهو مستثنى من Git ولا يوضع في المستودع.

## ملاحظات الإنتاج

- غيّر `APP_ENV` إلى `production` وولّد `JWT_SECRET` قويًا.
- لا تستخدم `OTP_PROVIDER=test` في الإنتاج؛ يجب ربط مزود SMS حقيقي.
- احصر `CORS_ALLOWED_ORIGINS` في النطاقات الموثوقة.
- غيّر كلمة مرور الإدارة وأنشئ نسخة احتياطية قبل أي ترحيل.
- WebSocket وWebRTC وFCM تحتاج خدمات خارجية وإعدادًا مستقلًا؛ ملفات الواجهة وحدها لا توفر هذه الخدمات.
