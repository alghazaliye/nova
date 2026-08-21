# حزمة الإصلاحات الكاملة — متطلبات الملفات الستة المرفقة

## مصدر المتطلبات
- /home/ubuntu/upload/pasted_content_4.txt — إصلاحات قائمة المحادثة + WebSocket/FCM + آخر ظهور + المكالمات/WebRTC (لم تُقرأ كاملة في السياق الحالي، خلاصتها في أسفل هذا الملف)
- /home/ubuntu/upload/pasted_content_5.txt — "نتيجة نهائية" قائمة المحادثة احترافية (26 بند)
- /home/ubuntu/upload/pasted_content_6.txt — إصلاح صفحة الخصوصية (22 بند)
- /home/ubuntu/upload/pasted_content_7.txt — إصلاح آخر ظهور Last Seen/Online (15 بند)
- /home/ubuntu/upload/pasted_content_8.txt — إصلاح المستخدمين الجدد/جهات الاتصال/المحادثات/المكالمات (18 بند)
- /home/ubuntu/upload/pasted_content_9.txt — نظام إدارة الأجهزة وتعدد الأجهزة (17 بند)

## قواعد التنفيذ الصارمة (من رسالة المستخدم الأخيرة)
1. قراءة المشروع كاملًا وفحص المتطلبات قبل أي تعديل.
2. خطة أولويات + تحديد تعارض/مخاطر قبل تعديل الكود.
3. كل الإصلاحات محليًا أولًا: المستخدمون وجهات الاتصال والمحادثات، الرسائل للجديد والقديم، WebSocket وFCM، آخر ظهور، المكالمات/WebRTC، الخصوصية، إعدادات لوحة التحكم.
4. ممنوع كسر ميزة تعمل أو حلول وهمية.
5. اختبار Backend وFlutter + flutter analyze + flutter test.
6. اختبار فعلي بين مستخدمين جدد وقدامى: جهات اتصال، محادثات، رسائل، آخر ظهور، مكالمات.
7. إصلاح أي Regression.
8. مراجعة git diff — لا أسرار.
9. Commit + Push GitHub فقط بعد نجاح كل الاختبارات.
10. نشر Render + Smoke Test على الإنتاج.
11. تقرير مختصر: ما أُصلح، الملفات، نتائج الاختبارات، Commit، حالة GitHub والاستضافة.

## ملخص المتطلبات التفصيلية

### pasted_content_5 — قائمة المحادثة احترافية (بنود مهمة)
- 17. Backend Security: كل عملية تتحقق في Backend (calls=false → FEATURE_DISABLED حتى POST مباشر). Block/Report/Clear chat/Search/Temporary messages/Media/Groups/Calls.
- 18. SQLite: افحص schema أولًا، لا جداول مكررة، migration آمن.
- 19. تحديث الواجهة بدون إعادة تشغيل (state management الحالي).
- 20. دعم RTL.
- 21. لا تغير التصميم.
- 22. Loading/Success/Error بحالات عربية واضحة.
- 23. ربط WebSocket للتغييرات المهمة (Block/Unblock/Message deleted/expired/Chat settings).
- 24. اختبار كل زر: عرض جهة اتصال، بحث، وسائط/روابط/مستندات، كتم، سمة، رسائل مؤقتة، المزيد، إبلاغ، حظر+إلغاء، مسح دردشة (لدى المستخدم فقط دون حذف رسائل الطرف الآخر)، نقل الدردشة.
- 25. flutter analyze + php -l + Release Build.
- 26. لا onPressed: () {} — كل زر وظيفة حقيقية.

### pasted_content_6 — صفحة الخصوصية (شاشة بيضاء لا نهائية)
- تشخيص كامل من Flutter → API → PHP → SQLite.
- لا Loading لا نهائي: Loading/Success/Empty/Error + «إعادة المحاولة».
- Timeout 15 ثانية.
- JSON متوافق بين Flutter وPHP.
- إعدادات افتراضية إذا لا يوجد سجل (من إعدادات المشروع/لوحة التحكم).
- تحديث إعداد → نجاح تثبيت / فشل رجوع للقيمة السابقة + رسالة خطأ.
- اختبارات: مسجل/غير مسجل/توكن خاطئ (401)/offline.
- flutter analyze + php -l + فحص SQLite.

### pasted_content_7 — آخر ظهور Last Seen/Online
- تنسيق عربي: «متصل الآن»/«منذ لحظات»/«منذ X دقيقة» (مفرد/مثنى/جمع)/«منذ ساعة/ساعتين/X ساعات»/«منذ يوم/يومين/X أيام»/تاريخ ووقت محلي «20 أغسطس، 10:35 م».
- تحديث آخر ظهور عند: تسجيل دخول، فتح، عودة من خلفية، نشاط، heartbeats كل 30-60 ثانية.
- Online = وجود اتصال WebSocket صالح.
- UTC في التخزين، ISO 8601 في API، تحويل محلي في Flutter.
- دالة formatLastSeen واحدة مركزية.
- Presence مرتبط بـWebSocket الموجود.
- تحديث لحظي في قائمة المحادثات وشاشة المحادثة.
- احترام إعداد الخصوصية last_seen (الجميع/جهات الاتصال/لا أحد).
- لا نصوص ثابتة «منذ فترة».
- اختبار الحالات الزمنية كلها + timezone.

### pasted_content_8 — المستخدم الجديد لا يرى السابقين/الرسائل لا تصل
- التشخيص الكامل: users/contacts/conversations/conversation_members/messages/calls/call_signals/WebSocket/FCM.
- عدم الخلط user_id/contact_id/conversation_id.
- إرسال أول رسالة: get-or-create conversation في transaction (7 خطوات).
- منع محادثات مكررة (A→B = B→A).
- WebSocket يرسل إلى receiver_id.
- FCM: user_id → fcm_token، الرسالة تُحفظ DB أولًا.
- تحديث قائمة المحادثات عند أول رسالة (preview كامل).
- GET /conversation/by-user/{otherUserId} أو ما يعادله.
- المكالمات: caller_id/callee_id صحيحان، signaling يصل.
- 10 اختبارات (جديد→قديم، قديم→جديد، Online/Offline، مغلق، متتالية، فتح من الطرف الآخر، صوتية، فيديو، إعادة فتح).
- المعيار: المستخدم الجديد يُضاف كجهة → يظهر → أول رسالة → conversation تلقائية → DB → WebSocket/FCM → تظهر للطرفين → الرد يعمل عكسيًا → المكالمات تصل.

### pasted_content_9 — نظام إدارة الأجهزة (كبير)
- إعداد «الحد الأقصى للأجهزة لكل حساب» في لوحة التحكم.
- تسجيل الدخول من جهاز جديد: لا منع مباشر، عرض طرق تحقق حسب الإعدادات (تأكيد من جهاز آخر، SMS، Email OTP، موافقة الإدارة).
- QR للربط: token عشوائي مؤقت استخدام واحد، لا يحتوي secrets، انتهاء صلاحية، replay protection.
- أجهزة تابعة حقيقية: نفس المحادثات/الرسائل/جهات/إشعارات/آخر ظهور/إعدادات/مكالمات (عبر Backend+WebSocket+FCM الحالي).
- شاشة إعدادات → الأجهزة المرتبطة (اسم، نوع، OS، آخر نشاط، تاريخ ربط، حالة، تسجيل خروج).
- خروج من الرئيسي = إنهاء التابعة. إزالة تابع = revoke + invalidate + disconnect + FCM stop.
- تجاوز الحد → رسالة + «إدارة الأجهزة».
- إعدادات: حد الأجهزة، enable linked devices، طرق التحقق (5 طرق قابلة للتفعيل/التعطيل).
- جداول: user/device/session/device_link/login_request/verification مع device_type/platform/is_primary/status/revoked_at.
- Audit log لإضافة/إزالة الأجهزة.
- 24 اختبار.
- لا local-only، كل شيء حقيقي مربوط بالـBackend.

### pasted_content_4 — (الجزء الأول من سلسلة الإصلاحات)
شامل: إصلاح قائمة المحادثة (عرض جهة الاتصال، بحث، وسائط/روابط/مستندات، كتم، سمة الدردشة، الرسائل المؤقتة، المزيد، إبلاغ، حظر، مسح الدردشة، نقل الدردشة)، WebSocket وFCM، آخر ظهور Online/Last Seen، المكالمات الصوتية والمرئية WebRTC، الخصوصية وإعدادات لوحة التحكم وربطها بالتطبيق.

## حالة المشروع الحالية (مهم)
- المشروع: /home/ubuntu/nova_new (Flutter + PHP/SQLite backend)
- Render: https://nova-wn25.onrender.com — commit أحدث 684e594+ (typing + icons fix + delete user)
- الحسابان على Render: +966738155861 (uid 3) و+966770105284 (uid 4)
- seed_production.sql: المستخدمون الوهميون معطلون (أحمد/سارة)
- ميزات مكتملة ومحفوظة: typing indicator (POST/GET /conversations/{id}/typing)، حذف مستخدم (DELETE /admin/users/{id})، router.php + Apache Alias لـ/assets/packages و/assets
- admin: admin@nova-messenger.com / 738155861
- JWT_SECRET Render: nova-prod-secret-2026-9702924b74e9a6aa (محلي: nova-dev-secret-key-2026-xyz)
- Flutter SDK: /home/ubuntu/flutter/bin (v3.38.1)
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php
- DB محلية: backend/config/nova.sqlite (تحتوي typing_status، المستخدمون المحليون أحمد +966501234567 / سارة +966502345678 بحالة verified، legacy)
- registrations API يرجع {rows: [...]} وليس {data}
- POST /conversations payload: {type: "private", user_id: X}
- POST /messages payload: {body: "...", client_message_id: "uuid"}
- OTP pipeline جديد: otp_verifications بـotp_hash (bcrypt)، admin code endpoint: GET /admin/otp/registrations/{id}/code
- app_settings (source of truth): allow_calls, allow_groups, allow_stories, story_duration_hrs, edit_time_limit_minutes, delete_time_limit_minutes, message_type_default, fcm_enabled + auth_*/otp_* + timezone
- drift في schema: schema.sqlite.sql lines 552 privacy_settings يستخدم show_last_seen/show_online_status/show_read_receipts بينما UserController يتوقع last_seen_visibility/photo_visibility/status_visibility/read_receipts
- UserController.appSettings() (lines 294-319) لا يعيد edit/delete/disappearing defaults
- MessageController.enforceMessageTimeLimit() يستخدم MySQL-style UNIX_TIMESTAMP/TIMESTAMPDIFF (مكسور SQLite؟ يجب فحص)
- CallController: calls ليس به callee_id (لدينا call_participants)، initiate/signals/incoming/answer/reject/end + FCM
- privacy_settings جدول موجود (add-on) مع show_last_seen/show_online_status/show_read_receipts
- index.php يحتوي _diag endpoint مؤقت (lines 141-231) — يجب إزالته قبل الرفع
- admin/settings.php lines 11-30: keys القابلة للتعديل
- StoryController: expires_at > NOW()، _now() helper موجود (استخدمه بدل NOW() مباشرة)

## حالة تقنية مهمة مكتشفة (بعد الفحص)
- لا يوجد WebSocket server حقيقي في المشروع: كل التحديثات live تعتمد على polling (chats_screen: poll كل 5s + heartbeat كل 30s، chat_screen: poll كل 3s + مكالمات كل 2s، incoming call timer كل 2s، call_service signal polling كل 2s)
- FCM: FCMHelper موجود backend/helpers/FCMHelper.php — يتطلب nova-firebase-sa.json (قد لا يوجد على Render) — فحص isEnabled
- الملفات المرفقة 4-7: P4=ربط settings.php بالميزات (الحالات/المجموعات/المكالمات/تعديل/حذف الطرفين/اختفاء)، P5=وظائف قائمة المحادثة الـ11 بند، P6=صفحة الخصوصية، P7=آخر ظهور LastSeen
- P8 (ملف 8) + P9 (ملف 9) كما لُخص أعلاه
- app_settings keys الحالية في settings.php: allow_calls, allow_groups, allow_stories, story_duration_hrs, edit_time_limit_minutes, delete_time_limit_minutes, message_type_default + auth_*/otp_* + timezone (لدينا خطاف)
- جدول privacy_settings منفصل: show_last_seen/show_online_status/show_read_receipts

## خطة التنفيذ المقترحة (مرتبة بالأولوية)
P1 — أساسات الحزمة (blocking):
1. فحص schema + إصلاح drift (privacy_settings) وschema.sqlite.sql
2. إصلاح آخر ظهور (last_seen/heartbeat/online): backend + formatLastSeen Flutter (تتقاطع مع privacy)
3. إصلاح المستخدمين الجدد/جهات الاتصال/المحادثات/المكالمات (pasted_8)
4. WebSocket events + FCM (ربط presence/block/edit/delete/message.new بـWebSocket الحالي)
5. Backend security enforcement (app_settings flags على كل endpoint)
P2 — صفحات وميزات:
6. صفحة الخصوصية (pasted_6)
7. قائمة المحادثة/جهات الاتصال (pasted_4 + pasted_5)
8. إعدادات لوحة التحكم → ربطها بـFlutter (auth-settings موجود، الباقي: settings)
P3 — ميزات جديدة كبيرة:
9. نظام إدارة الأجهزة المتعددة + QR (pasted_9) — يتطلب جداول جديدة + UI
10. اختبار شامل: flutter analyze + flutter test + backend tests + smoke local + git diff
11. Commit + Push + Deploy Render + re-create accounts + production smoke
12. تقرير نهائي
