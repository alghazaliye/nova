# مهام متبقية — الخيار 1 (المعتمد) لحماية بيئة Render

## منجز
- [x] رفع إصلاح العداد التنازلي إلى GitHub (commit 2d874bd) ✓ ونشط على Render ✓ (dep-da3ob2jl550s73cm38cg live)
- [x] التحقق: JWT_SECRET مثبت وثابت على Render (`nova-prod-secret-2026-9702924b74e9a6aa`) — ليس سبب 401
- [x] التشخيص الكامل: DB Render فُقدت مع البناء الجديد (الخطة المجانية بدون Persistent Disk) → الحسابان +966738155861 و+966770105284 غير موجودين → 401 + لا رسائل + آخر ظهور خاطئ
- [x] Dockerfile startup.sh يحتوي بالفعل: restore من NOVA_DATA_DIR إن وجد، bootstrap schema عند DB فارغة، sync نشط→DATA عند كل startup (commit الحالي)
- [x] Persistent Disk غير متاحة في free plan (أكدنا من رسالة Render)

## متبقٍ
- [x] commit + push 488858b (Dockerfile sync) — **live الآن الساعة 11:07 PM** (dep-da3ogv2jobas739h36hg) ✓
  **قرار مهم**: بناء جديد الآن سيمسح أي بيانات أنشئها لاحقًا إلا إذا أنشأنا الحسابات بعد البناء الجديد. الترتيب: push → انتظار live → إعادة إنشاء الحسابين → تسليم
- [ ] إعادة إنشاء الحسابين: register +966738155861 و+966770105284 (POST /api/v1/auth/register)، جلب OTP من /api/v1/admin/otp/registrations (rows) ثم GET /admin/otp/registrations/{id}/code، ثم verify-otp لكل رقم
  - سكربت نموذجي: /home/ubuntu/nova_new/scripts/... (نمط من /tmp/check_render_db.py و render_signal_test2.py)
  - ملاحظة: +966738155861 كان محظورًا is_blocked=1 في DB القديمة — بعد إعادة الإنشاء يكون 0
- [ ] اختبار: تسجيل دخول الرقمين + تبادل رسالة + فحص آخر ظهور + heartbeat
- [ ] تسليم للمستخدم مع: رابط التطبيق https://nova-wn25.onrender.com/web_app/، لوحة التحكم https://nova-wn25.onrender.com/admin/ (محمد/738155861)، وإرشاد المستخدم بإعادة تسجيل الدخول (token القديم أصبح 401)
- [ ] إعلام المستخدم أن Persistent Disk تتطلب ترقية Render (Starter ~7$/شهر) كحل دائم نهائي ضد فقدان DB مع كل بناء

## حقائق تقنية للتذكير
- Render service: srv-da2hq5rm8hqs73dn9ep0، build تلقائي من GitHub main
- BASE=https://nova-wn25.onrender.com
- admin login: POST /api/v1/admin/otp/login {email: "admin@nova-messenger.com", password: "738155861"} → {data:{token}}
- registrations: GET /api/v1/admin/otp/registrations → {rows:[{id,phone,...}]} — صيغة rows وليس data!
- code: GET /admin/otp/registrations/{id}/code → {otp_code}
- verify: POST /api/v1/auth/verify-otp {phone, otp} → {data:{token}}
- calls: POST /api/v1/calls {callee_id, call_type}، signal: POST /api/v1/calls/{id}/signal {signal_type,payload}، GET /api/v1/calls/{id}/signals?since=
- OTP_EXPIRY_MINUTES في settings (افتراضي 5 دقائق؟) — عدّاد التنازلي يعمل الآن
- Dockerfile ENVs موجودة على Render: APP_ENV, DB_TYPE, ENCRYPTION_KEY, GMAIL_SMTP_*, JWT_SECRET, OTP_ENCRYPTION_KEY, OTP_PROVIDER
- cold start على free: 50+ ثانية
- DB على Render حالياً فارغة من users — registrations تحتوي أرقامي التجريبية فقط (+966738155897 وغيرها)
- GitHub token متاح عبر gh CLI (مسجل مسبقًا)، remote: origin → github.com/alghazaliye/nova
