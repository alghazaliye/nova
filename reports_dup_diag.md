# تشخيص reports — حالة 01:25 UTC

## المؤكدات:
- DB nova.sqlite يرى الصفوف 37 (58/59 سبب X), 38 (58/59 سبب X), 39 (58/59 سبب Y) — INSERTات من curl تصل فعلاً
- PRAGMA read_uncommitted=1 مفعّل (تأكدت CLI)
- journal=wal
- curl: البلاغ المكرر (37 ثم 38 نفس المستخدم/السبب) يُقبل كلاهما

## الاحتمال الأخير المفتوح: PDO SQLite snapshot لا يُحدث عند READ UNCOMMITTED؟
لا — SQLite read_uncommitted مع WAL يوفر "read uncommitted" فعلًا.
لكن! PDO SQLite قد يعيد snapshot عند BEGIN (implicit per-statement tx).
مع READ UNCOMMITTED يجب ألا يحدث.
اختبار قادم: عبر نفس PHP request: INSERT ثم SELECT يظهر الصف (نجح CLI test_ru.php id=33!)
فـ CLI نجح! عبر HTTP لا. الفرق: CLI كل statement في process واحد نفس الاتصال singleton
نفس process... HTTP نفس الشيء (php -S process واحد يخدم كل الطلبات).
الاختلاف الوحيد: opcache cache للـSQL string؟ لا.
فحص تالي: HTTP debug — هل create() تستخدم Database::getInstance() الحقيقي؟
(ربما ReportsController يستخدم $this->pdo من Database ولكن connection مختلف لا — singleton static.)
اختبار نهائي: نفس SELECT من داخل index.php عند POST /reports وطباعة النتيجة +
المعلمات — معرفة ما يرجعه fetch فعلًا مع سبب X بعد وجود صف مطابق.

## ملاحظة 04:09: params=[60,61,"سبب X",null] row=NONE رغم وجود صفوف 58/59 سابقة!
الصفوف 37-39 (58/59) موجودة في sqlite3 CLI لكن SELECT عبر HTTP لا يراها إطلاقًا!
هذا يعني: **PDO connection في الخادم يقرأ نسخة قاعدة بيانات مختلفة أو snapshot قديم
بشكل دائم** — أي أن WAL readers في هذا التطبيق لا يرون أحدث commits.
الحل الحاسم المعروف: SQLite مع PDO + WAL + opcache/process pool:
reader snapshot يبقى قديمًا إذا connection يُعاد استخدامه عبر requests (php -S keeps process alive).
في CLI نجح لأن process جديد. في HTTP: connection singleton process طويل — reader
snapshot يُعيّن عند أول READ في كل process lifetime؟ لا، SQLite snapshot per-transaction.
لكن READ UNCOMMITTED + autocommit per-statement...
التجربة النهائية: استخدام transaction صريح في create() (beginTransaction حول SELECT+INSERT).
SQLite في transaction: كل SELECT بعد INSERT يرى التعديلات.

# الحل الجذري المكتشف (04:14 UTC) — ROOT CAUSE FINAL:
**علامات الاقتباس المزدوجة "" في SQL في PHP double-quoted strings**:
عندما يكون SQL داخل string مزدوج PHP مثل `"VALUES (..., \"pending\", NOW())"`
فإن PHP يفسّر \"pending\" كـliteral صحيح — لكن المشكلة الأصلية في ReportsController
كانت مختلفة: string منفرد مع escaped quotes '..."pending"...' — هذه تعمل PHP-syntactically
لكن SQLite يفسّر "pending" كـ**identifier** (اسم عمود) وليس string literal!
→ INSERT "pending" (عمود) = error "column index out of range" عند EMULATE_PREPARES=false
(العمود لا يوجد!). وعند CLI نجح سابقًا... لا، CLI نجح لأن CLI استخدم
SQL بمفردات مختلفة ('pending' مفردة).

وأيضًا COALESCE(reason, "") — "" = empty string identifier → NULL → مقارنة فاشلة دائمًا!

ملاحظة معممة مهمة: قاعدة المشروع القديمة تستخدم "pending" المزدوجة في كثير من SQL
داخل single-quoted PHP strings (يعمل SQLite quote-doubles لـstring literals IF
العمود غير موجود؟ لا — SQLite: "text" = string literal فقط إذا لم يوجد عمود بنفس
الاسم وإلا = identifier!). هذا خطر كامِن في كل الكود!

## الحالة الحالية ReportsController.php (بعد الإصلاح):
- SQL uses single quotes داخل double-quoted PHP strings ('pending', datetime('now','localtime'))
- reason = ? فقط (بدون COALESCE) — لأن SQLite string comparison مع NULL: NULL != 'x' → لا مطابقة ✓
- transaction صريح + PRAGMA read_uncommitted=1
- debug CNT أُزيل
- لم يعد test: next = php -l + server restart + test_reports_curl.sh

## بعدها (الترتيب):
1. اختبار reports (4 checks: 201/409/201/201)
2. flutter analyze (في nova_flutter)
3. php -l لكل الـcontrollers
4. git status / git diff — مراجعة لا أسرار
5. commit + push (المستخدم سمح سابقًا)
6. Render auto-build → get_render_tokens.py → render_smoke_bundle.py
7. التقرير النهائي للمستخدم
Render: https://nova-wn25.onrender.com | JWT Render secret: nova-prod-secret-2026-9702924b74e9a6aa
سكربتات: /tmp/get_render_tokens.py (login بدل register إن وُجد)، /tmp/render_smoke_bundle.py
ملفات معدلة للرفع: backend/controllers/{User,Message,Conversation,Call,Story,Reports}Controller.php
backend/helpers/SettingsHelper.php backend/config/database.php backend/public/index.php
nova_flutter/lib/{models/user_model.dart, screens/privacy_screen.dart, screens/chat_screen.dart}

# حالة 04:35 UTC — test_create_local.php مع JWT مزيف: UNAUTHORIZED (صحيح)
- لا stack trace ظاهري — Response::error يرمي ResponseException يلتقطه... لا catcher في index.php؟ Fatal؟
- Response.php path: backend/helpers/Response.php
- AuthMiddleware: backend/middleware/AuthMiddleware.php
- التالي: توليد JWT محلي حقيقي (JWT_SECRET=nova-dev-secret-key-2026-xyz) لمستخدم موجود
  وتشغيل create() لرؤية stack trace الحقيقي. أو: curl مع توكن حقيقي + display_errors=1 —
  Response error message يعرض JSON فقط.
- الخطأ "25 column index out of range" — SQLITE_RANGE (25) = bind index out of range.
  hypothesis: PDO statement يعاد استخدامه أو execute([]) بـarray من 8 على placeholders 8 — صحيح.
  لكن! PDO SQLite: if EMULATE_PREPARES + named/positional mix = هذا الخطأ!
  هل PDO::ATTR_EMULATE_PREPARES = true في MysqlCompatPdo constructor؟
  إذا emulate ON مع positional (?,?) وPDO يعيد number them من 1..n يجب أن يعمل.
  هل يوجد في ReportsController execute بأعمدة غير matching؟ INSERT 8 placeholders/8 params.
  جرب: echo PDO error بعد كل statement في create() عبر try/catch فردي.
