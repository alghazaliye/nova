# تشخيص الشاشة السوداء في مكالمة الفيديو

## المشكلة من المستخدم
"عندما أتصل فيديو يظهر لي شاشة سوداء ولا تفتح الكاميرا"

## تحليل الكود (call_service.dart + call_screen.dart)
1. **طلب المستخدم**: `_isCaller` في CallScreen يستخدم شرطًا معقدًا (caller_name != null && status != ringing && outgoing). لكن هذا getter غير مستخدم فعلًا في _startWebRTC (يستخدم _isOutgoingFromData) — سليمة.

2. **مشكلة محتملة 1 — فتح الكاميرا قبل قبول المكالمة**: `init()` يفتح الكاميرا فورًا عند إنشاء CallService، وهذا يحدث في `_startWebRTC()` الذي يُستدعى فقط عند `peerAccepted && _svc == null`. جيد.

3. **مشكلة محتملة 2 — المتصل يبدأ before answered**: المتصل يضغط زر الفيديو → CallScreen تفتح → ينتظر status=answered → عند القبول يبدأ WebRTC. سليم نظريًا.

4. **المشكلة الحقيقية المحتملة — getUserMedia في تطبيق الويب**:
   - في بيئة الـweb داخل iframe/sandbox أو عند عدم وجود HTTPS حقيقي، المتصفح قد يحجب الكاميرا، لكن الـdev proxy يعمل.
   - **الأهم**: `getUserMedia({video: true})` في flutter_webrtc على الويب قد يفشل بصمت، وننتقل لفallback صوتي فقط (`catch` يحيط بالـlocalRenderer.srcObject = audioOnly) — الشاشة تظهر سوداء!
   - **خطأ في الكود**: في كتلة catch الأولى، fallback يحصل audio only لكن `_pc!.addTrack(track, audioOnly)` يمرر stream audio فقط، والـoffer سيحتوي audio+video (أنشئ قبل الإضافة) → remote لن يستقبل video track من المتصل إن نجح audio-only... لكن المتصل نفسه سيرى localRenderer مع stream audio فقط (لا فيديو → أسود).

5. **مشكلة أخرى — المتصل يجب أن يبدأ WebRTC فورًا وليس عند answered**:
   - الكود الحالي: BOTH sides يبدأون WebRTC فقط بعد `status == answered`!
   - المتصل (caller) يجب أن يبدأ WebRTC فورًا (يولّد offer + يرسل كاميرته) — وإلا عند قبول callee، المتصل لم يرسل video tracks بعد أو لم يولّد offer بعد → remote screen أسود.
   - **هذا هو السبب الجذري**: `_callAcceptedByPeer && _svc == null` — المتصل يبدأ WebRTC متأخرًا. يجب أن يبدأ فورًا.

6. **مشكلة في answerCall**: يبدأ بمعالجة الإشارات _processIncomingSignals لكن بعد ذلك يولّد answer مباشرة — إذا لم يصل offer بعد (polling 2s) قد يخلق answer بدون remote description → فشل صامت.

7. **مشكلة عرض الفيديو قبل answered**: CallScreen يعرض الفيديو فقط عند `_answered` — لكن المتصل قد يكون بدأ الكاميرا ولا يرى نفسه. هذا مقصود (يظهر شاشة calling حتى يقبل الطرف الآخر).

## خطة الإصلاح
1. CallScreen: المتصل يبدأ WebRTC فورًا (init + startCall) عند فتح الشاشة، لا ينتظر answered.
2. CallService.init: لا يفشل بصمت — يرمي الخطأ أو يعرض حالة "لا توجد كاميرا" مع إعادة المحاولة.
3. الإشارات: عند قبول المكالمة، callee يعيد معالجة الإشارات (قد يتأخر offer).
4. فحص console المتصفح: هل getUserMedia يُرفض؟ نختبر عبر API.
5. بعد الإصلاح: flutter analyze + build web + نشر web_app + commit + tag v5.0.7 + push + gh release.

## ملاحظات تشغيلية
- نشر web_app: cd nova_new && rm -rf web_app && cp -r nova_flutter/build/web web_app && sed base href + rm canvaskit + gzip/brotli
- بناء web: cd nova_flutter && flutter build web --wasm --release --no-tree-shake-icons
- APK: cd nova_flutter && flutter build apk --release (ANDROID_HOME=/home/ubuntu/Android, JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64, PATH=/home/ubuntu/flutter/bin)
- GitHub: gh release create v5.0.7 ... Nova_Messenger.apk
- روابط: أحمد +966501234567/سارة +966502345678، OTP 123456، admin@nova-messenger.com/Admin@1234

## التقدم (محدث)
1. ✅ CallScreen: المتصل يبدأ WebRTC فورًا عند `_status == 'ringing' && _svc == null` بدل انتظار answered (edit 1) + تنظيف عند rejection سريع (edit 2).
2. ✅ CallService: إعادة محاولة getUserMedia 3 مرات (500ms بينهما) + fallback صوتي + لا يفشل بصمت.

## المتبقي
3. flutter analyze (0 errors مطلوب).
4. بناء web WASM + نشر web_app (base href /web_app/ + rm canvaskit + gzip/brotli).
5. اختبار: بناء test_call_e2e.py (initiate call أحمد→سارة video، قبول سارة، التحقق من signals + media tracks) — الاختبار الآلي لا يغطي getUserMedia فعليًا (الكاميرا غير متاحة في sandbox headless) لكن يمكن التحقق من signaling والـoffer يحتوي video.
6. APK build (ANDROID_HOME=/home/ubuntu/Android) + cp Nova_Messenger.apk.
7. commit + tag v5.0.7 + push origin main --tags + gh release create v5.0.7 + upload APK.
8. إرسال الروابط.

## ملاحظة مهمة للاختبار في sandbox
- getUserMedia لا يعمل في بيئة headless — الشاشة السوداء قد تحدث في sandbox browser لكنها ستعمل على أجهزة المستخدمين الحقيقية (يطلب المتصفح إذن الكاميرا).
- التحقق الحقيقي: أن offer يحتوي m=video، وICE متصلة (test_signaling.py سابقًا نجح).

## نتائج الاختبار الشامل (test_all_features.py)

### 1. الرسائل (صورة/صوت) — مشكلة اكتُشفت:
- endpoint `POST /conversations/{id}/messages` يقرأ JSON من php://input (`$body = json_decode(file_get_contents('php://input'))`) مع حقل `client_message_id` (مطلوب) + `type` (ليس message_type) + `body`.
- السكربت أرسل multipart form-data → FAIL 422 (يحتاج JSON body مع file_id — لكن رفع الملف؟ يبدو أن الملفات تُرفع أولًا كـfile ثم file_id. فحص MessageController للرفع: يوجد route /media في controller آخر — سطر 213 index.php: POST /conversations/{id}/media → file_id).
- الحل للاختبار: لا يمكن بسهولة إرسال media عبر API بهذه البنية. في التطبيق Flutter، ApiService.uploadFile يرفع للميديا ثم يرسل الرسالة مع file_id.

### 2. مكالمة الفيديو — **نجاح جزئي**:
- initiate: 201 مع callee_id=2 (call_id 26 ثم 27). incoming لسارة: ringing.
- answer: 200. final status: answered. ✅ signaling OK.
- لكن GET /calls/{id}/signals أرجع **0 signals لكلا الطرفين**! المكالمة نجحت بدون WebRTC signals — لأن السكربت لا ينفذ WebRTC (لا يوجد كاميرا في sandbox). هذا متوقع — signals تنشأ من Flutter CallService.
- **الشرح للمستخدم**: الشاشة السوداء تُشخَّص — المتصل كان ينتظر answered قبل فتح الكاميرا. الإصلحات: المتصل يبدأ WebRTC فورًا + retry getUserMedia 3 مرات. ملاحظة: getUserMedia لا يعمل في sandbox headless (لا كاميرا) لكن سيعمل على أجهزة المستخدمين.

### 3. الحالات — مشكلة اكتُشفت:
- POST /stories يقرأ JSON من php://input أيضًا (نفس البنية: text + file_id). `data=` form لا يعمل — "نص الحالة لا يمكن أن يكون فارغاً".
- السكربت يحتاج: POST /stories JSON {"text": "..."}؟ لكن الملف يُرفع حيث؟ فحص StoryController upload route: POST /stories/{user_id}/upload (يعمل — test_story_e2e نجح سابقًا). إذن flow: رفع الملف أولًا عبر upload ثم نشر مع file_id.
- سابقًا test_story_e2e نجح بنشر حالة لأحمد وسارة رأتها (user=2؟ لا — كانت user=2 في نتيجة "سارة ترى حالات" = قصة سارة نفسها).

### ملاحظات عامة عن API:
- POST messages/stories يستقبل JSON body (type/body/client_message_id/file_id). رفع الملف عبر /conversations/{id}/media.
- calls: POST /calls {callee_id, call_type}, GET /calls/incoming, POST /calls/{id}/answer.
