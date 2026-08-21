# تشخيص خطأ 401 الشامل على Render (من كونسول هاتف المستخدم)

## الأعراض (pasted_content_3.txt)
كل هذه الطلبات رُفضت 401 Unauthorized:
- GET /api/v1/calls/incoming (×38)، GET /api/v1/calls (×21)، GET /api/v1/conversations (×15)،
  GET /api/v1/contacts/new (×10)، GET /api/v1/conversations/2/messages (×7)، POST /api/v1/heartbeat (×2)

## الحقائق الثابتة
- admin login (POST /api/v1/admin/otp/login) يعمل 200 ويرجع token ✓
- جدول registrations على Render فيه صف واحد فقط (+966738155897 من اختباري) — يعني DB Render فُقدت!
  (المستخدمون +966770105284 و +966738155861 و+966738155891 و+966738155892 لم يعودوا موجودين)
- JWT_SECRET يعتمد على $_ENV['JWT_SECRET'] — إن لم يُعيّن ثابت في Render env، قد يتغير مع كل rebuild → كل tokens القديمة تبطل
- AuthMiddleware: يطلب JWT + سطر sessions في جدول sessions + expires_at > NOW() + غير blocked
- login masked: رقم غير مسجل → لا token (مقصود)، رقم مسجل → يرجع masked success بدون token؟ (لم يظهر token في الرد — masked login يمنح OTP فقط؟ الأهم: verify هو الذي يمنح token)

## الاستنتاج
المستخدم كان مسجلًا سابقًا وحفظ token في localStorage، وبعد rebuild الجديد:
1. إما JWT_SECRET تغيّر → Verify يفشل → 401
2. وإما DB فقدت جدول sessions/users → 401
+ مشكلة "الرسائل لا تظهر والآخر ظهور خطأ" تفسرها DB الجديدة الفارغة:
  حسابا +966770105284 و+966738155861 لم يعودا موجودين على Render أصلًا → لا رسائل ولا آخر ظهور!

## الحل المطلوب
1. ربط Persistent Disk على Render: /data/nova_data (NOVA_DATA_DIR) لحماية nova.sqlite من الفقدان
2. إعادة إنشاء الحسابات على DB الجديدة (بأرقام المستخدمين نفسها) حتى تعود الرسائل/الآخر ظهور
3. إعادة تسجيل الدخول في التطبيق (token جديد) — المستخدم سيضطر لإعادة الدخول بعد كل rebuild بدون persistent DB
4. فحص JWT_SECRET كـ Render env variable ثابت

## ملاحظة الرفع
- GitHub: commit 2d874bd مرفوع ✓ (إصلاح العداد التنازلي)
- Render: البناء التلقائي بدأ من GitHub؛ main.dart.js المنشور = 3564826B (لم نتأكد بعد أنه نسخة إصلاح العداد — البناء wasm? لا، js)
- مهم: web_app/ المحلي بعد flutter clean+build كان قيد العمل وقت انقطاع; build/web الأخير (22:57) main.dart.js 3564826 لا يحتوي نص «الوقت المتبقي» في grep — يجب التحقق مجددًا بعد إعادة البناء
- index.html يحتاج <base href="$FLUTTER_BASE_HREF"> (placeholder جديد مطلوب في Flutter 3.38) + dynamic-base script يجب إعادته بعد البناء

## نتائج السكربت /tmp/check_render_db.py (بعد البناء الجديد)
- admin login 200 ✓، registrations rows=1 (رقمي التجريبي +966738155897 فقط) — أرقام +966770105284 و+966738155861 و+966738155891/+966738155892 غير موجودة في registrations
- login masked لرقم +966738155861 و+966770105284 يرجع masked success (يموه وجود الرقم)
- لا يوجد API admin لعد users مباشرة (404 على /api/v1/admin/users) — لكن conversations 401 + registrations فارغة يؤكد DB أُعيدت من الصفر
- admin login يعمل لأن جدول admins منفصل عن users

## الخلاصة النهائية
السبب الجذري لمشكلة المستخدم (401 شامل + لا رسائل + آخر ظهور خاطئ): **DB على Render أُعيدت من الصفر مع البناء الجديد** لأن Persistent Disk غير مربوط على /data/nova_data في Render dashboard.
الحل:
1. ربط Persistent Disk: Render dashboard → nova → Disks → + Add Persistent Disk → Mount Path = /data/nova_data → Service = nova → Deploy سيُعيد البناء، وبعدها DB دائمة
2. إعادة إنشاء حسابي +966770105284 و+966738155861 عبر register → OTP من لوحة التحكم → verify (استخدم /tmp/render_signal_test2.py كنموذج)
3. تثبيت JWT_SECRET كـenv variable ثابت في Render (Settings → Environment Variables) منعًا لبطلان كل tokens مع كل rebuild
4. المستخدم يعيد تسجيل الدخول في التطبيق بعد ذلك

## مستخدمو Render السابقون (لإعادة الإنشاء إن لزم)
- +966770105284 (user جديد للمستخدم)، +966738155861 (حساب المستخدم الرئيسي)، +966738155891، +966738155892 (أرقام تجريبية)
- id=1 admin محمد (admin@nova-messenger.com) — لا يزال يعمل (super_admin) → DB ليست فارغة كليًا؟ registrations rows=1 فقط لكن admin موجود — إذن فقدت users؟ admin login يعمل يعني جدول admins منفصل. فحص: users table على Render فارغة فعليًا
