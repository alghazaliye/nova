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
<style>
  /* ===== تحسينات صفحة إعدادات OTP ===== */
  .auth-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 20px;
    margin-bottom: 20px;
  }
  .auth-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 14px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--line);
  }
  .auth-card-header .icon {
    width: 42px; height: 42px; flex: 0 0 42px;
    border-radius: 13px;
    background: linear-gradient(135deg, #5b5ce2, #7c3aed);
    color: #fff;
    display: grid; place-items: center;
    font-size: 20px;
    box-shadow: 0 8px 18px rgba(91, 92, 226, .25);
  }
  .auth-card-header h3 { margin: 0; font-size: 16px; font-weight: 800; }
  .auth-card-header small { display: block; color: var(--muted); font-size: 12.5px; margin-top: 2px; }
  .form-group { margin-bottom: 14px; }
  .form-group:last-child { margin-bottom: 0; }
  .form-group > label {
    display: block;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 6px;
    color: var(--text);
  }
  /* مبدّل تفعيل أنيق */
  .auth-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--surface2);
    border: 1.5px solid var(--line);
    border-radius: 12px;
    padding: 10px 12px;
    margin-bottom: 10px;
    transition: 0.2s;
    cursor: pointer;
  }
  .auth-toggle:hover { border-color: #5b5ce2; }
  .auth-toggle input[type="checkbox"] {
    appearance: none; -webkit-appearance: none;
    width: 22px; height: 22px; flex: 0 0 22px;
    border: 2px solid #c7cbd6; border-radius: 7px;
    background: var(--surface);
    position: relative;
    cursor: pointer;
    transition: 0.2s;
    margin: 0;
  }
  .auth-toggle input[type="checkbox"]:checked {
    background: linear-gradient(135deg, #5b5ce2, #7c3aed);
    border-color: #5b5ce2;
  }
  .auth-toggle input[type="checkbox"]:checked::after {
    content: "";
    position: absolute;
    right: 5px; top: 1px;
    width: 7px; height: 12px;
    border: solid #fff; border-width: 0 2.5px 2.5px 0;
    transform: rotate(40deg);
  }
  .auth-toggle .toggle-label { font-weight: 700; font-size: 13.5px; line-height: 1.5; }
  .auth-toggle .toggle-hint { color: var(--muted); font-size: 11.5px; line-height: 1.5; }
  /* حقول الإدخال */
  .auth-input, .auth-select {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid var(--line);
    border-radius: 11px;
    background: var(--surface);
    color: var(--text);
    font-size: 13.5px;
    transition: 0.2s;
    font-family: inherit;
  }
  .auth-input:focus, .auth-select:focus {
    outline: none;
    border-color: #5b5ce2;
    box-shadow: 0 0 0 4px rgba(91, 92, 226, .15);
  }
  .auth-hint { display: block; color: var(--muted); font-size: 11.5px; margin-top: 5px; line-height: 1.6; }
  /* شبكة الحقول */
  .auth-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 760px) { .auth-grid-2 { grid-template-columns: 1fr; } }
  /* الأزرار */
  .auth-actions {
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
    margin: 6px 0 28px;
  }
  .btn-auth-primary {
    display: inline-flex; align-items: center; gap: 9px;
    padding: 12px 26px;
    border-radius: 12px; border: 0;
    background: linear-gradient(135deg, #5b5ce2, #7c3aed);
    color: #fff;
    font-weight: 800; font-size: 14px;
    box-shadow: 0 10px 24px rgba(91, 92, 226, .32);
    transition: 0.2s;
  }
  .btn-auth-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(91, 92, 226, .4); }
  .btn-auth-primary:active { transform: translateY(0); }
  .btn-auth-secondary {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 22px;
    border-radius: 12px;
    border: 1.5px solid var(--line);
    background: var(--surface);
    color: var(--text);
    font-weight: 700; font-size: 14px;
    transition: 0.2s;
  }
  .btn-auth-secondary:hover { border-color: #5b5ce2; color: #5b5ce2; background: var(--surface2); }
  .btn-auth-primary:disabled, .btn-auth-secondary:disabled { opacity: .55; cursor: not-allowed; transform: none; }
  /* معاينة القالب */
  .tpl-preview {
    background: var(--surface2);
    border: 1.5px dashed var(--line);
    border-radius: 11px;
    padding: 10px 12px;
    font-size: 12.5px;
    color: var(--text);
    direction: ltr;
    text-align: left;
    line-height: 1.7;
  }
</style>
<div class="content">
  <div class="page-header">
    <h1 class="page-title">إعدادات التحقق OTP</h1>
    <p style="color:var(--muted)">التحكم الكامل في وضع الإرسال، خصائص الرمز، القالب، والحماية من الإساءة.</p>
  </div>

  <div id="globalAlert"></div>

  <!-- بطاقة وضع الإرسال -->
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="icon">📤</div>
      <div>
        <h3>وضع الإرسال</h3>
        <small>كيف يصل رمز التحقق للمستخدم: تلقائيًا عبر المزود، أو يدويًا من المدير.</small>
      </div>
    </div>
    <div class="form-group">
      <label>الوضع الافتراضي لطلبات التسجيل</label>
      <select id="deliveryMode" class="auth-select" onchange="updateModeHint()">
        <option value="auto">تلقائي بالكامل (إرسال SMS فقط)</option>
        <option value="manual">يدوي بالكامل (المدير يعرض الرمز)</option>
        <option value="auto_fallback">تلقائي مع تحول يدوي عند فشل التسليم</option>
      </select>
      <small class="auth-hint" id="modeHint">سيتم إرسال الرمز تلقائيًا عبر المزود الافتراضي فقط.</small>
    </div>
    <label class="auth-toggle">
      <input type="checkbox" id="enableFallback">
      <div>
        <div class="toggle-label">تفعيل المزود الاحتياطي</div>
        <div class="toggle-hint">عند فشل المزود الافتراضي يُجرَّب المزود التالي تلقائيًا.</div>
      </div>
    </label>
    <label class="auth-toggle">
      <input type="checkbox" id="enableManualFallback">
      <div>
        <div class="toggle-label">السماح بالرمز اليدوي للمدير</div>
        <div class="toggle-hint">يظهر الرمز في صفحة «طلبات التسجيل» عندما يفشل كل المزودين.</div>
      </div>
    </label>
  </div>

  <!-- بطاقة خصائص الرمز -->
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="icon">🔑</div>
      <div>
        <h3>خصائص الرمز</h3>
        <small>طول الرمز ومدته وحد المحاولات، وفرض التحقق عند الدخول.</small>
      </div>
    </div>
    <div class="auth-grid-2">
      <div class="form-group"><label>طول الرمز</label>
        <select id="otpLength" class="auth-select"><option value="4">4 خانات</option><option value="6">6 خانات</option></select>
      </div>
      <div class="form-group"><label>مدة الصلاحية (دقائق)</label>
        <input type="number" id="expiryMinutes" class="auth-input" min="1" max="60">
      </div>
      <div class="form-group"><label>حد المحاولات لكل طلب</label>
        <input type="number" id="maxAttempts" class="auth-input" min="1" max="20">
      </div>
      <div style="display:flex; align-items:flex-end;">
        <label class="auth-toggle" style="width:100%; margin-bottom:0;">
          <input type="checkbox" id="otpRequired">
          <div>
            <div class="toggle-label">فرض OTP عند الدخول</div>
            <div class="toggle-hint">الأرقام المسجلة تحتاج الرمز حتى مع كلمة المرور.</div>
          </div>
        </label>
      </div>
    </div>
  </div>

  <!-- بطاقة قالب رسالة SMS -->
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="icon">✉️</div>
      <div>
        <h3>قالب رسالة SMS</h3>
        <small>المتغيرات المتاحة: {OTP} · {PHONE} · {MINUTES} · {APP_NAME}</small>
      </div>
    </div>
    <div class="form-group">
      <textarea id="messageTemplate" class="auth-input" rows="3" style="direction:rtl; resize:vertical;" oninput="previewTemplate()"></textarea>
    </div>
    <div class="form-group" style="margin-top:12px;">
      <label>معاينة حية</label>
      <div class="tpl-preview" id="templatePreview">معاينة: —</div>
    </div>
  </div>

  <!-- بطاقة الحدود والحماية -->
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="icon">🛡️</div>
      <div>
        <h3>الحدود والحماية من الإساءة</h3>
        <small>إعادة الإرسال والحدود الزمنية لكل رقم وعنوان IP.</small>
      </div>
    </div>
    <div class="auth-grid-2">
      <div class="form-group"><label>فترة انتظار إعادة الإرسال (ثوانٍ)</label>
        <input type="number" id="resendCooldown" class="auth-input" min="5" max="600">
      </div>
      <div class="form-group"><label>حد إعادة الإرسال لكل طلب</label>
        <input type="number" id="maxResends" class="auth-input" min="0" max="20">
      </div>
      <div class="form-group"><label>حد الطلبات لكل رقم (ساعة)</label>
        <input type="number" id="ratePhone" class="auth-input" min="1" max="100">
      </div>
      <div class="form-group"><label>حد الطلبات لكل IP (ساعة)</label>
        <input type="number" id="rateIp" class="auth-input" min="1" max="300">
      </div>
    </div>
  </div>

  <!-- الأزرار -->
  <div class="auth-actions">
    <button class="btn-auth-primary" id="saveBtn" onclick="saveSettings()">💾 حفظ الإعدادات</button>
    <button class="btn-auth-secondary" onclick="loadSettings()">🔄 تحديث</button>
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
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
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
  btn.disabled = false;
}

document.addEventListener('DOMContentLoaded', loadSettings);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
