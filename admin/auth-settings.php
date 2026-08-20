<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdminLogin();
define('PAGE_TITLE', 'المصادقة والتسجيل');
define('PAGE_ACTIVE', 'auth-settings');
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
$API = '/api/v1/admin/auth';
?>
<div class="content">
  <div class="page-header">
    <h1 class="page-title">المصادقة والتسجيل</h1>
    <p style="color:var(--muted)">التحكم في طرق التسجيل والدخول وإعدادات OTP للهاتف والبريد بشكل مستقل.</p>
  </div>
  <div id="globalAlert"></div>

  <div class="grid" style="grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
    <!-- طرق التسجيل -->
    <div class="card">
      <h3 style="margin-bottom:12px;">طرق التسجيل</h3>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="regPhone" style="width:18px;height:18px;">
          التسجيل برقم الهاتف
        </label>
        <small style="color:var(--muted)">إيقاف كل طرق التسجيل يعرض رسالة «التسجيل متوقف حاليًا».</small>
      </div>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="regEmail" style="width:18px;height:18px;">
          التسجيل بالبريد الإلكتروني
        </label>
        <small style="color:var(--muted)">يتطلب تفعيل مزود بريد واحد على الأقل.</small>
      </div>
    </div>
    <!-- طرق الدخول -->
    <div class="card">
      <h3 style="margin-bottom:12px;">طرق تسجيل الدخول</h3>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="loginPhone" style="width:18px;height:18px;">
          الدخول برقم الهاتف (OTP)
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="loginEmail" style="width:18px;height:18px;">
          الدخول بالبريد الإلكتروني (كلمة مرور)
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="loginUsername" style="width:18px;height:18px;">
          الدخول باسم المستخدم (كلمة مرور)
        </label>
      </div>
    </div>
  </div>

  <div class="grid" style="grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
    <!-- OTP الهاتف -->
    <div class="card">
      <h3 style="margin-bottom:12px;">OTP الهاتف</h3>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="otpPhoneEnabled" style="width:18px;height:18px;">
          تفعيل OTP الهاتف
        </label>
      </div>
      <div class="form-group"><label>وضع التسليم</label>
        <select id="otpPhoneDelivery"><option value="auto">تلقائي</option><option value="manual">يدوي</option><option value="auto_fallback">تلقائي مع تحول يدوي</option></select>
      </div>
      <div class="form-group"><label>مدة الصلاحية (دقائق)</label><input type="number" id="otpPhoneExpiry" min="1" max="60"></div>
      <div class="form-group"><label>الحد الأقصى للمحاولات</label><input type="number" id="otpPhoneAttempts" min="1" max="100"></div>
      <div class="form-group"><label>الانتظار بين إعادة الإرسال (ثانية)</label><input type="number" id="otpPhoneCooldown" min="0" max="600"></div>
      <div class="form-group"><label>أقصى مرات إعادة الإرسال</label><input type="number" id="otpPhoneResends" min="1" max="50"></div>
    </div>
    <!-- OTP البريد -->
    <div class="card">
      <h3 style="margin-bottom:12px;">OTP البريد الإلكتروني</h3>
      <div class="form-group">
        <label style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" id="otpEmailEnabled" style="width:18px;height:18px;">
          تفعيل OTP البريد
        </label>
      </div>
      <div class="form-group"><label>وضع التسليم</label>
        <select id="otpEmailDelivery"><option value="auto">تلقائي (SMTP/REST)</option><option value="manual">يدوي (المدير يعرض الرمز)</option><option value="auto_fallback">تلقائي مع تحول يدوي</option></select>
      </div>
      <div class="form-group"><label>مدة الصلاحية (دقائق)</label><input type="number" id="otpEmailExpiry" min="1" max="60"></div>
      <div class="form-group"><label>الحد الأقصى للمحاولات</label><input type="number" id="otpEmailAttempts" min="1" max="100"></div>
      <div class="form-group"><label>الانتظار بين إعادة الإرسال (ثانية)</label><input type="number" id="otpEmailCooldown" min="0" max="600"></div>
      <div class="form-group"><label>أقصى مرات إعادة الإرسال</label><input type="number" id="otpEmailResends" min="1" max="50"></div>
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
function showAlert(msg, kind) {
  const a = document.getElementById('globalAlert');
  a.innerHTML = `<div class="alert alert-${kind === 'error' ? 'danger' : 'success'}">${msg}</div>`;
  setTimeout(() => a.innerHTML = '', 6000);
}
function setCb(id, val) { const el = document.getElementById(id); if (el) el.checked = !!Number(val || 0); }
function setSel(id, val) { const el = document.getElementById(id); if (el) el.value = val; }
function setNum(id, val) { const el = document.getElementById(id); if (el) el.value = (val === undefined || val === null || val === '') ? '' : val; }
async function loadSettings() {
  try {
    const r = await fetch(API + '/settings', { headers: { Authorization: 'Bearer ' + token() } });
    const j = await r.json();
    const s = j.settings || {};
    const w = j.warnings || [];
    if (w.length) showAlert('تنبيهات: ' + w.join(' — '), 'success');
    setCb('regPhone', s.auth_phone_registration); setCb('regEmail', s.auth_email_registration);
    setCb('loginPhone', s.auth_phone_login); setCb('loginEmail', s.auth_email_login); setCb('loginUsername', s.auth_username_login);
    setCb('otpPhoneEnabled', s.otp_phone_enabled);
    setSel('otpPhoneDelivery', s.otp_phone_delivery_mode || 'auto');
    setNum('otpPhoneExpiry', s.otp_phone_expiry_minutes ?? 5);
    setNum('otpPhoneAttempts', s.otp_phone_max_attempts ?? 5);
    setNum('otpPhoneCooldown', s.otp_phone_resend_cooldown_seconds ?? 30);
    setNum('otpPhoneResends', s.otp_phone_max_resends ?? 10);
    setCb('otpEmailEnabled', s.otp_email_enabled);
    setSel('otpEmailDelivery', s.otp_email_delivery_mode || 'auto');
    setNum('otpEmailExpiry', s.otp_email_expiry_minutes ?? 5);
    setNum('otpEmailAttempts', s.otp_email_max_attempts ?? 5);
    setNum('otpEmailCooldown', s.otp_email_resend_cooldown_seconds ?? 30);
    setNum('otpEmailResends', s.otp_email_max_resends ?? 10);
  } catch (e) { showAlert('خطأ في الاتصال: ' + e.message, 'error'); }
}
async function saveSettings() {
  const payload = {
    auth_phone_registration: document.getElementById('regPhone').checked ? '1' : '0',
    auth_email_registration: document.getElementById('regEmail').checked ? '1' : '0',
    auth_phone_login: document.getElementById('loginPhone').checked ? '1' : '0',
    auth_email_login: document.getElementById('loginEmail').checked ? '1' : '0',
    auth_username_login: document.getElementById('loginUsername').checked ? '1' : '0',
    otp_phone_enabled: document.getElementById('otpPhoneEnabled').checked ? '1' : '0',
    otp_phone_delivery_mode: document.getElementById('otpPhoneDelivery').value,
    otp_phone_expiry_minutes: parseInt(document.getElementById('otpPhoneExpiry').value) || 5,
    otp_phone_max_attempts: parseInt(document.getElementById('otpPhoneAttempts').value) || 5,
    otp_phone_resend_cooldown_seconds: parseInt(document.getElementById('otpPhoneCooldown').value) || 30,
    otp_phone_max_resends: parseInt(document.getElementById('otpPhoneResends').value) || 10,
    otp_email_enabled: document.getElementById('otpEmailEnabled').checked ? '1' : '0',
    otp_email_delivery_mode: document.getElementById('otpEmailDelivery').value,
    otp_email_expiry_minutes: parseInt(document.getElementById('otpEmailExpiry').value) || 5,
    otp_email_max_attempts: parseInt(document.getElementById('otpEmailAttempts').value) || 5,
    otp_email_resend_cooldown_seconds: parseInt(document.getElementById('otpEmailCooldown').value) || 30,
    otp_email_max_resends: parseInt(document.getElementById('otpEmailResends').value) || 10,
  };
  try {
    const r = await fetch(API + '/settings', {
      method: 'POST',
      headers: { Authorization: 'Bearer ' + token(), 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const j = await r.json();
    if (!j.success) { showAlert(j.message || 'فشل الحفظ', 'error'); return; }
    showAlert('تم حفظ الإعدادات بنجاح', 'success');
    await new Promise((res) => setTimeout(res, 400));
    await loadSettings();
  } catch (e) { showAlert('خطأ في الاتصال: ' + e.message, 'error'); }
}
document.addEventListener('DOMContentLoaded', loadSettings);
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
