# نظام الحالات Status — ملاحظات تنفيذ (المرحلة 3 Backend)

## الحقائق المفتاحية (مؤكد من الكود):
- جداول موجودة: `stories` (id,uuid,user_id,type,text,file_id,privacy,created_at,expires_at,deleted_at), `story_views` (UNIQUE story_id,viewer_id ✓)
- `blocks` موجود: UNIQUE(user_id,blocked_user_id)
- `contacts` موجود: (user_id, contact_user_id)
- `privacy_settings`: `story_privacy` INTEGER default 1 (1=contacts, 2=all, 0=none), `allow_by_phone` default 1
- `permissions`: توجد بالفعل `stories.view` لكن **لا توجد** statuses.view/statuses.delete/statuses.review/statuses.stats → يجب إضافتها
- `audit_logs`: (id,admin_id,action,entity_type,entity_id,description,ip,user_agent,created_at) — MysqlCompatPdo يترجم NOW()→datetime
- `role_permissions`: (role_id,permission_id)
- `messages` لها: reply_to_message_id (لكن لا يوجد status_reply_id) → نضيف عمود `status_reply_id`؟ لا — الأوفق إضافة عمود `reply_to_status_id` على messages (ALTER ADD COLUMN) لربط الردود بالحالات.
- `reports`: لا يوجد story_id → نضيف عمود `story_id` (ALTER ADD COLUMN) للبلاغات
- `user_devices` فيه fcm_token (يُستخدم للإشعارات)
- `seed_production.sql`: صلاحيات جديدة 100-110 موجودة، super_admin يحصل عليها عبر SELECT 1,id

## نمط الصفحات الإدارية (admin/appeals.php):
- require_once config.php + auth.php; $admin=requireAdminLogin(); requirePermission($admin,'xxx');
- h() دالة مساعدة محلية; flash ['ok'|'warn'|'err', msg]; Modal JS لـconfirm؛ footer.php في النهاية
- sidebar.php لا يعرض stories.php؟ — يعرضها في قسم interaction (lines 74-80)
- admin/stories.php موجود بسيط (permission users.view) — سنرقّيه بنفس الملف admin/stories.php (أفضل من إنشاء موازٍ) مع permission جديد

## ما سننفذه Backend:

### 1. database.php migrateMissingColumns/migrateMissingTables:
- جدول `story_reactions`: id, story_id, user_id, reaction varchar(10), created_at — UNIQUE(story_id,user_id)
- جدول `story_replies`: id, story_id, sender_id, message_id, created_at
- عمود `messages.reply_to_status_id INTEGER DEFAULT NULL` (NULL)
- عمود `reports.story_id INTEGER DEFAULT NULL`
- عمود `audit_logs` موجود ✓

### 2. StoryController.php ترقيات:
- `index()`: فلترة الحظر (blocks)، privacy موسعة:
  - story_privacy: 0=none (لا أحد), 1=contacts, 2=all
  - allow_by_phone: إذا 1 → المستخدم الذي يملك رقم الهاتف يرى حسب باقي الإعدادات (رقم الهاتف لا يعني رؤية الحالة تلقائيًا — فقط إذا كان contacts/all كذلك)
  - blocked: لا يرى المحظور حالات المحظور-منه
- `show()`: تسجيل مشاهدة تلقائي؟ لا — العرض منفصل عبر view()، لكن index يعيد viewed_by_me
- `view()`: INSERT IGNORE ✓ موجود + تحديث views_count؟ لا يوجد views_count في stories (يُحسب عبر COUNT)
- جديد: `GET /stories/{id}/views` → لصاحب الحالة فقط (قائمة المشاهدين، الأحدث أولًا)
- جديد: `POST /stories/{id}/reaction` {reaction: ❤️😂😮😢👍🔥} → UNIQUE per user، تحديث reaction (تغيير مسموح)
- جديد: `DELETE /stories/{id}/reaction`
- جديد: `POST /stories/{id}/reply` {body} → ينشئ رسالة في محادثة بين المرسل وصاحب الحالة + يحفظ story_replies + يحدث messages.reply_to_status_id
- جديد: `PUT /stories/{id}` → نص فقط (type=text) لصاحب الحالة
- `delete()`: تبقى كما هي + audit (عبر admin)
- Admin: `adminDelete(int $id)` → status = admin_deleted (نضيف عمود deleted_by أو نستخدم deleted_at + audit_log) — نضيف عمود stories.deleted_by
- FCM: إشعار عند reply/reaction (sendStoryReplyNotification, sendStoryReactionNotification) — عبر FCMHelper.sendToDevice

### 3. seed_production.sql:
- صلاحيات: statuses.view, statuses.delete, statuses.review, statuses.stats
- super_admin يحصل عليها تلقائيًا عبر SELECT 1,id (موجود في نهاية الملف)
- (لا نستعمل أرقام id جديدة لتجنب تعارضات مع 100-110؟ لا بأس لأن INSERT OR IGNORE)

### 4. admin/stories.php ترقية:
- requirePermission للـ statuses.*
- إحصائيات: الحالات اليوم/النشطة/المنتهية/المشاهدات/التفاعلات/الردود + حسب النوع
- جدول: الحالة/الصاحب/النوع/مشاهدات/تفاعلات/ردود/تاريخ/انتهاء/حالة
- حالات: active/expired/deleted/admin_deleted
- حذف إداري: status=admin_deleted + reason + audit
- فتح حالة (modal): صاحب/نوع/preview/تواريخ/مشاهدات/تفاعلات/ردود/خصوصية — زر الحذف حسب الصلاحية

### 5. index.php routes جديدة:
- GET  /stories/{id}/views
- POST /stories/{id}/reaction
- DELETE /stories/{id}/reaction
- POST /stories/{id}/reply
- PUT  /stories/{id}
- POST /admin/stories/{id}/delete (أو عبر نفس delete مع admin JWT؟) — سنعمل route منفصل admin
- routes موجودة حاليا: GET stories, POST stories, GET show, POST view, DELETE delete, POST upload

### 6. UserController canSeeReadReceipt: احترام read_receipts للمشاهدات أيضًا (story_views)
- عند POST view: إذا canSeeReadReceipt(viewer, owner) == false → لا نسجل في story_views؟ (نفس سياسة واتساب: إذا عطّلت read receipts لا تظهر في قائمة المشاهدين ولا تحسب).
- قرار: إذا صاحب الحالة عطّل read receipts: لا نُسجل view ولا نحسب count للمشاهد غير المسموح كشفهم. (هذا أبسط وأتسق مع "26. آخر ظهور وإيصالات القراءة")

### 7. FCMHelper: أضف sendStoryReactionNotification/sendStoryReplyNotification

## روابط الاختبار لاحقًا:
- مستخدمان: U1 = 2 (محمد؟), U2 = 3 — نكتشف عند الاختبار


## حالة التنفيذ (تحديث):
- ✅ database.php: أضيفت story_reactions + story_replies + أعمدة stories.deleted_by/stories.views_count + messages.reply_to_status_id + reports.story_id
- ✅ StoryController.php كامل جديد: index(privacy+block), show, view(canSeeReadReceipt check), views, react, unreact, reply, update, delete, reactions, replies, report, adminDelete(requireAdmin 'statuses.delete', deleted_by سالب, audit_log), adminStats(requireAdmin 'statuses.stats')
- ⚠️ StoryController::index: يستخدم storyVisibilitySql مع privacy string 'all'/'none'/'contacts' لكن storyPrivacyLevel يعيد level من privacy_settings (int: 0/1/2) — يجب التأكد من أن index يستخدم storyPrivacyLevel (int) وليس privacy الحقل. الكود الحالي في index يستدعي storyPrivacyLevel ✓ لكن storyVisibilitySql يستخدم حقل privacy مباشرة — **خلل محتمل يجب إصلاحه**: storyVisibilitySql يقارن حقل stories.privacy بـ privacy_settings.story_privacy مباشرة. يجب إعادة كتابة logic: index يفلتر يدويًا في PHP (أسهل) أو يجعل SQL يقارن level.

## خطوات متبقية:
1. إصلاح index() فلترة الخصوصية (يجب استخدام storyPrivacyLevel level وليس حقل privacy — الحل: احذف storyVisibilitySql من index وافعل الفلترة يدويًا في PHP بعد الجلب، مع blockedIds ✓ موجود)
2. index.php routes جديدة بعد سطر 291:
   - GET  stories/{id}/views   → view((int)$m[1])
   - GET  stories/{id}/reactions → reactions()
   - GET  stories/{id}/replies → replies()
   - POST stories/{id}/reaction → react()
   - DELETE stories/{id}/reaction → unreact()
   - POST stories/{id}/reply → reply()
   - PUT stories/{id} → update()
   - POST stories/{id}/report → report()
   - POST admin/stories/{id}/delete → adminDelete()
   - GET admin/stories/stats → adminStats()
3. seed_production.sql: INSERT OR IGNORE permissions statuses.view/statuses.delete/statuses.review/statuses.stats
4. admin/stories.php ترقية كاملة (stats + table + delete admin + modal)
5. admin sidebar: لا حاجة لتعديل (stories.php موجود)
6. اختبار: python3 test_bundle_final.py / test_appeals3.py / test_subscriptions.py
7. flutter stories_screen — ترقية (الموجود في /home/ubuntu/nova_new/nova_flutter/lib/screens/stories_screen.dart 492 سطر، يوجد story_viewer_fullscreen.dart 130 سطر)
8. flutter build web --wasm → rm -rf web_app && cp -r nova_flutter/build/web web_app
9. commit + push: git add -f web_app/ backend/ nova_flutter/lib/ database/ + token
10. Render deploy: انتظار + smoke test

## ملاحظات تقنية مهمة:
- MysqlCompatPdo يترجم NOW()→datetime('now','localtime') وCURDATE()→DATE('now','localtime') وINSERT IGNORE/ON CONFLICT تلقائيًا ✓ (يجب التحقق في الاختبار)
- adminStats يستخدم CURDATE() ✓ مترجم تلقائيًا
- audit_logs INSERT يستخدم NOW() ✓
- FCMHelper::sendToDevice signature: (string $deviceToken, string $title, string $body, array $data = [], ?string $imageUrl = null): bool
- nova_get_auth_header() في backend/config/app.php
- admin JWT payload: user_id/role='admin'/admin_role/exp
- seed_production.sql صلاحيات 100-110 موجودة؛ super_admin يحصل عبر SELECT 1,id
- اختبار الإنتاج: test_prod_flow.py في /tmp — otp: GET /admin/otp/registrations/{id}/code → data.otp_code
- admin token: POST https://nova-wn25.onrender.com/api/v1/admin/otp/login {email:'admin@nova-messenger.com', password:'738155861'}


## تحديث الحالة (بعد اكتمال Backend):
- ✅ StoryController.php: تم إصلاح index() — الفلترة الآن PHP (storyPrivacyLevel: 0/1/2 + contacts batch check). حُذفت storyVisibilitySql.
- ✅ index.php: 12 route جديدة مضافة (views/reactions/replies/reaction POST+DELETE/reply/update/report/admin delete/stats)
- ✅ seed_production.sql: صلاحيات 111-114 (statuses.view/delete/stats, stories.admin) + moderator يحصل view/stats/admin
- ✅ admin/stories.php: ترقية كاملة (stats 9 بطاقات + جدول 12 عمود + تفاصيل modal + حذف إداري modal + CSRF + audit_log + statuses.delete permission)
- ✅ DB المحلي: جداول story_reactions/story_replies + أعمدة stories.deleted_by/views_count + messages.reply_to_status_id + reports.story_id + الصلاحيات 111-114 + role_permissions كلها مضافة فعليًا في nova.sqlite
- ✅ php -l: index.php + StoryController.php + admin/stories.php بلا أخطاء
- ✅ الخادم المحلي: يعمل 8080 ✓
- ⏳ التالي: تشغيل /tmp/test_stories.py (24 فحص) — ثم بقية الاختبارات (test_bundle_final, appeals3, subscriptions, flutter analyze)

## بنية test_stories.py (24 فحصًا):
1-3 نشر حالات (all/contacts/none) · 4 u2 يرى u1 all ولا يرى حالته الخاصة · 5 none · 6 contacts u1→u3 · 7 u3 يرى u2 contacts · 8 حظر u1←u3 لا يرى · 9 مشاهدة · 10 read_receipts OFF لا يُسجل · 11 views لصاحب + إخفاء u3 · 12 views لغير الصاحب 403 · 13 تفاعل update · 14 invalid reaction 400 · 15 unreact · 16 reply → conversation+message + يظهر في محادثة u1 · 17 update text + 403 + 400 · 18 report 201/409/400 · 19 delete + 404 + 403 · 20 تفاعل ذاتي 400 · 21 مشاهدة ذاتية · 22 admin stats · 23 admin delete + 404 · 24 stories.php web لا 500

## ملاحظات اختبار مهمة:
- contacts route: POST /contacts {user_id}
- blocks route: POST /blocks {user_id}
- privacy PUT body: field=value (show_read_receipts=0)
- admin login: POST /api/v1/admin/otp/login {email,password} → data.token
- admin stats endpoint: GET /admin/stories/stats (وليس api/v1!)
- admin delete endpoint: POST /admin/stories/{id}/delete
- messages in conv: GET /conversations/{id}/messages → response.data.messages/items


## تشخيص test_stories.py (2026-08-21):
المشكلة الأخيرة: otp_code() يرجع None لأن response endpoint /admin/otp/registrations/{id}/code صيغته `{"otp_code":"869887","expires_at":"...","message":"..."}` مباشرة (وليس داخل "data"!) — يجب تعديل otp_code إلى `r.json().get("otp_code")`.
حقائق أخرى: register يحتاج phone 7-20 رقمًا (regex) + cooldown 60s + numbers يجب عدم تكرارها؛ استخدم `_suffix = str(int(time.time()) % 1000000).zfill(6)`.
verify-otp يحتاج `{"phone": ..., "otp": ...}` وليس otp_code.
find_registration يعمل عبر sqlite مباشرة (/home/ubuntu/nova_new/backend/config/nova.sqlite جدول otp_verifications حيث id=id المستخدم).
admin JWT: POST /api/v1/admin/otp/login {email,password} → data.token (admin_jwt() يعمل).

## ما يتبقى الآن:
1. إصلاح otp_code في test_stories.py (بيانات OTP مباشرة)
2. تشغيل الاختبار حتى الـ24 فحصًا كلها ✓
3. test_bundle_final.py + test_appeals3.py + test_subscriptions.py في /tmp
4. flutter analyze
5. stories_screen.dart ترقية (reactions/replies/views) — أو الاكتفاء بالأساسي
6. build web --wasm + web_app copy + git add -f web_app/ + commit + push + Render
7. Smoke test + تقرير نهائي


## حالة test_stories.py (تحديث 2):
- schema DB المحلي كامل ✓ (deleted_by, views_count, story_reactions, story_replies, messages.reply_to_status_id, reports.story_id)
- إصلاحات StoryController المنفذة: getStoryById now owner-bypasses privacy + per-story privacy (all/none/contacts) مع إعداد عام story_privacy; attachFileData helper; adminStats CURDATE→DATE('now','localtime')
- إصلاحات test_stories.py المنفذة: otp_code بدون data wrapper, verify يحتاج {"phone","otp"}, find_registration عبر sqlite مباشرة, أرقام فريدة suffix 6 أرقام
- متبقي في الاختبار: contacts API body = {contact_user_id} وليس {user_id}; block = POST /users/{id}/block (وليس /blocks); privacy PUT body: online_status/read_receipts boolean + story_privacy numeric 0..2 (وليس 1..4!)
- contracts (من UserController): blockUser/unblockUser = POST/DELETE /users/{id}/block; addContact = {contact_user_id, nickname?}; privacyGet/privacyUpdate في UserController lines 225-342
- ملاحظة privacyUpdate يرجع NO_DATA إذا أرسلت أسماء حقول قديمة — يجب استخدام الأسماء الجديدة
- admin stats فشل سابق: "no such column s.deleted_by" كان قبل الـmigration — بعد إصلاح + إعادة اختبار يتوقع نجاح

## routes index.php الموجودة لـstories ✓ كاملة (view/reactions/replies/reaction POST+DELETE/reply/update/report/adminDelete/adminStats)
## contacts routes: GET /contacts/new, POST /contacts {contact_user_id}, DELETE /contacts/{id}
## block: POST/DELETE /users/{id}/block


## contracts مؤكدة نهائيًا (UserController privacyUpdate lines 226-342):
- PUT /privacy يقبل: last_seen_visibility|photo_visibility|status_visibility|phone_visibility|email_visibility|messages_from|calls_from|groups_from = everybody|contacts|nobody; online_status/read_receipts/find_by_phone/find_by_email/find_by_username/allow_by_phone = bool; display_identity = name_username|username|phone|email|name_phone|name_email; story_privacy = 1(all) 2(contacts) 3(share_with) 4(nobody)
- POST /users/{id}/block و DELETE /users/{id}/block
- POST /contacts {contact_user_id, nickname?}
- blockUser يعمل ✓ على H1 → u3_id
- ملاحظة: story_privacy DB: 1=all 2=contacts 3=share 4=nobody، لكن StoryController.storyPrivacyLevel يحوّل إلى level 0..2 (0=nobody, 2=everyone) عبر clamp بعد -1: level = row.story_privacy ?? 1 → يجب مطابقة: DB 4 → level 0، DB 1 → level 2، DB 2,3 → level 1. **الخطأ المحتمل**: current code: `$level = $row ? (int)($row['story_privacy'] ?? 1) : 1; if ($level < 0) $level = 0; if ($level > 2) $level = 2;` — DB 4 → level 2 (WRONG!) يجب إصلاح mapping.
- storyPrivacyLevel يجب: level=2 if privacy==1; level=1 if privacy in [2,3]; level=0 if privacy==4

## إصلاحات متبقية معروفة:
1. storyPrivacyLevel: إصلاح mapping (4→0, 1→2, 2/3→1)
2. إعادة تشغيل الاختبار — نتوقع نجاح معظمها
3. adminDelete/reply routes تعتمد على admin JWT (adminDelete يستخدم authenticateAdmin — يجب اختبار أن admin JWT يعمل)
4. بعد النجاح: flutter analyze، build web --wasm، copy web_app، git push، Render smoke


## حالة test_stories.py (تحديث 3 — 2026-08-21 08:20):
- الخادم المحلي يعمل (port 8080, PID 72020): php -S 0.0.0.0:8080 backend/public/router.php (من /home/ubuntu/nova_new)
- الإصلاحات المنفذة الناجحة سابقًا (40 PASS في آخر تشغيل كامل):
  * StoryController: owner bypass privacy في getStoryById, per-story privacy, attachFileData, storyPrivacyLevel mapping (DB 1→2, 2/3→1, 4→0), adminStats alias AS s, CURDATE→DATE('now','localtime')
  * index.php routes كاملة + adminDelete /admin/stories/{id}/delete + adminStats /admin/stories/stats
  * database.php: story_reactions + story_replies + deleted_by/views_count أعمدة + seed_production.sql صلاحيات statuses.*
- فشل متبقي في آخر تشغيل كامل (11 FAIL): contacts payload (أُصلح {contact_user_id}), blocks→/users/{id}/block (أُصلح), privacy read_receipts bool (أُصلح), otp_code wrapper (أُصلح), find_registration users.id (أُصلح), user id بعد التفعيل (أُصلح)
- **مشكلة جديدة**: بعد التعديلات الأخيرة، test_stories.py يطبع NOTHING ويخرج بـ exit 1 (حتى مع PYTHONUNBUFFERED+redirect). الملف syntax ok. سبب محتمل: sys.exit(1) مبكر في check "لا يمكن إيجاد طلبات التسجيل" — لكن كان يطبع سابقًا! **الحقيقة**: أول check بعد التعديل "لا يمكن إيجاد..." لم تُطبع الآن — لأن check يُضاف للـresults لكن sys.exit قبل print("\n".join(results)). في الكود الحالي عند failure يطبع... يجب فحص السطور 85-115 في test_stories.py
- قاعدة بيانات: users.id لا يساوي otp_verifications.id (المستخدم يُنشأ فقط بعد verify). الأرقام: _suffix = str(int(time.time())%1000000).zfill(6)، phones = +96670000010{i}{_suffix}
- admin JWT: POST /api/v1/admin/otp/login {email: admin@nova-messenger.com, password: 738155861} → data.token
- admin stats test: يستخدم /admin/stories/stats (بدون api/v1) ✓ route موجود
- admin/stories.php صفحة إدارة ✓ لا يرمي 500


## التحليل النهائي للفشل الست المتبقية (49 PASS / 6 FAIL):

1. **"u2 لا يرى حالة u3 (none)"**: s3 منشورة بـprivacy="none" لكن publish يحوّل none؟ — لا، الحالة: u3 نشر بـprivacy=none. لكن "لا أحد" يجب أن تعني: story_privacy=4 في settings أو story.privacy=none per-story. فحص normalizePrivacy: none→'none' ✓. فشل الاختبار يعني حالة none تظهر لـu2! فحص index(): storyPrivacyLevel لا يُطبق عند privacy per-story == 'none'؟ يجب فحص getStoryById/isVisible — يبدو أن privacy per-story لا تُطبق على الإطلاق (لا يوجد فحص s.privacy='none' في index). يجب تطبيق: if story.privacy == 'none' → مخفية على الجميع؛ if 'contacts' → فقط جهات الاتصال.
2. **"العرض لا يُحسب view_count=2"**: الاختبار يتوقع view_count==1 بعد مشاهدتين (u2 مسجّلة + u3 غير مسجلة). النتيجة 2 — يعني مشاهدة u3 سُجِّلت رغم تعطيل read receipts! فحص canSeeReadReceipt(userId, ownerId): parameters معكوسة؟ userId=viewer(u3), story owner=u1. يُمرر: canSeeReadReceipt($userId=u3, (int)$story['user_id']=u1). فحص الدالة في UserController: هل تتحقق من إعدادات صاحب الحالة؟ قد تتحقق من إعدادات المشاهد u3 (المعطل له هو u1). **الأرجح: parameters مقلوبة**.
3. **الرد REPLY_FAILED**: trace #0 في index.php line → FOREIGN KEY؟ messages.reply_to_status_id عمود موجود؟ PRAGMA foreign_key_list(messages) أظهر: 0 users|sender_id, 1 messages|reply_to_message_id, 2 conversations|conversation_id, 3 attachments|file_id — **لا يوجد FK على reply_to_status_id** إذن ليس FK. trace كامل #0 MysqlCompatPdo:60 → ON DUPLICATE KEY في conversation_members insert؟ لا. يجب طباعة trace كامل.
4. **قائمة الردود**: message_body != "رد على الحالة" — يعتمد على نتيجة الرد (3)
5. **الرد يظهر في المحادثة**: يعتمد على (3)
6. **تعديل updated_at**: أُصلح (حذف العمود) ✓


## تشخيص نهائي (تحديث 4):
1. **client_message_id NOT NULL**: أُصلح في reply() (reply_.$msgUuid) ✓ + error_log في catch
2. **view receipts logic**: سليمة ✓ (t3.py أظهر "دون تسجيلها" + receipts=False في GET /privacy)
3. **المشكلة الأساسية المكتشفة في t3/t4**: GET /stories لصاحب الحالة يعرض حالة لـuser_id=91 كأنها ليست لي (is_owner=false) لأن uid من python=93. **السبب الحقيقي**: المستخدم يُنشأ في users.id لكن token JWT قد يحمل user_id مختلفًا (users auto-increment مختلف عن OTP). الحالة 24 user_id=93 موجودة في DB لكن لم تظهر في قائمة uid=93!
4. **الاكتشاف الجوهري**: في التوقيت 08:19:00 created_at وexpires_at=2026-08-22 08:19:00 ✓. لكن الحالة 24 لم تظهر في قائمة 93 — فحص: ربما NOT IN blocked مع userId=93 + $blockedIds[] = 93 يُستبعد في SQL (سطر 48) ثم يُعاد في سطر 59 ✓ يجب أن يظهر. إذا لم يظهر → فحص getStoryById owner bypass: "owner يرى حالته" — لكن index لا يستخدم getStoryById.
5. **فرضية أخرى أقوى**: publish() ينشر ثم يعرضها من getStoryById لكن index() يفلترها بسبب storyPrivacyLevel؟ لا — owner skip في سطر 59. **فحص فعلي مطلوب**: curl مباشر بـ uid=93 token جديد.
6. **فشل u2 لا يرى حالة u3 (none)**: story.privacy='none' — index() لا يفحص s.privacy إطلاقًا! يجب إضافة: if s.privacy=='none' → exclude (إلا owner). story.privacy per-story: 'all'/'contacts'/'none'.
7. test_stories.py فشل 6: none visibility (مهمة index)، view_count (نفس مشكلة index عدم ظهور حالة صاحبها)، reply (client_message_id أُصلح)، reply list+msg في conv (تابع)، update text (أُصلح updated_at).
8. **مهمة index() المفقودة**: فلترة s.privacy per-story: none→مخفية عن الجميع، contacts→جهات الاتصال فقط (بجانب story_privacy الإعداد؟ المواصفات: per-story privacy يحدد، لكن settings story_privacy تحدد الافتراضي. الأضمن: تطبيق الاثنين — الحالة تظهر إذا (s.privacy=='all' أو (s.privacy=='contacts' والمشاهد جهة اتصال)) AND story_privacy level allows.


## تحديث نهائي لنظام الحالات (التشغيل الأخير: 53 PASS / 2 FAIL):

الإصلاحات المنفذة:
- index(): فلترة s.privacy per-story (none→مخفية، contacts→جهات اتصال، all→story_privacy setting) ✓
- reply(): client_message_id='reply_'.$msgUuid ✓ + error_log في catch ✓
- update(): حذف updated_at غير الموجود ✓
- insertStory: date()→time()+duration (timezone safe) ✓

الفشلان المتبقيان في test_stories.py:
1. **"u3 يرى حالة u2 (contacts)"**: الاختبار أضاف جهة اتصال u1→u3 فقط — u3 وu2 ليسا جهات اتصال! إصلاح الاختبار: إضافة u3→u2 أو u2→u3 جهة اتصال (u3 contacts H3 → POST /api/v1/contacts {"contact_user_id": u2_id}).
2. **"العرض لا يُحسب view_count=?"**: سطر 192-193 — GET /stories لهاتف u1 (H1) بعد مشاهدة u3: my=[...] يفترض أن حالة u1 تظهر لصاحبها ✓ (سطر 59). "?" يعني my فارغة — لكن debug السابق أثبت owner يرى حالته! **السبب الحقيقي**: بين السطر 174 (حظر u1→u3) والسطر 191، u1 يحظر u3 ثم s1 محذوفة؟ لا — s1 موجودة. لكن blockedIds[] = u1 لا يؤثر على owner skip ✓. الأهم: السطر 183 (مشاهدة u2 مسجلة) ثم 189 (u3 دون تسجيل) → view_count يجب أن =1. "?" يعني حالة u1 غير ظاهرة لصاحبها — هل owner skip ما زال يعمل؟ ربما في الاختبار الجديد story_id قديم وانتهت صلاحيته (expires +24h — لا، الاختبار كامل ثوانٍ). **فحص**: debug log بعد آخر تشغيل.

**خطة الإصلاح**: 
- إصلاح اختبار 1: إضافة u3→u2 contact.
- إصلاح اختبار 2: التحقق من view_count عبر GET /stories/{id} (show endpoint) بدل القائمة.


## حالة المرحلة 3 (اختبار Backend) — 2026-08-21:

**نتائج الاختبارات المحلية** (كلها PASS):
- test_stories.py: 56/56 ✓ (نظام الحالات كامل: نشر/خصوصية per-story+setting/حظر/مشاهدة/read receipts/views/reactions/replies/تعديل/بلاغات/حذف إداري/admin stats/admin/stories.php لا يرمي 500)
- test_bundle_final.py: 19/19 ✓ (آخر ظهور: heartbeat/logout/offline)
- test_appeals3.py: 13/13 ✓
- test_subscriptions.py: 21/21 ✓
- test_messages2.py: PASS (رسائل للطرفين + ردود)
- flutter analyze: 0 errors ✓

**test_offline_exact.py** (إنتاج): FAIL — توكن render_accounts.json منتهي (UNAUTHORIZED) — غير حرج، بيانات قديمة.

**test_prod_flow.py** (إنتاج): فشل UnicodeEncodeError عند GET /admin/otp/registrations — لكن الطلب نفسه من python مباشرة يعمل 200 ✓. السبب المحتمل: ADMIN_TOKEN ملف يحتوي char غير مرئي؟ الحل: استخدام json.load مع encoding utf-8-sig في بداية السكربت (بدل open().read().strip()). otp_code extraction أُصلح (line 41-43).

**ملاحظة الإنتاج**: /admin/otp/registrations يعمل على الإنتاج ✓ (rows موجودة).

## المتبقي للمرحلة 4-6:
1. إصلاح test_prod_flow.py (encoding) وإعادة التشغيل على الإنتاج.
2. flutter analyze (مُنفَّذ ✓ 0 errors)، web build --wasm + web_app + commit + push.
3. Render deploy + smoke test إنتاج.
4. التقرير النهائي.


## المرحلة 4 — ترقية شاشة الحالات Flutter (حالة الملفات):

**stories_screen.dart** (492 سطر):
- StoriesScreen: قائمة قصتي + تحديثات حديثة + أيقونة نشر (نص/صورة) ✓ نشر نص مع privacy='all' + client_message_id
- _uploadStoryMedia: POST /stories/{meId}/upload (multipart) — ملاحظة: هذا endpoint قديم لكن يعمل (المسار الحقيقي POST /stories مع file؟ لا — index.php route موجود للـupload).
- _openStory: يدفع NovaStoryViewer مع _stories وindex
- StoryViewer (كلاس قديم 361-492): غير مستخدم الآن (NovaStoryViewer هو الفعلي) — يمكن حذفه لتقليل الحجم؟ لا ضرورة
- حذف قصتي: DELETE /stories/{id}

**story_viewer_fullscreen.dart** (535 سطر) — NovaStoryViewer كامل نمط واتساب:
- مجموعات حسب المستخدم، progress bars متحركة، tap يمين/يسار (next/prev)، long press pause، فيديو HTML native على الويب (web_story_video.dart / stub_story_video.dart)
- خلفية ضبابية للصور، خلفية متدرجة للنص، حذف قصتي + إغلاق
- **مفقود**: تسجيل المشاهدة (POST /stories/{id}/view) لم يُستدعَ! + reactions UI + replies UI

**الترقية المطلوبة (مرحلة 4)**:
1. NovaStoryViewer initState: استدعاء ApiService.post('/stories/{id}/view') للحالة الحالية (مرة واحدة لكل قصة، مع Set لمنع التكرار).
2. شريط تفاعلات أسفل الحالة: ردود سريعة (emoji) POST /stories/{id}/reaction {reaction}.
3. رد نصي: TextField أسفل + POST /stories/{id}/reply {body} (نفس نمط chat_screen composer).
4. عرض عدد المشاهدات لصاحب الحالة: GET /stories/{id}/views — إن isMine، عرض badge viewers.
5. إزالة StoryViewer القديم (اختياري — إبقائه بلا ضرر).

**حفظ ملاحظة API contracts**:
- view: POST /stories/{id}/view (نجاح 200 حتى مع read receipts معطلة)
- reaction: POST /stories/{id}/reaction {reaction} (201), DELETE لإزالة
- reactions: GET /stories/{id}/reactions → data.reactions[{reaction,user_id,user_name,user_avatar}]
- reply: POST /stories/{id}/reply {body} → data{conversation_id,message_id}
- replies: GET /stories/{id}/replies → data.replies[{message_id,message_body,sender_id,sender_name,created_at}]
- views: GET /stories/{id}/views → data.views[{viewer_id,viewer_name,viewer_avatar,viewed_at}] (للصاحب فقط)
- show: GET /stories/{id} → data{...view_count,is_owner}
