# نتائج اختبار العداد التنازلي OTP (محليًا)

## الإصلاحات المنفذة
1. `nova_flutter/lib/providers/auth_provider.dart` — `_parseOtpExpiry`:
   - بدون TZ marker تعامل السلسلة كـ UTC: `DateTime.parse(str).add(DateTime.now().timeZoneOffset).toUtc()`
   - مع Z: `DateTime.parse(str).toUtc()`
2. `nova_flutter/lib/screens/otp_screen.dart` — `_OtpCountdown`:
   - المقارنة `DateTime.now().toUtc()` في `_remaining()` وinitState loop

## الاختبار المحلي ✓ (الخادم :8080 + web_app/ الجديد)
- `?phone=+966738155896&otp=45832&otp_expires=2026-08-20 22:57:26` → شاشة OTP تعرض:
  - «الوقت المتبقي لانتهاء الرمز: 04:51» ثم تناقصت إلى 04:47 بعد 4 ثوانٍ ✓ (العداد يعمل)
- `?phone=+966738155896&otp=45832&otp_expires=2026-08-20 22:50:00` (منتهي) → نص أحمر «انتهت صلاحية الرمز — اطلب رمزًا جديدًا» ✓
- auto-verify برمز 6 أرقام صالح → يدخل مباشرة إلى شاشة المحادثات ✓ (user 33 +966738155896)

## ملاحظات Render
- Render: verify OTP كان يرجع OTP_SERVICE_ERROR (خطأ 500 في OtpService::verify على Render DB الفارغة)
  — هذا منفصل عن مشكلة العداد. السبب المحتمل: جدول/عمود مفقود في DB Render الجديدة أو PHP error
- DB Render الجديدة تحتوي: id=8 +966770105284 verified، id=5 +966738155861 verified، id=4 +966738155891 verified، id=2 +966738155892 manual، id=3 +966738155890 manual
- login +966738155861 على Render يرجع empty (المستخدم محظور أو bypass غير مفعل — سابقًا 403 بسبب is_blocked=1)

## الحالة
- build web جديد محليًا في web_app/ ✓
- لم يُرفع إلى Render/GitHub (قيد المستخدم الصارم)
- اختبار signaling Render الكامل لم يكتمل بعد (يتوقف عند verify OTP)

## بيانات تجريبية محلية
- الرقم المحلي المستخدم للاختبار: +966738155896 (user id=33)، كود OTP الحالي 458329 ينتهي 22:57:26 UTC
