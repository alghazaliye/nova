# حالة Render Smoke — محدث (آخر تحديث)

## الإنجاز الأساسي (مكتمل ومرفوع):
- **ReportsController.php**: إصلاح جذري (placeholder mismatch، ""→''، NOW()→datetime، transaction). **commit 86ada88 مرفوع على GitHub main**.
- **database.php**: PRAGMA read_uncommitted=1. index.php + Response.php: traces dev-mode.
- **المحلية**: 18/18 PASS. flutter analyze 0 errors (1 قديم web-only).

## اكتشاف حرج: IP الساندبوكس محجوب من Render مباشرة (SSLEOF عام)
- الحل: استخدام **متصفح Manus (browser_console_exec)** على صفحة `https://nova-wn25.onrender.com/web_app/` — يعمل (نفس-origin fetch، لا CORS).
- webpage_extract يعمل أيضًا من شبكة Manus.

## نتائج smoke عبر المتصفح (كلها بنفس-origin fetch):
| الفحص | النتيجة |
|---|---|
| PUT/GET privacy nobody + restore | PASS |
| disappearing set (PUT /conversations/1 {disappear_after:86400}) + send | PASS |
| mute + unmute (POST /conversations/1/mute) | PASS |
| **report created: POST /reports → 201 + data** | **PASS ✅** |
| **duplicate report: 409 DUPLICATE_REPORT** | **PASS ✅** |
| GET /reports list | PASS |
| call initiation (POST /calls voice) | PASS |
| edit + delete-for-all message | PASS |
| typing POST + seen by other | PASS |
| get-or-create conv + send msg | PASS |
| **story: FAIL — "نص الحالة لا يمكن أن يكون فارغاً" (body مطلوب ولا media_url null)** | يحتاج إصلاح payload |

## payloads الصحيحة المكتشفة على Render:
- privacy PUT: {last_seen_visibility, online_status} على /privacy
- disappearing: PUT /conversations/{cid} {disappear_after: 86400}
- mute: POST /conversations/{cid}/mute {muted:true/false}
- stories: {body:'...'} فقط (media_url ممنوع إذا null — يجب media_url حقيقي أو حقل media_type)
- reports: {reported_user_id, reason} → 201 / 409
- calls: {type:'voice', callee_id} → 201-ish (data.success true)
- conversations: {type:'private', user_id} → data.data.id
- messages: {body, client_message_id} → data.data.id
- messages edit: PUT /messages/{id} {body}
- messages delete-for-all: DELETE /messages/{id}?delete_for=all

## flow OTP (يعمل عبر المتصفح):
login → admin registrations (rows: id, phone_number, status, expires_at) → admin code (otp_code) → verify-otp → token. retry على 3 صفوف.
- verify-otp قد يُلغي جلسات المستخدم الأخرى → تجديد + اختبار فورًا.

## المتبقي:
1. إعادة اختبار story بـpayload صحيح (body فقط) عبر المتصفح → إذا PASS = 23/23
2. إذا نجح كل شيء: كتابة التقرير النهائي وتسليمه (commit 86ada88، GitHub OK، Render يعمل)
3. ملاحظة: لا تغييرات كود متبقية مطلوبة — story موجود يعمل (enforceFeature من settings). الفشل كان payload السكربت.

## سكربتات /tmp (للمرجعية، تعمل من شبكة Manus فقط):
- wake_render.py, get_render_tokens.py, render_smoke_bundle.py, test_bundle_final.py (محلي 18/18)
