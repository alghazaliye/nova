# مشكلة DB_CONNECTION_ERROR — متابعة (2026-08-16)

## الأعراض:
- بعد تثبيت php8.3-mysql وإضافة تحميل .env إلى router.php: API يرجع DB_CONNECTION_ERROR
- verify-otp أرجع استجابة بدون 'data' (يجب فحص رسالة الخطأ)
- sudo mysql يعمل، MariaDB نشط، DB nova موجودة

## التشخيص المطلوب:
1. فحص /tmp/php_server.log لخطأ PDO الفعلي
2. التحقق: هل index.php (المطلوب داخل router.php) يستخدم $_ENV نفسه؟ نعم لأن require في نفس العملية
3. ربما المشكلة: router.php يحفظ $_ENV في بداية الطلب، لكن PHP built-in server يشارك العملية — يجب التأكد عدم تجاوز
4. فحص database.php: PDO::MYSQL_ATTR_INIT_COMMAND (كان الخطأ قبل تثبيت mysql extension — تم إصلاح)

## خطوات الاختبار:
```bash
tail -5 /tmp/php_server.log
curl -s localhost:8080/api/v1/auth/verify-otp -X POST -H 'Content-Type: application/json' -d '{"phone":"+966501234567","otp":"123456"}'
```

## Tokens (بعد إصلاح):
- أحمد: verify-otp +966501234567/123456
- سارة: +966502345678/123456
