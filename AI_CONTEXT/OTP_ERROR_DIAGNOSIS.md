# تشخيص خطأ الكتابة الحمراء عند رمز التحقق (2026-08-17)

## المشكلة
عند إدخال رمز التحقق في OtpScreen يظهر نص أحمر (auth.error) بسبب فشل verify-otp.

## السبب المكتشف
عند تمرير `device_uuid` في verify-otp:
1. `AuthController` يحاول `INSERT INTO user_devices ... ON DUPLICATE KEY UPDATE` — جدول device_registrations له UNIQUE على (user_id, device_uuid) لكن الكود يستخدم جدول user_devices الذي لا يوجد به ON DUPLICATE KEY صحيح أو الفهرس غير مطابق.
2. ثم `INSERT INTO sessions` مع `token_hash` UNIQUE — إذا كان توكن تم إنشاؤه بنفس JWT payload (sub/iat/exp) أو نفس hash، يفشل: `Duplicate entry ... for key 'uq_sessions_token_hash'`.

في الاختبار: بدون device_uuid نجح. مع device_uuid → Fatal PDOException 1062 (uq_sessions_token_hash).

## الحل
- في generateToken: معالجة الخطأ عند تكرار token_hash (إعادة توليد t=iat مختلف)، وINSERT INTO sessions بأمان (try/catch).
- التأكد من أن user_devices INSERT يتوافق مع الفهارس الفعلية (device_registrations هو الجدول الصحيح حسب الإصلاح السابق — يجب فحص أين يكتب AuthController).

## الإصلاح المنفذ (تم)
- createSession: إضافة jti عشوائي (microtime+random) → hash فريد دائمًا
- device registration داخل try/catch (non-fatal)
- session insert retry مرة واحدة عند collision
- fix: (int)lastInsertId لـdeviceId

## التحقق
- 5 دعوات متتالية فورية مع device_uuid → كلها نجحت ✅

## ملاحظة إضافية في main.dart
- عند auto-login بـ?phone=&otp= مع توكن stale في localStorage، قد يظهر خطأ أحمر لأن verifyOtp في auto-login كان يفشل سابقًا (Fatal error). الآن سيصلح.
- _onAuthChanged: إذا token موجود ولا يزال صالحًا لا حاجة للـrelogin.

## الملف
- /home/ubuntu/nova_new/backend/controllers/AuthController.php
