# ملاحظات الكود الحالي (UserController.php 434 سطر)

## الحالة الحالية للملفات (قبل تنفيذ الخصوصية الجديدة)
- DB محلي أُعيد بناؤه من schema.sqlite.sql الجديد: privacy_settings (20 حقولًا)، reports (reason_code+priority)، plans (plan_type, enable_verification, verification_duration_days)، payment_requests، user_bans، user_appeals، report_attachments — كلها موجودة الآن.
- الخادم المحلي يعمل على 8080 (router.php) — health OK.

## UserController: البنية الحالية
- getUser(int $id) @110: AuthMiddleware + getPublicProfile → Response::success
- search @121: SELECT id,uuid,name,username,avatar,is_online,last_seen,is_verified WHERE (name/username/phone LIKE) AND is_blocked=0 (بدون فلترة find_by_* ولا exclusion للمستخدم المحظور من owner!)
- heartbeat @143: is_online=1 + last_seen=NOW
- privacyGet @156: يرجع last_seen_visibility/online_status/photo_visibility='contacts'(hardcoded!)/status_visibility='contacts'(hardcoded!)/read_receipts فقط
- privacyUpdate @183: يقبل last_seen_visibility/online_status/read_receipts فقط
- _visibilityToInt/_visibilityForInt موجودان (static)
- getUserById @390: private — يستخدم في me/updateMe (يعيد كل الحقول بما فيها phone/email للحساب نفسه — OK)
- getPublicProfile @422: SELECT name,username,bio,avatar,status_text,is_online,last_seen,is_verified (لأي viewer — بدون فلترة خصوصية!)

## المطلوب تنفيذه في UserController
1. privacyGet: إضافة photo_visibility/avatar (1=everyone,2=contacts,0=nobody)، phone/email/status visibility، messages_from/calls_from/groups_from، find_by_*, display_identity، story_privacy، allow_by_phone
2. privacyUpdate: قبول كل الحقول الجديدة (with validation)
3. getPublicProfile($id, $viewerId):
   - إذا blocks (viewer→owner أو owner→viewer): لا شيء/minimum
   - display_name = viewer's contact nickname إن وُجد ELSE حسب owner.display_identity (1=name_username,2=username,3=phone,4=email,5=name_phone,6=name_email)
   - avatar: فقط إذا show_avatar مسموح
   - phone: فقط إذا show_phone مسموح
   - email: فقط إذا show_email مسموح
   - last_seen/online: show_last_seen/show_online_status
   - status_text: show_status_text
   - ملاحظة: contact_name يُحسب في رد الـAPI (contactNickname)
4. search: فلترة find_by_phone/email/username حسب query type + لا phone في الرد + استثناء المحظورين
5. contacts: إضافة contactNickname للرد

## Routes index.php: /privacy = privacyGet/privacyUpdate (GET/PUT)، /users/{id} = getUser
## helpers: Response::success/error/notFound موجودة

## بنية إضافية (مهمة للتنفيذ)
- blockUser @219: INSERT INTO blocks (user_id, blocked_user_id, created_at) — unblock @237: DELETE
- newContacts @246: يرجع phone صراحة لكل جهات الاتصال! (u.phone — يجب إخفاؤه بحسب show_phone)
- addContact @266: {contact_user_id, nickname?}
- appSettings @306: يرجع allow_*, max_*, story_duration_hrs
- canSeeLastSeen/viewerId/targetId: show_last_seen (2/1/0) — 1=contacts (يطلب mutual contact! OR بالعكس)
- canSeeOnline: show_online_status 1/0
- canSeeReadReceipt: show_read_receipts (من الطرف الآخر)
- contacts schema: user_id, contact_user_id, nickname, is_blocked (no UNIQUE mentioned — addContact uses ON DUPLICATE KEY... SQLite لا يدعمها! ملاحظة: MysqlCompatPdo يحولها)
- NOW() ← MysqlCompatPdo يحولها إلى datetime('now','localtime') ✓ (سطر 70 adaptSql)

## خطة التعديل الفوري لـUserController.php (استبدال functions):
1. privacyGet: SELECT كل الحقول الجديدة + تحويلها (display_identity نص، int→string visibility)
2. privacyUpdate: قبول 14 حقلاً جديدًا مع validation
3. getPublicProfile($id, $viewerId=null) مع filterProfile($profile, $viewerId, $ownerId): تطبيق قواعد العرض + display_name
4. search: استثناء viewer إذا blocked من owner + فلترة find_by_* + no phone
5. newContacts: إزالة phone وإضافة contactNickname (nickname > display_name)
6. getUser(int $id): pass viewerId

## ملاحظات PHP:
- NOW() يعمل في MySQL schema لكن SQLite runtime migration — database.php يستخدم NOW() في queries (MysqlCompatPdo لا يحولها!؟) — في الحقيقة SQL يستخدم NOW() في many places (heartbeat updateUser uses NOW()). يجب التحقق من MysqlCompatPdo adaptSql يحول NOW() إلى current_timestamp. (في الكود الحالي heartbeat يعمل محليًا = يعمل)

## تقدم التنفيذ (مرحلة 2 - Backend خصوصية)
- [x] privacy_settings: 14 حقلًا جديدًا + payment_requests/plans/reports في schema + migrateMissingColumns + migrateMissingTables
- [x] UserController.php: privacyGet/privacyUpdate كاملتان (16 حقلًا)، filterProfile، isBlockedEither، isContactOf، contactNameDisplay، _displayNameForIdentity، _defaultPrivacyRow
- [x] search: فلترة find_by_* + استثناء المحظورين + no phone + display_name
- [x] newContacts: contact_name (nickname > display_name)، hide phone/email، احترام online/last_seen
- [x] php -l OK

## مشكلة الاختبار المحلي: جدول admins فارغ بعد إعادة بناء DB من schema!
- الحل: إنشاء admin seed عند إعادة البناء: INSERT admin@nova-messenger.com مع password_hash('738155861')
- adminApiLogin: POST /admin/otp/login {email,password} → data.token (AdminOtpController)
- registrations API: GET /admin/otp/registrations?identifier=PHONE → rows: [{id,otp_code,...}]
- code endpoint: GET /admin/otp/registrations/{id}/code مع admin Bearer token → data.otp_code
- adminApiLogin JWT: user_id=admin id, role=admin, exp +72h

## المتبقي في المرحلة 2
- [ ] getUser: يمرر viewerId ✓ (تلقائي عبر getPublicProfile($id, $auth['user_id'])؟ — لم يتغير getUser بعد، يجب تمرير $auth['user_id'])
- [ ] ConversationController: index/show — no raw phone + display_name + online يحترم show_online
- [ ] CallController: calls_from + blocks check عند initiate + incoming
- [ ] StoryController: story_privacy
- [ ] MessageController: messages_from + blocks check عند الإرسال
- [ ] ConversationController: groups_from عند إنشاء مجموعة
- [ ] Flutter privacy_screen: الإعدادات الجديدة
- [ ] Flutter: عرض contact_name/display_name

## المرحلة 4 (البلاغات/الحظر): ReportsController موجود (POST يقبل reported_user_id/reason/description/message_ids)، user_bans+user_appeals موجودان schema لكن:
- [ ] AuthController: فحص blocked identities في register + ban screenACCOUNT_BANNED error في login/verify + messaging/calls blocks enforcement
- [ ] AppealsController جديد: POST/GET /appeals (user) + admin review + notifications
- [ ] AdminController: banUser suspend (duration) + appeals endpoints
- [ ] admin/reports.php upgrade + admin/appeals.php + users.php suspend UI
- [ ] payment_requests: POST /subscriptions/request + admin review + device limit check في device register
- [ ] plans admin page plan_type+verification
- [ ] admin/chats.php pagination+filters+admin delete

## تفاصيل API اكتشفت في اختبار المرحلة 2 (محلي)
- **admin seed محلي**: جدول admins فارغ بعد إعادة بناء DB — بذر يدوي: `php -r` PDO sqlite INSERT admin@nova-messenger.com + password_hash('738155861') + role super_admin (role_id=1 يجب بذره: `INSERT OR IGNORE INTO roles (id,name,description) VALUES (1,'super_admin','...')`)
- **role_permissions**: جدول permissions فارغ أيضًا — seed في /tmp/seed_permissions.php (74 صلاحية لـrole 1 super_admin). يجب تشغيله بعد كل إعادة بناء DB محليًا وعلى Render post-migration.
- **POST /auth/register** (وليس register-phone) `{phone, name}`
- **GET /admin/otp/registrations?identifier=PHONE** → يحتاج **admin Bearer token**! يرجع {rows:[{id, ...}]}
- **GET /admin/otp/registrations/{id}/code** مع admin token → يرجع `{"otp_code":"123","expires_at":"...","message":"..."}` **بلا wrapper data!**
- **POST /auth/verify-otp** `{phone, otp}` → data.token
- **POST /admin/otp/login** `{email, password}` → data.token (role=admin JWT, exp+72h)
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php (من /home/ubuntu/nova_new)
- اختبار الخصوصية: python3 /tmp/test_privacy_api.py (يستخدم أرقام +9669990000+TS لتفادي cooldown)

## تقدم المرحلة 2 (Privacy + Backend) — آخر تحديث
### تم إنجازه في UserController.php:
1. privacyGet: 16 حقل كامل (last_seen_visibility, online_status, photo_visibility, status_visibility, phone_visibility, email_visibility, read_receipts, messages_from, calls_from, groups_from, find_by_phone, find_by_email, find_by_username, display_identity, story_privacy, allow_by_phone)
2. privacyUpdate: تحديث كل الحقول الجديدة (تحقق من القيم: last_seen/photo/status = everybody|contacts|nobody، messages/calls/groups = everybody|contacts|nobody|none)
3. canSeeLastSeen/canSeeOnline/canSeeReadReceipt helpers موجودة
4. filterProfile: يطبق show_* على phone/email/avatar/status/is_online/last_seen + display_name + contact_name (nickname > identity)
5. getUser: يستدعي getPublicProfile(id, viewerId) + self يرى last_seen/is_online الخام
6. search: إصلاح $search→$like + display_name صحيح (بناء قبل unset name) + find_by_* فلترة
7. index (آخر المحادثات): contact_name = nickname أو display_name، last_seen/online محترم

### ملاحظة اختبار:
- test_privacy_api.py: 18 فحصًا محليًا — بحث بالرقم [] صحيح، phone=None للغير صحيح، display_name="تجربة1" صحيح للغير. self last_seen=False طبيعي (لا heartbeat في الاختبار).
- self last_seen يجب أن يظهر من raw getUserById عند viewer===owner (أُصلح).

### المتطلبات المتبقية من الملفات 10-20:
1. (مكتمل تقريبًا) Privacy: حقول الرؤية الجديدة + display_identity + search filtering + contacts first-name logic في search؟ (contacts: nickname > display_name في index)
2. Reports + appeals: POST /reports يعمل (commit 86ada88). المتبقي: user_appeals (الاعتراضات) — POST اعتراض من التطبيق + عرض/حسم في admin
3. Bans: user_bans table (أُضيف schema + migrateMissingTables). المتبقي: admin ban/suspend + app side check عند login (AuthController يجب أن يفحص user_bans)
4. Plans + subscriptions + payment_requests (الباقات والاشتراكات والتحقق المستقل): أعمدة plans أُضيفت — المتبقي admin plans/subscriptions endpoints
5. Admin panel: pages reports.php (موجود 117 سطر)، devices.php, plans.php, subscriptions.php, chats.php (19), calls.php (16) تحتاج ترقية
6. Flutter: لا تعديلات في الواجهة حتى الآن — الأزرار report/block تعمل (مرفوعة سابقًا). هل نضيف screens للـprivacy الجديدة؟ (المتطلبات تطلب ربط)
7. رفع: بعد كل الاختبارات المحلية (حزمة 18/18 + flutter analyze) → commit → Render → smoke 23/23

### ملاحظات تشغيلية:
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php (من /home/ubuntu/nova_new)
- DB محلي: backend/config/nova.sqlite — بعد كل إعادة بناء: بذر admin (PHP PDO، hash 738155861) + role super_admin + /tmp/seed_permissions.php
- الاختبارات: /tmp/test_privacy_api.py (privacy), /tmp/test_bundle_final.py (18 tests), /tmp/test_reports_curl.sh (reports)
- flutter: /home/ubuntu/flutter/bin/flutter, project nova_flutter

## تحديث المرحلة 3 (Flutter privacy_screen.dart)
### تم:
- privacy_screen.dart (283→~520 سطر): أقسام: معلوماتي (last_seen, online_status, photo, status, phone, email) + التواصل (messages_from, calls_from, groups_from) + قابلية الاكتشاف (find_by_phone/email/username toggles) + الهوية الظاهرة (display_identity: name_username/username/phone/email/name_phone/name_email) + أخرى (read_receipts, allow_by_phone)
- _update تقبل dynamic؛ _boolSetting؛ showIdentitySheet جديد

### التالي:
1. flutter analyze (0 errors requirement)
2. اختبار محلي: python3 /tmp/test_privacy_api.py + /tmp/test_bundle_final.py (18/18)
3. المرحلة 4: appeals نظام الاعتراضات (user_appeals table أُضيف schema + migrate) — POST /appeals من التطبيق + admin endpoints + admin page
4. المرحلة 5: plans/subscriptions admin endpoints + payment_requests verification
5. المرحلة 6: ترقية admin pages (reports.php/audit.php موجودة — ترقية؛ devices/plans/subscriptions/chats/calls ناقصة)
6. commit + Render + smoke (23/23)

### ملاحظات:
- ban/unban في AdminController يعمل، AuthController verify-otp يفحص ban (line 200) — يعمل
- user_bans table أُضيف schema.sqlite.sql + migrateMissingTables في database.php
- seed: admin email= admin@nova-messenger.com pass=738155861، role super_admin، /tmp/seed_permissions.php
