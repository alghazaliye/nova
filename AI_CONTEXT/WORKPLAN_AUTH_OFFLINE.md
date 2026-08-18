# خطة العمل الحالية (بعد تعليق Render بسبب الكابتشا)

المستخدم قال: "كمل الخطط الي طلبتك بعدين بنرجع للاستضافة"
أي: نكمل تنفيذ نظام المصادقة + Offline-First ثم نرجع لـ Render.

## المرحلة 1: نظام المصادقة المتكامل (pasted_content_3.txt)
الموقع: /home/ubuntu/upload/pasted_content_3.txt (712 سطر)

### المطلوب:
1. إعدادات طرق التسجيل: auth_phone_registration / auth_email_registration (ON/OFF مستقل)
2. إعدادات تسجيل الدخول منفصلة: auth_phone_login / auth_email_login / auth_username_login
3. Phone OTP مستقل: enabled/expiry/attempts/resend_cooldown/max_resend/delivery_mode
4. Email OTP مستقل (نفس الحقول): otp_email_*
5. مزودو SMS موجودون (migrate_otp.sql) — لا تغيير، فقط التأكد
6. مزودو Email جدد: email_providers جدول (smtp/http_rest) بحقول: name/type/host/port/encryption/username/password/api_key/from_email/from_name/priority/is_enabled/is_default/is_backup — لا نعرض كلمات المرور كاملة
7. مزود افتراضي + احتياطي لكل قناة مستقل + Fallback chain (manual fallback → عرض OTP في لوحة الإدارة — موجود جزئيًا في otp settings)
8. صفحة admin موحدة: /admin/auth-settings.php "المصادقة والتسجيل" بأقسام: طرق التسجيل / طرق الدخول / Phone OTP / Email OTP / SMS Providers / Email Providers
9. منع تناقض: Phone Registration ON + Phone OTP OFF → تحذير
10. UI ديناميكي حسب config (Flutter)
11. حساب واحد: users {id, username, email, phone, password_hash, email_verified, phone_verified} — schema الحالي: users فيه phone/email/username/password_hash وis_verified واحد فقط → إضافة email_verified + phone_verified بـ migration
12. إضافة هاتف/بريد لاحقًا عبر الإعدادات + OTP
13. RBAC جديد: auth.settings.view/update (الموجود otp.providers.* + registration.* تبقى)
14. Audit Log: AUTH_SETTING_CHANGED / PHONE_REGISTRATION_ENABLED/... / OTP_VIEWED / OTP_REGENERATED (audit_logs موجود + logAudit موجود في admin/includes/auth.php)
15. API جديد: GET /api/v1/auth/config → {registration:{phone,email}, login:{phone,email,username}} بدون أسرار
16. Backend يفرض الإعدادات: register/login يرفض حسب الإعدادات
17. register بالبريد + login بالبريد/username/password
18. إعدادات DB في app_settings (الجداول موجودة: app_settings, audit_logs)

### ملفات Backend الرئيسية:
- /home/ubuntu/nova_new/backend/controllers/AuthController.php: register (هاتف فقط حاليًا) / verifyOtp / login / resendOtp
- /home/ubuntu/nova_new/database/schema.sql: users مع phone/email/username/password_hash/is_verified، client_message_id موجود في messages مع unique key (conversation_id, client_message_id)
- /home/ubuntu/nova_new/database/migrate_otp.sql: مزود test افتراضي + صلاحيات OTP
- /home/ubuntu/nova_new/admin/settings.php: صفحة إعدادات عامة
- /home/ubuntu/nova_new/backend/public/index.php: routes

### Flutter:
- nova_flutter/lib/providers/auth_provider.dart: phone-only حاليًا
- nova_flutter/lib/screens/phone_screen.dart: login بالهاتف فقط
- nova_flutter/lib/screens/otp_screen.dart: verification

## المرحلة 2: Offline-First (pasted_content_2.txt)
الموقع: /home/ubuntu/upload/pasted_content_2.txt (961 سطر)

### المطلوب:
- Drift + SQLite: chats/messages/contacts/media (metadata)/sync_queue
- Message status: local/sending/sent/delivered/read/failed/pending_sync
- Outbox: SEND_MESSAGE/UPLOAD_MEDIA/EDIT_MESSAGE/DELETE_MESSAGE/MARK_DELIVERED/MARK_READ/UPDATE_PROFILE
- Exponential backoff: 2/5/10/30 ثانية قابل للتعديل
- Network detection: Online/Offline/Server-unreachable
- Health check: GET /api/v1/health
- Media storage: app_storage/media/{images,videos,audio,documents,thumbnails} + checksum + download_status
- Incremental sync: last_sync_cursor + pagination 50
- Idempotency: client_message_id UUID (موجود بالـschema)
- UI: عرض Local DB فورًا ثم sync في الخلفية
- Search offline
- Storage settings page
- مؤشرات UI صغيرة: Offline/Syncing/Pending/Failed مع إعادة إرسال
- لا كسر JWT/FCM/WebRTC

### Backend APIs مطلوبة:
- GET /api/v1/health
- GET /api/v1/sync?cursor=&limit=50 → {cursor, messages, chats, contacts, deletions, updates}
- POST /api/v1/messages/batch (اختياري)
- POST /api/v1/messages/{id}/ack + /read موجودة (MessageController)

## حالة Render (متوقف):
- صفحة: https://dashboard.render.com/register
- بيانات: alghazaliye@gmail.com / Aa738155861
- hCaptcha لم تُحل. المستخدم سيرجع لها لاحقًا.
- بعد نجاح التسجيل: Web Service من GitHub alghazaliye/nova branch main Docker port 8080
- Env vars: MYSQL_DATABASE=nova, MYSQL_USER=nova_user, MYSQL_PASSWORD=render2026, JWT_SECRET=nova-render-secret-key-2026-xyz, OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456

## أوامر مهمة:
- الخادم المحلي: cd /home/ubuntu/nova_new && pkill -f "php -S" ; nohup php -d opcache.enable=0 -S 0.0.0.0:8080 backend/public/router.php > /tmp/php_server.log 2>&1 &
- Flutter APK: export PATH=/home/ubuntu/flutter/bin:$PATH && export JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64 && export ANDROID_HOME=/home/ubuntu/Android && export GRADLE_OPTS="-Xmx512m" && export _JAVA_OPTIONS="-Xmx1g" && cd /home/ubuntu/nova_new/nova_flutter && flutter build apk --release --android-skip-build-dependency-validation
- Flutter web: flutter build web --wasm --release --no-tree-shake-icons ثم نسخ لـ web_app + base href + gzip (انظر context السابق)
- Docker image: nova-messenger:511، mysql exec: mysql -h127.0.0.1
- GitHub: gh repo alghazaliye/nova، tag v5.1.0 مرفوع

## معرفة بنيوية (تمت قراءتها):

### AuthController.php (597 سطر) — النقاط المهمة:
- register(): هاتف فقط + OTP bypass (otp_required=0) + rate limit + cooldown + createAndSend + otp_dev في dev
- verifyOtp(): pipeline OTP + fallback legacy + إنشاء user (uuid, phone, name, is_verified=0) + ban check + createSession
- login(): هاتف + OTP + otp_required=0 → loginOrCreateWithoutOtp
- resendOtp(): resend من OtpService
- helpers: getAppSetting(), isDevelopmentOtp(), assertOtpProviderAvailable(), createSession(), getUserById() (ReflectionMethod على UserController::getUserById)
- registerWithoutOtp() + loginOrCreateWithoutOtp()

### index.php routes:
- auth: /auth/register, /auth/login, /auth/verify-otp, /auth/resend-otp, /auth/logout, /auth/me, /auth/refresh (أسطر 127-150)
- admin otp: /admin/otp/* (355-400)
- health: GET /health → {status:ok, version:1.0.0, timestamp} (445-447) — **موجود بالفعل!** Offline-first يحتاج فقط /api/v1/health → إضافة route
- 404 fallback في النهاية

### AdminOtpController:
- authenticateAdmin($permission): JWT Bearer + role=admin + role_permissions check
- logAdminAudit($admin, $action, $entityType, $entityId, $description) — global function في نهاية الملف
- settingsGet(): يقرأ app_settings keys → ['settings'=>...]
- settingsUpdate(): allowlist keys → upsert → logAdminAudit
- registrationsGetCode: OTP_VIEWED audit; registrationsVerify: OTP_VERIFIED; cancel: OTP_CANCELLED

### ProviderManager:
- list/get/create/update/delete/toggle/test — تشفير secrets عبر OtpEncryption::encrypt/decrypt
- test(): code=000000, match على type (twilio/vonage/http_rest/test)

### schema.sql:
- users: id, uuid, phone(NOT NULL), email, password_hash, name, username, bio, avatar, status_text, is_online, last_seen, is_verified, is_blocked, blocked_at — UNIQUE phone + UNIQUE username
- audit_logs: admin_id, action, entity_type, entity_id, description, ip_address, user_agent
- app_settings: setting_key (UNIQUE), setting_value
- messages فيها client_message_id مع unique(conversation_id, client_message_id) — idempotency جاهز

### المطلوب إضافته (DB migration جديدة migrate_auth.sql):
1. users: email_verified TINYINT(1) DEFAULT 0, phone_verified TINYINT(1) DEFAULT 0
2. users: تعديل phone UNIQUE → يجب إبقاء phone=NULL مستخدمين بريد (مستخدمو البريد فقط phone=NULL → نحتاج تغيير UNIQUE phone للسماح بـ NULL متعددة أو استخدام partial index... MariaDB لا يدعم partial index → الحل: users يسجلون بـ phone أو email، ونضيف UNIQUE غير جزئي لا يعمل مع NULLs متعددة، MariaDB لا يدعم فهارس جزئية. الحل: نترك UNIQUE على phone لكن نسجل بريد-فقط بـ phone=NULL لا يُقبل إلا بـ phone فريد؛ أو نجعل UNIQUE(phone) مع phone NULL غير فريد — في MySQL/MariaDB فهارس B-tree تتجاهل NULL وتسمح بتكرار NULL. ✅ NULLs متعددة مسموحة في UNIQUE!)
3. users: email UNIQUE (NULLs allowed ✅)
4. email_providers جدول: id,name,type(smtp/http_rest),status(enabled/disabled),priority,is_default,is_fallback,host,port,encryption(none/ssl/tls),username,password(API key),from_email,from_name,extra_config,success_count,failure_count,last_used_at
5. app_settings جديدة: auth_phone_registration, auth_email_registration, auth_phone_login, auth_email_login, auth_username_login, otp_phone_enabled, otp_email_enabled, otp_phone_expiry_minutes, otp_email_expiry_minutes, otp_phone_max_attempts, otp_email_max_attempts, otp_phone_resend_cooldown_seconds, otp_email_resend_cooldown_seconds, otp_phone_max_resends, otp_email_max_resends, otp_phone_delivery_mode, otp_email_delivery_mode
6. صلاحيات RBAC جديدة: auth.settings.view, auth.settings.update, email.providers.view/create/update/delete/test
7. جدول email_delivery_logs (اختياري)

### صفحات admin موجودة: admin/otp-settings.php (نمط: fetch POST /api/v1/admin/otp/settings, Bearer localStorage.adminToken)

## تقدم التنفيذ (محدّث):

### مكتمل (Backend المصادقة):
1. ✅ database/migrate_auth.sql — مُطبّق على DB (users: email_verified + phone_verified + uq_users_email, email_providers, email_delivery_logs, 20 إعداد, 7 صلاحيات RBAC, مزود اختبار (manual))
2. ✅ backend/otp/EmailOtpService.php — email_verification_codes table (ensured on construct), sendSmtp (php-native TLS SMTP), sendRest, createAndSend, verifyCode, resendCooldown, resend, revealManualCode, getPendingCodes, adminVerify, cancel
3. ✅ backend/otp/EmailProviderManager.php — list/get/create/update/delete/toggle/test (test email مع 000000), deliveryLogs
4. ✅ backend/helpers/AuthConfigService.php — getConfig (registration:{phone,email}, login:{phone,email,username}, otp.{phone,email}, registration_disabled, app_name), assertRegistrationMethod, assertLoginMethod
5. ✅ backend/controllers/EmailAuthController.php — config(), registerEmail(), verifyEmailOtp(), resendEmailOtp(), loginEmail(), loginUsername(), setPassword()
6. ✅ backend/controllers/AdminAuthController.php — settingsGet/Update, providers CRUD+toggle+test, registrations (email OTP) index/getCode/verify/cancel — يستخدم logAdminAudit

### متبقٍ:
- إضافة routes في index.php: GET /auth/config, POST /auth/register-email, /auth/verify-email-otp, /auth/resend-email-otp, /auth/login-email, /auth/login-username, /auth/set-password + /admin/auth/settings + /admin/email-providers + /admin/email-registrations
- فحص Validator class (يجب أن يدعم email()/required() — تحقق من وجود)
- تحديث AuthController: register/login يدققان assertRegistrationMethod/assertLoginMethod (phone) + دعم OTP phone disabled (bypass)
- صفحة admin موحدة: admin/auth-settings.php + admin/email-providers.php + admin/email-registrations.php + روابط sidebar
- إعادة تشغيل الخادم + اختبار
- Docker: تحديث Dockerfile/entrypoint لاستيراد migrate_auth.sql
- Flutter: auth_config fetch + شاشة ديناميكية + login email/username + set-password

## نتائج الاختبار (18 Aug):

### ✅ يعمل:
- GET /auth/config → يرجع registration/login/otp settings صحيح
- POST /auth/register-email → يعمل في test mode (delivery_mode=manual, otp_dev=123456)
- POST /auth/verify-email-otp → ينجح (verified)، لكن **يجب طلب رمز جديد لكل محاولة** (الرمز يتحقق مرة واحدة ثم يُعلّم verified)
- POST /auth/login-email مع auth_email_login='0' → EMAIL_LOGIN_DISABLED ✅
- POST /auth/login-username مع auth_username_login='0' → USERNAME_LOGIN_DISABLED ✅
- POST /auth/login-email بكلمة مرور خاطئة → AUTH_FAILED (sleep 1) ✅

### إصلاحات DB:
- ALTER TABLE users MODIFY phone VARCHAR(30) NOT NULL DEFAULT '' (العمود كان NOT NULL بدون default → كسر INSERT للإيميل)
- UPDATE users SET phone='' WHERE phone IS NULL

### لم يختبر بعد:
- set-password (يحتاج JWT — نستخدم تسجيل هاتف +3966501234567 موجود)
- admin/auth/settings + admin/email-providers + admin/email-registrations (تحتاج JWT إدارة: POST /admin/otp/login بـ admin@nova-messenger.com/Admin@1234)
- إعادة تسجيل بالبريد ثاني مرة بعد verified → يجب أن يرجع EMAIL_EXISTS أو يربط الإيميل

### خطوات متبقية:
1. صفحات admin: auth-settings.php + email-providers.php + email-registrations.php + روابط sidebar
2. تحديث migrate_auth.sql ليشمل phone default ''
3. Docker: COPY migrate_auth.sql + استيراد في entrypoint
4. Flutter: auth config + شاشة تسجيل/دخول ديناميكية + login email/username
5. رفع GitHub + بناء APK

## الحالة (Phase 2 - اكتمل Backend + Admin):

### ✅ مكتمل:
- migrate_auth.sql مطبق (email_providers + حقول users + إعدادات + 7 صلاحيات auth.* + email.*)
- ALTER users.phone = NOT NULL DEFAULT '' (يصلح email-only registration)
- EmailOtpService + EmailProviderManager + AuthConfigService + EmailAuthController + AdminAuthController
- routes في index.php: /auth/config, register-email, verify-email-otp, resend-email-otp, login-email, login-username, set-password + 12 admin route
- AdminController requires في index.php موجودة (خط 23-24)
- auth-settings.php + email-providers.php + email-registrations.php + sidebar links
- صفحات admin تستخدم نفس بنية OTP pages (header.php footer.php JWT bootstrap من $_SESSION['admin_id'])

### ✅ اختبارات API ناجحة:
- GET /auth/config يعمل
- register-email + verify (test mode 123456) يعمل
- login-email/username مفعّل/معطّل يعمل (AUTH_FAILED مع sleep 1)
- admin/auth/settings GET/POST يعمل + warnings
- admin/email-providers GET يعمل (مزود اختبار موجود)
- admin/email-registrations GET يعمل (rows/pagination)
- حفظ settings يعمل ويُكتب لـ app_settings

### بنية API للـ admin (من الكود):
- settingsGet: {settings:{flat keys}, warnings:[]} (NOT {success:true})
- settingsUpdate: {updated:true, message}
- providersIndex: {providers:[...], message}
- registrationsIndex: {rows:[], total, pages, ...}
- registrationsGetCode: {otp_code, expires_at}
- providersTest: {success, message}
- JWT: POST /admin/otp/login (email/password)

### متبقي الآن:
1. إعادة ضبط settings للاختبار (auth_phone_registration=1, auth_email_registration=1, auth_username_login=1, rest كما هي) — مهم لأننا ضبطنا 0/0 أثناء الاختبار!
2. اختبار صفحات admin فعليًا بالمتصفح (تسجيل دخول admin@nova-messenger.com/Admin@1234)
3. تحديث migrate_auth.sql + Dockerfile + entrypoint + rebuild image 512
4. Flutter: auth config + شاشة تسجيل/دخول ديناميكية (pasted_content_3.txt)
5. ثم Offline-First (pasted_content_2.txt)
6. GitHub push + APK + Render (لاحقًا)

## الحالة بعد اختبار المتصفح (Phase 3 جارٍ):

### ✅ صفحات admin تعمل:
- auth-settings.php: تعمل كاملة، الحفظ يعمل (رسالة "تم حفظ إعدادات المصادقة والتسجيل")، لا تحذيرات PHP
- email-providers.php: تعمل (مزود اختبار manual يظهر) — إصلاح: loadProviders كان يفحص j.success غير موجود، الآن يفحص r.ok
- email-registrations.php: API يعمل (rows/pagination)
- sidebar.php: إصلاح $admin undefined: `if (!isset($admin)) { $admin = ['name' => '', 'role_name' => '']; }`
- صلاحيات super_admin (role_id=1): جميع email.providers.* + auth.settings.view موجودة ✅

### ⚠️ ملاحظة مهمة للـ Docker/Render:
- صفحة email-providers في HTML تعرض "0 / 0" ناجح/فاشل وحالة "مقفل" (disabled badge) للمزود الاختباري - يبدو أن status badge يُقرأ من provider.status لكن المزود enabled في DB - يجب التحقق (قد تكون badge class تُستخدم 'good'/'bad' بدل 'enabled'/'disabled')
- settings الافتراضية الحالية: auth_phone_registration=1, auth_email_registration=1, login phone/email/username كلها=1, otp_phone/otp_email enabled

### الخطوات التالية (Phase 3):
1. تحديث migrate_auth.sql في Dockerfile + entrypoint.sh (استيراد بعد migrate_otp.sql)
2. rebuild image: `docker build -t nova-messenger:512 .` من /home/ubuntu/nova_new
3. اختبار container (mysql -h127.0.0.1 داخلها)
4. git add/commit/push + tag v5.2.0 + release
5. Flutter: /auth/config + شاشة تسجيل/دخول ديناميكية (read pasted_content_3.txt lines about flutter)

## حالة Docker 512 (Phase 3):

### ✅ Docker 512 يعمل:
- image nova-messenger:512 مبنية بـ sudo docker (النسخة السابقة بدون sudo فشلت: permission denied docker.sock)
- container nova512 على port 8081: كل الجداول + email_verification_codes + settings=33 مزودي OTP/email + /auth/config يعمل + /health يعمل + admin/auth-settings.php = 302 (redirect to login = صحيح)
- register-email → 200 delivery_mode=manual يعمل
- **مشكلة**: verify-email-otp بعد verify أول ناجح يرجع OTP_EXPIRED (لأن الكود القديم cancel) لكن **الأول نفسه أحيانًا يرجع HTTP 500** — يجب فحص EmailLoginController verifyEmailOtp/completeEmailRegistration: السبب المرجح خطأ PHP بعد التحقق الناجح (status=verified في DB)
- curl طويل مع sudo docker exec يسبب انتظار طويل في shell — استخدم -timeout أو افصل الأوامر

### خطوات التحقق من المشكلة:
1. tail /var/www/html/logs/apache_error.log داخل container
2. EmailLoginController: بعد verify يتحقق من otp_phone_login؟ قد يقفز لخطأ settings أخرى
3. auth_phone_login=1 default لكن في container الافتراضي؟ (يجب فحص migrate_auth.sql القيم)

### المتبقي بعد إصلاح:
- اختبار login-email + login-username في container
- git commit/push + tag v5.2.0 + release + APK
- Flutter: إضافة fetchAuthConfig + تسجيل بريد (registerEmail) + verifyEmailOtp في auth_provider.dart + تعديل phone_screen (تبويب هاتف/بريد حسب config) + login email/username
- ثم Phase 4 Offline-First

## تشخيص verify-email-otp (تم الحل):

السلوك الفعلي **صحيح**: أول request verify ينجح (status→verified) ويُرجع token، والثاني يرجع 400 OTP_EXPIRED لأن الرمز أُستهلك. الـ"HTTP:500" الظاهري في output كان بسبب أمر curl الطويل المتداخل (output متشابك) — لا يوجد خطأ فعلي. سجل apache_error.log فارغ (log لا يكتب لأجل www-data permissions لكن لا توجد أخطاء PHP fatal).

**نتيجة الاختبار النهائي**: register-email 200 + verify-email-otp 200 مع token + user يعملان في container 512. لا إصلاح مطلوب.

## نتائج الاختبار النهائية (Phase 3 - Backend كامل ✅):

تم اختبار كل المسارات على الخادم المحلي (8080) بنجاح:
| الاختبار | النتيجة |
|---|---|
| register-email (OTP) | 200 + delivery_mode=manual |
| verify-email-otp → إنشاء user | 200 + token + user (id=17) |
| set-password | 200 |
| login-email + password | 200 + token |
| منع register-email عند auth_email_registration=0 | 400 EMAIL_REGISTRATION_DISABLED |
| منع login بالهاتف عند auth_phone_login=0 | PHONE_LOGIN_DISABLED |

**إصلاحات منفذة**: EmailAuthController verifyEmailOtp — INSERT user بدون phone كان يسبب `Duplicate entry '' for key 'uq_users_phone'` → الآن يُدرج `phone='e_'+substr(sha256(email),0,26)` و`username` بنفس القيمة (فريد لكل حساب).

**ملاحظة للمرحلية Flutter**: حسابات email-only لها phone/username=e_hash — واجهة Flutter يجب أن تعامل phone كـ e_... بصمة وليس رقمًا حقيقيًا.

### متبقي Phase 3:
1. إعادة ضبط الإعدادات للقيم الافتراضية (auth_phone_registration=1, auth_email_registration=0, login phone=1/email=0/username=0) — تمت على local DB: أُعدت email_registration=0 + phone_login=0 (يجب إعادة phone_login=1!)
2. إعادة بناء Docker 512 (بعد إصلاح INSERT) + اختبار container مرة أخرى
3. git commit/push + release v5.2.0 + APK
4. Phase 4: Offline-First Flutter
5. ملاحظة: سكربت الاختبار الكامل في /home/ubuntu/nova_new/tmp_test_auth2.php — أعد استخدامه

### ملفات Flutter الحالية:
- lib/providers/auth_provider.dart (register/login/verifyOtp — يحتاج: fetchAuthConfig + registerEmail + verifyEmailOtp + loginEmail)
- lib/screens/phone_screen.dart (شاشة الدخول الحالية — يحتاج تبويبات حسب config)
- lib/screens/otp_screen.dart (6-digit OTP — يحتاج support email mode)
- ApiService.baseUrl افتراضي: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/api/v1

## تشخيص HTTP 500 في verify-email-otp داخل Docker 513 (جارٍ):

الأعراض: register-email يرجع `delivery_mode:"manual"` لكن status في DB = `manual` مع delivery_mode column = `auto` (مغلوطة؟ لا — INSERT يضع status=manual, delivery_mode='manual'... لكن العرض أظهر status=manual وdelivery_mode=auto — INSERT يستخدم status=$manual?'manual':'pending' و delivery_mode=$deliveryMode التي كانت 'auto' رغم manual=true! خطأ في الكود).

نظرية السبب: EmailOtpService::createAndSend سطر delivery_mode/INSERT غير متسق (manual mode لا يضبط status=manual إذا كان هناك chain واحد باسم مختلف)، ثم verifyCode يبحث عن status IN ('pending','sent','manual') → يجد، لكن code_hash قد يكون NULL أو sendViaProviders فشل بصمت ثم manual_code_hash وُضع... والـ500 بعد verify ناجح = createSession/UserController getUserById failure على حساب بدون phone؟ لا — phone أصبح e_... الآن.

**خطوة التحقق التالية**: فحص /tmp/ver.json (الجسم 500) عبر curl output، وفحص logs داخل container: `sudo docker exec nova513 php /var/www/html/public/index.php` غير متاح — الأفضل إضافة error_log=/var/www/html/logs/php_errors.log في .env أو php ini داخل container وتشغيل الطلب مجدداً.

ملاحظة مهمة: على الخادم المحلي 8080 كل شيء يعمل ✅ (Docker 513 وحده 500). الفارق: container يقرأ OTP_PROVIDER=test من ENV لكن email providers chain — EmailOtpService::getProviderChain يقرأ جدول email_providers! إذا email_providers فارغ وotp_providers فيه test فقط، getProviderChain يرجع [] → manual=true → status=manual ✅ هذا صحيح. الـ500 بعد ذلك = في verify: getUserById من UserController قد يفشل؟ أو createSession.

### حالة Docker images:
- nova-messenger:513 = أحدث image (تشمل إصلاح INSERT + ENV defaults)
- container: nova513 (-p 8082:8080) يعمل، DB password: nova2026
- admin login داخل 513: admin@nova-messenger.com / Admin@1234

## حالة Docker 513 (محدثة):

**السبب النهائي المفترض للـ500**: جدول `device_registrations` كان مفقودًا من Docker DB → `UserController::getUserById()` يسقط. أُضيف الجدول في migrate_auth.sql (قسم 7: columns: id,user_id,device_uuid,device_name,device_model,platform,os,os_version,app_version,device_fingerprint,fcm_token,is_active,last_seen,created_at,updated_at + uq_device_user UNIQUE).

**تم**: إضافة جدول device_registrations لـcontainer nova513 عبر root (✅)، و /tmp/php_errors.log error_log مع display_errors في /usr/local/etc/php/conf.d/99-nova-debug.ini (✅).

**ملاحظة**: killing apache2-foreground أوقف container! إعادة تشغيله بـ `docker start nova513` (✅ health 200).

**الخطوة التالية**: إعادة اختبار register+verify عبر 8082 — إن نجح → إعادة بناء image 514 (لأن ini وdevice_registrations غير محفورين في image — لكن migrate_auth.sql الجديد سيضيف الجدول عند init جديد فقط، وini الجديد يُحفظ بـ COPY في Dockerfile: أضف `COPY docker/99-php-debug.ini` أو sed في Dockerfile) → ثم git commit + push + release v5.2.0 + APK → ثم Phase 4 Offline-First.

**تذكير**: الخادم المحلي 8080 يعمل كاملًا مع كل الاختبارات ✅. المشكلة فقط في Docker init القديم (schema قديم). الخادم المحلي لا يحتاج هذا الإصلاح.

## تشخيص container nova513 (محدّث):

**حقائق مثبتة**:
1. createAndSend (EmailOtpService) يعمل داخل container — سجل id=11 status=manual ✓ (تأكد عبر tmp_debug_createandsend.php).
2. verify عبر HTTP = 500 بجسم فارغ. سجل php_errors.log فارغ رغم log_errors=1 في /usr/local/etc/php/conf.d/99-nova-debug.ini (تم التحقق من phpinfo أنه مُحمَّل).
3. ini الجديد مُفعّل عبر phpinfo (/api/v1/phpinfo route أُضيفت مؤقتًا في index.php container سطر 512 — يجب حذفها قبل commit!).
4. user id=15 docktest@example.com أُنشئ يدويًا عبر SQL (اختبار SELECT يعمل).
5. Apache vhost: ErrorLog /var/www/html/logs/apache_error.log (فارغ).

**المهم**: الـ500 لا يُسجَّل في أي مكان → Apache قطع الاتصال (process kill/crash) وليس PHP exception. سبب محتمل: **Apache timeout / memory limit** أو **output buffer crash**. أو أن الخطأ يحدث في مرحلة مبكرة قبل routing (router.php).

**الخطوة التالية**: فحص access log: curl -s ... verify → cat /var/www/html/logs/apache_access.log. ثم تجربة: إعادة إنتاج 500 + مراقبة docker logs nova513 مباشرة أثناء الطلب (Apache writes errors to stdout).

**ملاحظة**: الخادم المحلي 8080 يعمل كاملًا ✅ (register+verify+login بالبريد OK). المشكلة فقط container 513.

**المتبقي بعد إصلاح 500**:
1. إعادة بناء image nova-messenger:514 (migrate_auth.sql الجديد + ini debug مضمن + /phpinfo route محذوفة)
2. git commit + push + tag v5.2.0 + release + APK
3. Phase 4: Offline-First Flutter (Drift + Sync + Outbox + Health)
4. العودة لـ Render.com (تسجيل + نشر)

## اكتشاف سبب 500 في Docker (مُحل):

**السبب**: جدول `user_subscriptions` (و`plans`) مفقود من Docker DB — `UserController::getUserById()` سطر 336 يستعلمه. أضفت CREATE TABLE IF NOT EXISTS + seed plans (1 مجاني/2 بريميوم/3 مؤسسي) إلى migrate_auth.sql قسم 8 (✅).

**إصلاحات idempotent**: قسم 1 في migrate_auth.sql حُوّل من ALTER مباشر إلى conditional via @sql PREPARE (✅) لأن ALTER column يكرر الخطأ.

**اكتشافات debugging**:
- set_exception_handler في index.php container كشف الخطأ الحقيقي (أُضيف بـsed بعد declare strict_types سطر 9)
- /phpinfo route موقت أُضيف container سطر 512 (يجب حذفها + handler قبل commit image!)
- ini debug في /usr/local/etc/php/conf.d/99-nova-debug.ini (display_errors+log_errors=/tmp/php_errors.log) — لم يُفعّل في mod_php حتى restart container (لا restart Apache وحده)

**المتبقي**:
1. `docker exec nova513 mysql -uroot nova < migrate_auth.sql` — نفّذته لكن فشل عند سطر 13 القديم (قبل edit). أعد تطبيق الملف الجديد الآن
2. اختبار verify الكامل: register → verify → login-email → login-username على :8082
3. بعد النجاح: بناء image 514 (حذف handler/phpinfo من index.php قبل commit — أو أبقِ handler كتحسين مفيد؟ الأفضل حذف phpinfo route والإبقاء على exception handler كإصلاح دائم مفيد production)
4. git add/commit/push + tag v5.2.0 + release + APK
5. Phase 4: Offline-First Flutter
6. Render.com لاحقًا

**ملاحظة**: health endpoint GET /api/v1/health موجود ✅ في index.php (خط 512) — مطلوب لمراقبة Offline-First.

## Progress update (Phase 3 completed, Phase 4 in progress — Aug 19, 2026):

### Phase 3 DONE ✅:
- migrate_auth.sql idempotent (conditional PREPARE for users columns/email index)
- Docker image nova-messenger:514 built & tested: register→verify→set-password→login-email→login-username all 200 ✅; user_subscriptions table present from entrypoint
- set_exception_handler دائم في backend/public/index.php الأصلي (JSON INTERNAL_ERROR 500 production-safe)
- git commit + tag v5.2.0 + push + GitHub Release: https://github.com/alghazaliye/nova/releases/tag/v5.2.0
- Container nova514 يعمل على :8082 (nova513 محذوف)

### Phase 4 Offline-First — progress:
- pubspec.yaml: drift ^2.21.0, sqlite3_flutter_libs, path_provider, connectivity_plus ^6.0.5, sqflite_common_ffi, sqflite, rxdart, crypto + dev: build_runner, drift_dev — flutter pub get نجح
- أنشأت lib/offline/:
  - local_nova_db.dart (Drift: local_chats, local_messages, local_users, local_media, local_outbox, local_sync_state; upsertChat helper; schemaVersion=1)
  - local_nova_db_provider.dart (singleton NativeDatabase.createInBackground, wipeLocalData عند logout)
  - network_detector.dart (NovaNetworkState: online/serverDown/offline; probe كل 30s + listener; GET /health)
  - media_store.dart (downloadMedia local-first, clearCache, usageByCategory)
- المتبقي:
  1. outbox_service.dart + sync_engine.dart (push outbox بـexponential backoff 2/5/10/30، pull incremental بـlast_sync_ts من conversation updated_at + messages updated_at)
  2. local_sync_service.dart (upsert محادثات/رسائل/مستخدمين، personal-delete sync)
  3. dart run build_runner generate .g.dart
  4. دمج: auth_provider (registerEmail/verifyEmailOtp/loginEmail/loginUsername/fetchAuthConfig) + chats_screen (local-first) + chat_screen (send offline, status chips) + settings_screen (storage page)
  5. flutter analyze + APK build + رفع + تقرير
- Backend جاهز: client_message_id idempotency ✅ (uq_messages_client_message_id), health endpoint ✅, before_id pagination ✅
- env Flutter: export PATH=/home/ubuntu/flutter/bin:$PATH; JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64; ANDROID_HOME=/home/ubuntu/Android; GRADLE_OPTS="-Xmx512m"; _JAVA_OPTIONS="-Xmx1g"

## Phase 4 debug notes (Aug 19):

Created lib/offline/: local_nova_db.dart, local_nova_db_provider.dart, network_detector.dart, media_store.dart, local_sync_service.dart, outbox_service.dart, sync_engine.dart. pubspec updated (drift+sqlite3_flutter_libs+path_provider+connectivity_plus+sqflite+sqflite_common_ffi+rxdart+crypto+build_runner+drift_dev), pub get OK, build_runner generated .g.dart.

**BUG discovered in .g.dart**: duplicate definition — local_nova_db.g.dart:3050 `class $LocalMediaTable extends LocalMedia` and 3343 `class LocalMedia extends DataClass` → same name. @DataClassName('LocalMedia') present on LocalMedia table but drift still generated DataClass named LocalMedia (same as table name — conflict). Fix: rename the TABLE class or DataClass. Correct approach: use distinct names: keep @DataClassName('LocalMediaRecord') on LocalMedia table (or rename table to LocalMediaTable class). Other tables: LocalChats→LocalChat, LocalMessages→LocalMessage, LocalUsers→LocalUser already OK (plural table ≠ singular class). So ONLY LocalMedia (singular both) conflicts. Fix: @DataClassName('LocalMediaRecord') on LocalMedia table + update all usages in media_store.dart/outbox_service/local_sync_service.

Also fix in outbox_service.dart: import OrderingTerm from drift (import 'package:drift/drift.dart' as drift; use drift.OrderingTerm). Same in sync_engine.dart. Remove unused _backoffFor in outbox.

**Drift row access**: rows expose plain fields (row.localPath is String) — media_store errors were cascade from duplicate class; after fixing name conflict, row.localPath access works (DataClass has plain fields confirmed: class LocalMedia extends DataClass { final int id; ... }).

**Next after fix**: rebuild build_runner, re-analyze, then integrate into UI:
- chats_screen: local-first (fallback localChats select, then API refresh)
- chat_screen: _sendMessage writes Local DB immediately with status pending_sync + outbox push SEND_MESSAGE; display local messages from db.localMessages streamed (watch)
- auth_provider: fetchAuthConfig + registerEmail/verifyEmailOtp/loginEmail/loginUsername (API paths: /auth/register-email, /auth/verify-email-otp, /auth/login-email {email,password}, /auth/login-username {username,password}, /auth/config GET)
- settings_screen: storage usage page (MediaStore.usageByCategory + clearCache)
- flutter analyze lib/ whole, then flutter build apk (env in previous notes), commit/push v5.3.0 + report.

## Phase 5 progress (Aug 19):

Offline core DONE (lib/offline/, analyze clean): local_nova_db (Drift; LocalChats/LocalMessages/LocalUsers/LocalMedia[DataClassName LocalMediaRecord]/LocalOutbox/LocalSyncState; companion names: LocalChatCompanion, LocalMessageCompanion, LocalUsersCompanion, LocalMediaCompanion(!), OutboxItemCompanion, SyncStateCompanion; DataClasses: LocalChat, LocalMessage, LocalUser, LocalMediaRecord, OutboxItem, SyncState; constructor: LocalNovaDb(super.executor)).

auth_provider.dart UPDATED: added AuthConfig class + fetchAuthConfig + registerEmail + verifyEmailOtp + resendEmailOtp + loginEmail(email,password) + loginUsername(username,password) + setPassword.

phone_screen.dart REWRITTEN (217→dynamic): tabs هاتف/بريد/اسم مستخدم حسب authConfig; single-mode when 1 tab enabled; disabled-login message; _doPhoneLogin/_doEmailLogin/_doUsernameLogin; _handleLoginResult.

Remaining Phase 5:
1. chats_screen.dart: add offline layer — LocalSyncService.upsertChats after fetch + fallback when serverDown; show NovaNetworkState chip.
2. chat_screen.dart: _sendMessage writes local_messages first (status pending_sync) + OutboxService.push SEND_MESSAGE; show local messages on top when offline; message status chip (pending_sync/offline/syncing).
3. settings screen: add storage page (MediaStore.usageByCategory, clearCache).
4. main.dart: init NetworkDetector + OutboxService.start at app start (check where provider listens).
5. flutter analyze whole lib, flutter build apk, commit+tag v5.3.0, report.

Key API paths: /auth/config, /auth/register-email, /auth/verify-email-otp, /auth/resend-email-otp, /auth/login-email, /auth/login-username, /auth/set-password, /health.
chat_screen: message types text/structured; client_message_id uuid via _uuid.v4(); _sendStructuredMessage for media types. chats_screen: _load at line 346 (ApiService.get /conversations).

## Phase 5 continuation (Aug 19, after chats/chat_screen/phone_screen):

DONE: chats_screen._load: saves via LocalSyncService.upsertChats(toJson-map) + cachedChats fallback (rows→Conversation manual ctor). chat_screen._sendMessage: storePendingMessage + OutboxService.push(operation:'SEND_MESSAGE', entityRef:'${localRow!.id}', payload:{conversation_id,...}). Imports added in chat_screen (local_nova_db.dart, local_sync_service.dart, outbox_service.dart) and chats_screen (local_sync_service.dart). phone_screen.dart rewritten: tabs per authConfig; phone screen fixed errors. All analyze clean.

NOTE: outbox_service.drain() processes SEND_MESSAGE via _sendTextMessage(p): reads payload['conversation_id'], 'client_message_id', 'type', 'body', 'reply_to_message_id' — matches my payload keys. Good.

Remaining:
1. main.dart: init NetworkDetector.start() + OutboxService.start() + SyncEngine.start() at app bootstrap; also registerCurrentDevice? check main.dart structure first.
2. settings_screen.dart: add storage section — MediaStore.usageByCategory() + clearCache(), show offline indicator (NetworkDetector state).
3. chat_screen: optionally load cached messages locally before API.
4. flutter analyze full lib/, fix, flutter build apk (env vars known), git commit+tag v5.3.0, release notes, report.

## Phase 5 progress update 2 (Aug 19):

DONE: main.dart bootstrap — NetworkDetector.instance.start(), OutboxService.start(), SyncEngine.start() added in main(). chat_screen imports + storePendingMessage + OutboxService.push wired in _sendMessage. chats_screen offline layer done. phone_screen dynamic tabs done. auth_provider: AuthConfig + email/username methods. All clean.

NetworkDetector API: NetworkDetector.instance (singleton), start(), state (NovaNetworkState.online/serverDown/offline), addListener.
SyncEngine.start()/drain(); OutboxService.start() listens detector and calls drain() on online.

Remaining:
1. settings_screen: storage page using MediaStore.usageByCategory() + clearCache() + offline status chip (NetworkDetector.instance.state). Check MediaStore static API names first.
2. chat_screen: preload local messages when offline? optional.
3. flutter analyze lib/ full; flutter build apk (env: PATH=/home/ubuntu/flutter/bin, JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64, ANDROID_HOME=/home/ubuntu/Android, GRADLE_OPTS="-Xmx512m", _JAVA_OPTIONS="-Xmx1g"; cmd: flutter build apk --release --android-skip-build-dependency-validation).
4. git: cd /home/ubuntu/nova_new, add -A, commit, tag v5.3.0, push --tags, release create v5.3.0 with APK zip.
5. Final report to user.

Environment: local PHP server pid 75610 port 8080 running; Docker nova514 on 8082 OK; GitHub alghazaliye/nova main.
