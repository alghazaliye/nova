# طلبات جديدة من المستخدم (19-أغسطس)

## 1) Offline-First (pasted_content_2.txt — 961 سطر)
مطلوب تحويل تطبيق Flutter إلى Offline-First كامل:
- **Local Database (Drift + SQLite)**: جداول chats/messages/contacts محليًا (الهيكل الكامل في الملف)
- **Message Status**: local→sending→sent→delivered→read + failed + pending_sync
- **Outbox/Sync Queue** لعمليات: SEND_MESSAGE, UPLOAD_MEDIA, EDIT_MESSAGE, DELETE_MESSAGE, MARK_DELIVERED, MARK_READ, UPDATE_PROFILE
- **Retry + Exponential Backoff** قابل للتعديل (2s, 5s, 10s, 30s...)
- **Network Detection**: Online/Offline/Internet-ok-but-server-down + Health check `GET /health`
- **Media Storage محلي**: media/{images,videos,audio,documents,thumbnails} + cache + أولوية الملف المحلي قبل URL
- **Media Download Strategy**: تلقائي/WiFi-only/يدوي/بيانات الهاتف (إعدادات)
- **Thumbnails** للفيديو
- **Incremental Sync** بمؤشر last_sync_cursor + Pagination 50
- **Launch**: عرض Local DB فورًا ثم sync في الخلفية
- **Idempotency**: client_message_id (UUID) + server_message_id mapping لمنع التكرار
- **Conflict Resolution**: server timestamp + version
- **Multi-device**: السيرفر مصدر الحقيقة
- **حماية**: لا توكنات نص عادي (flutter_secure_storage)، مسح بيانات عند logout
- **صفحة إدارة التخزين**: مساحات + مسح cache
- **UI مؤشرات**: غير متصل / جارٍ المزامنة / pending / failed + إعادة إرسال
- **APIs جديدة/موجودة**: GET /sync, POST /messages/send (مع client_message_id), batch, ack, read, edit, delete, GET /health, GET /media/{id}
- **Sync response**: {cursor, messages, chats, contacts, deletions, updates}
- **قواعد**: لا كسر JWT/FCM/WebRTC/المحادثات الحالية، RTL/عربي، السيرفر مصدر النسخ الاحتياطي
- معيار قبول: 38 اختبارًا محددًا في الملف

## 2) Authentication Phone+Email (pasted_content_3.txt — 712 سطر)
- **طرق تسجيل**: phone ON/OFF + email ON/OFF مستقلة (إيقاف الاثنين = رسالة "التسجيل متوقف")
- **طرق تسجيل دخول مستقلة**: phone/email/username (كل واحد ON/OFF منفصل)
- **Phone OTP مستقل**: enabled/expiry/attempts/resend/max_resends/delivery_mode
- **Email OTP مستقل** (غير معتمد على Phone OTP): نفس الحقول + SMTP/REST/email provider خارجي (host/port/encryption/user/pass/API key/from)
- **مزودو SMS** (موجودون بالفعل — تطويرهم) + **مزودو Email جدد** (smtp/rest)
- Fallback مزود أساسي→احتياطي→يدوي (manual fallback ON يعرض OTP للإدارة)
- **الحساب الواحد**: user {id, username, email, phone, password_hash, email_verified, phone_verified, status}
- **إضافة هاتف/بريد لاحقًا** من إعدادات الحساب بعد OTP
- **Login UI ديناميكي** حسب الإعدادات (placeholder يتغير)
- **منع التناقض**: تحذير phone_registration ON + phone_otp OFF (يسمح بالحفظ لكن مع تحذير)
- **صفحة إدارة موحدة** "المصادقة والتسجيل": طرق التسجيل ← طرق الدخول ← Phone OTP ← Email OTP ← SMS Providers ← Email Providers
- **صلاحيات RBAC**: auth.settings.view/update + otp.providers.* + registration.* (موجودة جزئيًا)
- **Audit Log** لكل تغيير
- **صفحة طلبات التسجيل** في لوحة التحكم (عرض/نسخ/إنشاء OTP/إلغاء) — OTP-registrations موجود، نضيف type (phone/email) وprovider

## حالة OTP الحالية (من العمل المنجز):
- نظام OTP للهاتف مكتمل: otp_providers, otp_verifications, otp_delivery_logs
- 3 أوضاع auto/manual/auto_fallback تعمل
- AdminOtpController: providers/registrations/stats/settings endpoints
- صفحات admin: otp-providers.php, otp-registrations.php, otp-settings.php (settings API GET/POST يعمل)
- timezone fix: toUnixTs helper في OtpService (MySQL UTC vs Asia/Riyadh)
- JWT bootstrap في footer.php admin يعمل
- TODO: settingsUpdate يقبل otp_required? (الصفحة ترسله لكن allowed keys في controller لا تشمل otp_required — إضافة)

## الأولوية المقترحة:
1. إكمال OTP الحالي (settings + رفع v5.1.0 + Docker + GitHub + Render) — المستخدم طلبها سابقًا
2. ثم Authentication Phone+Email (يمتد على نظام OTP الحالي)
3. ثم Offline-First (أكبر مهمة — Flutter Drift + Sync API)
