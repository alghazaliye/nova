# تشخيص v5.0.8 — عدم ظهور الكاميرا/الصوت في مكالمات الفيديو

## السبب الجذري المؤكد

اختبار signaling (node/webrtc_test.js) أثبت أن مسار الإشارات يعمل بشكل صحيح:
- initiate 201، answer 200، offer يُخزن كـpayload JSON، المستقبِل يراه مع `since`.
- حالة المكالمة تتحول إلى `answered` بشكل صحيح.

إذن signaling سليم. المشكلة في **تشغيل الوسائط فعليًا**:

1. **Web App (WASM) يعمل عبر HTTP غير آمن** (`https://...manus.computer/web_app/` هو proxy، لكن عند تشغيل الخادم محليًا كان http). getUserMedia يتطلب secure context — على mobile Android عبر الـproxy يجب أن يعمل. لكن السد قد يُرفض بسبب:
   - طلب إذن الكاميرا في webview/المتصفح لم يُمنح
   - Flutter web يحتاج `--web-renderer html` أو canvaskit مع دعم getUserMedia

2. **Android APK**: يجب أن يعمل getUserMedia على Android مباشرة. الشكوى "لا تظهر الكاميرا لا عند المتصل ولا المستقبل ولا الصوت" تشير إلى أن WebRTC لا يبدأ أصلًا أو track غير مضاف.

## المشاكل المكتشفة في الكود (Flutter):

1. **عرض الفيديو مشروط بـ `_answered`**: الفيديو المحلي والبعيد يظهران فقط `if (_answered && isVideo)`. حتى مع نجاح WebRTC، لا يرى المستخدم شيئًا قبل أن تصبح الحالة `answered`.

2. **المستقبِل لا يرى الفيديو المحلي عند قبوله**: `_answered` لا يتحقق حتى polling التالي (2 ثانية) بعد قبول المكالمة.

3. **عدم وجود معاينة فيديو أثناء الرنين للمتصل**.

4. **لا يوجد TURN**: عبر الشبكات المعقدة (NAT متماثل) لن يتصل ICE أبدًا.

## خطة v5.0.8:

- [x] معاينة الفيديو المحلي فور بدء WebRTC (أثناء ringing أيضًا)
- [x] عرض الفيديو الثنائي فورًا بعد قبول المكالمة (دون انتظار polling)
- [ ] فحص payload format مع candidates: Flutter يرسل candidate: null أحيانًا
- [ ] TURN credentials مؤقتة من backend (/calls/turn-credentials)
- [ ] إعادة بناء APK + web_app
- [ ] اختبار WebRTC فعلي: سكربت node مع RTC + media من microphone/摄像头 simulation

## نتائج الاختبار:
- signaling: سليم 100% (node test).
- رفع ملفات + حالات + رسائل: سليم (اختبار سابق 100%).
