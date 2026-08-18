# حالة تنفيذ نظام OTP المتكامل (v5.1.0) — محدث

## مكتمل حتى الآن:
### Core PHP (backend/otp/):
- OtpProviderInterface.php — واجهة + OtpSendResult (errorClass: auth/rate/server/timeout/client/success)
- OtpTemplate.php — {OTP}{PHONE}{MINUTES}{APP_NAME}
- TwilioProvider.php, VonageProvider.php, HttpSmsProvider.php (REST عام: http_method/content_type/auth_type: none|bearer|basic|header|query + to_field/otp_field/template_mode + success_expr), TestProvider.php (يرفض في production APP_ENV)
- OtpService.php — createAndSend(create+chain), sendViaChain(fallback), resend, verify(attempts/expiry/block), checkRateLimit, resendCooldown, getDeliveryMode, getStats, admin: getPendingRegistrations/revealManualCode(adminVerify)/cancel. generateCode: OTP_TEST_CODE env في dev + OTP_FIXED_CODE دائم + عشوائي settings. loadProviderConfig null-safe
- ProviderManager.php — CRUD + test + deliveryLogs
- OtpEncryption.php — AES-256-GCM من otp_encryption_key
- AdminOtpController.php — 13 endpoint JWT+RBAC عبر /api/v1/admin/otp/* (authenticateAdmin يبحث عن admin من JWT user_id + role_permissions)
- database/migrate_otp.sql — 4 جداول + مزود اختبار افتراضي enabled + 12 صلاحية + 14 إعداد

### AuthController.php معدل:
- register/login: OtpService (rate limit + cooldown + createAndSend)
- verifyOtp: pipeline جديد + fallback legacy app_settings
- resendOtp() جديد POST /auth/resend-otp
- assertOtpProviderAvailable: fail-closed إلا dev test أو مزود enabled

### routes في index.php:
- /admin/otp/* (13 route) + /auth/resend-otp

### Pages (admin):
- admin/otp-providers.php ✅ (CRUD مزود + toggle + test modal + type fields: twilio/vonage/http_rest/test + settings status line)
- admin/otp-registrations.php ✅ (قائمة + عرض رمز + تأكيد يدوي + إلغاء + stats)
- sidebar.php ✅ (روابط مزودو OTP + طلبات التسجيل)

### DB + اختبارات نجحت:
- مزود اختبار id=1 enabled في DB (INSERT كان يعمل فقط بعد إصلاح loadProviderConfig)
- register → auto delivery → verify 123456 → JWT ✅ (تست4/5/6/7/8 في users)
- otp_delivery_logs: status success
- ملاحظة: register +966559990005 name='تست5' لكن users.name='مستخدم NOVA' — يجب فحص: في createAndSend name يُسند للاسم في otp_verifications لكن عند إنشاء user في verify يستخدم $name من body (null) ثم otpData... الآن legacy path يستخدم legacyOtp['name'] — يجب التحقق أن register الجديد يمرر الاسم. (تست4+ أظهرت name='' في otp_verifications!)

## حالة (01:05 يوم 19): كل شيء يعمل ✅ (محلي على 8080)
- RBAC: 13 صلاحية otp*/registration* موجودة ومربوطة بـrole_id=1 (super_admin) فقط ✓
- adminApiLogin JWT + authenticateAdmin يدعم standalone admin JWT وsession-bound ✓
- footer.php bootstrap: mint JWT من $_SESSION['admin_id'] (cache 5 دقائق في $_SESSION['admin_jwt']) + localStorage adminToken — يعمل ✓
- login.php session → otp-providers.php → API يعمل بالتوكن ✓
- ob_start() حول require app.php لمنع "headers already sent"
- **المتبقي في phase 4-5**: صفحة settings.php إدارية (إعدادات OTP: delivery_mode, template, length, expiry, cooldown) — AdminOtpController settingsGet/settingsUpdate موجودان؟ تحقق. ثم اختبار شامل، ثم Docker/Render/GitHub.
- رابط الاختبار المحلي: http://localhost:8080 — container nova-test على 9090 (Docker image nova-messenger:509) يعمل أيضًا

## حالة (22:30): API كامل يعمل ✅
- adminApiLogin + route '/admin/otp/login' + authenticateAdmin يدعم JWT standalone (role=admin) وsession-bound JWTs
- GET /admin/otp/providers + registrations + stats نجحوا بالكامل
- ملاحظة لغز PDO في HTTP: $stmt->fetch() بدون explicit mode في AdminOtpController أعطى warning — الحل: fetch(PDO::FETCH_ASSOC) صراحة في كل places في AdminOtpController

## حالة (22:10): Core يعمل كامل. login.php: POST fields = login/password + _csrf hidden → 302 → index.php. جلسة admin = session PHP وليست JWT.
- **مشكلة حالية**: صفحات otp-providers/otp-registrations تستدعي API عبر Bearer localStorage.getItem('adminToken') — لكن admin login يعطي session لا JWT!
- **الحل قيد التنفيذ**: إضافة AdminOtpController::adminApiLogin() + route GET/POST '/admin/otp/login' في index.php → يتحقق من email/password عبر جدول admins ثم يرجع JWT {user_id: admin.id, role: admin, iat, exp=+72h}. صفحات admin تضيف JS login: POST /api/v1/admin/otp/login → localStorage.setItem('adminToken', token).
- admins جدول: id,name,email,password_hash,role_id,is_active — لا user_id. authenticateAdmin يبحث WHERE a.id = JWT user_id.
- otp-providers.php سطر 493 fetch API+'/admin/otp/providers' + Authorization Bearer.

## قضايا متبقية للتحقق:
1. register لا يمرر الاسم إلى createAndSend؟ في AuthController: createAndSend($phone, $name, ...) حيث $name يأتي من validator — لكن في اختبار تست4 كان name='' في otp_verifications رغم إرسال name=تست4! السبب: $name في register كان '' لأن $v->sanitizeString('name')... يجب فحص. ربما لأن $body['name'] غير موجود بعد trim — لا! تم إرساله. فحص هذا عند الاختبار.
2. إعدادات OTP settingsGet تشمل: otp_length, expiry, max_attempts, cooldown, max_resends, delivery_mode, default_provider_id, enable_fallback, enable_manual_fallback, message_template, rate limits + app_name
3. يجب اختبار: resend-otp endpoint، cooldown، rate limit، delivery_mode manual عبر settings (PUT /admin/otp/settings)
4. settingsUpdate يحتاج otp.settings permission
5. adminToken: صفحات admin تستخدم localStorage.getItem('adminToken') — لكن جلسة admin هي session! يجب حل: login من داخل لوحة التحكم يعطي JWT وتخزينه، أو استخدام cookie. الحل: في login.php بعد نجاح تسجيل الدخول، خزّن JWT في localStorage عبر JS. سيتطلب تعديل login.php ليعيد JWT token في الاستجابة أو تخزينه عند الجلسة.
6. بعد اكتمال الصفحات: اختبار شامل + Flutter (لا تغيير جوهري) + APK v5.1.0 + GitHub release + Docker rebuild :510 + Render env (OTP_FIXED_CODE=123456)

## معلومات تشغيلية:
- DB: nova@127.0.0.1:3306 nova_user/nova2026
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php من /home/ubuntu/nova_new
- Docker container: nova-test port 9090 (image nova-messenger:509)
- Admin login: admin@nova-messenger.com / Admin@1234
- .env: APP_ENV=development, OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456
- flutter build: PATH=/home/ubuntu/flutter/bin:$PATH, JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64, ANDROID_HOME=/home/ubuntu/Android, cd nova_flutter && flutter build apk --release --android-skip-build-dependency-validation
- GitHub: alghazaliye/nova — gh يعمل، git branch -f main HEAD قبل push
- APK v5.0.9 موجود: /home/ubuntu/nova_new/Nova_Messenger.apk
- web_app build: flutter build web --wasm --release --no-tree-shake-icons ثم cp + sed base href="/web_app/" + gzip/brotli

## مُحل (22:00) — لغز cooldown:
- السبب: backend/config/app.php سطر 37: date_default_timezone_set('Asia/Riyadh') — PHP يحسب التوقيت +3 بينما MySQL datetime بلا timezone (مخزن UTC). strtotime يفترض datetime MySQL محلي (+3) → timestamps ناقصة 3 ساعات → remaining سالبة → cooldown معطل عبر HTTP. CLI لم يحمّل app.php (tz=UTC) لذا عمل.
- الحل: toUnixTs() في OtpService يحلل datetime مع DateTimeZone('UTC'). استُخدم في resendCooldown + verify expires_at.
- الاختبار: cooldown يعمل الآن عبر HTTP (429 + "يمكنك إعادة الإرسال بعد 20 ثانية").
- ملاحظة مهمة: أي مقارنة strtotime على MySQL datetime في هذا المشروع يجب مراعاة timezone أو استخدام toUnixTs.

## لغز cooldown عبر HTTP (21:56):
- CLI (php /tmp/cd5.php): cooldown يعمل 100% (rejects بـOTP_COOLDOWN)
- HTTP (curl localhost:8080): يمر sempre مع otp_id متزايد (DB حقيقي يتحدث)
- CD_DEBUG error_log يظهر في php_errors.log من HTTP بنجاح → الخادم ينفّذ OtpService المحدث فعلاً ويطبع row صحيح (last_req حديث، now=time())
- لكن $remaining يجب أن يكون >0 ومع ذلك createAndSend يُنفَّذ
- opcache.enable=0 الآن في cli.ini (كان On قبل ذلك — ربما كان سبب part)
- marker '__marker' في response لم يظهر عبر curl رغم php -l سليم
- الخادم الوحيد على 8080 = PID 75610، cwd=/home/ubuntu/nova_new
- **فرضية متبقية**: curl response ربما من proxy خارجي لا يعكس marker؟ لكن NOT_FOUND يظهر لرقم غير موجود → DB محلي. CD_DEBUG يصل للوج → التنفيذ محلي. التناقض: row صحيح + remaining يجب >0 + success مع otp_id جديد
- ملاحظة: CD_DEBUG في CLI now=1787090113 والـtimestamp متطابق مع UTC
- **خطوة تالية**: اختبار من process داخل الخادم نفسه (wget/PHP stream من نفس الـsocket) أو فحص أن index.php route '/auth/resend-otp' هو route صحيح — curl URL: /api/v1/auth/resend-otp → router strips /api/v1 → /auth/resend-otp ✓
