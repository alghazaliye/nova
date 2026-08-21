# حالة العمل — آخر تحديث (Render rebuild ناجح)

## مرفوع ومُطبَّق على Render ✓ (commit 38dbdd3 → 684e594 → 38dbdd3)
GitHub pushed: 38dbdd3. Render rebuilt ونشط (health 200، main.dart.js 3567483).

## الأيقونات ✓
- /assets/fonts/MaterialIcons-Regular.otf على Render = 200 ✓
- /assets/packages/cupertino_icons/fonts/CupertinoIcons.ttf = 200 ✓
- السبب: router.php معالجا assets (محلي) + Apache Alias /assets (docker)

## حذف المستخدم ✓ (endpoint جديد)
- DELETE /api/v1/admin/users/{id} — يعمل على Render (DELETE id=1,2 = 200 ✓)
- admin@nova-messenger.com / 738155861 — login يرجع {data: {token}} بـ role=admin
- registrations endpoint: /api/v1/admin/otp/registrations يرجع {"rows":[...]}
- code endpoint: /admin/otp/registrations/{id}/code — يعطي 404 "OTP_NOT_VIEWABLE" إذا الرمز تم التحقق منه (مهم: يجب إنشاء registration جديد قبل code)

## سجل الأحداث على Render (بعد rebuild):
- rebuild أحدث DB جديدة (لا وهميين — seed_production.sql عُدِّل ليُعطِّل أحمد/سارة)
- أُنشئ الحسابان +966738155861 (uid 1) و +966770105284 (uid 2) — ثم حُذفا للتو (DELETE 200×2) لإعادة إنشاء نظيف

## التحقق البصري النهائي ✓
- الأيقونات ظهرت على Render (شاشة الدخول NOVA Messenger مع أيقونة الدردشة وأيقونات تسجيل الدخول/إنشاء حساب وسهام >) ✓
- FontManifest.json: يطلب CupertinoIcons من /assets/packages/cupertino_icons/assets/CupertinoIcons.ttf = 200 ✓
- الحسابات أُنشئت: +966738155861 uid=3، +966770105284 uid=4 ✓ (التوكنات في /tmp/render_accounts.json)
- الاختبار الشامل على Render: محادثة 201، رسائل 201×2، typing POST 200، typing GET يظهر للمستخدم الآخر ✓

## الخطوة التالية (جارية):
1. /tmp/get_render_tokens.py: register → admin/otp/registrations rows → code → verify-otp → يحفظ /tmp/render_accounts.json (يحتاج registration جديد بعد الحذف — الآن صحيح)
2. /tmp/render_full_test.py: محادثة + رسائل + typing (بعد جلب التوكنات)

## ملاحظات مهمة:
- register 409 إذا الحساب موجود ومتحقق — الحذف ثم التسجيل يعمل
- seed_production.sql: سطر 116-118 (users وهميين) معطَّل بالتعليق
- Dockerfile Render: startup.sh — لا يحتاج تغيير
- Render URL: https://nova-wn25.onrender.com
