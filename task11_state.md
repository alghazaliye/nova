# حالة المهمة: مراجعة الملفات 10-20 وتنفيذ المتطلبات المتبقية

## التعليمات الحالية من المستخدم
- إكمال المهام السابقة التي بدأت (reports/privacy/dashboard...)، ثم مراجعة الملفات النصية السابقة وتنفيذ المتبقي.
- لا إعادة تنفيذ ما تم إنجازه، أصلح الناقص فقط، لا أنظمة مكررة، لا Mock.
- اختبار كل مهمة بعد تنفيذها. **لا رفع إلى GitHub/Production قبل اكتمال كل شيء**.
- الترتيب: إكمال → اختبار → مراجعة ملفات → متبقي → تنفيذ → اختبار شامل → GitHub → Production.
- التقرير النهائي لكل حزمة: ما نُفذ، ملفات، API، نتائج اختبارات، حالة GitHub/الاستضافة.

## مخرجات الفحص (من gap_analysis.md + shell outputs)

### الحالة الحالية (commit 86ada88 على GitHub، Render live)
- DB tables موجودة: users, conversations, messages, contacts, blocks, reports, privacy_settings(3 حقول فقط), user_devices, user_subscriptions, plans, calls, call_participants, call_signals, stories, story_views, messages/story_views, attachments, message_edits, message_deletions, message_reads, message_reactions, typing_status, notifications, audit_logs, admins, roles, permissions, role_permissions, otp_*, email_*, sessions, group_settings, groups, conversation_members, device_registrations.
- users columns: لا يوجد display_identity، لا حقول privacy جديدة، فقط is_blocked/blocked_at (global ban system موجود).
- privacy_settings فقط: show_last_seen, show_online_status, show_read_receipts (الباقي ناقص).
- plans: name,description,price,currency,period,max_devices,features(JSON),badge_color,is_active — لا plan_type، لا enable_verification، لا verification_duration مستقلة.
- subscriptions: user_subscriptions (user_id,plan_id,status,starts_at,expires_at) — تفعيل الاشتراك = UPDATE users SET is_verified=1 بلا مدة مستقلة. لا payment_requests/receipts حاليًا (يجب فحص).
- user_bans + banUser/unbanUser في AdminController + AuthController يتحقق من ban (login/OTP/register/device/QR). لا suspension مؤقتة. لا appeals.
- reports: ReportsController (POST/GET, rate limit, duplicate) + admin/reports.php (جدول بسيط: status pending/reviewing/resolved/rejected + resolve/reject buttons). لا attachments (إرفاق 5 رسائل)، لا الأولوية، لا ban/suspend من البلاغ، لا appeals.
- devices: /devices/* endpoints كاملة + admin/devices.php كاملة.
- calls: admin/calls.php سجل كامل. chats: admin/chats.php بسيط (100 حد، بدون pagination/فلاتر متقدمة/حذف إداري).
- audit_logs + admin/audit.php pagination يعمل. monitoring.php 342 سطرًا (يجب فحص).
- plans/subscriptions في admin تعمل.

### الفجوات الرئيسية (مرتبة)
1. **privacy** (file 10): إضافة حقول privacy_phone/email/avatar/status_text/messages/calls/groups/find_by_*/display_identity إلى privacy_settings أو users + Backend filtering في GET /users/{id}/profile + search + contact names من جهات اتصال viewer + Flutter privacy_screen كامل.
2. **reports** (file 16): جدول report_attachments/آخر 5 رسائل + قائمة أسباب مسبقة + admin details + ban/suspend/reject + الأولوية + notifications + ban screen في التطبيق.
3. **ban/suspend/appeals** (file 16): suspension مؤقتة + appeals (user_appeals) + قبول/رفض + إشعارات.
4. **subscriptions** (file 18): plan_type + verification مستقلة المدة + payment flow (لا بوابة وهمية: manual receipts + provider structure) + notifications للاشتراك + device limit enforcement في QR/devices register + ربط الحسابات المميزة.
5. **admin dashboard** (file 13): مراجعة monitoring.php وترقيته (مؤشرات: users today/week/month + %, messages today/yesterday/week, online %).
6. **chats admin** (file 19): pagination + فلاتر (مرسل/مستلم/تاريخ/حالة قراءة/message ID/conv ID) + حذف إداري + private chats + audit.
7. **stories** (file 11): reply + interactions + privacy story (everyone/contacts/exclude/share_with/nobody) + contact owner semantics + allow_by_phone option + prevent phone reveal.
8. **attachments** (file 20): فحص MediaController (image/video/document/contact/poll/location upload), private chats, one-time media, multiple upload.
9. **offline** (file 12): فحص Hive + pending + idempotency.
10. **display names** (file 14): priority: viewer's contact name > user's display_identity setting.
11. **calls admin** (file 17): monitoring today/active counts — فحص monitoring.php.

### قرارات تنفيذية
- الحقول الجديدة تضاف إلى جدول privacy_settings (لا حقول في users لتجنب التعقيد): show_phone(1=everyone,2=contacts,0=nobody), show_email, show_avatar, show_status_text, messages_from(0/2/1), calls_from, groups_from, find_by_phone, find_by_email, find_by_username, display_identity (1=+username,2=username only,3=phone,4=email,5=name+phone,6=name+email), story_privacy(1-5), allow_by_phone(0/1).
- Backend: دالة canViewField في UserController تستخدم للـprofile/search/conversations/stories.
- admin/reports.php ترقية: تفاصيل + إرفاقات + ban/suspend + reject + فلاتر + أولوية.
- لا bootstrap API gateway جديد — استخدام router الحالي في index.php.
- Flutter: privacy_screen كامل + ban screen + contact names logic + story privacy.

### أوامر مهمة
- الخادم المحلي: من /home/ubuntu/nova_new: `php -S 0.0.0.0:8080 backend/public/router.php`
- flutter: /home/ubuntu/flutter/bin/flutter، analyze في nova_flutter/
- admin login: admin@nova-messenger.com / 738155861 — OTP admin code: POST /admin/otp/login ثم GET /admin/otp/registrations/{id}/code بـ admin JWT
- Render: nova-wn25.onrender.com — DB تُمسح مع كل deploy، إعادة إنشاء حسابات عبر /tmp/get_render_tokens.py (تحتاج resend/OTP flow) ثم smoke.
- IP الساندبوكس محجوب مؤقتًا من Render: اختبار الإنتاج عبر browser_console_exec على nova-wn25.onrender.com/web_app/
- commit last: 86ada88. reports 201/409 يعمل.
