# حالة DB المحلية (2026-08-21)

DELETE /api/v1/admin/users/1 على الخادم المحلي: 401 UNAUTHORIZED.
تشخيص: GET /admin/users/999 → 404 (route غير موجودة) لكن GET /admin/users/999/admin → 401!
الـ404 يعني أن route DELETE الجديد لم يعمل — لكن يجب أن يُطابق regex `/admin/users/(\d+)$`.
الأرجح أن الخادم المحلي يعمل عبر router.php الذي قد يعيد توجيه الطلبات إلى index.php بشكل مختلف، أو أن index.php الذي يُحمَّل قديم (OPcache؟) أو أن route الجديد وُضع بعد return مبكر.
GET admin/users/999/admin أعطى 401 — هذه route موجودة وتستخدم adminId() عبر AuthMiddleware الذي يحتاج sessions JOIN users.
الـUNAUTHORIZED على GET admin/users/999/admin يعني adminId() أو AuthMiddleware فشلا — توكن admin يحمل user_id=1 وrole=admin لكنه ليس في جدول sessions/users.
أيضًا: GET admin/users/999 = 404 → Route غير موجودة؟ لكنها موجودة في index.php! هذا يعني الخادم المحلي لا يستخدم index.php المحدَّث — router.php يمرر إلى ملف آخر (backend/public/index.php موجود لكن ربما php -S يخدم router.php مباشرة دون include index.php).
فحص: router.php — ماذا يفعل؟
