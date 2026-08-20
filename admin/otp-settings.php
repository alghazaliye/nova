<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdminLogin();

define('PAGE_TITLE', 'إعدادات التحقق OTP');
define('PAGE_ACTIVE', 'otp');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$API = '/api/v1/admin/otp';
?>

<div class="content">
  <div class="page-header">
    <h1 class="page-title">إعدادات التحقق OTP</h1>
    <p style="color:var(--muted)">التحكم الكامل في وضع الإرسال، خصائص الرمز، القالب، والحماية من الإساءة.</p>
  </div>

  <div id="globalAlert"></div>

  <div class="grid" style="grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
    <!-- وضع الإرسال -->
    <div class="card">
      <h3 style="margin-bottom:12px;">وضع الإرسال (Delivery Mode)</h3>
      <div class="form-group">
        <label>الوضع الافتراضي لطلبات التسجيل</label>
        <select id="deliveryMode" onchange="updateModeHint()">
          <option value="auto">تلقائي بالكامل (إرسال SMS فقط)</option>
          <option value="manual">يدوي بالكامل (المدير يعرض الرمز)</option>
          <option value="auto_fallback">تلقائي مع تحول يدوي عند فشل التسليم</option>
        </select>
        <small style="color:var(--muted)" id="modeHint">سيتم إرسال الرمز تلقائيًا عبر المزود الافتراضي فقط.</small>
      </div>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="enableFallback" style="width:18px;height:18px;">
          تفعيل التحول التلقائي للمزود الاحتياطي عند فشل المزود الأساسي
        </label>
        <small style="color:var(--muted)">عند فشل المزود الافتراضي يُجرَّب المزود التالي تلقائيًا.</small>
      </div>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="enableManualFallback" style="width:18px;height:18px;">
          السماح بظهور الرمز للمدير (نسخة يدوية) عند فشل كل المزودين
        </label>
        <small style="color:var(--muted)">يظهر الرمز في صفحة «طلبات التسجيل» عندما يفشل كل المزودين.</small>
      </div>
    </div>

    <!-- خصائص الرمز -->
    <div class="card">
      <h3 style="margin-bottom:12px;">خصائص الرمز</h3>
      <div class="form-group"><label>طول الرمز</label>
        <select id="otpLength"><option value="4">4 خانات</option><option value="6">6 خانات</option></select>
      </div>
      <div class="form-group"><label>مدة الصلاحية (دقائق)</label>
        <input type="number" id="expiryMinutes" min="1" max="60">
      </div>
      <div class="form-group"><label>حد المحاولات لكل طلب</label>
        <input type="number" id="maxAttempts" min="1" max="20">
      </div>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="otpRequired" style="width:18px;height:18px;">
          فرض التحقق برمز OTP عند تسجيل الدخول للأرقام المسجلة
        </label>
        <small style="color:var(--muted)">بدون هذا الخيار يمكن للأرقام المسجلة الدخول بكلمة المرور مباشرة.</small>
      </div>
    </div>
  </div>

  <!-- قالب رسالة SMS -->
  <div class="card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">قالب رسالة SMS</h3>
    <p style="color:var(--muted); margin-bottom:10px;">المتغيرات المتاحة: <code>{OTP}</code> <code>{PHONE}</code> <code>{MINUTES}</code> <code>{APP_NAME}</code></p>
    <div class="form-group">
      <textarea id="messageTemplate" rows="3" style="width:100%; direction:rtl; font-size:14px;"></textarea>
    </div>
    <div id="templatePreview" style="margin-top:10px; padding:12px; background:var(--bg-secondary,#f8f9fb); border-radius:8px; font-size:13px; color:var(--muted);">معاينة: —</div>
  </div>

  <!-- الحماية من الإساءة -->
  <div class="card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">الحماية من الإساءة (Rate Limiting)</h3>
    <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap:14px;">
      <div class="form-group"><label>إعادة إرسال كل (ثانية)</label>
        <input type="number" id="resendCooldown" min="10" max="600">
      </div>
      <div class="form-group"><label>حد إعادة الإرسال / ساعة</label>
        <input type="number" id="maxResends" min="1" max="20">
      </div>
      <div class="form-group"><label>طلبات / رقم / ساعة</label>
        <input type="number" id="ratePhone" min="1" max="50">
      </div>
      <div class="form-group"><label>طلبات / IP / ساعة</label>
        <input type="number" id="rateIp" min="5" max="100">
      </div>
    </div>
  </div>

  <div style="display:flex; gap:12px; margin-bottom:24px;">
    <button class="btn btn-primary" onclick="saveSettings()">حفظ الإعدادات</button>
    <button class="btn btn-secondary" onclick="loadSettings()">تحديث</button>
  </div>
</div>

<script>
const API = '<?= $API ?>';
function token() { return localStorage.getItem('adminToken') || ''; }
let settings = {};

function showAlert(msg, kind) {
  const a = document.getElementById('globalAlert');
  a.innerHTML = `<div class="alert alert-${kind === 'error' ? 'danger' : 'success'}">${msg}</div>`;
  setTimeout(() => a.innerHTML = '', 6000);
}
function setSel(id, val) { const el = document.getElementById(id); if (el) el.value = (val === undefined || val === null) ? 'auto' : val; }
function setNum(id, val) { const el = document.getElementById(id); if (el) el.value = (val === undefined || val === null || val === '') ? '' : val; }
function setCb(id, val) { const el = document.getElementById(id); if (el) el.checked = !!Number(val || 0); }

async function loadSettings(){
  try {
    const res = await fetch(API + '/settings', {headers: {'Authorization': 'Bearer ' + token()}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل التحميل');
    settings = j.settings || {};
    setSel('deliveryMode', settings.otp_delivery_mode || 'auto');
    setCb('enableFallback', settings.otp_enable_fallback);
    setCb('enableManualFallback', settings.otp_enable_manual_fallback);
    setSel('otpLength', settings.otp_length || '6');
    setNum('expiryMinutes', settings.otp_expiry_minutes ?? 5);
    setNum('maxAttempts', settings.otp_max_attempts ?? 5);
    document.getElementById('messageTemplate').value = settings.otp_message_template || '';
    setNum('resendCooldown', settings.otp_resend_cooldown_seconds ?? 60);
    setNum('maxResends', settings.otp_max_resends ?? 5);
    setNum('ratePhone', settings.otp_rate_limit_per_phone_per_hour ?? 10);
    setNum('rateIp', settings.otp_rate_limit_per_ip_per_hour ?? 30);
    setCb('otpRequired', settings.otp_required);
    updateModeHint();
    previewTemplate();
    document.getElementById('messageTemplate').addEventListener('input', previewTemplate);
  } catch (e) { showAlert('خطأ في الاتصال: ' + e.message, 'error'); }
}

function updateModeHint(){
  const v = document.getElementById('deliveryMode').value;
  const hints = {
    auto: 'سيتم إرسال الرمز تلقائيًا عبر المزود الافتراضي فقط.',
    manual: 'لن يتم إرسال أي رسائل — المدير يعرض الرمز يدويًا من صفحة طلبات التسجيل.',
    auto_fallback: 'إرسال تلقائي، وعند فشل التسليم يُظهر النظام الرمز للمدير كنسخة يدوية.'
  };
  document.getElementById('modeHint').textContent = hints[v] || '';
}

function previewTemplate(){
  const t = document.getElementById('messageTemplate').value;
  if (!t) { document.getElementById('templatePreview').textContent = 'معاينة: —'; return; }
  const sample = t.replace('{OTP}', '123456').replace('{PHONE}', '+966501234567').replace('{MINUTES}', '5').replace('{APP_NAME}', 'NOVA Messenger');
  document.getElementById('templatePreview').textContent = 'معاينة: ' + sample;
}

async function saveSettings(){
  const data = {
    otp_delivery_mode: document.getElementById('deliveryMode').value,
    otp_enable_fallback: document.getElementById('enableFallback').checked ? '1' : '0',
    otp_enable_manual_fallback: document.getElementById('enableManualFallback').checked ? '1' : '0',
    otp_length: document.getElementById('otpLength').value,
    otp_expiry_minutes: parseInt(document.getElementById('expiryMinutes').value) || 5,
    otp_max_attempts: parseInt(document.getElementById('maxAttempts').value) || 5,
    otp_message_template: document.getElementById('messageTemplate').value,
    otp_resend_cooldown_seconds: parseInt(document.getElementById('resendCooldown').value) || 60,
    otp_max_resends: parseInt(document.getElementById('maxResends').value) || 5,
    otp_rate_limit_per_phone_per_hour: parseInt(document.getElementById('ratePhone').value) || 10,
    otp_rate_limit_per_ip_per_hour: parseInt(document.getElementById('rateIp').value) || 30,
    otp_required: document.getElementById('otpRequired').checked ? '1' : '0'
  };
  try {
    const res = await fetch(API + '/settings', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token()},
      body: JSON.stringify(data)
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل الحفظ');
    showAlert(j.message || 'تم حفظ الإعدادات بنجاح', 'success');
    await new Promise((r) => setTimeout(r, 400));
    await loadSettings();
  } catch (e) { showAlert(e.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', loadSettings);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
