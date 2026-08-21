# تشخيص وإصلاح العداد التنازلي لشاشة التحقق OTP

## المشكلة
المستخدم (السعودية GMT+3) يفتح شاشة التحقق فيظهر فورًا «انتهت صلاحية الرمز» بدل عدّاد تنازلي.

## السبب الجذري (مؤكّد رياضيًا)
- الخادم يخزن ويرجع `expires_at` بصيغة UTC صريحة (gmdate) لكن **بدون TZ marker**: `2026-08-20 22:54:53`
- Flutter القديم: `DateTime.parse(raw).toUtc()` — بدون TZ يُفسَّر كتوقيت محلي، ثم toUtc يطرح إزاحة الجهاز
- على جهاز GMT+3: الساعة المحلية 01:50 > 22:54 (بالأمس) → الفرق = **-356 دقيقة** → يظهر «منتهي» فورًا
- الفرق الصحيح: +4 دقائق

## الإصلاح (نفّذ)
1. `auth_provider.dart` — `_parseOtpExpiry`: بدون TZ تعامل السلسلة كـ UTC:
   `DateTime.parse(str).add(DateTime.now().timeZoneOffset).toUtc()`
2. `otp_screen.dart` — `_OtpCountdown`: المقارنة `DateTime.now().toUtc()` بدل local

## الاختبار المحلي
- build web ✓ (main.dart.js جديد في web_app/)
- الخادم المحلي :8080 يعمل، register/login يستجيبان
- ملاحظة: `?phone=` مع رقم غير مسجل يعيد masked success دون فتح OTP (مقصود — lastLoginUnregistered)
- الاختبار العملي: `?phone=<مسجل>&otp=<6 digits>&otp_expires=<ISO>` في main.dart سطر 184-205
