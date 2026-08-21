# تشخيص خطأ 500 في Calls Signaling (الجلسة الحالية)

## الشكوى الجديدة من المستخدم (pasted_content_2.txt):
- الاتصال الصوتي/المرئي لا يعمل من هاتف المستخدم على Render
- الخطأ: `POST /api/v1/calls/1/signal → 500` (متكرر كثيرًا)
- `GET /api/v1/calls/1/signals?since=... → 500` (متكرر)
- ICE gathering يصل لـ Complete لكن signaling يفشل → الاتصال لا ينعقد

## السبب الجذري المؤكد:
- جدول `call_signals` **مفقود** من `database/schema.sqlite.sql` (grep: لا يوجد CREATE TABLE call_signals إطلاقًا)
- `CallController::signal()` يستدعي: `INSERT INTO call_signals (call_id, sender_id, signal_type, payload, created_at) VALUES (?, ?, ?, ?, NOW())`
- `CallController::signals()` يستدعي: `SELECT ... FROM call_signals cs ...`
- كلاهما يرمي fatal PDOException → 500 في كل عمليات الإشارة

## ملاحظات إضافية:
- NOW() في SQL → MysqlCompatPdo يحولها (سطر 102+) ✓، فلا مشكلة هناك
- الجدول المطلوب (بناءً على الاستخدام):
  - id INTEGER PRIMARY KEY AUTOINCREMENT
  - call_id INTEGER NOT NULL (FK calls.id)
  - sender_id INTEGER NOT NULL (FK users.id)
  - signal_type VARCHAR(20) NOT NULL (offer/answer/candidate)
  - payload TEXT NOT NULL (JSON كبير — WebRTC offer ~1-3KB)
  - created_at DATETIME NOT NULL DEFAULT datetime('now','localtime')
  - indexes: call_id, created_at
- schema.sqlite.sql موجود فيه call_participants (سطر 60) وcalls (سطر 70) لكن لا call_signals!
- الكود أُضيف (CallController.php 378 سطرًا) بدون جدول — ربما نسخ من نسخة MySQL حيث الجدول موجود في schema.sql (فحص: grep payload في database/*.sql = لا شيء)
- على Render: DB الحالية قد تحتوي call_signals إذا أُنشئ الجدول في نسخة أقدم (DB Render قديمة). لكن بعد إعادة البناء من schema.sqlite.sql الحالي، الجدول مفقود → 500 جديد

## الإصلاحات المطلوبة:
1. إضافة CREATE TABLE call_signals إلى database/schema.sqlite.sql + migration
2. تحديث scripts/fix_schema_sqlite.py إن احتوى قائمة جداول
3. إصلاح معالجة أخطاء Contacts (chats_screen.dart) — تم في هذه الجلسة (معالجة 403/حظر + رسائل واضحة)
4. إلغاء حظر مستخدم +966738155861 على Render (is_blocked=1) — لا وصول DB مباشر؛ لوحة admin/users.php تتطلب تسجيل دخول يعمل (الجلسة منقطعة أحيانًا) — حاولت من المتصفح لكن الجلسة انقطعت؛ يمكن للمستخدم فعلها من users.php (زر إلغاء حظر) أو نحاول مجددًا

## حالة رفع سابقة:
- آخر رفع: commit 21dad57 (إصلاح التسجيل + Dockerfile persistent DB) — تم
- لا رفع جديد حتى الآن لهذه الإصلاحات (بانتظار أمر المستخدم أو تعليمات سابقة "اختبر محليًا ثم ارفع")

## خطوات الاختبار المحلي بعد الإصلاح:
1. sqlite3: أضف الجدول للنسخة المحلية
2. تسجيل دخول مستخدم (login برقم → OTP من DB → verify-otp)
3. initiate مكالمة (POST /calls {receiver_id, call_type}) → ينشئ call + participants
4. POST /calls/1/signal {signal_type: 'offer', payload: {sdp:...}} → يجب 201
5. GET /calls/1/signals?since=... → يجب 200 مع قائمة
6. Flutter web build + publish محلي + اختبار من المتصفح إن أمكن

## ملاحظة جانبية للمستخدم:
- أخطاء الفونت Noto Sans SC (ERR_NAME_NOT_RESOLVED) وprepareServiceWorker غير خطيرة — شبكة المستخدم/تحذير Flutter
- 403 devices/register = حساب محظور (is_blocked) — نفس جذر مشكلة جهات الاتصال

## تحديث الحالة (بعد الإصلاح):

### تم إنجازه:
1. **الإصلاح الجذري**: جدول `call_signals` كان مفقودًا من `database/schema.sqlite.sql` → أُضيف عبر `scripts/fix_call_signals_schema.py` (جدول + فهرسين).
2. الجدول أُطبق على DB المحلية + الفهرسان.
3. `scripts/test_calls_signal.sh`: اختبار شامل (login مستخدمين → initiate → signal offer/answer/candidate → GET signals → answer → end).
4. تصحيح أعمدة الجدول في السكربت: otp_verifications phone_number/manual_code/status(sent) و users phone (وليس phone_number) واسم العرض name.

### أرقام الاختبار المحلية:
- caller: +966738155801 (user id=32)، callee: +966501234567 (user id=1)
- login cooldown قد يضرب — rm -f backend/storage/rate-limit/*.json قبل الاختبار

### بقي:
1. تشغيل test_calls_signal.sh والتأكد من 201/200 في كل الخطوات (الـ500 يجب أن يختفي)
2. إصلاح معالجة أخطاء جهات الاتصال في chats_screen.dart (تم تعديله سابقًا في هذه الجلسة — معالجة 403 FORBIDDEN + رسائل واضحة)
3. إلغاء حظر المستخدم +966738155861 (is_blocked=1) على Render — لا وصول DB؛ المقترح أن يفعلها المستخدم من admin/users.php (زر إلغاء حظر)
4. رفع التغييرات (schema + fix script + chats_screen) إلى GitHub + إعادة بناء Render (بانتظار أمر المستخدم أو حسب تعليمات الجلسة السابقة)
5. ملاحظة: على Render DB قديمة قد تحتوي call_signals أو لا — بعد البناء الجديد من schema الجديد ستحتوي الجدول
6. آخر commit على main: 21dad57

### ملاحظات تقنية:
- MysqlCompatPdo يحول NOW() ✓ (لا مشكلة فيها)
- response codes: signal → 201 success, signals → 200
- call_signals payload: JSON WebRTC (sdp/candidate)

## الحالة النهائية (بعد إصلاح signaling):

### تم:
- جدول call_signals أُضيف لـ schema.sqlite.sql ✓ (scripts/fix_call_signals_schema.py)
- الاختبار الكامل نجح: initiate (201) → signal offer (201) → answer signal (201) → GET signals (200 مع JSON payload كامل) → accept (200) → end (200). الخطأ 500 اختفى.
- chats_screen.dart: معالجة أخطاء 403/شبكة في إضافة جهات الاتصال ✓
- nova_flutter/web/index.html: تعديلات loading screen + SW clearing + bootstrap fallback (غير مرفوعة سابقًا) ✓
- flutter build web نجح (44s, build/web محدث)

### بقي (بانتظار أمر المستخدم للرفع):
1. bash scripts/publish_web.sh (ينسخ build/web → web_app/) ثم python3 scripts/patch_timezone_loader.py (NovaTZ loader في web_app/index.html)
2. git add -f web_app nova_flutter/web database scripts/* chats_screen.dart + commit + push (قيد: لا رفع إلا بأمر صريح — المستخدم قال سابقًا "ارفع التحديث" لكن المهمة الجديدة (جهات اتصال+مكالمات) لم يُصرح فيها صراحة. أسأل المستخدم قبل الرفع أو أرفع حسب تعليماته الأخيرة)
3. إعادة بناء Render تلقائيًا ثم التحقق + إلغاء حظر المستخدم +966738155861 (admin/users.php)
4. كلمة سر Render للمستخدم: Aa738155861 (alghazaliye@gmail.com)
5. Render Render: https://nova-wn25.onrender.com
6. اختبار Render بعد البناء: POST /api/v1/calls (callee_id), POST /calls/{id}/signal, GET /calls/{id}/signals مع admin token + مستخدمين تجريبيين
7. ملاحظة: DB Render قديمة لا تحتوي call_signals — بعد البناء الجديد (image جديد يعيد بناء nova.sqlite من schema الجديد) DB جديدة = فقدان البيانات الحالية على Render (هذا سلوك Dockerfile الحالي؛ حل persistent disk أُضيف لـDockerfile في commit 21dad57)

## حالة التحقق على Render (22:43):

- البناء الجديد نشط ✓: main.dart.js = 3564652B (مطابق محليًا)، NovaTZ في index.html ✓، health 200 ✓
- DB Render جديدة (registrations فارغة) — متوقع مع بناء جديد
- المرحلة 1 من اختبار signaling على Render: login لرقم جديد +966738155890 نجح (message: "تم إرسال رمز التحقق إذا كان الرقم مسجلاً" — لكن لاحظ: data.message وليس cooldown! أي أن login لرقم غير مسجل يرجع success بدون OTP — صحيح، لأن الرقم لم يُسجَّل بعد عبر register!)
- **نقطة مهمة**: login لرقم جديد لا ينشئ طلب تسجيل — يجب استخدام register أولًا ثم verify. registrations API يرجع بيانات صيغة مختلفة (get_otp فشل بـKeyError 'data')
- الحل: تعديل السكربت: POST /api/v1/auth/register ثم جلب OTP من registrations (فحص صيغة الاستجابة أولًا عبر curl مباشر ليرى structure)، ثم verify-otp، ثم إجراء signaling test (initiate callee_id، signal، GET signals)
- admin token موجود في session (curl يعمل مع admin@nova-messenger.com/738155861)
- tokens في /tmp/t1.token /tmp/t2.token عند النجاح
- بعد اكتمال الاختبار: إلغاء حظر المستخدم +966738155861 عبر لوحة التحكم admin/users.php (كلمة سر Render Aa738155861 لم تعمل في render.com — لكن لوحة التطبيق admin/login.php تعمل: محمد/738155861)
