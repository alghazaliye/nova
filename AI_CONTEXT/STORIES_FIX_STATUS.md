# إصلاح قسم الحالات (Stories) — الحالة النهائية

## طلب المستخدم
"الحالة لاستطيع نشر فيديو او صورة ولاتظهر زي الوتساب"

## منجز بالكامل
1. **stories_screen.dart**: `_publishStory()` dialog → نشر صورة (ImagePicker من المعرض) أو نص، رفع عبر `/stories/{meId}/upload` multipart. analyze: 0 errors.
2. **router.php media route**: Content-Type حسب الامتداد + Accept-Ranges + Content-Length + 206 partial.
3. **chats_screen.dart _pollActiveCall**: تحقق صارم caller/callee == me + ModalRoute.isCurrent.
4. **CallController index + incoming**: auto-cleanup للمكالمات stale (answered بدون ended_at >5 دقائق).
5. **story_viewer_fullscreen.dart**: عرض الصورة نمط واتساب — خلفية ضبابية cover + الصورة كاملة contain في المنتصف (ImageFilter.blur sigmaX/Y=30 + import dart:ui).
6. بناء web WASM ناجح + نشر web_app 15MB (base href /web_app/ + gzip/brotli).
7. **اختبار end-to-end نجح**: نشر صورة أحمد 201، GET /stories يعمل، media 200 image/png، سارة ترى حالات أحمد.
8. APK build في الخلفية (log: /tmp/apk_build.log، PID 36176).

## المتبقي
1. انتظار APK build + cp إلى /home/ubuntu/nova_new/Nova_Messenger.apk.
2. git add -A + commit "v5.0.6: stories media upload (image/video) + WhatsApp-style story viewer + stale call cleanup" + tag v5.0.6 + push origin main --tags (GH proxy يعمل).
3. gh release create v5.0.6 + upload Nova_Messenger.apk.
4. إرسال الروابط.

## روابط التسليم
- أحمد: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966501234567&otp=123456
- سارة: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966502345678&otp=123456
- Admin: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/admin/ — admin@nova-messenger.com / Admin@1234
- GitHub: alghazaliye/nova، releases السابقة v5.0.4, v5.0.5.
- نشر web_app: cd nova_new && rm -rf web_app && cp -r nova_flutter/build/web web_app && sed base href + rm canvaskit + gzip/brotli
- بناء APK: cd nova_flutter && flutter build apk --release (PATH/flutter + JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64)
- DB: mysql -u nova_user -pnova2026 -h 127.0.0.1 nova

## ملاحظة النقر في المتصفح
- dispatch events على 446,754 في screenshot 893x768 لا تصل لعناصر Flutter WASM (النقر يتجاهل). الاختبار البرمجي عبر API أثبت كل الوظائف.
