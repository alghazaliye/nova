# تفاصيل المتطلبات من الملفات المرفقة (بعد الفحص)

## file 10 — الخصوصية والهوية
إعدادات (كلها everyone/contacts/nobody افتراضات مذكورة):
- رؤية رقم الهاتف (افتراضي: contacts)
- رؤية الصورة الشخصية (افتراضي: everybody)
- رؤية الحالة/الحالة النصية status_text
- من يمكنه مراسلتي (everyone/contacts/nobody + مراعاة نظام الرسائل والطلبات)
- من يمكنه إضافتي إلى المجموعات (everyone/contacts/exclude)
- من يمكنه الاتصال بي (everyone/contacts/nobody) — Backend يتحقق قبل إنشاء المكالمة
- آخر ظهور (everyone/contacts/nobody) + متصل الآن (everyone/same_as_last_seen/nobody)
- إيصالات القراءة (bool)
- **هويتي الظاهرة** display_identity (6 خيارات: name+username / username only / phone / email / name+phone / name+email)
- الاستقلالية: طريقة التسجيل ≠ الهوية الظاهرة
- رؤية البريد (everyone/contacts/nobody) — أولوية أعلى من الهوية
- الأولوية: اسم جهة الاتصال المحفوظ (nickname) > هوية المستخدم الظاهرة. **يُقرر Backend**
- البحث: find_by_phone/find_by_email/find_by_username (each everyone/contacts/nobody)
- الحظر يتجاوز كل إعدادات الخصوصية (blocks موجود: user_id, blocked_user_id)
- GET /users/{id}/profile يرجع display_name,username,avatar,phone,email,last_seen,online,status فقط المسموح
- الحقول المطلوبة: privacy_phone,email,avatar,status,messages,calls,groups,find_by_phone,email,username,read_receipts,display_identity

## file 16 — البلاغات والحظر والاعتراضات
1. بلاغ من التطبيق: قائمة أسباب (محتوى مزعج/إساءة/انتحال/احتيال/محتوى غير مناسب/رسائل مزعجة/حساب مشبوه/أخرى) + تفاصيل اختياري
2. إرفاق آخر 5 رسائل مع المُبلَّغ عنه (اختياري) — report_attachments يحفظ message_id+conversation_id
3. admin: رقم البلاغ/المبلغ/المبلَّغ عنه/السبب/التفاصيل/التاريخ/الوقت/الحالة(جديد/قيد المراجعة/تم اتخاذ إجراء/مرفوض/مغلق)/الأولوية/عدد الإرفاقات/المشرف
4. تفاصيل البلاغ: معلومات المبلغ (اسم, user_id, هاتف/بريد حسب الصلاحية) + المبلَّغ عنه (اسم, id, حالة الحساب, تاريخ الإنشاء, التوثيق) + الرسائل المرفقة (مرسل/مستلم/رسالة/تاريخ/وقت/نوع/message_id)
5. زر حظر من البلاغ + تعليق مؤقت (24h/3d/7d/30d/مخصص) مع unban تلقائي عند الانتهاء
6. ban يمنع: login/API/رسائل/مكالمات/جلسات جديدة/يلغي sessions/أجهزة جديدة/OTP
7. منع التسجيل بنفس phone/email المحظور (Backend) — رسالة "هذا الحساب محظور..."
8. شاشة ban للمستخدم مع السبب + زر "تقديم اعتراض"
9. الاعتراض: user_appeals (user_id, phone, email, reason, status[pending/under_review/approved/rejected], reviewed_at/by, admin_note)
10. admin قسم الاعتراضات: قبول→unban+إشعار "تم قبول اعتراضك"، رفض→"تم رفض اعتراضك"
11. devices: الأجهزة المسجلة — existing /admin/devices.php works

## file 17 — المكالمات admin
- calls.php: total today, active, longest, audio/video split — يجب ترقية monitoring.php

## file 18 — الباقات والاشتراكات
- plans: type (free/verification/premium/pro/custom), enable_verification, verification_duration_days, price, currency, period, max_devices, features, badge
- الاشتراك: verified مستقلة المدة (subscription_expires) حتى لو الباقة انتهت تبقى حتى انتهائها، إشعار قبل الانتهاء
- payment: طلب ترقية من المستخدم، admin يراجع ويرفع الفاتورة (receipt) ويُفعّل — flow يدوي
- ربط الحسابات المميزة بمزايا: max_devices فعلي في الأجهزة، ميزات
- admin: subscriptions.php (تفعيل/إلغاء) + plans.php تعمل

## file 19 — إدارة المحادثات admin
- فلاتر: المرسل/المستلم/تاريخ/حالة قراءة/message ID/conversation ID/الحجم/النوع
- حذف إداري للرسالة + audit + soft delete tracking
- قائمة المحادثات الخاصة (private) بشكل صحيح — chats.php بسيط حاليًا

## file 20 — المرفقات
- MediaController: image/video/document upload + voice/contact/poll/location
- private chats, one-time media, multiple upload — فحص MediaController أولًا

## file 11 — الحالات (stories)
- text/photo/video, replies, interactions, views (status_views مع UNIQUE)
- story privacy: everyone/contacts/exclude/share_with/nobody + allow_by_phone toggle
- جهات الاتصال = جهات اتصال صاحب الحالة
- عدم كشف رقم الهاتف لمشاهدي الحالة
- Flutter: شاشة حالات كاملة (progress bars, swipe, pause, viewer list) — Flutter موجود جزئيًا (story screens)

## file 12 — Offline
- Hive local cache, pending messages queue, idempotent client_message_id — Flutter موجود جزئيًا؛ Backend supports idempotent messages (client_message_id)

## ملف 13 — لوحة الإدارة monitoring
- مراقبة حية: إحصائيات المستخدمين والرسائل والمكالمات والتسجيلات

## ملف 14 — المجموعات/جهات الاتصال
- أولوية الاسم: nickname المحفوظ > display_identity
- group privacy (من يضيفني)

## ملف 15 — الأجهزة
- user_devices + device_registrations موجودان كاملان + admin/devices.php

## حالة البنية الحالية (مؤكدة)
- privacy_settings: فقط show_last_seen(2/1/0), show_online_status(1/0), show_read_receipts(1/0) — يجب توسيع
- users: لا display_identity
- reports: id, reporter_id, reported_user_id, reason(text), details?, message_id?, conversation_id?, status(pending/reviewing/resolved/rejected), reviewed_by/at — بدون attachments/priority
- user_bans: غير موجود! users فيها is_blocked+blocked_at فقط (AuthController يتحقق من user_bans؟ وجدت banUser في AdminController يستخدم INSERT INTO user_bans!) — يجب فحص وجود الجدول في database/ أو runtime creation
- AdminController: banUser/unbanUser موجودان يستخدمان user_bans (insert/update unbanned_at)
- AuthController: يتحقق user_bans في login/OTP/register/device/QR
- contacts: user_id, contact_user_id, nickname, is_blocked — OK
- blocks: OK
- user_subscriptions: user_id,plan_id,status,starts_at,expires_at — OK
- plans: name,description,price,currency,period,max_devices,features(JSON),badge_color,is_active — لا plan_type/enable_verification
- media/attachments: فحص MediaController لاحقًا

## اكتشاف حرج: user_bans غير موجود في أي schema
- user_bans غير موجود في database/schema.sqlite.sql ولا database/schema.sql ولا في nova.sqlite المحلي
- لكن الكود يعتمد عليه: AdminController::banUser/unbanUser, AuthController (login/OTP checks بـSELECT reason FROM user_bans), admin/users.php
- Render يعمل لأن DB قديمة أُنشئ فيها الجدول يدويًا سابقًا — خطر كسر ban في أي DB جديدة!
- الحل: إضافة user_bans (+ user_appeals) إلى schema.sqlite.sql + migrateMissingColumns في database.php (CREATE TABLE IF NOT EXISTS)

## خطة التنفيذ التفصيلية
### المرحلة 2: Backend خصوصية
1. توسيع privacy_settings (migrations runtime: ALTER TABLE IF EXISTS...): show_phone(2), show_email(2), show_avatar(1), show_status_text(1), messages_from(1), calls_from(1), groups_from(1), find_by_phone(1), find_by_email(1), find_by_username(1), display_identity('name_username'), story_privacy(1), allow_by_phone(1) + جدول privacy_exceptions إن لزم (استثناءات)
2. UserController: getPublicProfile($id, $viewerId) — display_name حسب أولوية + فلترة phone/email/avatar/last_seen/online حسب الإعدادات والحظر
3. search: فلترة find_by_* + عدم إظهار phone في النتائج
4. newContacts: نفس الفلترة
5. ConversationController getOtherParticipant: فلترة
6. CallController: checks messages_from/calls_from قبل create
7. StoryController: story_privacy + allow_by_phone
8. privacyGet/privacyUpdate: كامل الإعدادات
9. display_identity = اسم الحساب الظاهر: name+username(1)...(6)
### المرحلة 3: Flutter
- privacy_screen: كل الأقسام (منظمة: البيانات الظاهرة/آخر ظهور/إيصالات القراءة/البحث/المحادثات والمكالمات/الحالة)
- contact names: backend يرجع contact_name في كل endpoints تعرض المستخدمين
### المرحلة 4: البلاغات
- reports schema: إضافة details/attachments_data JSON(message_id,conversation_id) + priority (low/medium/high) أو جدول report_attachments
- ReportsController: accept message_ids[] + reason codes validation
- AdminController: banUser (durable) + new suspendUser(duration) + unban
- new endpoints: POST/GET /appeals + admin endpoints
- admin/reports.php ترقية كاملة + admin/appeals.php صفحة جديدة
- Flutter: ban screen (read-only error من API) + appeal screen
### المرحلة 5: الباقات
- plans: plan_type, enable_verification, verification_duration_days
- subscriptions: verified حتى subscription_expires
- payment flow: payment_requests جدول + admin review + webhook none (manual)
- notifications للاشتراك
- device limit enforcement في devices/register
### المرحلة 6: admin
- monitoring.php ترقية
- chats.php ترقية
- فحص media/admin/storage
