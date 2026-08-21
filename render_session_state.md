# حالة جلسة Render الحالية (21 أغسطس 2026)

## تم بنجاح
- كلمة سر Render صحيحة الآن: alghazaliye@gmail.com / Aa738155861
- سجلت الدخول في dashboard.render.com (المتصفح في الساندبوكس)
- المسار: My project → Production → nova (Docker, Free, Singapore)
- Service URL: https://nova-wn25.onrender.com
- Service ID: srv-da2hq5rm8hqs73dn9ep0
- GitHub: alghazaliye/nova (main)
- Events: 33 events، "Build not found" في صفحة events (غير مهم)

## الهدف الحالي
إلغاء حظر المستخدم +966738155861 في DB على Render (is_blocked=1 في users)
- الخادم يرمي 403 "تم حظر حسابك. يرجى التواصل مع الدعم الفني" عند is_blocked
- Render لا يوفر الوصول المباشر لـnova.sqlite — الحل: عبر لوحة تحكم التطبيق نفسها:
  https://nova-wn25.onrender.com/admin/users.php → login admin: محمد / 738155861
  → زر unblock مقابل المستخدم المحظور (hasPermission users.block مطلوب للمدير)

## ما تم إصلاحه في Flutter (جاهز للرفع لكن لم يُرفع بعد)
- nova_flutter/lib/screens/chats_screen.dart: معالجة أخطاء _showAddNewContactDialog
  (فشل الشبكة، 403 محظور، رسالة واضحة بدل "لم يتم العثور على مستخدم")
- flutter analyze: لا أخطاء (info/warning فقط غير ذات صلة)

## الخطوات المتبقية
1. إلغاء الحظر عبر لوحة التحكم (admin/users.php على Render) أو عبر Shell في Render
2. التحقق: curl POST /users/search برقم أحمد من Render
3. بناء Flutter web محلي + نشر في web_app + git add -f web_app + commit + push (بأمر المستخدم)
4. إعادة بناء Render تلقائيًا بعد push — التحقق بعد البناء

## ملاحظة DB Render
- NovaTZ ✓، main.dart.js=3562793 (البناء الحالي)
- DB نجت من إعادة البناء السابق
- رقم المستخدم في DB المحلية id=30 (+966738155861) — غير محظور محليًا
- على Render: المستخدم محظور (403 في devices/register + contacts)
- أرقام اختبارية على Render: id=1 +966738155862 (verified)، id=2 +966738155899 (manual)، +966738155879 (اختبار مسجل اليوم)

## Render Shell
- متاح: Manage → Shell (في dashboard) — يمكن استخدام sqlite3 على nova.sqlite
- مسار DB: /app/backend/config/nova.sqlite (في Docker)
- أو عبر One-Off Jobs

## تحديث 22:27 (لوحة التحكم على Render)
- نجح تسجيل دخول admin/users.php على Render: محمد / 738155861 (جلسة متصفح نشطة)
- الصفحة الحالية: admin/index.php — سجل دخول نجح
- لوحة التحكم تعرض: 5 مستخدمين إجماليًا، 4 رسائل، 1 مكالمة اليوم، 2 متصلون (40%)
- عنصر القائمة "♙ المستخدمون" = index 3 في sidebar (admin/index.php)
- الخطوة التالية: النقر على المستخدمون → البحث عن +966738155861 → unblock
