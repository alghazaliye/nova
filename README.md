# NOVA Messenger

تطبيق مراسلة متكامل (شبيه واتساب) مبني بـ **Flutter** للويب والأندرويد، مع خلفية **PHP 8.3 + Apache + MariaDB**، ولوحة تحكم إدارية، ونظام إشعارات FCM.

> الإصدار الحالي: **v4.0.0** — يتضمن دعم الوسائط الكامل (صور، فيديو، تسجيل صوتي)، تسجيل الدخول التلقائي، وواجهة RTL كاملة.

---

## 1. بنية المشروع

```
nova/
├── backend/            # الخادم (PHP 8.3)
│   ├── config/         # إعدادات قاعدة البيانات وJWT
│   ├── controllers/    # منطق API (رسائل، محادثات، مكالمات، وسائط)
│   ├── public/         # نقطة الدخول index.php (Apache)
│   └── storage/        # المرفقات المرفوعة (attachments, voices, media)
├── nova_flutter/       # تطبيق Flutter (ويب + أندرويد)
│   ├── lib/screens/    # الشاشات: محادثات، محادثة، مكالمات، حالات، إعدادات
│   ├── lib/services/   # خدمات API والمصادقة
│   └── android/        # مشروع أندرويد (APK/AAB)
├── admin/              # لوحة تحكم الإدارة (PHP)
├── database/           # مخطط قاعدة البيانات وبيانات تجريبية
└── screens/            # لقطات الشاشة التوثيقية
```

---

## 2. التشغيل على سيرفر (Linux + Apache أو XAMPP)

### المتطلبات

| المكوّن | الإصدار المطلوب |
| --- | --- |
| Apache | 2.4+ مع mod_rewrite |
| PHP | 8.3 (مع mysqli، **mbstring**، curl) |
| MariaDB / MySQL | 10.5+ |
| Flutter | 3.x (لبناء التطبيق — اختياري إذا رفعت build جاهزة) |

> تنبيه: بدون `php-mbstring` تظهر أخطاء قاتلة تمنع عرض لوحة الإدارة. على أوبونتو:
> `sudo apt install php8.3-mbstring php8.3-mysqli php8.3-curl`

### خطوات التثبيت

1. **رفع الملفات:** انسخ مجلدات `backend` و`admin` و`database` و`app` (ناتج بناء Flutter) إلى `htdocs` (أو `/var/www/html/nova`).

2. **إنشاء قاعدة البيانات:**

   ```sql
   CREATE DATABASE nova CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   ثم استورد `database/schema.sql` ثم `database/seed.sql` للبيانات التجريبية.

3. **تحرير `backend/config/.env`:**

   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=nova
   DB_USER=root
   DB_PASS=كلمة_السر
   APP_ENV=production
   JWT_SECRET=سر_طويل_وعشوائي_64_حرف
   STORAGE_URL=http://your-domain/nova/backend
   STORAGE_PATH=/var/www/html/nova/backend/storage
   OTP_ENABLED=true          # false = تسجيل دخول فوري بدون رمز
   OTP_PROVIDER=test         # غيّره لمزود SMS حقيقي عند الاشتراك
   ```

4. **إعداد Apache:** ملف `backend/.htaccess` مرفوع بالفعل ويوجّه طلبات `/api/v1` إلى `public/index.php`. تأكد أن `AllowOverride All` مفعّل في إعدادات Apache.

5. **الصلاحيات:**

   ```bash
   chmod -R 755 backend/storage
   ```

### التحقق

```bash
curl https://your-domain/nova/backend/api/v1/health
# المتوقع: {"success":true}
```

---

## 3. بناء التطبيق (Flutter)

```bash
cd nova_flutter
flutter pub get
flutter build web --release          # للنشر على الويب → nova_flutter/build/web
flutter build apk --release          # APK
flutter build appbundle --release    # AAB لمتجر Google Play
```

> ملاحظة: ملف `google-services.json` مهيأ على `com.nova.messenger` مع Firebase Project: nova-messenger-265fb، وهو مستثنى من Git ويجب نسخه يدويًا قبل البناء.

---

## 4. الحسابات التجريبية

| الدور | الرقم / الاسم | كلمة المرور |
| --- | --- | --- |
| مستخدم 1 | +966500001111 (محمد الفزالي) | 123456 |
| مستخدم 2 | +966500001112 (مستخدم NOVA) | 123456 |
| مدير الإدارة | محمد | 738155861 |
| مدير تطوير (بعد seed) | admin@nova-messenger.com | Admin@1234 |

### الدخول السريع من الويب (تسجيل دخول تلقائي + عمق رابط)

```
https://your-domain/nova/app/?phone=%2B966500001111&otp=123456
https://your-domain/nova/app/?phone=%2B966500001111&otp=123456&chat=5      # محادثة معينة
https://your-domain/nova/app/?phone=%2B966500001111&otp=123456&tab=calls  # تبويب المكالمات
```

قيم `tab` المدعومة: `chats` (المحادثات) · `calls` · `status` (الحالات) · `contacts` (جهات الاتصال) · `settings` (الإعدادات).

---

## 5. واجهات API الأساسية

| المسار | الطريقة | الوظيفة |
| --- | --- | --- |
| `/api/v1/auth/login` | POST | تسجيل دخول بالرقم، إرجاع JWT |
| `/api/v1/otp/verify` | POST | تحقق من رمز OTP |
| `/api/v1/conversations` | POST | إنشاء محادثة (`{"user_id": n}`) |
| `/api/v1/conversations` | GET | قائمة محادثات المستخدم |
| `/api/v1/conversations/{id}/messages` | GET/POST | جلب/إرسال رسائل |
| `/api/v1/conversations/{id}/media` | POST | رفع صورة/فيديو (حقل `attachment` + `client_message_id` + `retry_count`) |
| `/api/v1/messages/voice` | POST | رفع تسجيل صوتي (حقل `voice`، حد 10MB، أنواع: webm/ogg/mp4/mpeg/wav) |
| `/api/v1/media/{path}` | GET | تنزيل الوسائط (يدعم HTTP 206 Range للتشغيل التدريجي) |
| `/api/v1/calls/outgoing` | POST | بدء مكالمة صوت/فيديو |
| `/api/v1/calls/incoming` | GET | فحص المكالمات الواردة (polling كل 2 ثانية) |
| `/api/v1/stories` | GET/POST | القصص (Stories) |
| `/api/v1/health` | GET | فحص حالة الخادم |

**رأس المصادقة:** `Authorization: Bearer <jwt_token>`

**مثال رفع تسجيل صوتي (curl):**

```bash
curl -X POST https://your-domain/api/v1/messages/voice \
  -H "Authorization: Bearer $TOKEN" \
  -F "voice=@recording.mp3;type=audio/mpeg" \
  -F "conversation_id=5" \
  -F "client_message_id=1234567890" \
  -F "retry_count=0"
```

---

## 6. الميزات

- **المراسلة:** نص، صور، فيديو، تسجيلات صوتية مع حالة sent/read وعرض الفقاعات RTL.
- **تسجيل الصوت بأسلوب واتساب:** اضغط مطولًا المايك للتسجيل، ارفع إصبعك للإرسال.
- **المكالمات:** سجل مكالمات صوت/فيديو (جارية، منتهية، فائتة، مرفوضة) مع إشعار فوري.
- **الحالات (Stories):** إضافة وعرض مثل واتساب.
- **الإعدادات:** الملف الشخصي، الأجهزة المتصلة، الخصوصية، الرسائل المختفية، FCM، تسجيل الخروج الفوري.
- **RTL كامل:** الترتيب مطابق لواتساب (المحادثات أولًا يمين، الإعدادات آخرًا يسار).
- **لوحة الإدارة:** مستخدمون، مديرون، خطط التوثيق، المزودون (SMS/بريد)، سجل الأخطاء، إعدادات OTP والمحادثة الافتراضية.

---

## 7. ملاحظات الإنتاج

- غيّر `JWT_SECRET` إلى قيمة عشوائية قوية ولا تشاركه.
- لا تستخدم `OTP_PROVIDER=test` في الإنتاج؛ اربط مزود SMS حقيقي (أو فعّل OTP عبر لوحة التحكم).
- احصر `CORS_ALLOWED_ORIGINS` في النطاقات الموثوقة فقط.
- أنشئ نسخة احتياطية لقاعدة البيانات قبل أي ترحيل جديد.
- نقل الصوت/الفيديو المباشر (WebRTC) يعمل بشكل أفضل على الأجهزة الفعلية من المتصفح الآلي.

---

*جميع الحقوق محفوظة — NOVA Messenger © 2026*
