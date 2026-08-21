# حالة الرفع إلى GitHub وRender (المرحلة 1 من خطة الرسائل)

## GitHub ✓ تم الرفع
- commit: 2d874bd (بعد 0271901)
- المضمون: إصلاح العداد التنازلي OTP (auth_provider.dart + otp_screen.dart) + web_app/ مبني جديد
- push: 0271901..2d874bd main -> main ✓

## Render — البناء تلقائي من GitHub
- الخدمة: srv-da2hq5rm8hqs73dn9ep0 (nova) في مشروع prj-da2hq5jm8hqs73dn9dvg
- البناء التلقائي انطلق بعد push (Render مربوط بـ GitHub repo alghazaliye/nova)
- الحالة: "Updated 16min" في project overview (قبل push الجديد)
- سنراقب البناء عبر https://dashboard.render.com/service/srv-da2hq5rm8hqs73dn9ep0/deploy (الصفحة تعيد التوجيه أحيانًا، نجرب مرة أخرى)
- بديل للتحقق: curl HEAD https://nova-wn25.onrender.com/web_app/ ونقارن آخر تعديل لـmain.dart.js

## المهمة التالية (من طلب المستخدم)
- مشكلتان على Render بين +966770105284 (user) و +966738155861 (user، كان محظورًا سابقًا):
  1. الرسائل لا تظهر بين الطرفين
  2. آخر ظهور (last seen/online) لا يظهر صحيحًا
- الخادم المحلي :8080 يعمل — DB محلية تحتوي user 33 = +966738155896
- API endpoints الرسائل: POST/GET /api/v1/messages, GET /api/v1/conversations/threads?last_message_at
- التحقق محليًا أولًا ثم على Render
- قيد المستخدم: لا رفع إلا بأمر صريح — أمر الرفع الحالي وصل؛ أي تغييرات جديدة (مشكلة الرسائل) تتطلب أوامر جديدة من المستخدم، لكن إصلاح مشكلة الرسائل في الباكند قد يحتاج رفعًا جديدًا — سنسأل المستخدم بعد الفحص
