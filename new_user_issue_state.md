# حالة مشكلة الحساب الجديد + قواعد بيانات مجانية (2026-08-21)

## التشخيص الكامل — الخلاصة

### النتائج على Render (كلها نجحت):
1. `users/search` يعمل — يرجع id=3,4,5 (مستخدمي NOVA الحقيقيون) + id=1,2 (حسابات seed وهمية من seed_production.sql: أحمد الغزالي ahmed +966501234567، سارة العمري sara +966502345678)
2. إرسال الرسائل بين الحسابات — 201 وتظهر عند المستقبل ✓
3. إنشاء المحادثات — يعمل ✓
4. المكالمات (POST /calls initiate) — 201 status=ringing ✓
5. البحث '+9667' يرجع id=4,5؛ 'مستخدم' يرجع id=4,5؛ '861' فارغ (id=3 مستثنى ذاتيًا)
6. GET /users/1 و /users/2 يعملان (seed موجودان في DB)

### الحسابات الحالية في DB Render:
- id=3 +966738155861 (user_id من /tmp/render_accounts.json)
- id=4 +966770105284
- id=5 +966770123456 (حساب اختباري أُنشئ أثناء التشخيص — يمكن تركه أو حذفه)
- id=1,2 = seed وهمي (أحمد/سارة) — يجب حذفهما

### السبب الجذري المحتمل لمشكلة المستخدم:
- **كل rebuild جديد لـ Render يمسح DB بالكامل** (خطة مجانية بدون Persistent Disk)
- أي حسابات أُنشئت قبل آخر rebuild فُقدت — المستخدم يرى أن حسابه الجديد «لا يجد» الحسابات السابقة لأنها لم تعد موجودة
- الحل الدائم: Persistent Disk على Render (~7$/شهر) أو DB خارجية

### FK في schema.sqlite.sql (مع CASCADE على users):
blocks(2), call_participants, contacts(2), conv_members, message_reactions, message_reads, notifications, reports(2), sessions, stories, story_views, devices, call_signals — كلها ON DELETE CASCADE
بدون CASCADE: attachments(uploader), calls(caller), conversations(created_by), groups(created_by), messages(sender) — يجب حذف صريح أو تجاهل

## الخطوات المتبقية:
1. إضافة deleteUser endpoint (AdminController + route DELETE /api/v1/admin/users/{id}) — لم يُنفذ بعد
2. حذف seed id=1,2 من DB Render
3. بحث قواعد بيانات خارجية مجانية (Neon, Supabase, Turso...) — ملاحظات في new_user_issue_diagnosis.md
4. التسليم للمستخدم

## معلومات مهمة:
- Admin token: POST /api/v1/admin/otp/login {email: admin@nova-messenger.com, password: 738155861} → data.token
- الحسابات الحالية في /tmp/render_accounts.json
- Render URL: https://nova-wn25.onrender.com — service srv-da2hq5rm8hqs73dn9ep0
- الخادم المحلي: php -S 0.0.0.0:8080 backend/public/router.php (يعمل)
- آخر commit: 684e594 (typing indicator + تحسين الأداء)
