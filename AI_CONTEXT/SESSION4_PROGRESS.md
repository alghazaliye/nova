# Nova Messenger — Session 4 Progress (محدّث نهائي)

## مكتمل بالكامل:
- GroupsController + routes ✅ (mine/show/members/add/remove/role/settings/title/avatar/leave)
- contacts routes ✅ (newContacts/addContact/removeContact)
- MessageController: enrich (is_edited/deleted_for_me/deleted_for_all) + delete for_all/for_me ✅ + only_admins_can_message check في send ✅
- Flutter: GroupInfoScreen + groups_screen + incoming_call_overlay + chat_screen (عنوان المجموعة → GroupInfoScreen) + chats_screen (إنشاء مجموعة + اختيار أعضاء + incoming polling مع handledIds)
- call_screen إصلاح "بانتظار الطرف الآخر" ✅
- flutter analyze: **0 errors** ✅
- Flutter web built ✅ (build/web → /home/ubuntu/nova_new/web_app, base href=/web_app/)
- الخادم يعمل: php -S 0.0.0.0:8080 (cd /home/ubuntu/nova_new; router.php يحمل .env)

## بيئة (مهم):
- DB: nova_user@% password nova2026, DB=nova
- .env يحتوي: OTP_PROVIDER=test, OTP_TEST_CODE=123456, DB_*, JWT_SECRET
- OTP bypass: login → verify-otp (otp=123456)
- جداول أُنشئت: user_subscriptions, plans, device_registrations, message_edits, message_deletions, + أعمدة messages.disappear_after, conversation_members.disappear_after
- users: أحمد id=1, سارة id=2, محمد id=3, نور id=4, خالد id=5
- المجموعات: id=1 (مجموعة تجريبية: أحمد+سارة+محمد), id=2 (مجموعة سارة: سارة+أحمد+نور+خالد)
- contacts: أحمد أضاف 2,3,4,5
- admin: admin@nova-messenger.com / Admin@1234
- لوحة التحكم admin: /admin (router يخدم backend/public/index.php؟ لا — admin يجب فحصه: يوجد admin folder في nova_old؟ admin في nova_new غير موجود؟)

## المتبقي:
1. ❌ Android SDK غير موجود → يجب تنزيل cmdline-tools + platform-tools + platforms + build-tools → /home/ubuntu/Android
2. ❌ بناء APK: cd /home/ubuntu/nova_new/nova_flutter && PATH=/home/ubuntu/flutter/bin ANDROID_HOME=/home/ubuntu/Android GRADLE_OPTS="-Xmx900m -XX:+UseG1GC" flutter build apk --release --no-pub
3. ❌ نسخة APK سابقة: /home/ubuntu/Nova_Messenger.apk (من nova القديمة)
4. ❌ التسليم: لوحة التحكم + رابطا مستخدمين + APK + web URL

## كيفية التسليم:
- expose port 8080 → https://8080-{uuid}.us3.manus.computer
- admin url: .../admin (فحص هل admin folder موجود — router.php لا يخدم /admin! يجب فحص nova_old/admin)
- web: .../web_app/?phone=%2B966501234567&otp=123456
- SARA: +966502345678, AHMAD: +966501234567
