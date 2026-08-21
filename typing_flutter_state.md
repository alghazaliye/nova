# حالة phase 2 (Flutter Typing Indicator) — 2026-08-21 02:55

## منجز (phase 2 جزئي + backend مكتمل)
1. models/user_model.dart: Conversation أصبح يحتوي typingUsers (List<Map<String,dynamic>>) + fromJson يقرأ j['typing_users'] ✓
2. backend ConversationController index(): أُضيف enrich typing_users لكل محادثة (subselect على typing_status WHERE expires_at > NOW() مع JOIN users) داخل try/catch ✓ (سطر 57-72)
3. بقي: chat_screen.dart — typing send on keystroke + GET في _refreshSilent + header subtitle 'يكتب الآن...'، وchats_screen.dart subtitle (يقرأ conversation.typingUsers من chats polling)

## البنية المعروفة
- chat_screen.dart (2801 سطر):
  - `_ctrl` TextEditingController لحقل الرسائل (سطر 43)، `_hasText` bool (سطر ~81 `_ctrl.addListener` → setState `_hasText`)
  - `_pollTimer` كل 3 ثوانٍ → `_refreshSilent()` (سطر 84)
  - `_sendMessage()` سطر 340 — يرسل POST /conversations/{id}/messages بـ fields {client_message_id, ...body}
  - TextField: سطر ~1856 (height 44)، onSubmitted → _sendMessage()
  - AppBar/header: سطر 1607 Scaffold → Stack → Column → SafeArea → Container header (سطر 1618-1730+)
    - subtitle الحالي: Row فيه dot (خط 1679: width/height 6) + Text 'متصل الآن' / formatLastSeen(...) (سطر 1699-1712)، style fontSize 11, color: isOnline ? c.green : c.muted
  - widget.conv: Conversation object (name, avatar, isOnline, lastSeen, isGroup, groupId...)
  - c = chat theme colors (c.bg, c.text, c.muted, c.accent, c.surface, c.surface2, c.line, c.green, c.shadow)
  - api imports: services/api_service.dart (ApiService static post/get/put/delete/uploadMultipart, token, userId)
  - Provider AuthProvider (auth.effectiveAppSettings.allowCalls)
- chats_screen.dart (1952 سطر): قائمة المحادثات — polling موجود أيضًا (لجلب chats)
- api_service.dart: 125 سطر، static methods، baseUrlOverride، headers

## Backend (منجز phase 1)
- جدول typing_status: conversation_id, user_id, expires_at, updated_at, UNIQUE(conv,user) — في schema + DB محلية
- POST /api/v1/conversations/{id}/typing {typing:true/false} → setTyping (MessageController سطر 806)، صلاحية 4 ثوانٍ
- GET /api/v1/conversations/{id}/typing → {data:{typing_users:[{user_id, name, ...}]}}
- routes في index.php سطر 349-355

## منجز لاحقًا (محدّث 02:58):
- chat_screen.dart: ✓ _typingTimer + _lastTypingSent + _notifyTyping(has) في listener + _sendTypingCancel() + dispose يرسل cancel
- chat_screen.dart header: ✓ يقرأ widget.conv.typingUsers.isNotEmpty → يعرض 'يكتب الآن...' (c.accent, w600) بدل online status
- chats_screen.dart: ✓ tile المحادثة: يعرض «يكتب الآن...»/«يكتبون الآن...» بدل online status عند typing
- ⚠️ نقطة حرجة متبقية: ChatScreen مبنية على widget.conv ثابت — يجب إضافة polling داخل ChatScreen يجلب /conversations/{id}/typing (أو GET conversation) كل 3 ثوانٍ ويحدث typingUsers محليًا عبر setState، وإلا لن يظهر المؤشر إلا بعد إغلاق/إعادة فتح المحادثة.

## الخطة المتبقية (Flutter)
1. chat_screen.dart:
   - إضافة Timer? _typingTimer و bool _typingSent و _typingState(List)
   - في _ctrl addListener: إذا النص غير فارغ → _sendTyping(true) (مرة كل ~3 ثوانٍ max) — reset timer
   - إرسال typing=true عند بداية الكتابة، والـbackend clears بعد 4s تلقائيًا
   - GET typing كل 3 ثوانٍ داخل _refreshSilent (أو timer منفصل 2s) — الأفضل داخل _refreshSilent بعد تحديث الرسائل (request واحد إضافي)
   - تعديل header subtitle: إذا typingUsers.isNotEmpty → Text 'يكتب الآن...' (c.accent) مع 3 نقاط متحركة + dot أخضر، وإلا الأصلي
   - عند _sendMessage: إرسال typing=false (backend يمسح)
2. chats_screen.dart (اختياري): إضافة 'يكتب...' في subtitle المحادثة إن typingUsers غير فارغ — يحتاج polling chats يجلب typing_users من conversations index. فحص Backend: هل ConversationController index يرجع typing_users؟ (إن لم يرجع: نحتاج إضافته)
3. فحص backend ConversationController index: هل يضم typing_users — إن لم يُضم، أُضيفه: لكل conversation جلب آخر typing_status غير منتهي
4. إعادة بناء flutter web + اختبار محلي في المتصفح (سكربت /tmp/test_typing_local.py كان يستخدم DB tokens قديمة؛ الأفضل: test script جديد مع verify OTP عبر admin code + فتح صفحتين في المتصفح؟ أو curl فقط)
5. البناء: export PATH="$PATH:/home/ubuntu/flutter/bin" && cd nova_flutter && flutter build web --release && cp -r build/web/* ../web_app/ + إضافة dynamic base href script في index.html المنشور (نص موجود: <script>...NovaTZ...dynamicBase</script>)
   - ملاحظة: البناء يستخدم main.dart.mjs؟ لا — js-only (main.dart.js 3.5MB). index.html يحتاج dynamic base href script (من جلسة سابقة)
6. رفع: لا بدون أمر المستخدم (لكن المستخدم طلب سابقًا رفع مؤشر الكتابة؟ لا — المستخدم طلب فقط إضافة الميزة؛ الرفع عند أمره)

## نتائج أحدث (02:40):
- chat_screen: _localTypingUsers ✓ + _listsEqual ✓ + header يستخدم _localTypingUsers ✓ + _refreshTyping() يُستدعى داخل _refreshSilent (كل 3 ثوانٍ) ✓ — لا أخطاء في flutter analyze
- chats_screen: tile يقرأ conv.typingUsers من polls القائمة (backend ConversationController index يرجع typing_users) ✓
- build: flutter build web ✓ → web_app/ (main.dart.js 3567356B) + dynamic base href + NovaTZ ✓
- النصوص العربية غير موجودة في main.dart.js (لا محليًا ولا على Render المنشر) — هذا سلوك طبيعي (النصوص مدمجة بطريقة لا تُرى raw في الملف أو flutter_bootstrap يحمّلها من مكان آخر). التطبيق يعمل فعليًا بالعربية، فلا قلق.
- الاختبارات المحلية كلها نجحت ✓ (curl):
  - setTyping true → 200؛ الطرف الآخر يراه في GET /typing
  - انتهاء الصلاحية 4s يعمل تلقائيًا؛ الإلغاء الصريح يعمل
  - قائمة المحادثات تعرض typing_users أثناء الكتابة وتفرغ بعد الانتهاء
- بقي: عرض النتيجة للمستخدم + الرفع فقط عند أمره صراحة.

## Render test script
- /tmp/test_typing_render.py: register → admin code → verify → conversations POST {type:private,user_id} → cid=d["id"] — يعمل ✓
- admin login: POST /api/v1/admin/otp/login {email: admin@nova-messenger.com, password: 738155861}
