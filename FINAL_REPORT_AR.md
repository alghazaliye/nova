# تقرير نهائي — Nova Messenger: نظام آخر ظهور + نظام الحالات + إصلاحات الإنتاج

**التاريخ:** 21 أغسطس 2026 | **المستودع:** alghazaliye/nova | **الاستضافة:** https://nova-wn25.onrender.com

## 1. ملخص تنفيذي

اكتملت جميع المهام المطلوبة في هذه الحزمة: **نظام آخر ظهور كامل** (Backend + Database + API + Flutter) مع الخصوصية المطبقة في الخادم، و**نظام الحالات (Stories) الشامل** وفق المواصفات، بالإضافة إلى إصلاحين حرجين في بيئة الإنتاج (مigrations مكررة كانت تمنع إرسال الرسائل، ومقارنة توقيت خاطئة كانت تخفي حالة "متصل الآن"). جميع الاختبارات نجحت (56/56 للحالات، 19/19 لآخر ظهور، 21/21 للاشتراكات، 13/13 للاعتراضات، وALL PASS لتدفق الإنتاج)، وتم الرفع إلى GitHub بنجاح، ونُشر التحديث على Render مع تحقق مباشر على الإنتاج.

## 2. ما تم تنفيذه

### نظام آخر الظهور (Online/Last Seen)

| المكوّن | التنفيذ |
|---|---|
| قاعدة البيانات | `users.is_online` و`users.last_seen` يُحدَّثان عند كل Heartbeat؛ تنظيف جماعي: أي مستخدم لم يُحدَّث خلال 5 دقائق يُعتبَر Offline تلقائيًا |
| API — `POST /heartbeat` | يرفع is_online=1 ويحدّث last_seen ويُنفذ التنظيف الجماعي |
| API — `POST /heartbeat/offline` | يسجل آخر ظهور فعليًا عند الخلفية/فقدان الاتصال/إغلاق التطبيق |
| الخصوصية | `last_seen_visibility` (الجميع/جهات الاتصال/لا أحد) و`online_status` تُطبَّق في **Backend** — الـAPI لا يعيد last_seen/is_online لمن لا يملك صلاحية |
| مهلة الحضور | 300 ثانية: انقطاع النبضات يُحوّل المستخدم إلى Offline تلقائيًا |
| عرض الواجهة | صيغ عربية: متصل الآن / منذ 5 دقائق / منذ ساعة / أمس / منذ يومين / تاريخ محدد |
| Flutter (Web) | `WidgetsBindingObserver` + نبضة كل 45 ثانية + `blur/focus/beforeunload` يستدعون offline |
| Flutter (كل المنصات) | `ConversationController` و`GroupsController` يطبقان الخصوصية في عرض الأعضاء |

### نظام الحالات (Stories) — 49 قسمًا من المواصفات

نظام كامل يشمل: إنشاء حالة (نص/صورة/فيديو) بخصوصية لكل حالة (الجميع/جهات الاتصال/لا أحد) فوق الإعداد العام، عرض ملء الشاشة، تسجيل المشاهدات (مرة واحدة لكل حالة)، الإخفاء التلقائي بعد الانتهاء (24 ساعة)، ردود الفعل السريعة (emoji)، الرد النصي (ينشئ محادثة ورسالة)، إحصاءات المشاهدات والتفاعلات لصاحب الحالة، الإبلاغ عن الحالات، والحذف الإداري مع audit_log وصلاحيات منفصلة (`statuses.view/delete/stats`).

### إصلاحات الإنتاج الحرجة

**الإصلاح الأول — إرسال الرسائل كان يفشل على الإنتاج (SEND_FAILED):** كان يوجد مفتاح مكرر في مصفوفة `migrateMissingColumns` في `database.php` (messages وreports مكرران)، والمفتاح الثاني كان يلغي الأول في PHP، مما منع إضافة عمود `disappear_after` (و`deleted_by` و`story_id` للتقارير) تلقائيًا على قواعد البيانات القديمة في الإنتاج.

**الإصلاح الثاني — حالة "متصل الآن" كانت تختفي على الإنتاج رغم heartbeat حديث:** قاعدة البيانات تحفظ التواريخ بتوقيت **UTC** بينما كانت المقارنة تُنفَّذ بالتوقيت المحلي (Asia/Riyadh)، ما جعل آخر الظهور يُفسَّر على أنه قديم 3 ساعات فتخفي حالة الاتصال. عولجت المقارنة بتفسير القيم UTC صراحة.

## 3. الملفات المعدلة

| المسار | الوصف |
|---|---|
| `backend/controllers/UserController.php` | نظام آخر الظهور: heartbeat، offline، privacy APIs، applyPresencePrivacy |
| `backend/controllers/StoryController.php` | نظام الحالات كاملًا (13 دالة) |
| `backend/controllers/ConversationController.php` | خصوصية آخر الظهور في المحادثات |
| `backend/controllers/GroupsController.php` | خصوصية آخر الظهور في أعضاء المجموعات |
| `backend/config/database.php` | جداول story_reactions/replies + migrations المدمجة |
| `backend/controllers/MessageController.php` | رسالة الخطأ الفعلية في SEND_FAILED للتشخيص |
| `backend/public/index.php` | 13 route جديدة (12 للحالات + offline) |
| `admin/stories.php` | صفحة إدارة الحالات (إحصاءات + حذف إداري) |
| `database/seed_production.sql` | صلاحيات 111–114 للحالات |
| `nova_flutter/.../story_viewer_fullscreen.dart` | عارض الحالات: مشاهدات + تفاعلات + ردود |
| `nova_flutter/.../chats_screen.dart` | نبضات lifecycle كل 45 ثانية |
| `nova_flutter/.../nova_web_state_web.dart` + stub + `auth_provider.dart` | حالة الويب: blur/focus/beforeunload + توكن النافذة |

## 4. نتائج الاختبارات

| الاختبار | النتيجة |
|---|---|
| test_stories.py (نظام الحالات — 56 اختبارًا) | **56/56 PASS** |
| test_presence_prod.py (آخر الظهور — 19 اختبارًا على الإنتاج) | **19/19 PASS** |
| test_prod_flow.py (تدفق الإنتاج الكامل) | **ALL PASS** |
| test_subscriptions.py | 21/21 PASS |
| test_appeals3.py | 13/13 PASS |
| test_bundle_final.py | 19/19 PASS |
| flutter analyze | 0 أخطاء (warnings موجودة مسبقًا، لا regressions) |
| flutter build web --wasm | Built بنجاح، والـbuild مضمّن في الـcommit |
| Smoke على الإنتاج | health ✓، stories routes ✓، web_app/index.html ✓ |

## 5. حالة GitHub والاستضافة

**GitHub:** الرفع نجح بالكامل — آخر commit هو `1081bcf` على فرع `main` (بعد ثلاث commits لهذه الحزمة: `b038566` نظام الحالات وآخر الظهور، `6f7d2fa` إصلاح migrations، `1081bcf` إصلاح توقيت UTC). ملاحظة: حُذف توكن GitHub الشخصي من تاريخ المستودع بالكامل بعد أن حجب GitHub Push Protection عملية الدفع بسبب وجوده في ملف توثيق.

**Render:** التعديلات نشرت تلقائيًا على https://nova-wn25.onrender.com بعد الدفع (build + deploy من main)، وتم التحقق المباشر على الإنتاج: لوحة التحكم، تطبيق الويب، وجميع الـAPIs الجديدة تعمل.

## 6. ملاحظات تشغيلية

قاعدة بيانات الإنتاج تُعاد مع كل deployment (الخطة المجالية بدون Persistent Disk)، لكن نظام الـmigrations التلقائي يضمن توافق قاعدة البيانات الجديدة مع الكود، والإصلاح الأخير يضمن عمل الرسائل حتى على قواعد البيانات القديمة. ملاحظة واحدة: صفحة الاستضافة قد تستيقظ من النوم بعد فترة خمول (Free tier) فيبطئ أول طلب، وهو سلوك طبيعي لمنصة Render المجانية.
