# حالة debug حذف المستخدم (2026-08-21)

## حقائق مؤكدة:
1. JwtHelper::verify يقبل توكن admin محليًا ✓ (payload: user_id=1, role=admin, admin_role=super_admin, exp ok)
2. admin/otp/registrations يعمل بتوكن admin محليًا (200) — لأنه يستخدم authenticateAdmin
3. GET admin/users/1/admin و DELETE admin/devices: 401 — لأنها تستخدم AuthMiddleware::authenticate() الذي يتطلب sessions JOIN users (توكن admin ليس session) — سلوك قديم موجود، ليس بسبب تعديلاتنا
4. JWT_SECRET المحلي: nova-dev-secret-key-2026-xyz (في backend/.env)
5. Render JWT_SECRET: nova-prod-secret-2026-9702924b74e9a6aa (env متغير)
6. توكن admin يُولد بنجاح — verify يعمل

## المشكلة الغامضة: DELETE /api/v1/admin/users/2 → 401 "الجلسة غير موجودة أو منتهية"
- Route الجديد في index.php سطر 588: if (preg_match('#^/admin/users/(\d+)$#', $uri, $m) && $method === 'DELETE') → userDelete()
- userDelete المحدث لا يستدعي AuthMiddleware::authenticate() إلا في فرع !$isStandaloneAdminJwt
- payload يحتوي role=admin → isStandaloneAdminJwt=true → لا يستدعي AuthMiddleware
- لكن 401 لا يزال! رسالة «الجلسة غير موجودة أو منتهية» هي رسالة Response::unauthorized الثانية في JwtHelper::verify
- استنتاج: JwtHelper::verify يفشل (يرجع null) في سياق request الخادم!
- السبب المرجح: $_ENV['JWT_SECRET'] غير محمَّل في سياق php -S؟ لا — admin/otp/registrations نجح بتوكن نفس adminApiLogin وهو يستخدم JwtHelper::verify أيضًا!

## تناقض: نفس JwtHelper::verify نجح في admin/otp/registrations (يعمل بتوكن admin) وفشل في DELETE!
- الفرق: admin/otp/registrations يُعالج في AdminOtpController (يُحمَّل عبر route خاص)
- DELETE /admin/users/2: هل route جديد فعلاً يُطابَق؟ هل index.php الذي يعمل به الخادم قديم؟
- index.php معدّل 23:59 اليوم — الخادم php -S أعيد تشغيله بعد التعديل؟
- أوقفنا الخادم وأعدنا تشغيله الساعة الحالية — يجب أن يحمل أحدث index.php
- الاحتمال القوي: route في index.php لا يُطابَق بسبب شيء قبله — هل يوجد: if (str_starts_with($uri, '/admin') ...) early return؟
- أو: $method === 'DELETE' متغير $method غير معرف؟ فحص بداية index.php: كيف يُعرَّف $method
- أو: route يطابق لكن userDelete يرمي 401 من مكان آخر: nova_get_auth_header()؟ لا — nova_get_auth_header مجرد قراءة header
- أو: JwtHelper::verify يفشل لأن header Authorization يُمرر بشكل مختلف في DELETE؟ curl يرسله صحيحًا
- اختبار تالي مقترح: إزالة مؤقتًا شرط method===DELETE لمعرفة هل route يُطابَق أصلًا، أو echo قبل verify

## ملاحظات أخرى:
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php (أعيد تشغيله مع error_log=/tmp/php_errors.log — لا أخطاء ظاهرة)
- ملف test_jwt_verify.php يعمل verify بنجاح CLI
- DB المحلية users كثيرة (اختبارات): id=1,2 seed أحمد/سارة، id=30 +966738155861، etc.
- DB Render: id=3 (+966738155861), id=4 (+966770105284), id=5 (+966770123456 اختباري)، id=1,2 seed
