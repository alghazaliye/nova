# NOVA Messenger - تقرير التحليل وتصميم البنية (Architecture)

## 1. الموجود حالياً
تم فحص الملفات المرفوعة وتتكون من:
1. `index.html`: نموذج (Prototype) لتطبيق المستخدم النهائي (Mobile First, RTL) يحتوي على تصميم الشاشات الأساسية مثل المحادثات، المكالمات، الحالات، جهات الاتصال، والإعدادات. يعتمد على HTML/CSS/JS ببيانات وهمية (Mock Data).
2. `admin.html`: نموذج للوحة تحكم الإدارة (Admin Panel) بتصميم حديث (RTL) يدعم الوضع الفاتح والداكن، ويحتوي على شاشات المحادثات والمكالمات والحالات والإعدادات ببيانات وهمية.
3. `PROJECT_RULES.md`: وثيقة المتطلبات التفصيلية وقواعد المشروع. تحدد كل شيء بدءاً من الجداول المطلوبة في قاعدة البيانات، مروراً بالـ API، وحتى شاشات الـ Android المطلوبة والأمان.

## 2. ما تم إنجازه
- **التصميم الأولي (UI/UX):** تم توفير الهوية البصرية، الألوان، الخطوط، وتجربة المستخدم الأساسية لتطبيق المستخدم النهائي ولوحة الإدارة.
- **تحديد المتطلبات:** تم توثيق المتطلبات التقنية، البنية التحتية، والجداول بشكل دقيق جداً في `PROJECT_RULES.md`.

## 3. الناقص (ما يجب بناؤه)
المشروع الحالي هو مجرد واجهات أمامية ثابتة (Static UI). لإنشاء منصة مراسلة حقيقية، ينقصنا:
- **قاعدة البيانات (Database):** بناء هيكل جداول MySQL (Schema) كما هو محدد.
- **الخلفية البرمجية (Backend):** بناء REST API باستخدام PHP للتعامل مع قاعدة البيانات وتوفير البيانات للتطبيق ولوحة الإدارة.
- **لوحة الإدارة (Admin Panel):** تحويل `admin.html` إلى تطبيق ويب ديناميكي يعتمد على PHP/API.
- **تطبيق الأندرويد (Android App):** بناء تطبيق حقيقي (Native) باستخدام Kotlin بدلاً من الـ Web View، مع ربطه بالـ API والـ Realtime.
- **الاتصال اللحظي (Realtime):** إعداد خادم WebSocket أو خدمة Realtime للتعامل مع الرسائل الفورية.
- **نظام الإشعارات (FCM):** ربط التطبيق والـ Backend بخدمات Firebase Cloud Messaging.

## 4. المشاكل المحتملة أو التحديات
- **الاتصال اللحظي (Realtime) عبر PHP:** PHP ليست الخيار الأمثل لخوادم WebSocket المستمرة. **الحل:** استخدام Ratchet (PHP WebSocket) أو خدمة خارجية مثل Pusher/Soketi، أو Node.js/Socket.io كخدمة جانبية. سيتم بناء البنية الأساسية لدعم الـ WebSockets.
- **تطبيق الأندرويد:** تحويل التصميم من HTML/CSS إلى Jetpack Compose/XML يتطلب دقة للحفاظ على الهوية البصرية.
- **المكالمات (Calls):** يتطلب WebRTC وخادم Signaling (STUN/TURN). سيتم بناء الـ Signaling كخطوة أولى.

## 5. البنية المعمارية (Architecture)

```text
[ Android App (Kotlin/Compose) ] <---(HTTPS/WSS)---> [ Firebase Cloud Messaging (FCM) ]
           |                                                      ^
           |                                                      |
           v                                                      |
[ PHP REST API (Backend) ] ---------------------------------------+
           |
           +---> [ MySQL Database ] (Central Data Store)
           |
           +---> [ WebSocket Server / Realtime Service ] <---> [ Android App ]
           |
           +---> [ Local Storage / S3 ] (Media & Attachments)

[ PHP Admin Panel ] <---(HTTPS)---> [ PHP REST API ] / [ MySQL ]
```

## 6. الجداول المطلوبة (Database Schema)
بناءً على `PROJECT_RULES.md`، سيتم إنشاء الجداول التالية:
- `users`, `user_devices`, `sessions`, `contacts`
- `conversations`, `conversation_members`
- `messages`, `message_reads`, `message_reactions`, `attachments`
- `groups`, `group_settings`
- `calls`, `call_participants`
- `stories`, `story_views`
- `notifications`, `reports`, `blocks`
- `admins`, `roles`, `permissions`, `role_permissions`, `audit_logs`, `app_settings`

## 7. الـ Endpoints الأساسية (API)
- `Auth API`: `/api/v1/auth/login`, `register`, `refresh`, `logout`
- `Users API`: `/api/v1/users/me`, `/api/v1/users/search`
- `Conversations API`: `/api/v1/conversations`, `/api/v1/conversations/{id}/messages`
- `Groups API`, `Stories API`, `Calls API`, `Notifications API`
- `Admin API`: للتحكم في لوحة الإدارة.

## 8. تطبيق الأندرويد (Android)
- **التقنية:** Kotlin, Jetpack Compose, MVVM Architecture.
- **الشبكة:** Retrofit, OkHttp.
- **قاعدة البيانات المحلية:** Room Database لدعم الـ Offline First.
- **الإشعارات:** Firebase Cloud Messaging (FCM).

## 9. لوحة الإدارة (Admin Panel)
- سيتم تحويل `admin.html` إلى بنية PHP مع تقسيم الملفات إلى مكونات قابلة لإعادة الاستخدام (`header.php`, `sidebar.php`...).
- ستتصل بقاعدة البيانات إما مباشرة عبر PDO أو عبر الـ Admin API.

## 10. الأمان (Security)
- Password Hashing (`password_hash`).
- استخدام Prepared Statements لمنع SQL Injection.
- استخدام Token-based Authentication (JWT أو Secure Session Tokens).
- حماية الـ API بـ Rate Limiting.
- التحقق من الملفات المرفوعة (MIME Types).

---
**الخطوة التالية:** إنشاء هيكل قاعدة البيانات (Schema) والـ Migrations في مجلد `database`.
