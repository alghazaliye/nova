# نتائج اختبار v5.0.8 — 18 أغسطس 2026

## 1) إرسال صورة/فيديو ✅
| الاختبار | النتيجة |
|---|---|
| أحمد يرسل صورة → سارة تراها مع الملف | ✅ (file_id=23، id=28، سارة ترى 15 صورة مع ملفات) |
| سارة ترسل فيديو → أحمد يراه مع الملف | ✅ (file_id=26، id=34) |
| ملاحظة: رفع "الفيديو" يقبل mp4 لكن الملف التجريبي jpg | يعمل |

## 2) الحذف لدي / لدى الطرفين ✅
| الاختبار | النتيجة |
|---|---|
| حذف لدي (DELETE /api/v1/messages/{id}) | ✅ "تم حذف الرسالة لديك" 200 — الطرف الآخر لا يزال يراها |
| حذف لدى الطرفين (for_all: true) | ✅ "تم حذف الرسالة لدى الجميع" — سارة تراها deleted_at (2026-08-18 21:12:01) |
| شخص آخر يحاول حذف رسالة المُرسل لدى الجميع | ✅ 403 محمي "لا يمكنك حذف رسالة شخص آخر لدى الجميع" |

## 3) حماية تصوير الشاشة — FLAG_SECURE ✅ (أُضيف)
- MainActivity.kt: `window.setFlags(WindowManager.LayoutParams.FLAG_SECURE, FLAG_SECURE)` في onCreate
- مفعّل في APK الجديد (يتطلب إعادة بناء APK)

## 4) المكالمات ✅
| الاختبار | النتيجة |
|---|---|
| مكالمة صوتية أحمد→سارة | ✅ initiate 201 (call_id=34)، answer 200، status=answered، end 200 |
| مكالمة فيديو سارة→أحمد | ✅ initiate 201 (call_id=35)، answer 200، status=answered، end 200 |
| auto-cleanup للمكالمات stale | ✅ CallController (v5.0.7) |

## 5) الحالات ✅
| الاختبار | النتيجة |
|---|---|
| رفع قصة صورة (أحمد) | ✅ 201 id=15 |
| رفع قصة فيديو/صورة ثانية | ✅ 201 id=16 |
| سارة ترى حالات أحمد | ✅ 8 حالات |
| تسجيل المشاهدة | ✅ view 200 "تم تسجيل المشاهدة" — view_count أصبح 1، viewed_by_me=1 |
| file_url صحيح (/media/attachments/...) مع file_mime | ✅ |

## بنية stories API الصحيحة:
- GET /api/v1/stories → data[]: {id, user_id, type, file_id, view_count, viewed_by_me, file_url, file_mime, user_name, user_avatar, expires_at}
- POST /api/v1/stories/{user_id}/upload → multipart "file"
- POST /api/v1/stories/{id}/view
- DELETE /api/v1/messages/{id} مع {for_all: true}

## الملاحظات:
- المسار القديم /conversations/{id}/messages/{id} غير موجود — الصحيح /messages/{id}
- media_type/views_count غير موجودة بالاسم؛ الصحيح type/view_count
- بناء APK: تم (21:09) قبل FLAG_SECURE — يحتاج إعادة بناء
