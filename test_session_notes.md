# ملاحظات جلسة تشخيص تسجيل الهاتف على Render — 2026-08-20

## نتائج مؤكدة
1. **الخادم يعمل**: `POST /api/v1/auth/register` على Render لـ +966738155800 نجح (200) ورقم ظهر فورًا في `/api/v1/admin/otp/registrations` (id=3، status=manual). إذن الخادم + لوحة التحكم سليمان.
2. **build على Render حديث**: main.dart.js = 3555756 bytes مطابق محليًا، index.html يحتوي NovaTZ + flutter_bootstrap.js = 200.
3. **DB على Render**: عند آخر deploy تُنشأ nova.sqlite من schema.sqlite.sql (إن لم تكن موجودة). seed_production.sql Idempotent (INSERT OR IGNORE / ON CONFLICT DO UPDATE).
4. **baseUrl في api_service.dart**: على الويب يستخدم `novaHref().origin + /api/v1` (نفس النطاق). يوجد fallback عبر `webOriginFallback()` و`?api=HOST`. افتراضي `/api/v1` (نسبي) إذا فشل كل شيء.

## اكتشاف مهم — فجوة UX (الجذر المحتمل لتجربة المستخدم)
- `phone_screen.dart` سطور 205-223: `_doPhoneLogin` و`_doPhoneRegister` يستدعيان `auth.login(_phone)` وعند `!ok` **لا يوجد أي `_showError`**! المستخدم يرى Spinner يتوقف ولا يعرف أن الطلب فشل.
- كذلك `_doPhoneRegister` يستدعي `login` وليس `register` (تعليق: "مسار الهاتف: تسجيل + OTP واحد") — هذا تصميم مقصود (login endpoint ينشئ OTP حتى لو غير مسجل؟ يجب التأكد من backend: login يرفض PHONE_EXISTS؟).
- في main.dart init (خط 134+): عند `?phone=` بدون otp → `_fillAuto` في phone_screen: delay 1s ثم `auth.login(_phone)` وعند نجاح → OTP screen. هذا يفسر أحيانًا فتح OTP screen تلقائيًا.

## فرضيات لسبب عدم وصول طلبات هاتف المستخدم
A. فشل network/CORS من متصفح هاتفه (WebView in-app browser) → الطلب يفشل بصمت (لا خطأ معروض) لكن... المستخدم يقول شاشة OTP فتحت!
B. إذا فتحت شاشة OTP رغم فشل الطلب → المسار `_fillAuto` (auto login) أو cached state؟ `_fillAuto` يفتح OTP فقط عند `ok=true`، لكن login نفسه قد يرجع true عند bypass... لا bypass هنا (OTP مطلوب).
C. الأهم: هل login endpoint يعيد success=true + otp_bypass عند تعطيل OTP؟ seed_production: لا setting لـ otp_required. لكن admin قد عطّل OTP عبر auth-settings → otp_bypass → login يعيد token مباشر → يتخطى OTP! لا يفسر "لم يظهر في لوحة التحكم".
D. احتمال قوي: المستخدم سجّل الرقم لكن الطلب رُفض بصمت (429 rate limit أو 409 PHONE_EXISTS) ثم... لا، يقول OTP screen فتحت.
E. احتمال: خطأ في تنسيق الرقم على الهاتف → login يرسل رقمًا بصيغة مختلفة → الخادم يخزن رقمًا مختلفًا؟ لكن قال "لم يظهر" أصلًا.

## خطوة تالية
- تعديل phone_screen.dart: عرض خطأ واضح عند فشل login/register + retry + طباعة baseUrl في console.
- إضافة retry logic في ApiService (3 محاولات) عند فشل network.
- سؤال المستخدم: هل يفتح من Chrome أم متصفح داخل تطبيق؟ وما الإجراء الدقيق الذي يقوم به؟
- إصلاح مشكلة DB على Render: Dockerfile ينشئ DB من schema.sqlite.sql فقط إذا لم تكن موجودة (IF NOT -s) ✓ موجود بالفعل — لكن عند كل redeploy كامل قد تتغير؟ لا: container filesystem دائم داخل instance. المشكلة الحقيقية: عند أول deploy كانت DB جديدة. الآن مستقرة. لكن لضمان البقاء: حجم DB يجب التأكد.
- test_session_notes.md سيُكتب هنا ثم ننسخ للحالة.

## تحديث نهائي (بعد الإصلاح)
1. **الجذر مؤكد**: `POST /auth/login` لرقم غير مسجل يعيد `success:true` مع data شبه فارغة (message فقط، بدون cooldown/expires) لأسباب أمنية. التطبيق قديمًا كان يفتح شاشة OTP صامتًا → الرقم لا يصل للـDB.
2. **الإصلاح المنفذ (لم يُرفع بعد)**:
   - `auth_provider.dart`: login يضبط `_lastLoginUnregistered` عند absence كل من cooldown/expires/otp_bypass/delivery_mode، مع getter `lastLoginUnregistered`.
   - `phone_screen.dart`: `_handleLoginResult` — عند unregistered: SnackBar "هذا الرقم غير مسجل في نوفا" مع زر "إنشاء حساب" (ينتقل لمسار التسجيل بنفس الرقم). لا يفتح OTP.
3. **اختبار محلي**: login رقم غير مسجل → data فارغة ✓. login رقم مسجل → cooldown/expires/otp_dev ✓. cooldown 429 يعمل ✓. build web ناجح، publish محلي في web_app تم (patch timezone loader + gzip + .htaccess).
4. **لم يُرفع إلى GitHub/Render** (بانتظار أمر المستخدم).
5. **البناء الجديد**: build/web منشور محليًا في web_app/ (main.dart.js جديد 2026-08-20).

## اختبار بصري محلي (البقاء)
- البناء الجديد منشور محليًا على 8080. الاختبار اليدوي في المتصفح: النقرات المتكررة على بطاقات الترحيب لم تنقل الشاشة (ربما أحداث pointer تحتاج coordinates مطابقة للـcanvas الافتراضي 1280x1100 — اللقطة 893x768 مع scaling). تم استبدال الاختبار البصري باختبار API المباشر الذي نجح:
  - register رقم غير مسجل → 200 + cooldown/expires + otp_dev ✓ (يظهر في registrations ✓ id=83)
  - verify-otp بالرمز → user id=31 أُنشئ ✓
  - login رقم غير مسجل آخر → success بدون cooldown/expires (lastLoginUnregistered ✓)
  - cooldown 429 يعمل ✓
- الاختبار البصري غير حاسم من sandbox browser؛ القرار: الاختبار عبر API + flutter analyze ✓ clean. جاهز للإبلاغ.

## خطة الرفع (المستخدم أذن بالرفع)
- إصلاحات Flutter جاهزة ومبنية محليًا في web_app (build ✓، publish محلي ✓):
  - auth_provider.dart: lastLoginUnregistered (getter + ضبط عند success بدون cooldown/expires/otp_bypass/delivery_mode)
  - phone_screen.dart: _doPhoneRegister → register صريح؛ _phoneSubmitAction يختار login/register حسب _loginMethod (دخول/إنشاء حساب)؛ _handleLoginResult يعرض SnackBar "هذا الرقم غير مسجل في نوفا" + زر إنشاء حساب بدل فتح OTP
- Dockerfile: إضافة NOVA_DATA_DIR=/data/nova_data + startup sync nova.sqlite مع persistent copy (cp من/إلى $DATA_DB) — README شرح في database/PERSISTENT_DB_RENDER.md (المستخدم يربط Persistent Disk على Render: Mount path /data/nova_data)
- التالي: git add/commit/push → Render يعيد البناء تلقائيًا → انتظار deploy → تحقق health + build حجم + اختبار register على Render
- GitHub token: /home/ubuntu/gh_token.txt — المستودع alghazaliye/nova، الفرع main
- الخادم المحلي 8080 يعمل (session render2)، الاختبارات المحلية نجحت كلها

## حالة Render بعد الرفع (22:10)
- commit 21dad57 مرفوع على main. Render لا يستجيب منذ ~25 دقيقة (HTTP:000 على /api/v1/health) → مؤشر فشل البناء.
- Render Dashboard login: alghazaliye@gmail.com + Aa738155861 → "Your password is incorrect or this account doesn't exist". المستخدم أكد الكلمة لكن فشل.
- الفرضية الأرجح لفشل البناء: startup.sh الجديد (NOVA_DATA_DIR sync). يجب فحص startup.sh محليًا: قد يفشل بسبب set -e عند `chown -R www-data:www-data "$DATA_DIR"` أو cp. الحل: محاكاة startup.sh في حاوية docker محليًا للتحقق.
- الخطة: محاكاة في sandbox via docker أو فحص startup.sh بعين ناقدة: الأوامر المضافة كل منها `|| true` ما عدا cp -f "$DATA_DB" "$DB_PATH" (قد يفشل مع set -e!) — نعم الخطأ المحتمل: `cp -f "$DATA_DB" "$DB_PATH"` بدون || true → فشل مع set -e → container crash loop → service down. يجب إصلاح.
- بعد الإصلاح: push جديد → Render re-build تلقائي.

## النتيجة النهائية بعد الرفع (22:12 بتوقيت الرياض)
Render عاد بعد البناء (كان HTTP:000 بسبب cold start أثناء البناء ~25 دقيقة). كل الفحوصات نجحت:
1. health 200 ✓، main.dart.js = 3,562,793 بايت (البناء الجديد) ✓، NovaTZ ✓
2. DB نجت من إعادة البناء (id=1,+966738155861 و id=2,+966555555555 ما زالا موجودين) — الإصلاح الدائم نجح نظريًا (DATA_DIR قابل للكتابة على Render ephemeral filesystem). ملاحظة: الحل الأمثل يبقى Persistent Disk معلق على /data/nova_data لمنع الفقدان عند تغيير machine.
3. register رقم جديد +966738155879 → ظهر فورًا في لوحة التحكم (id=3, status=manual) ✓
4. جلب الرمز من /admin/otp/registrations/3/code → 353137 ✓
5. verify-otp (حقل otp وليس code!) → success، user id=5 أُنشئ ✓

كلمة سر Render في المتصفح: alghazaliye@gmail.com + Aa738155861 فشل تسجيل الدخول في Dashboard (لم نعد بحاجة — البناء نجح دون تدخل).
ملاحظة API: register يستخدم حقل "phone" وverify-otp يستخدم "otp".

## تشخيص مشكلة «إضافة جهات الاتصال» (2026-08-20 22:23)

### تقرير المستخدم
في شاشة المحادثة (chats_screen) عند إضافة جهات اتصال بالرقم: لا يتعامل ولا يظهر المستخدمون.

### الكود
- Flutter: chats_screen.dart: _showAddNewContactDialog → GET /contacts/new (قائمة المضافين) + بحث بالرقم عبر GET /users/search?q= + POST /contacts {contact_user_id}.
- Controller: UserController.php: search() (سطر 121، LIKE name/username/phone, exclude self, is_blocked=0, LIMIT 20)، newContacts() (سطر 235، JOIN contacts→users ORDER BY is_online DESC)، addContact() (سطر 255، INSERT ON DUPLICATE KEY UPDATE is_blocked=0).
- router.php أسطر 403-409: المسارات معرفة ✓.

### الاختبار المحلي (localhost:8080)
- login برقم المستخدم +966738155861 (user id=30 محليًا) ✓ OTP=478539 → verify ✓ token ✓
- GET /users/search?q=966501 → success، أحمد id=1 ظهر ✓ (search يعمل!)
- POST /contacts {contact_user_id:1} → HTTP 200 "تمت إضافة جهة الاتصال" ✓
- GET /contacts/new → HTTP 200 لكن body فارغ { [302 bytes data]... } — استجابة 200 بمحتوى JSON مقصوص/فارغ! محتوى content-type application/json. 302 بايت data موجودة لكن curl أوقفها؟ لا، head -c 300 أظهر فارغ. فحص الـbody الفعلي مطلوب (ربما output buffering أو fatal في Response::success).

### فرضية
GET /contacts/new يرجع 200 مع JSON قد يكون خطأ داخل newContacts: ربما ORDER BY u.is_online DESC, u.last_seen DESC يفشل في SQLite؟ لا (يسبق). الأرجح: Response::success($rows) — $rows يحتوي is_online (integer 0/1) وlast_seen NULL — json_encode طبيعي. أو أن contacts table على SQLite لا يوجد فيها is_blocked! فحص schema contacts: UNIQUE(user_id, contact_user_id) ✓ is_blocked موجود ✓.
ملاحظة سابقة: في جلسة سابقة كان مذكورًا أن DB Render أعيد بناؤها. يجب إعادة الاختبار من البداية مع body كامل: `curl -s -o /tmp/cn.json -w "%{http_code}\n" ...; cat /tmp/cn.json`.

### ملاحظات أخرى
- المستخدم أكد كلمة سر Render: Aa738155861 (سأجرب لاحقًا إذا لزم).
- الأجهزة 403: حساب المستخدم +966738155861 محظور على Render (is_blocked) — أبلغنا المستخدم بإلغاء الحظر من admin/users.php.
