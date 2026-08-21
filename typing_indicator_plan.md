# خطة إضافة مؤشر الكتابة (Typing Indicator) — Nova Messenger

## المهمة الحالية (phase 1 من 4)
إضافة API مؤشر كتابة + تحديث Flutter + اختبار محلي + تسليم. لا رفع حتى يأمر المستخدم.

## قرارات التصميم (polling-based — لا websocket)
- لا يوجد websocket في المشروع؛ الحل الأنسب: polling خفيف.
- Backend: جدول جديد `typing_status` (conversation_id, user_id, expires_at) + endpoint:
  - POST /api/v1/conversations/{id}/typing {typing: true} — يكتب/يعيد تعيين expires_at = now + 4s
  - GET /api/v1/conversations/{id}/typing — يرجع قائمة المتواجدين الآن (expires_at > now)
  - حذف تلقائي: عند POST، DELETE من القديم ثم INSERT
- Flutter: debounce 1s أثناء الكتابة؛ كل 2-3s يسحب getTyping؛ عند عدم وجود حدث يكتب «يكتب الآن...» تحت اسم المحادثة في chats_screen وداخل المحادثة chat_screen.
- احترام إعداد الخصوصية: لا يوجد إعداد للـtyping حاليًا (show_last_seen/show_online_status فقط) — يمكن تخطي العرض إذا last_seen_visibility=none (نفس منطق last_seen) — اختياري.

## أنماط الكود الحالية (مهم)
- MessageController /backend/controllers/MessageController.php — requireMember($convId,$userId) يفحص عضوية المحادثة
- AuthMiddleware::authenticate() يرجع ['user_id'] (مصادقة JWT + session في جدول sessions)
- routes في backend/public/index.php: routes نمط `if ($uri === 'X' && $method === 'POST')`
- Response::success($data, $msg); Response::error / validationError
- ملاحظة: index.php يستخدم `NOW()` — MysqlCompatPdo في Database يحولها SQLite تلقائيًا؟ (schema يستخدم datetime('now','localtime')) — التحقق من كيفية تعامل heartbeat مع NOW()
- schema.sqlite.sql: CREATE TABLE في النهاية (خط 552+) — نمط append tables هناك + bootstrap في PHP عند DB جديدة
- Flutter: nova_flutter/lib/screens/chats_screen.dart (قائمة المحادثات)، chat_screen.dart (شاشة المحادثة — إن وجد)
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php من /home/ubuntu/nova_new
- اختبار: حسابان محليان أحمد +966501234567 وسارة +966502345678 (Test@1234) أو أي رقمان في DB المحلية

## حالة DB المحلية
- sqlite3 backend/config/nova.sqlite
- جدول call_signals موجود ✓ (إصلاح سابق)

## حسابات Render الحية
- +966738155861 (user_id=3)، +966770105284 (user_id=4) — tokens في /tmp/nova_tokens.json
- Render: 488858b live — JWT_SECRET مثبت، كل شيء يعمل

## خطوات التنفيذ التفصيلية
1. database/schema.sqlite.sql: إضافة جدول typing_status (conversation_id INTEGER NOT NULL, user_id INTEGER NOT NULL, expires_at DATETIME NOT NULL, UNIQUE(conversation_id,user_id))
2. backend/controllers/MessageController.php: إضافة typing() وgetTyping() methods
3. backend/public/index.php: إضافة routeين: POST /conversations/{id}/typing و GET /conversations/{id}/typing
4. flutter: chat_screen.dart:
   - حقل النص: on_changed debounce → POST typing=true (كل 1s)
   - Timer.periodic 2.5s → GET typing → setState → عرض "يكتب الآن..." بدل subtitle إذا كان الطرف الآخر يكتب
   - chats_screen.dart: نفس المنطق في subtitle كل محادثة
5. اختبار محلي: php server + build web + curl أو متصفح بحسابين
6. رفع: git commit + push فقط عند أمر المستخدم
