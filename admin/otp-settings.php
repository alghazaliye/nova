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
    <p style="color:var(--muted)">التحكم الكامل في وضع الإرسال، القالب، المدة، والحماية من الإساءة.</p>
  </div>

  <div id="globalAlert"></div>

  <div class="grid" style="grid-template-columns: 1fr 1fr; gap:20px;">
    <!-- وضع الإرسال -->
    <div class="card">
      <h3 style="margin-bottom:12px;">وضع الإرسال (Delivery Mode)</h3>
      <div class="form-group">
        <label>الوضع الافتراضي لطلبات التسجيل</label>
        <select id="deliveryMode" onchange="updateModeHint()">
          <option value="auto">تلقائي بالكامل (مرسل SMS فقط)</option>
          <option value="manual">يدوي بالكامل (المدير يعرض الرمز)</option>
          <option value="auto_fallback">تلقائي مع تحول يدوي عند فشل التسليم</option>
        </select>
        <small id="modeHint" style="color:var(--muted)">سيتم إرسال الرمز تلقائيًا عبر المزود الافتراضي فقط.</small>
      </div>

      <div class="form-group">
        <label style="display:flex; align-items:center; gap:8px; margin-top:10px;">
          <input type="checkbox" id="enableFallback" style="width:18px;height:18px;">
          تفعيل التحول التلقائي للمزود الاحتياطي عند فشل المزود الأساسي
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" id="enableManualFallback" style="width:18px;height:18px;">
          السماح بظهور الرمز للمدير (نسخة احتياطية يدوية) عند فشل كل المزودين
        </label>
      </div>
    </div>

    <!-- رمز OTP -->
    <div class="card">
      <h3 style="margin-bottom:12px;">خصائص الرمز</h3>
      <div class="form-group">
        <label>طول الرمز</label>
        <select id="otpLength"><option value="4">4 خانات</option><option value="6">6 خانات</option></select>
      </div>
      <div class="form-group">
        <label>مدة الصلاحية (دقائق)</label>
        <input type="number" id="expiryMinutes" min="1" max="60" style="width:100%;">
      </div>
      <div class="form-group">
        <label>حد المحاولات لكل طلب</label>
        <input type="number" id="maxAttempts" min="1" max="20" style="width:100%;">
      </div>
    </div>
  </div>

  <!-- القالب -->
  <div class="card" style="margin-top:20px;">
    <h3 style="margin-bottom:12px;">قالب رسالة SMS</h3>
    <p style="color:var(--muted); margin-bottom:10px;">المتغيرات المتاحة: <code>{OTP}</code> <code>{PHONE}</code> <code>{MINUTES}</code> <code>{APP_NAME}</code></p>
    <textarea id="messageTemplate" rows="3" style="width:100%; direction:rtl; font-size:14px;"></textarea>
    <div id="templatePreview" style="margin-top:10px; padding:10px; background:var(--bg-secondary,#f8f9fb); border-radius:8px; font-size:13px;"></div>
  </div>

  <!-- الحماية -->
  <div class="card" style="margin-top:20px;">
    <h3 style="margin-bottom:12px;">الحماية من الإساءة (Rate Limiting)</h3>
    <div class="grid" style="grid-template-columns: 1fr 1fr 1fr 1fr; gap:14px;">
      <div class="form-group">
        <label>إعادة إرسال كل (ثانية)</label>
        <input type="number" id="resendCooldown" min="10" max="600" style="width:100%;">
      </div>
      <div class="form-group">
        <label>حد إعادة الإرسال / ساعة</label>
        <input type="number" id="maxResends" min="1" max="20" style="width:100%;">
      </div>
      <div class="form-group">
        <label>طلبات / رقم / ساعة</label>
        <input type="number" id="ratePhone" min="1" max="50" style="width:100%;">
      </div>
      <div class="form-group">
        <label>طلبات / IP / ساعة</label>
        <input type="number" id="rateIp" min="5" max="100" style="width:100%;">
      </div>
    </div>
    <div class="form-group" style="margin-top:10px;">
      <label style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" id="otpRequired" style="width:18px;height:18px;">
        فرض التحقق برمز OTP عند تسجيل الدخول للأرقام المسجلة
      </label>
    </div>
  </div>

  <div style="margin-top:20px; display:flex; gap:10px;">
    <button class="btn" onclick="saveSettings()">💾 حفظ الإعدادات</button>
    <button class="btn" style="background:var(--bg-secondary,#f0f2f5); color:var(--text,#222);" onclick="location.reload()">↺ إلغاء</button>
  </div>
</div>

<script>
const API = '<?php echo $API; ?>';
let settings = {};

function showMsg(msg, ok){
  const el = document.getElementById('globalAlert');
  el.innerHTML = '<div class="alert" style="background:' + (ok ? 'rgba(18,183,106,.1)' : 'rgba(240,68,56,.1)') + '; color:' + (ok ? 'var(--good,#12b76a)' : 'var(--bad,#f04438)') + '; padding:12px 16px; border-radius:8px;">' + msg + '</div>';
  setTimeout(() => el.innerHTML = '', 4000);
}

async function loadSettings(){
  try {
    const res = await fetch(API + '/settings', {headers: {'Authorization': 'Bearer ' + (localStorage.getItem('adminToken') || '')}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل التحميل');
    settings = j.settings || {};
    document.getElementById('deliveryMode').value = settings.otp_delivery_mode || 'auto';
    document.getElementById('enableFallback').checked = settings.otp_enable_fallback === '1';
    document.getElementById('enableManualFallback').checked = settings.otp_enable_manual_fallback === '1';
    document.getElementById('otpLength').value = settings.otp_length || '6';
    document.getElementById('expiryMinutes').value = settings.otp_expiry_minutes || '5';
    document.getElementById('maxAttempts').value = settings.otp_max_attempts || '5';
    document.getElementById('messageTemplate').value = settings.otp_message_template || '';
    document.getElementById('resendCooldown').value = settings.otp_resend_cooldown_seconds || '60';
    document.getElementById('maxResends').value = settings.otp_max_resends || '5';
    document.getElementById('ratePhone').value = settings.otp_rate_limit_per_phone_per_hour || '10';
    document.getElementById('rateIp').value = settings.otp_rate_limit_per_ip_per_hour || '30';
    document.getElementById('otpRequired').checked = settings.otp_required === '1';
    updateModeHint();
    previewTemplate();
    document.getElementById('messageTemplate').addEventListener('input', previewTemplate);
  } catch (e) { alert(e.message); }
}

function updateModeHint(){
  const v = document.getElementById('deliveryMode').value;
  const hints = {
    auto: 'سيتم إرسال الرمز تلقائيًا عبر المزود الافتراضي فقط.',
    manual: 'لن يتم إرسال أي رسائل — المدير يعرض الرمز يدويًا لكل طلب.',
    auto_fallback: 'إرسال تلقائي، وعند فشل التسليم يُظهر النظام الرمز للمدير كنسخة يدوية.'
  };
  document.getElementById('modeHint').textContent = hints[v] || '';
}

function previewTemplate(){
  const t = document.getElementById('messageTemplate').value;
  const sample = t.replace('{OTP}', '123456').replace('{PHONE}', '+966501234567').replace('{MINUTES}', '5').replace('{APP_NAME}', 'NOVA Messenger');
  document.getElementById('templatePreview').textContent = 'معاينة: ' + sample;
}

async function saveSettings(){
  const data = {
    otp_delivery_mode: document.getElementById('deliveryMode').value,
    otp_enable_fallback: document.getElementById('enableFallback').checked ? '1' : '0',
    otp_enable_manual_fallback: document.getElementById('enableManualFallback').checked ? '1' : '0',
    otp_length: document.getElementById('otpLength').value,
    otp_expiry_minutes: document.getElementById('expiryMinutes').value,
    otp_max_attempts: document.getElementById('maxAttempts').value,
    otp_message_template: document.getElementById('messageTemplate').value,
    otp_resend_cooldown_seconds: document.getElementById('resendCooldown').value,
    otp_max_resends: document.getElementById('maxResends').value,
    otp_rate_limit_per_phone_per_hour: document.getElementById('ratePhone').value,
    otp_rate_limit_per_ip_per_hour: document.getElementById('rateIp').value,
    otp_required: document.getElementById('otpRequired').checked ? '1' : '0'
  };
  try {
    const res = await fetch(API + '/settings', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + (localStorage.getItem('adminToken') || '')},
      body: JSON.stringify(data)
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل الحفظ');
    showMsg(j.message || 'تم حفظ الإعدادات بنجاح', true);
    await loadSettings();
  } catch (e) { showMsg(e.message, false); }
}

loadSettings();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
