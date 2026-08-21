# خطة تنفيذ المتطلبات المتبقية (files 10-20)

## منجز حتى الآن
- [x] schema.sqlite.sql: أُضيف user_bans + user_appeals + report_attachments
- [x] database.php: migrateMissingTables() أُضيف + يُستدعى بعد migrateMissingColumns
- [x] php -l لكل الـcontrollers الرئيسية: لا أخطاء syntax

## المرحلة 2: Backend خصوصية وهوية (file 10 + 14)
1. **privacy_settings توسيع** (ALTER TABLE runtime في migrateMissingColumns):
   show_phone=2 (contacts), show_email=2, show_avatar=1, show_status_text=1,
   messages_from=1, calls_from=1, groups_from=1, find_by_phone=1, find_by_email=1,
   find_by_username=1, display_identity='name_username', story_privacy=1, allow_by_phone=1
2. **UserController**:
   - getPublicProfile: عرض فقط المسموح (display_name بحسب display_identity + priority contact name، phone/email/last_seen/online/avatar فقط إذا مسموح). إذا viewer حُظر من صاحب الحساب: لا شيء.
   - search/contacts/newUsers: فلترة find_by_* + لا phone في النتائج
   - contact_name في كل الردود التي تعرض مستخدمين (أولوية: nickname من contacts > display_identity)
3. **ConversationController**: index/show — no raw phone, اسم حسب first-name/identity، online يحترم show_online_status
4. **CallController**: تحقق calls_from (everyone/contacts/nobody) + blocks قبل create
5. **StoryController**: story_privacy (1=all,2=contacts,3=close_friends/exclude?,4=share_with,5=nobody) + allow_by_phone
6. **PrivacyController (UserController privacyGet/Update)**: كامل الإعدادات الجديدة
7. groups_from: ConversationController createGroup يتحقق قبل الإضافة
8. الحظر يتجاوز كل شيء (blocks جدول موجود — المستخدمان يحجبان بعضهما)

## المرحلة 3: البلاغات والحظر والاعتراضات (file 16)
1. reports: إضافة details/priority لجدول reports؟ — reports موجود (id,reporter,reported,message_id,conversation_id,reason,description,status,reviewed_by/at). إضافة: priority (low/medium/high), reason_code (قائمة أسباب)
2. ReportsController: POST يقبل {reported_user_id, reason, description, message_ids:[...]} → يقرأ آخر 5 رسائل مع reported_user_id ويحفظها في report_attachments
3. AuthController: ban enforcement موجود في login/verifyOtp/bypass لكن:
   - register: يجب فحص blocked identities (phone/email محظورين ← "هذا الحساب محظور...")
   - ban يمنع: login/API/رسائل/مكالمات/جلسات جديدة/OTP → إضافة checks في MessageController(إرسال), CallController, AuthMiddleware?, device register
   - ban screen: API يرجع ACCOUNT_BANNED error مع reason + appeal support
4. suspendUser: مؤقت (24h/3d/7d/30d/custom) عبر suspend_until في user_bans + unban تلقائي عند login
5. appeals: POST/GET /appeals (user) + AdminController: GET/POST appeals/{id}/review {status:approved|rejected, admin_note}
   - approved → unbanUser + إشعار notification "تم قبول اعتراضك، يمكنك تسجيل الدخول"
   - rejected → notification "تم رفض اعتراضك"
6. notifications للاعتراضات

## المرحلة 4: admin UI
- admin/reports.php: ترقية — تفاصيل، إرفاقات، ban/suspend، reject/resolve، أولوية، فلاتر
- admin/appeals.php: صفحة جديدة (قائمة + مراجعة)
- admin/users.php: زر ban (موجود في lines 34-41 لكن بدون UI كامل؟) — إضافة suspend duration + appeals view
- admin/chats.php: pagination + فلاتر (sender/receiver/date/read/message_id/conv_id) + حذف إداري + audit
- monitoring.php: ترقية (users today/week/month + %، messages today/yesterday/week، online %، calls)
- admin/stories.php فحص إن وجد (file 11: story privacy admin review)

## المرحلة 5: الباقات (file 18)
- plans: إضافة plan_type (free/verification/premium/pro/custom), enable_verification (0/1), verification_duration_days
- user_subscriptions: verified تبقى حتى expires حتى لو الباقة تغيرت (already exists start/expires)
- payment_requests: جدول + POST /subscriptions/request (user) + admin review + receipt upload + activate
- notifications: قبل انتهاء الاشتراك (cron? — no cron on Render! → check at login + daily on first request)
- device limit: في /devices/register تحقق max_devices من اشتراك المستخدم

## المرحلة 6: Flutter
- privacy_screen: كل الإعدادات الجديدة (أقسام: هويتي الظاهرة، رؤية البيانات، آخر ظهور، إيصالات، البحث، المحادثات/المكالمات/المجموعات، الحالة)
- ban/appeal screen: عند ACCOUNT_BANNED يعرض سبب + زر اعتراض
- contact names: العرض من contact_name في الردود

## اختبارات ثم رفع
- محلي: حزمة كاملة 18+، flutter analyze (0 errors)، php -l
- Render: عبر browser console (IP sandbox محجوب) أو direct بعد انتظار — الحسابات تُمسح مع deploy → /tmp/get_render_tokens.py ثم smoke

## أوامر
- الخادم: cd /home/ubuntu/nova_new && php -S 0.0.0.0:8080 backend/public/router.php
- flutter: cd nova_flutter && /home/ubuntu/flutter/bin/flutter analyze
- admin: admin@nova-messenger.com / 738155861، OTP via /admin/otp/login + /admin/otp/registrations/{id}/code
- Render: https://nova-wn25.onrender.com — DB تُمسح مع كل deploy

## نقاط كود مهمة (من فحص الملفات)
- AuthController.php: register@18-109 (phone uniqueness@70-75، لا فحص blocked identity)، verifyOtp@111-229 (ban check@200-209 من user_bans)، login@231-292، bypass@425-485 (ban@468-476)
- ConversationController.php: index@17-84 (title=raw u.name@57-72)، createGroup@86-184 (لا groups_from check)، getOtherParticipant@263-339
- CallController.php: initiate@18-73 (فقط allow_calls flag)، incoming@173-215
- StoryController.php: index@17-55 (privacy all|contacts فقط)، create@57-164
- admin/users.php: block@22-57 (user_bans INSERT/UPDATE في catch فارغ — يتسامح مع غياب الجدول)، listing@82-95
- UserController: privacyGet/privacyUpdate موجودة (show_last_seen/online/read_receipts) — يجب توسيع
