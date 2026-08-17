# حالة إصلاح صفحات لوحة التحكم (Admin) — آخر تحديث

## المشكلة الأصلية
المستخدم قال: pages لا تعمل في "القالب": plans, subscriptions, devices, monitoring, admins, api-docs + العلامة الزرقاء للموثقين.

## التشخيص
- السبب: الصفحات كانت تستخدم أعمدة/جداول قديمة غير موجودة بعد إعادة بناء schema.
- schema الفعلي: user_devices (id, user_id, device_uuid, device_name, platform, app_version, fcm_token, last_active_at, created_at, updated_at) — لا يوجد os/is_active/last_seen.
- plans: لا description/features/is_active — أضيفت بـ ALTER TABLE.
- user_subscriptions: expires_at (ليس ends_at)، لا activated_by.
- admins: name (ليس username).

## الإصلاحات المنفذة
1. plans.php: ends_at→expires_at + إضافة description/features/is_active إلى plans
2. subscriptions.php: ends_at→expires_at + إزالة activated_by
3. devices.php: إعادة كتابة كاملة — platform بدل os، حذف activate/deactivate (is_active غير موجود)، أزرار مسح الإشعار + حذف
4. monitoring.php: استبدال d.os→d.platform، last_seen→last_active_at، عدد أجهزة last_active_at>=NOW()-1H
5. admins.php: إزالة username من الاستعلامات + HTML + JS
6. UserController.php: إضافة is_verified إلى search() + getPublicProfile + newContacts
7. ConversationController.php: إضافة is_verified إلى getOtherParticipant()

## اختبار API
- /api/v1/users/search?q=أحمد يرجع is_verified:1 ✅
- /api/v1/users/me يرجع is_verified ✅
- المستخدمون التجريبيون: أحمد id=1 و سارة id=2 كلاهما is_verified=1 (موثقون)
- API is_verified موجود أصلًا في: me (getUserById)، verify endpoint، AdminController (توثيق)

## اختبار HTTP
- كل الصفحات: 200 بدون Warning/Fatal ✅ (plans/subscriptions/devices/monitoring/admins/api-docs + index/users/chats/settings)
- monitoring.php: أُصلح last_seen→last_active_at و number_format((float)$db_size) (خطأ سطر 306)
- api-docs.php: يعمل (محتوى ثابت) ✅
- admins.php: يعمل، يعرض المدير + نموذج الإضافة + الأدوار ✅

## Web rebuild
- تم إعادة بناء web WASM ونشر web_app (BUILD_OK + DEPLOY_OK)

## المتبقي
1. إصلاح اتصال GitHub: gh يعطي "Connector token is invalid" — تحتاج reconnect من المستخدم أو استخدام token replacement (GH_TOKEN placeholder يعاد كتابته عبر proxy لكن proxy يعيد كتابة Authorization header). v5.0.3 رُفعت سابقًا بنجاح. v5.0.4 (إصلاح OTP) وv5.0.5 (إصلاح admin pages) جاهزان للـcommit.
2. إرسال الروابط للمستخدم.

## روابط
- أحمد: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966501234567&otp=123456
- سارة: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966502345678&otp=123456
- لوحة التحكم: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/admin/ (admin@nova-messenger.com / Admin@1234)
