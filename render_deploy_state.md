# حالة Render deploys (2026-08-20 23:02)

## مهم — الرفع نجح!
- deploy dep-da3ob2jl550s73cm38cg = **live** لـ commit **2d874bd** (fix: OTP countdown UTC-safe expiry parsing) — نشط الآن الساعة 10:55 PM
- أي: إصلاح العداد التنازلي مرفوع على GitHub ✓ ونشط على Render ✓ (نسخة إصلاح العداد 2d874bd live، لكن DB Render فُقدت كما شُخص في 401_diagnosis.md)
- dep-da3o38e7bikc7388gud0 = live لـ 0271901 (call_signals fix) — قبله
- Service ID: srv-da2hq5rm8hqs73dn9ep0، الرابط: https://nova-wn25.onrender.com
- البناء من GitHub تلقائي (Auto-Deploy)، region: singapore، Docker، free plan

## المهمة الجارية: ربط Persistent Disk
- صفحة Disk URL: https://dashboard.render.com/web/srv-da2hq5rm8hqs73dn9ep0/disks
- في القائمة الجانبية: Manage → Disk (index تقريبي 26 في صفحة الخدمة)
- يجب: Add Persistent Disk → Mount Path=/data/nova_data → Service=nova → Size: أصغر متاح (1GB) → Create → (سيُعيد البناء تلقائيًا)

## تذكير: بعد ربط القرص
- DB ستُعاد تهيئة من الصفر (schema من schema.sqlite.sql عند أول التشغيل) — يجب إعادة إنشاء حسابي +966738155861 و+966770105284
- تثبيت JWT_SECRET كـenv variable (Settings → Environment) — القيمة: أي سلسلة عشوائية طويلة ثابتة
- ثم إعادة تسجيل دخول المستخدم في التطبيق
- ملاحظة: ربط القرص على instance موجودة يعني أن أي بيانات قبله تضيع — لا يمكن استرجاع الرسائل المحذوفة
