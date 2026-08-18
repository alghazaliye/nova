# حالة v5.0.9 — سجل المكالمات في شاشة المحادثات (19 أغسطس 2026)

## طلب المستخدم:
"وريد المكالمات الفئات او أي مكالمه تظهر في شاشة المحادثه" — عرض سجل المكالمات (واردة/صادرة/فائتة) في شاشة المحادثات الرئيسية مع إمكانية إعادة الاتصال.

## الحقائق المكتشفة:

### API جاهز:
- GET /api/v1/calls → سجل مكالمات المستخدم (caller_id, callee_id, call_type, status [calling/ringing/answered/missed/rejected/ended/failed], started_at, ended_at, duration, created_at, caller_name, caller_avatar) + callee_id عبر call_participants
- GET /api/v1/calls/incoming → مكالمات واردة نشطة (ringing)
- POST /api/v1/calls {callee_id, call_type: voice|video} → initiation، response: data.id
- POST /api/v1/calls/{id}/answer

### Flutter:
- ChatsScreen (chats_screen.dart:22) — الشاشة الرئيسية
- ChatsTab (252) — تبويب المحادثات، _ChatsTabState يحمل _conversations/_filtered، يجلب عبر ApiService.get('/conversations')
- عرض tile في السطور ~925-1035 (PressScale + NovaCard + NovaAvatar + name + lastMessage + lastSeen + UnreadBadge)
- NovaCard له onTap
- _IncomingCallDialog عند ~1075، _CallBtn عند ~1164
- مكالمة: await Navigator.push(CallScreen(callData: call))؛ callData يحتاج: id, caller_id, callee_id, call_type, is_outgoing (يُضاف في الكود)
- بدء مكالمة: POST /calls {callee_id, call_type} → ثم CallScreen
- formatLastSeen دالة موجودة في الملف
- ContactsTab عند 1203
- ApiService في lib/services/api_service.dart
- NovaUser, Conversation في lib/models/user_model.dart

### خطة التنفيذ:
1. إضافة `Map<String,dynamic>? lastCall` إلى Conversation model (أو map من GET /calls)
2. في _ChatsTabState._load أو _refreshSilent: جلب GET /calls، ربط كل مكالمة بـ otherUserId (caller أو callee = المستخدم الآخر) → آخر مكالمة
3. في tile المحادثة: عرض أيقونة + تصنيف (مفقودة: حمراء ↑/↓، واردة: خضراء ↓، صادرة: رمادية ↑) + مدة + timestamp، وعند النقر عليها: إعادة الاتصال بنفس النوع (voice/video)
4. للمجموعات: لا عرض
5. إعادة بناء APK + web_app، commit v5.0.9، tag، push، gh release create v5.0.9 Nova_Messenger.apk

### روابط التسليم:
- أحمد: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966501234567&otp=123456
- سارة: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966502345678&otp=123456
- الإدارة: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/admin/
- release: https://github.com/alghazaliye/nova/releases/tag/v5.0.9
- APK: /home/ubuntu/nova_new/Nova_Messenger.apk (من ~/nova_new/nova_flutter/build/app/outputs/flutter-apk/app-release.apk)

### أوامر البناء الناجحة:
PATH=/home/ubuntu/flutter/bin:$PATH JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64 ANDROID_HOME=/home/ubuntu/Android GRADLE_OPTS="-Xmx512m" _JAVA_OPTIONS="-Xmx1g" flutter build apk --release --android-skip-build-dependency-validation
Web: flutter build web --wasm --release --no-tree-shake-icons ثم نسخ web_app + sed base href + gzip+brotli
Git: git add -A && commit -c user.name="alghazaliye" -c user.email="alghazaliye@users.noreply.github.com" && git tag -f vX && git push origin main --tags
Git detached HEAD حل: git branch -f main v5.0.9 && git push origin main

### ما كُمل سابقًا (v5.0.8 مرفوع ✅):
- إصلاح الكاميرا/الصوت WebRTC ✅، FLAG_SECURE ✅، sidebar للهاتف ✅، اختبار شامل كامل ✅، release v5.0.8 + APK

## تقدم v5.0.9 (تم حتى الآن):
1. ✅ Conversation model: أضفت `final Map<String, dynamic>? lastCall` (user_model.dart) في: المجال، const params، fromJson (j['last_call'])
2. المتبقي: في _ChatsTabState._load (سطر ~345): بعد جلب /conversations، جلب /calls ودمج آخر مكالمة لكل محادثة (callee_id==otherUserId أو caller_id==otherUserId) حسب created_at DESC → conversation.lastCall = {call_type, status, direction, started_at, duration, id}
3. المتبقي: في tile المحادثة (~1036 قبل `if (conv.unreadCount > 0)`): عرض سطر مكالمات مع أيقونة هاتف + نص + timestamp وعند النقر → _reCall(otherUserId, callType)
4. _reCall: POST /calls {callee_id, call_type} → push CallScreen(callData: {id: data.id, caller_id: me, callee_id: other, call_type, is_outgoing: true})

## ملاحظة مهمة عن backend:
GET /calls يُرجع كل المكالمات (مع cleanup stale). دمجها في Flutter أسهل من تعديل PHP.
call_status display:
- missed → 'مفقودة' (أحمر، أيقونة call_missed)
- answered/ended → according to direction ↑/↓ (أخضر فاتح)
- rejected → 'مرفوضة' (رمادي)
- calling/ringing (حالية) → لا تُعرض في السجل (مستخدمة في _pollActiveCall)
direction: caller_id == me → صادرة ↑ | callee_id == me → واردة ↓

## بحث الاستضافة المجانية (19 أغسطس 2026):

### InfinityFree — الخلاصة:
**لا تصلح لمشروع Nova!** مصدر: منتدى InfinityFree الرسمي (Meishin Leader + Admin، يوليو 2023):
1. نظام أمان يحجب الوصول غير المتصفح (Browser Security System) — يمنع تطبيق Flutter/Messenger من الوصول للـAPI من تطبيق الهاتف
2. CORS محظور تمامًا — الواجهة والـbackend يجب أن يكونا على نفس hostname
3. **GET وPOST فقط — PUT/PATCH/DELETE محظورة** → مشروعنا يعتمد على DELETE /messages/{id} وDELETE طرق أخرى!
4. ToS يمنع صراحة استضافة API ("API hosting violates ToS")
5. 500 Internal Server Error مع public domain على IP 185.27.134.125 غير موثوق

### بدائل مجانية تدعم REST API كامل:
- **Render.com** free tier: يدعم Node/Python/Docker — ليس PHP مباشرًا لكنه يدعم custom Dockerfile (يمكن تشغيل PHP 8.3 + MariaDB via Docker) — free tier: 0.5GB RAM, 750hrs/mo, sleeps after 15min idle
- **Aiven free tier**: MySQL مجاني
- **PlanetScale**: MySQL free (لم يعد مجانيًا كليًا)
- **000webhost / Hostinger free**: محدود
- **ByetHost** (شقيق InfinityFree): نفس القيود
- **Railway.app**: trial فقط
- **Koyeb/Supabase**: خيارات أخرى

### الخيار الأفضل لـ Nova (PHP + MariaDB):
**Render.com مع Dockerfile**:
- Dockerfile: php:8.3-apache + MariaDB local volume؟ لا — Render free PostgreSQL؛ لكن يمكن تشغيل MariaDB داخل نفس الحاوية (mysql_embedded) أو استخدام SQLite
- ملاحظة: Render free: الحاوية تنام بعد 15 دقيقة → polling المكالمات (5 ثوانٍ) يحتاج استيقاظ، مقبول لكن بطيء
- بديل: Railway $5/mo credit — محدود

### خطة التنفيذ للنشر:
1. بناء APK v5.0.9 ✅ (كود جاهز: سجل المكالمات في chats_screen + user_model)
2. إنشاء Dockerfile للنشر على Render
3. تسجيل Render.com ونشر (يحتاج حساب المستخدم أو token)
4. رفع v5.0.9 GitHub + release

### حالة v5.0.9 (كود جاهز ✅ analyze 0 errors):
- user_model.dart: lastCall مضاف ✅
- chats_screen.dart: _mergeCalls + _reCall + _buildCallRow + _formatCallTime + _formatDuration + سطر tile ✅
- متبقي: بناء APK + web_app + commit + release

### روابط التسليم:
- أحمد: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966501234567&otp=123456
- سارة: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966502345678&otp=123456
- الإدارة: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/admin/ (admin@nova-messenger.com / Admin@1234)
- release v5.0.9: https://github.com/alghazaliye/nova/releases/tag/v5.0.9 (لم يُنشأ بعد)
- APK: cp nova_flutter/build/app/outputs/flutter-apk/app-release.apk Nova_Messenger.apk

## تفاصيل نشر Render.com (19 أغسطس):

### ملفات Docker جاهزة في /home/ubuntu/nova_new/:
- `Dockerfile`: php:8.3-apache + MariaDB + GD/zip/opcache + Apache على 8080 + entrypoint
- `docker/000-default.conf`: VirtualHost *:8080، CORS headers، DocumentRoot public/
- `docker/entrypoint.sh`: يهيئ MariaDB في /data/mysql → يستورد database/schema.sql → ينشئ DB+user من env → يكتب backend/.env → apache2-foreground
- `.dockerignore`: يستثني nova_flutter/admin/web_app/APK

### إعدادات backend المهمة (من فحص الكود):
- config/database.php: $_ENV['DB_HOST|PORT|NAME|USER|PASSWORD'] (ملاحظة: DB_PASSWORD وليس DB_PASS)
- config/app.php: يقرأ backend/.env (APP_ENV, DB_*, JWT_SECRET, OTP_BYPASS, OTP_TEST_CODE, CORS_ALLOWED_ORIGINS)
- .env المحلي: DB_NAME=nova, DB_USER=nova_user, DB_PASSWORD=nova2026, JWT_SECRET=nova-dev-secret-key-2026-xyz, OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456
- storage dirs: attachments, avatars, voices, rate-limit
- schema: database/schema.sql + database/seed.sql

### إعدادات Render (Web Service):
- Source: GitHub alghazaliye/nova
- Branch: main
- Root Directory: (فارغ — Dockerfile في الجذر)
- Runtime: Docker
- Env vars: MYSQL_DATABASE=nova, MYSQL_USER=nova_user, MYSQL_PASSWORD=<تعسفي>, JWT_SECRET=..., OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456, APP_URL=https://<service>.onrender.com
- Port: 8080

### ملاحظة مهمة:
- Flutter app يستخدم baseURL ثابت في ApiService — يجب فحصه وتغييره إلى URL Render بعد النشر (أو استخدام env)
- Render free: الحاوية تنام بعد 15 دقيقة → أول طلب بعد النوم بطيء (~30ث)، ثم يستيقظ

### التقدم:
- APK build v5.0.9: بدأ في /tmp/apk_build_v509.log (session default-2، bg PID)
- web_app نشر: لم يُحدَّث بعد (نفس أمر rm -rf + cp + sed base href + gzip/brotli)
- GitHub: لم يُرفع v5.0.9 بعد

## تحديث الحالة (19 أغسطس — البناء):

### تم:
1. ✅ APK v5.0.9 بُني بنجاح: nova_flutter/build/app/outputs/flutter-apk/app-release.apk (88.9MB، EXIT=0)
2. ✅ ApiService أصبح baseUrl ديناميكي: baseUrlOverride + ?api=HOST للويب (import: package:nova_flutter/utils/nova_web_state.dart → novaHref())
3. ✅ flutter analyze: 0 errors
4. ✅ Docker files: Dockerfile, docker/000-default.conf (port 8080), docker/entrypoint.sh (MariaDB bootstrap + schema import + .env writing), .dockerignore

### متبقي:
1. ❌ بناء web WASM فشل: dart2js exit code -15 (SIGKILL = OOM — الذاكرة نفدت) — إعادة المحاولة بعد تحرير الذاكرة (pkill GradleDaemon)
2. نشر web_app: rm -rf web_app && cp -r nova_flutter/build/web web_app && sed base href /web_app/ && rm canvaskit && gzip/brotli
3. نسخ APK: cp .../app-release.apk Nova_Messenger.apk
4. commit + push + tag v5.0.9 (git add -A) — ملاحظة: v5.0.7 وv5.0.8 موجودان على origin/main
5. إنشاء release v5.0.9 على GitHub: gh release create v5.0.9 --title "Nova Messenger v5.0.9 - Call History in Chats" --notes "..." Nova_Messenger.apk
6. Register Render.com: Web Service, Docker, source alghazaliye/nova, branch main, root dir فارغ, env vars (MYSQL_DATABASE=nova, MYSQL_USER=nova_user, MYSQL_PASSWORD=xxx, JWT_SECRET=..., OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456, APP_URL=https://nova-messenger-xxx.onrender.com), port 8080 — يتطلب حساب المستخدم (Browser)
7. تسليم الروابط

### ملاحظات build web السابقة (نفس المشكلة حدثت سابقًا):
- الحل: pkill -f GradleDaemon + pkill -f "assembleRelease" ثم إعادة البناء
- web_app base href يجب أن يكون /web_app/

## Docker (19 أغسطس):
- ✅ صورة nova-messenger:509 بُنيت (1.16GB)
- ❌ خطأ: MariaDB 11.8.6 (من php:8.3-apache على Debian trixie) لا يدعم `mysqld --initialize-insecure`
- ✅ الإصلاح: استخدام `mariadb-install-db --user=mysql --datadir=$DATADIR --auth-root-authentication-method=normal` (مع fallback `mariadbd-install-db`) — عُدّل docker/entrypoint.sh
- التالي: إعادة بناء الصورة `sudo docker build -t nova-messenger:509 .` ثم اختبار:
  `sudo docker run -d --name nova-test -e MYSQL_DATABASE=nova -e MYSQL_USER=nova_user -e MYSQL_PASSWORD=nova2026 -e JWT_SECRET=nova-dev-secret-key-2026-xyz -e OTP_BYPASS=123456 -e OTP_PROVIDER=test -e OTP_TEST_CODE=123456 -p 9090:8080 nova-messenger:509`
  ثم `sleep 60; curl localhost:9090/api/v1/auth/verify-otp`
- ملاحظة: nova-test القديمة في حالة exited — `sudo docker rm -f nova-test` قبل الاختبار

## Render.com (مطلوب):
- بيانات: alghazaliye@gmail.com / Aa738155861
- بعد تسجيل الحساب: GitHub OAuth authorizing لـ alghazaliye/nova
- إنشاء Web Service: source alghazaliye/nova, branch main, root dir فارغ, Runtime Docker, port 8080
- Env vars: MYSQL_DATABASE=nova, MYSQL_USER=nova_user, MYSQL_PASSWORD=render2026, JWT_SECRET=nova-render-secret-key-2026-xyz, OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456, APP_URL=https://nova-messenger-xxx.onrender.com
- Free plan: sleep بعد 15 دقيقة (المستخدم يعرف هذا)

## Docker — الحالة الأخيرة (19 أغسطس 21:45):

### ما يعمل ✅:
- صورة nova-messenger:509 تُبنى بنجاح (entrypoint إصلاحات: mariadb-install-db بدل initialize-insecure, schema مع -D DB, mysqladmin shutdown بدل kill/wait)
- حاوية nova-test تعمل: schema (25 جدول) + seed + Apache 2.4.68 PHP 8.3.33 على 8080
- .env يُقرأ صحيحًا في PHP

### مشكلة حالية ❌:
- PDO يرفض الاتصال: Access denied nova_user@'localhost' رغم أن:
  - المستخدم nova_user@% موجود بـmysql_native_password وauth_string=41 char
  - GRANT ALL على nova.* صحيح
  - root يستطيع الاتصال بـ127.0.0.1
- السبب المشتبه: MariaDB 11.x + mysql cli/PDO مع 127.0.0.1:45:06 — خطأ 1045 مع user@'localhost'
  - ملاحظة مهمة: الخطأ يقول @'localhost' رغم host=127.0.0.1! يعني MariaDB يستخدم unix socket (mysqlclient يعيد كتابة 127.0.0.1 إلى socket) — mysql CLI بدون --protocol=tcp يستخدم socket
  - PHP PDO مع host=127.0.0.1 يستخدم TCP عادة... لكن الخطأ localhost
  - فحص لاحق: GRANT ظهر USER USAGE... الحساب ربما وُجد من boot سابق (data dir من /data/mysql volume جديد لا — volume جديد تم تهيئته)
  - **الفحص الصحيح التالي**: CREATE USER بدون IF NOT EXISTS + FLUSH ثم إعادة اختبار؛ أو فحص max_connections / password validation plugin
- admin login.php يعطي 404 → يحتاج فحص router: الصفحات admin يجب أن تُقدم مباشرة (DocumentRoot=public لكن admin في ../admin) — يجب Alias أو serve admin بـrouter

### البنية داخل الحاوية:
- /var/www/html = backend (public/ هو DocumentRoot)
- /var/www/html/../database/ = /var/www/database = schema.sql + seed.sql
- admin/ موجودة في /home/ubuntu/nova_new/admin (لا تدخل docker image لأن .dockerignore لا يستثنيها — لكن Dockerfile لا ينسخها!)
  → يجب إضافة COPY admin/ /var/www/admin/ إلى Dockerfile + إضافة location /admin/ في Apache
- web_app/ موجودة محليًا في /home/ubuntu/nova_new/web_app — يجب نشرها داخل الحاوية (COPY web_app/ /var/www/html/public/web_app/ أو volume) على Render

### Render plan:
- بعد إصلاح 1045 + admin + web_app → push Dockerfile إلى repo ثم Render Web Service
- Render: alghazaliye@gmail.com / Aa738155861
- Env vars: MYSQL_DATABASE=nova, MYSQL_USER=nova_user, MYSQL_PASSWORD=render2026, JWT_SECRET=nova-render-secret-key-2026-xyz, OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456
- Port 8080, free plan sleeps بعد 15 دقيقة

### v5.0.9 حالة GitHub:
- ✅ APK: Nova_Messenger.apk محدث (v5.0.9: سجل المكالمات + baseUrl ديناميكي)
- ✅ web_app منشور محليًا
- ❌ لم يُرفع commit/tag v5.0.9 بعد (آخر release: v5.0.8)
- التغييرات: nova_flutter/lib/services/api_service.dart (baseUrl ديناميكي: baseUrlOverride + ?api= query)، chats_screen.dart (_mergeCalls, _reCall, _buildCallRow, _formatCallTime, _formatDuration, سطر tile)، user_model.dart (lastCall)، Dockerfile + docker/* + .dockerignore

## Docker — الحل النهائي (19 أغسطس ~22:00):

### المشكلة الجذرية (1045) وحلها:
1. **mysqlnd/PDO**: host=127.0.0.1 → TCP يعمل، لكن MariaDB بدون skip-name-resolve يحل IP إلى localhost فيخطئ @'localhost' → 1045. الحل: `docker/99-nova.cnf` = [mariadbd] skip-name-resolve
2. **service mariadb restart**: أعاد إطلاق mariadbd بـdatadir الافتراضي /var/lib/mysql (فارغ) بدل /data/mysql → Unknown database nova! الحل: دائماً `mariadbd-safe --datadir=/data/mysql`
3. **GRANT مع 'nova'`: الملقم localhost فقط** — يجب CREATE USER + GRANT لكلا 'nova_user'@'%' و'nova_user'@'127.0.0.1'

### الحالة الحالية:
- Dockerfile محدث: admin/ → /var/www/admin/, web_app/ → /var/www/html/public/web_app/, schema → /var/www/database/, .dockerignore أزيل منه web_app+admin
- 000-default.conf: Alias /admin /var/www/admin + CORS headers
- image nova-messenger:509 بُنيت + nova-test تعمل الآن
- الخطوات المتبقية: انتظار 80 ثانية → curl API + admin login.php + web_app/
- ثم: kill nova-test, push code لـgithub, إنشاء Render Web Service

### Render:
- تسجيل: https://dashboard.render.com/signup بالبريد alghazaliye@gmail.com / Aa738155861
- بعد تسجيل + GitHub OAuth: https://dashboard.render.com/new → Web Service → Build from GitHub repo → alghazaliye/nova → Docker → port 8080
- Env: MYSQL_DATABASE=nova, MYSQL_USER=nova_user, MYSQL_PASSWORD=render2026, JWT_SECRET=nova-render-secret-key-2026-xyz, OTP_BYPASS=123456, OTP_PROVIDER=test, OTP_TEST_CODE=123456
- URL سيصبح مثل https://nova-xxxx.onrender.com

### v5.0.9 GitHub:
- لم يُرفع commit/tag v5.0.9 بعد (آخر release v5.0.8). التغييرات: api_service.dart, chats_screen.dart, user_model.dart, Dockerfile, docker/*, .dockerignore, MainActivity.kt (FLAG_SECURE من v5.0.8)
- APK محليًا محدث: /home/ubuntu/nova_new/Nova_Messenger.apk (v5.0.9)
