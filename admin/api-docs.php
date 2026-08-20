<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$pageTitle = 'ملفات API';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<style>
  .doc-section { margin-bottom: 22px; }
  .doc-section h3 { margin-bottom: 8px; }
  .api-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  .api-table th, .api-table td { border: 1px solid var(--line); padding: 7px 10px; text-align: right; }
  .api-table th { background: var(--surface2); font-size: 12.5px; }
  .meth { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:800; font-family:monospace; }
  .m-get { background: rgba(59,130,246,.12); color:#2563eb; }
  .m-post { background: rgba(34,197,94,.12); color:#16a34a; }
  .m-put { background: rgba(245,158,11,.12); color:#d97706; }
  .m-del { background: rgba(239,68,68,.12); color:#dc2626; }
  .endpoint { font-family: monospace; font-size: 12.5px; direction: ltr; display:block; text-align:left; }
  .copybox { background: var(--surface2); border:1px solid var(--line); border-radius: 12px; padding: 14px; font-family: monospace; font-size: 12.5px; direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-all; }
</style>
<div class="pagehead"><div><h2>ملفات واجهات برمجة التطبيقات (API)</h2><p>توثيق شامل لجميع نقاط الاتصال المتاحة في نظام NOVA Messenger.</p></div></div>

<div class="stats">
  <div class="stat"><div class="ico">🌐</div><div><b style="font-family:monospace;font-size:12px">/nova/backend/public/api/v1</b><small>المسار الأساسي لجميع الطلبات</small></div></div>
  <div class="stat"><div class="ico">🔑</div><div><b>Bearer Token</b><small>يُمرَّر في الترويسة Authorization</small></div></div>
  <div class="stat"><div class="ico">📡</div><div><b>JSON</b><small>جميع الطلبات والاستجابات بصيغة JSON</small></div></div>
</div>

<div class="doc-section">
  <div class="card panel">
    <h3>1️⃣ المصادقة والجلسات (/auth)</h3>
    <table class="api-table">
      <thead><tr><th>الطريقة</th><th>النقطة</th><th>الوصف</th></tr></thead>
      <tbody>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/auth/register</span></td><td>تسجيل مستخدم جديد (رقم الجوال)</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/auth/login</span></td><td>تسجيل الدخول — returns token + refresh_token</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/auth/verify-otp</span></td><td>التحقق من رمز OTP</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/auth/logout</span></td><td>تسجيل الخروج (إبطال الجلسة)</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/auth/me</span></td><td>بيانات المستخدم الحالي + الباقة + الحالة</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/auth/refresh</span></td><td>تجديد التوكن</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="doc-section">
  <div class="card panel">
    <h3>2️⃣ المستخدمون (/users)</h3>
    <table class="api-table">
      <thead><tr><th>الطريقة</th><th>النقطة</th><th>الوصف</th></tr></thead>
      <tbody>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/users/me</span></td><td>الملف الشخصي</td></tr>
        <tr><td><span class="meth m-put">PUT</span></td><td><span class="endpoint">/users/me</span></td><td>تحديث الاسم والبريد</td></tr>
        <tr><td><span class="meth m-get">PUT</span></td><td><span class="endpoint">/users/settings</span></td><td>إعدادات المستخدم (الاختفاء الافتراضي)</td></tr>
        <tr><td><span class="meth m-put">PUT</span></td><td><span class="endpoint">/users/settings</span></td><td>{disappear_default: 0|3600|86400|604800|-1}</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/users/avatar</span></td><td>رفع صورة شخصية (multipart)</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/users/search?q=</span></td><td>البحث عن مستخدمين</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/users/{id}/block</span></td><td>حظر مستخدم</td></tr>
        <tr><td><span class="meth m-del">DELETE</span></td><td><span class="endpoint">/users/{id}/block</span></td><td>فك الحظر</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/privacy</span></td><td>إعدادات الخصوصية</td></tr>
        <tr><td><span class="meth m-put">PUT</span></td><td><span class="endpoint">/privacy</span></td><td>تحديث الخصوصية</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="doc-section">
  <div class="card panel">
    <h3>3️⃣ المحادثات والرسائل (/conversations, /messages)</h3>
    <table class="api-table">
      <thead><tr><th>الطريقة</th><th>النقطة</th><th>الوصف</th></tr></thead>
      <tbody>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/conversations</span></td><td>قائمة المحادثات</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/conversations</span></td><td>إنشاء محادثة {user_ids}</td></tr>
        <tr><td><span class="meth m-put">PUT</span></td><td><span class="endpoint">/conversations/{id}</span></td><td>إعداد الاختفاء {disappear_after}</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/conversations/{id}/mute</span></td><td>كتم</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/conversations/{id}/pin</span></td><td>تثبيت</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/conversations/{id}/messages</span></td><td>الرسائل (limit, before_id)</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/conversations/{id}/messages</span></td><td>إرسال نص/وسائط (multipart)</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/messages/voice</span></td><td>إرسال رسالة صوتية (multipart)</td></tr>
        <tr><td><span class="meth m-put">PUT</span></td><td><span class="endpoint">/messages/{id}</span></td><td>تعديل (ضمن المدة المسموحة)</td></tr>
        <tr><td><span class="meth m-del">DELETE</span></td><td><span class="endpoint">/messages/{id}</span></td><td>حذف {for_all: true|false}</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/messages/{id}/read</span></td><td>تمييز مقروء</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/messages/{id}/reaction</span></td><td>تفاعل {emoji}</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="doc-section">
  <div class="card panel">
    <h3>4️⃣ المكالمات (/calls)</h3>
    <table class="api-table">
      <thead><tr><th>الطريقة</th><th>النقطة</th><th>الوصف</th></tr></thead>
      <tbody>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/calls</span></td><td>بدء مكالمة {callee_id, call_type}</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/calls/incoming</span></td><td>المكالمات الواردة</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/calls/{id}/answer</span></td><td>الرد على المكالمة</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/calls/{id}/reject</span></td><td>رفض المكالمة (يوقف الرنين فورًا)</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/calls/{id}/end</span></td><td>إنهاء المكالمة</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/calls/{id}/signal</span></td><td>إشارة WebRTC {signal_type, payload}</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/calls/{id}/signals</span></td><td>جلب الإشارات (polling)</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="doc-section">
  <div class="card panel">
    <h3>5️⃣ القصص (/stories) والأجهزة (/devices) والإشعارات (/notifications)</h3>
    <table class="api-table">
      <thead><tr><th>الطريقة</th><th>النقطة</th><th>الوصف</th></tr></thead>
      <tbody>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/stories</span></td><td>قصص جهات الاتصال</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/stories</span></td><td>نشر قصة (multipart)</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/stories/{id}/view</span></td><td>مشاهدة قصة</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/devices/register</span></td><td>تسجيل جهاز (بصمة + باركود)</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/devices</span></td><td>أجهزة المستخدم</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/devices/fcm-token</span></td><td>حفظ توكن FCM للإشعارات</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/notifications</span></td><td>إشعارات المستخدم</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/notifications/read-all</span></td><td>تمييز الكل مقروء</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/health</span></td><td>فحص صحة النظام</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/plans</span></td><td>الباقات العامة (public)</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="doc-section">
  <div class="card panel">
    <h3>6️⃣ نقاط الإدارة (/admin) — للمدراء فقط</h3>
    <table class="api-table">
      <thead><tr><th>الطريقة</th><th>النقطة</th><th>الوصف</th></tr></thead>
      <tbody>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/admin/plans</span></td><td>الباقات</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/admin/plans</span></td><td>إضافة باقة</td></tr>
        <tr><td><span class="meth m-put">PUT</span></td><td><span class="endpoint">/admin/plans/{id}</span></td><td>تعديل باقة</td></tr>
        <tr><td><span class="meth m-del">DELETE</span></td><td><span class="endpoint">/admin/plans/{id}</span></td><td>حذف باقة</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/admin/users/{id}/verify</span></td><td>توثيق مستخدم (علامة زرقاء)</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/admin/users/{id}/ban</span></td><td>حظر مستخدم</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/admin/users/{id}/unban</span></td><td>فك الحظر</td></tr>
        <tr><td><span class="meth m-post">POST</span></td><td><span class="endpoint">/admin/users/{id}/subscribe</span></td><td>تفعيل اشتراك</td></tr>
        <tr><td><span class="meth m-get">GET</span></td><td><span class="endpoint">/admin/devices</span></td><td>كل الأجهزة المسجلة</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="doc-section">
  <div class="card panel">
    <h3>📋 مثال اتصال</h3>
    <div class="copybox">curl -X POST "https://<?= $_SERVER['HTTP_HOST'] ?>/nova/backend/public/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+966500000000","otp":"654321"}'

curl -X GET "https://<?= $_SERVER['HTTP_HOST'] ?>/nova/backend/public/api/v1/conversations" \
  -H "Authorization: Bearer YOUR_TOKEN"
    </div>
  </div>
</div>

</div>
</main>
<?php include __DIR__ . '/includes/footer.php';
