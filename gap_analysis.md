# تحليل الفجوات — المتطلبات مقابل الحالة الحالية (بعد commit 86ada88)

## الحالة الحالية (ما نُفذ في الجلسات السابقة)
- reports: ReportsController.php (POST/GET)، منع التكرار، rate limit، SQLite WAL، admin/reports.php (جدول بسيط بحالات pending/reviewing/resolved/rejected + تحديث حالة)
- user_devices: نظام كامل (register/fcm/toggle/delete عبر API + admin/devices.php)
- plans/subscriptions: admin/plans.php + admin/subscriptions.php تعمل (plan: name/description/price/currency/period/max_devices/features/badge_color + activation = is_verified=1)
- calls: admin/calls.php سجل كامل (calls + call_participants + status/duration)
- chats: admin/chats.php قائمة المحادثات
- audit_logs: يعمل + admin/audit.php pagination
- privacy: privacy_settings (show_last_seen/show_online_status/show_read_receipts) + endpoints PUT/GET /privacy، UserController canSee*، privacy_screen.dart
- settings enforcement: SettingsHelper enforceFeature(allow_stories/groups/calls)
- auth-settings: admin/auth-settings.php + SettingsHelper
- typing indicator، last_seen عربي، chat menu (report/block)

## ما ينقص (من الملفات 10-20)

### 1) privacy (file 10) — **ناقص كبير**
- حقول privacy_settings مفقودة: show_phone, show_email, show_avatar, show_status_text, who_messages_me, who_calls_me, who_adds_groups, find_by_phone/email/username, display_identity
- جدول موجود فقط: show_last_seen, show_online_status, show_read_receipts
- Backend GET /users/{id}/profile لا يطبق الفلترة (يرجع كل الحقول)
- Flutter privacy_screen: فقط 3 إعدادات (last_seen/online/read_receipts)
- الخيارات المطلوبة: الجميع/جهات اتصالي/لا أحد لكل إعداد

### 2) display identity / أسماء جهات الاتصال (files 10+14)
- لا يوجد display_identity في users/privacy
- لا يوجد منطق "الاسم المحفوظ محليًا في جهات الاتصال" كـAPI
- contacts موجود كجدول لكن العرض محلي فقط في Flutter

### 3) reports (file 16) — **ناقص متوسط**
- attachments_data: لا يوجد جدول report_messages/attachments لإرفاق آخر 5 رسائل
- reason codes محدودة (varchar) — لا قائمة مسبقة (content_spam/abuse/impersonation/fraud/inappropriate/annoying/suspicious/other)
- admin/reports.php: لا إرفاقات، لا تفاصيل، لا ban/suspend من البلاغ، لا appeals
- أولوية البلاغ: لا يوجد حقل priority
- rate limiting موجود في ReportsController

### 4) ban/suspend/appeals (file 16)
- user_bans موجود + banUser/unbanUser في AdminController + AuthController يتحقق من ban عند login/OTP/register/device/qr
- ناقص: suspension (مؤقت حتى تاريخ)، admin users.php ban/suspend UI، إشعارات ban/suspend، صفحة ban في التطبيق، rate-limit للإبلاغ المتكرر، appeals (user_appeals جدول؟)

### 5) subscriptions (file 18)
- plans موجود لكن: type (free/verification/premium/pro/custom) غير موجود، enable_verification + duration مستقلة غير موجودة
- payment: لا يوجد payment_requests/receipts حاليًا؟ (يجب الفحص)
- user_subscriptions مربوط لكن: verified tied مباشرة بلا مدة مستقلة، notifications للاشتراك مفقودة
- device_limit ربط بالباقة: QR/linked devices لا يتحقق من plan max_devices؟ (يجب فحص devices/register)

### 6) admin dashboard monitoring (file 13)
- admin/monitoring.php 342 سطرًا — يجب فحصه (البطاقات + الإحصائيات: users today/week/month، messages today/yesterday/week، online %)

### 7) chats management (file 19)
- chats.php بسيط: بدون فلترة مرسل/مستلم/تاريخ/حالة قراءة، بدون pagination، بدون message ID/conv ID search، بدون حذف إداري، بدون soft delete tracking، بدون private chats listing

### 8) attachments (file 20)
- فحص MediaController: upload أنواع المدعومة (image/video/document/contact/poll/location)
- private chats، screenshot protection، one-time media، multiple upload

### 9) calls admin (file 17)
- calls.php يعمل؛ فحص: total today, active, audio/video split موجود

### 10) stories (file 11)
- stories موجود: text/image/video, views, 24h expiry, enforceFeature, story_duration_hrs
- ناقص محتمل: reply to story, interactions/reactions

### 11) offline (file 12)
- Flutter: Hive local cache + pending messages + client_message_id idempotency (Backend يدعم idempotent messages؟) — فحص
- sync engine: polling 5s موجود

## خطة التنفيذ المقترحة (مرتبة بالأولوية)
1. privacy_settings كامل + Backend filtering (GET users/{id}/profile, search, conversations list) + Flutter screen كامل
2. display_identity + contact names logic (Backend decides)
3. reports attachments (جدول report_attachments + last 5 messages) + reasons list + admin details + ban/suspend/reject flows + appeals
4. ban/suspend UI in admin/users.php + notifications
5. subscriptions: plan type + verification independent + payment flow structure + notifications + device limit enforcement
6. admin dashboard upgrade (monitoring)
7. chats admin upgrade
8. attachments completeness check
9. اختبارات + flutter analyze + رفع + production smoke
