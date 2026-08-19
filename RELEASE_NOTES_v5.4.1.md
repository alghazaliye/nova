# تقرير الإصدار v5.4.1 — نظام OTP حقيقي برموز عشوائية

## ملخص

هذا الإصدار يحوّل نظام التحقق من رمز OTP من نظام تجريبي ثابت (رمز `123456` ثابت أو رمز اختباري ثابت) إلى **نظام حقيقي يولّد رموزًا عشوائية من 6 أرقام في كل مرة**، مع الحفاظ على إمكانية المشرف على رؤية الرمز الحقيقي في لوحة التحكم أثناء بيئة التطوير. كما تم تنظيف قاعدة البيانات بالكامل من أي بيانات تجريبية.

## ما الذي تغيّر

| الملف | التغيير |
|---|---|
| `backend/otp/OtpService.php` | `generateCode()` يولّد الآن `random_int(100000, 999999)` دائمًا (حُذف block `OTP_TEST_CODE`)، `createAndSend()` و`resend()` تقبلان معلمة `devCode` مشتركة، `manual_code_hash` يُخزَّن دائمًا ليتمكن المشرف من رؤية الرمز |
| `backend/otp/EmailOtpService.php` | نفس التعديلات لخدمة OTP البريد الإلكتروني |
| `backend/controllers/AuthController.php` | `generateOtp()` عشوائي حقيقي (بقي `OTP_FIXED_CODE` استثناءً للتفعيل اليدوي من لوحة التحكم)، حذف bypass `123456` في `verifyOtp` (التحقق بـ`password_verify` فقط)، `isDevelopmentOtp()` يعتمد على `APP_ENV != production` فقط، تُولّد `devCode` واحدة مشتركة في `register`/`login`/`resend` تُمرَّر للخدمة فتطابق الرمز الظاهر للرد على الطلب المخزن في قاعدة البيانات |
| `backend/controllers/EmailAuthController.php` | نفس المنطق لـ`isDevTest()` و`randomDevCode()` |
| `backend/.env` | `OTP_PROVIDER=sms` و`APP_ENV=development` |

## كيف تعمل دورة OTP الآن

1. عند التسجيل، يولّد `AuthController` رمزًا عشوائيًا واحدًا (`devCode`) في وضع التطوير.
2. يُمرَّر هذا الرمز إلى `OtpService::createAndSend()` فيُخزَّن في `otp_hash` و`manual_code_hash` معًا، ويُعاد في حقل `otp_dev` في الرد.
3. يتحقق `verify-otp` من الرمز بـ`password_verify` فقط — **لا يوجد أي تجاوز ثابت**، ورمز `123456` القديم يُرفض.
4. يفتح المشرف صفحة طلبات التسجيل في لوحة التحكم `admin/otp-registrations.php` ويجد الرمز الحقيقي في سجل `manual`، ويستخدمه بنفسه للتحقق.
5. في الإنتاج (`APP_ENV=production`) لن يظهر حقل `otp_dev` إطلاقًا وسيُستخدم مزود SMS الحقيقي فقط.

## الاختبار المنفّذ

| الاختبار | النتيجة |
|---|---|
| تسجيل رقم جديد `+966501230112` | رمز عشوائي `971054` أُعيد في `otp_dev` |
| التحقق بالرمز الصحيح | نجح، JWT صادر |
| محاولة `123456` القديم | مرفوض `OTP_EXPIRED` |
| إعادة إرسال (resend) | رمز جديد عشوائي (`452038`) يطابق ما في قاعدة البيانات |
| التحقق النحوي PHP | لا أخطاء |

## تنظيف قاعدة البيانات

حُذف كل البيانات التجريبية من قاعدة `nova`: حذف المستخدم التجريبي الذي أُنشئ أثناء اختبار OTP، وتفريغ جداول `otp_verifications` و`otp_delivery_logs` و`otp_rate_limits` و`sessions`. تبقى الآن الحسابات الأصلية الخمسة (أحمد، سارة، محمد، نور، خالد) وحساب المشرف `admin@nova-messenger.com` والباقات الثلاثة.

## ملاحظات

- في بيئة التطوير الحالية (`APP_ENV=development`) يظهر الرمز في `otp_dev` للرد مع طلب `register`/`login`/`resend` — هذا مقصود لتسهيل التحقق، ويختفي تلقائيًا عند `APP_ENV=production`.
- ملاحظة على تطبيق الويب: ملف `web_app/index.html` يحتوي `<base href="/">` مما يكسر تحميل موارد Flutter عبر الوكيل العام للمسار `/web_app/`. يُنصح بتشغيل التطبيق أو تعديل المسار عند الحاجة.
- تعديلنا على واجهة Flutter (إزالة عرض "123456" من شاشة الدخول وشاشة OTP) فُقد مع إعادة ضبط بيئة العمل، ويجب إعادة تطبيقه عند بناء نسخة Flutter جديدة قبل رفع تحديث التطبيق:
  - `nova_flutter/lib/screens/phone_screen.dart`: إزالة نص "رمز تجريبي: 123456".
  - `nova_flutter/lib/screens/otp_screen.dart`: إظهار الرمز الحقيقي من `otp_dev` في شاشة OTP بوضع التطوير.
  - `nova_flutter/lib/providers/auth_provider.dart`: حفظ `otp_dev` في localStorage بوضع التطوير.
