# حالة إصلاح اختفاء الأيقونات (2026-08-21)

## التشخيص الكامل
- التطبيق يستخدم Icons.* (MaterialIcons) + cupertino_icons فقط — لا حزم أيقونات خارجية.
- Flutter web يطلب الأصول من base href + 'assets/...' حيث base href = '/' → يطلب:
  - /assets/fonts/MaterialIcons-Regular.otf
  - /assets/packages/cupertino_icons/assets/CupertinoIcons.ttf
  - /assets/FontManifest.json
  - /assets/AssetManifest.bin
- على Render: كل هذه المسارات = 404 (المسارات الصحيحة الوحيدة هي /web_app/assets/...)
- النتيجة: التطبيق يعمل لكن الأيقونات تختفي (سندboxes) — هذه مشكلة موجودة على Render منذ مدة
- محليًا (router.php): أُضيف معالجان (2.4b, 2.4c) لخدمة /assets/ و /assets/packages/ من web_app/assets/ — **تم اختبارهما محليًا: كلها 200 ✓**

## المشكلة: Render يستخدم Apache، وrouter.php المحلي لا يكفي
- Dockerfile: COPY web_app → /var/www/html/web_app/ فقط، وDocumentRoot = backend/public، FallbackResource = router.php
- على Render، router.php القديم لا يخدم /assets/... — يصل index.php → 404
- التعديل الجديد على router.php محليًا فقط — لم يُرفع بعد

## خطة الإصلاح النهائي (بعد الرفع):
- على Render سيخدم router.php الجديد الأصول بشكل صحيح (FallbackResource يعالج كل ما ليس ملفًا في DocRoot)
- بديل أضمن: إضافة Alias /assets → /var/www/html/web_app/assets في docker/000-default.conf — أكثر وضوحًا ولا يعتمد على FallbackResource. هذا أفضل وأضمن!

## توصية: عدّل docker/000-default.conf بإضافة:
```
Alias /assets /var/www/html/web_app/assets
<Directory /var/www/html/web_app/assets>
    Require all granted
</Directory>
```
(قبل Alias /web_app)

## تحديث (مكتمل):
- أضفت Alias /assets → /var/www/html/web_app/assets في docker/000-default.conf (لـRender/Apache)
- حذف route debug وdebug log من index.php — route DELETE /admin/users/{id} بقي
- router.php محلي: معالجا 2.4b (packages) و2.4c (assets) يعملان — 200 محليًا ✓
- لا حاجة لإعادة بناء Flutter

## ملاحظات:
- Dockerfile لا ينسخ router.php صراحةً — بل COPY backend/ كاملًا → router.php الجديد سيرفع مع COMMIT
- لا حاجة لإعادة بناء Flutter (الأصول موجودة أصلًا) — البناء الحالي صحيح
- يجب حذف الـdebug route (/__dbg/route-test) من index.php قبل الرفع
- تعديل index.php: route DELETE /admin/users/{id} (userDelete) ما زال يعطي 401 محليًا — لم يُحل بعد! يجب إكماله: ربما نفس مشكلة Render السابقة: index.php على الخادم المحلي قد يحمل نسخة قديمة (أعدنا تشغيل الخادم 00:02 — index.php معدّل 00:02) لكن 401 استمر. debug log لم يظهر → route لم يطابَق. لكن __dbg/route-test نجح على نفس الخادم الجديد...
- استنتاج معلق لحذف المستخدم: route DELETE يطابَق على الخادم الجديد (dbg نجح) لكن userDelete يرمي 401 — يجب فحص userDelete مباشرة (ربما nova_get_auth_header يفشل على الخادم؟ لا — admin/otp/registrations نجح). الاحتمال: route في index.php المحلي لم يُحمَّل لأن php -S يعمل من عملية shell قديمة — لكن أعدنا تشغيله. يجب إعادة اختبار بعد رفع index.php التعديلات النهائية.
