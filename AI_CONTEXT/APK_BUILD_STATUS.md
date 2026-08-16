# APK Build Status — Session 4 (قبل ضغط السياق)

## الحالة الحالية:
- Flutter web built ✅ → /home/ubuntu/nova_new/web_app (base href=/web_app/)
- الخادم يعمل على port 8080 (php -S 0.0.0.0:8080 backend/public/router.php من /home/ubuntu/nova_new)
- admin panel يعمل ✅: http://...:8080/admin/login.php (login=admin@nova-messenger.com, password=Admin@1234, CSRF required)
- كل APIs تعمل ✅ (groups, contacts, calls, messages enrich, settings)
- flutter analyze: 0 errors ✅

## مشكلة APK الحالية:
Flutter SDK المثبت (3.32.x, Dart 3.8) قديم جدًا لـ:
- record_platform_interface 2.1.0 (يحتاج Dart 3.12)
- record_linux 2.1.1 (يحتاج Dart 3.12)

## الخيارات:
1. (مفضّل) تنزيل Flutter أحدث (3.36.x+ / Dart 3.12+) → إعادة pub get + build
   - URL: https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_3.38.0-stable.tar.xz (أو 3.35.x)
   - ملاحظة: pubspec sdk: ^3.6.2 يقبل Dart 3.8 فقط! يجب رفعه إلى sdk: ^3.8.0 أو أحدث عند تحديث Flutter
2. بديل: البقاء على 3.32.x واستخدام record_platform_interface <2.0 وrecord_linux <1.1 (لكنها غير متوافقة مع record 5.2.1 — تعارض مع record 5.2.1 نفسه؟ record 5.2.1 يتطلب record_platform_interface ^1.6.0 لكن interface 1.6.0 API جديد (hasPermission(request)) غير موجود في record_linux 0.7.2 ولا record_web 1.3.0)

## الحل الأمثل: تحديث Flutter SDK إلى 3.36.x+ + رفع sdk في pubspec.yaml
- /home/ubuntu/flutter = SDK الحالي (3.32.x)
- الجديد: استبدال أو بجانبه /home/ubuntu/flutter_new ثم تحديث PATH

## ملاحظة مهمة:
- flutter_web_assets alias map في router.php يدعم Flutter 3.47+ (من الجلسة السابقة)
- web_app تم بناؤه بـ3.32.x ونجح — أي Flutter >=3.16 يعمل مع engine alias map

## APK بعد نجاح البناء:
- build/app/outputs/flutter-apk/app-release.apk → انسخه إلى /home/ubuntu/nova_new/Nova_Messenger.apk للتسليم

## تحديث (بعد):
- تم استبدال Flutter SDK: /home/ubuntu/flutter أصبح 3.38.7 (Dart 3.12) ✅
- pubspec.yaml sdk constraint = ^3.8.0 ✅
- dependency_overrides: record_linux ^2.1.1, record_platform_interface ^2.1.0 ✅
- flutter pub get نجح ✅
- تم إصلاح lib/utils/nova_web_state_web.dart (إزالة dart:js_util، استخدام dart:js_interop globalContext.callMethod)
- الخطوة التالية: flutter analyze → 0 errors → flutter build apk --release --no-pub (من /home/ubuntu/nova_new/nova_flutter, ANDROID_HOME=/home/ubuntu/Android, GRADLE_OPTS="-Xmx512m -XX:+UseG1GC", PATH=/home/ubuntu/flutter/bin)
- بعد البناء: cp build/app/outputs/flutter-apk/app-release.apk /home/ubuntu/nova_new/Nova_Messenger.apk
- web_app تم بناؤه سابقًا بـ 3.32.x — قد يحتاج إعادة بناء بـ 3.38.7 (flutter build web --release → sed base href=/web_app/ → cp إلى web_app)
- admin login: http://<url>:8080/admin/login.php
- web login: http://<url>:8080/web_app/?phone=%2B966501234567&otp=123456
- OTP: login → verify-otp (phone, otp=123456)

## تحديث 2:
Flutter 3.38.7 = Dart 3.10 فقط! record_platform_interface 2.1.0 يحتاج Dart 3.12+ → تنزيل Flutter 3.47.0 من https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_3.47.0-stable.tar.xz إلى /tmp/flutter_347.tar.xz (الخلفية، PID 14238).
الخطة: tar xf إلى /home/ubuntu/flutter347 → flutter347/bin/flutter doctor → flutter pub get → analyze → build web (sed base href=/web_app/ → cp إلى web_app) → build apk (ANDROID_HOME=/home/ubuntu/Android, GRADLE_OPTS="-Xmx512m -XX:+UseG1GC").
ملاحظة: android_build_guide.md في nova_new قد يفيد.
