# خطة التنفيذ النهائية — حزمة الإصلاحات الكاملة

## النطاق والواقعية
الحزمة تشمل 6 ملفات طلبات (P4-P9). بعضها ميزات ضخمة (نظام الأجهزة المتعددة QR — pasted_9 — مشروع مستقل بحده). سننفذ **الأساسات الحرجة بالكامل** وهي: آخر ظهور، الخصوصية، ربط إعدادات لوحة التحكم بالميزات، Backend security، قائمة المحادثة، إصلاح تدفق المستخدم الجديد/الرسائل/المكالمات. نظام الأجهزة المتعددة الكامل (QR+sessions) يُنفذ جزئيًا حسب ما لا يكسر النظام (حد الأجهزة موجود أساسًا في DeviceController) — لن نبني نظام QR كامل من الصفر في هذه الحزمة لكن سنبني عليه لاحقًا بأمر المستخدم.

## P1 — الأساسات (blocking، مرتبطة ببعضها)
1. [x] فحص schema: privacy_settings drift (UserController يتوقع last_seen_visibility vs schema يستخدم show_last_seen) → توحيدها + schema.sqlite.sql
2. [x] آخر ظهور (P7):
   - backend: heartbeat يحدّث users.last_seen + is_online (الآن)، UserController يرجع last_seen_iso
   - Flutter: formatLastSeen مركزية (العربية كاملة) في chats_screen + chat_screen header
   - privacy: احترام show_last_seen (الجميع/جهات/لا أحد)
3. [x] Backend security (P4): enforceSettings() helper — كل endpoint (stories/groups/calls/edit/delete_everyone/disappearing) يرفض بـFEATURE_DISABLED عندما setting=false؛ edit_time_limit_minutes وdelete_time_limit_minutes يُفرضان server-side (0=unlimited)
4. [x] FCM: verify isEnabled لا يرمي، send fails gracefully؛ presence notifications عند new_message/call incoming
5. [x] appSettings (UserController) يرجع: allow_calls/groups/stories + edit/delete/disappearing defaults + story duration + flags

## P2 — الصفحات والميزات
6. [x] صفحة الخصوصية (P6): privacy_screen تقرأ privacy_settings + fallback default + timeout 15s + error/retry + toggle يحفظ ويرجع
7. [x] قائمة المحادثة (P4/P5): chat menu items الحقيقية:
   - عرض جهة الاتصال → profile_screen (موجود)
   - بحث في المحادثة
   - الوسائط/الروابط/المستندات (media dialog من messages)
   - كتم الإشعارات (muted_until في conversations)
   - سمة الدردشة (theme color في conversation)
   - الرسائل المؤقتة (disappearing ttl)
   - إبلاغ (report -> reports table)
   - حظر (block/unblock)
   - مسح محتوى الدردشة (clear chat محلي/لدى الطرف فقط)
   - كل Backend محمي بـ enforceSettings
8. [x] تدفق المستخدم الجديد → رسالته تصل + محادثته تُنشأ تلقائيًا (get-or-create في transaction) + منع التكرار A↔B

## P3 — الاختبار والرفع
9. [ ] flutter analyze + flutter test + php -l
10. [ ] اختبار فعلي: مستخدم جديد (رقم لم يسبق) ↔ حسابات Render الحالية: تسجيل → بحث → محادثة → رسائل → رد → typing → last_seen → مكالمات (initiate/reject/end signaling)
11. [ ] git diff: لا أسرار (JWT_SECRET المحلي nova-dev-only في .env لا يوجد أصلاً، لكن نتأكد لا API keys/SA JSON)
12. [ ] Commit + Push → Render builds
13. [ ] Production smoke: health، web_app index 200، assets 200، تسجيل + رسائل + typing + last_seen
14. [ ] تقرير نهائي

## التعارضات والمخاطر المحددة
- T1: privacy_settings columns موحدة على أعمدة schema (show_last_seen...) — UserController يستخدم أسماء مختلفة. الحل: دالة helper تطابق كلا الاسمين.
- T2: last_seen: heartbeat الحالي يحدّث users.updated_at — نضيف عمود last_seen صريح + is_online bool (لا عمود جديد إن أمكن: نستخدم last_seen + last_online_at إن وجد، وإلا UPDATE is_online عند heartbeat وreset عند logout).
- T3: poll chat_screen كل 3s يبقى المصدر الرئيسي للرسائل — لا نلمسه إلا لإضافة last_seen. لا كسر WebSocket غير موجود أصلًا.
- T4: DB Render تُعاد مع كل deploy — seed_production.sql مصدر الحقيقة الوحيد للمخطط. schema.sqlite.sql يجب أن يحتوي كل الجداول (typing_status أُضيف ✓). أي جدول جديد يُضاف لهما.
- T5: لا تغيير على authentication أو JWT أو أسماء جداول قائمة (قيد المستخدم).
