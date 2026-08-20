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
<style>
  /* ===== تحسينات صفحة المصادقة والتسجيل ===== */
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
  }
  .auth-input:focus, .auth-select:focus {
    outline: none;
    border-color: #5b5ce2;
    box-shadow: 0 0 0 4px rgba(91, 92, 226, .15);
  }
  .auth-hint { display: block; color: var(--muted); font-size: 11.5px; margin-top: 5px; line-height: 1.6; }

  .hint-row { display: flex; gap: 8px; align-items: flex-start; margin-top: 5px; color: var(--muted); font-size: 11.5px; line-height: 1.6; }
  .hint-row .hi { color: #5b5ce2; }

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
</style>

<div class="content">
  <div class="page-header">
    <h1 class="page-title">المصادقة والتسجيل</h1>
    <p style="color:var(--muted)">التحكم في طرق التسجيل والدخول وإعدادات OTP للهاتف والبريد الإلكتروني بشكل مستقل.</p>
  </div>
  <div id="globalAlert"></div>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
    <!-- طرق التسجيل -->
    <div class="auth-card">
      <div class="auth-card-header">
        <div class="icon">📝</div>
        <div>
          <h3>طرق التسجيل</h3>
          <small>الطرق المتاحة لإنشاء حساب جديد</small>
        </div>
      </div>
      <label class="auth-toggle" onclick="document.getElementById('regPhone').click(); return false;">
        <input type="checkbox" id="regPhone">
        <div>
          <div class="toggle-label">التسجيل برقم الهاتف</div>
          <div class="toggle-hint">إنشاء حساب جديد عبر رمز تحقق OTP يصل إلى رقم الهاتف.</div>
        </div>
      </label>
      <label class="auth-toggle" onclick="document.getElementById('regEmail').click(); return false;">
        <input type="checkbox" id="regEmail">
        <div>
          <div class="toggle-label">التسجيل بالبريد الإلكتروني</div>
          <div class="toggle-hint">يتطلب تفعيل مزود بريد واحد على الأقل من «إعدادات الخدمات».</div>
        </div>
      </label>
      <div class="hint-row"><span class="hi">ⓘ</span><span>إيقاف جميع طرق التسجيل يعرض رسالة «التسجيل متوقف حاليًا» للمستخدمين.</span></div>
    </div>

    <!-- طرق الدخول -->
    <div class="auth-card">
      <div class="auth-card-header">
        <div class="icon">🔑</div>
        <div>
          <h3>طرق تسجيل الدخول</h3>
          <small>طرق دخول المستخدم بعد إنشاء الحساب</small>
        </div>
      </div>
      <label class="auth-toggle" onclick="document.getElementById('loginPhone').click(); return false;">
        <input type="checkbox" id="loginPhone">
        <div class="toggle-label">رقم الهاتف (OTP)</div>
      </label>
      <label class="auth-toggle" onclick="document.getElementById('loginEmail').click(); return false;">
        <input type="checkbox" id="loginEmail">
        <div class="toggle-label">البريد الإلكتروني (كلمة مرور)</div>
      </label>
      <label class="auth-toggle" onclick="document.getElementById('loginUsername').click(); return false;">
        <input type="checkbox" id="loginUsername">
        <div class="toggle-label">اسم المستخدم (كلمة مرور)</div>
      </label>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
    <!-- OTP الهاتف -->
    <div class="auth-card">
      <div class="auth-card-header">
        <div class="icon">📱</div>
        <div>
          <h3>OTP الهاتف</h3>
          <small>خصائص رمز التحقق المرسل إلى الهاتف</small>
        </div>
      </div>
      <label class="auth-toggle" onclick="document.getElementById('otpPhoneEnabled').click(); return false;">
        <input type="checkbox" id="otpPhoneEnabled">
        <div class="toggle-label">تفعيل OTP الهاتف</div>
      </label>
      <div class="form-group">
        <label for="otpPhoneDelivery">وضع التسليم</label>
        <select class="auth-select" id="otpPhoneDelivery">
          <option value="auto">تلقائي (عبر المزود)</option>
          <option value="manual">يدوي (المدير يعرض الرمز)</option>
          <option value="auto_fallback">تلقائي مع تحول يدوي</option>
        </select>
        <span class="auth-hint">في الوضع التلقائي يُرسل الرمز تلقائيًا عبر المزود الافتراضي.</span>
      </div>
      <div class="auth-grid-2">
        <div class="form-group">
          <label for="otpPhoneExpiry">مدة الصلاحية (دقائق)</label>
          <input class="auth-input" type="number" id="otpPhoneExpiry" min="1" max="60">
        </div>
        <div class="form-group">
          <label for="otpPhoneAttempts">الحد الأقصى للمحاولات</label>
          <input class="auth-input" type="number" id="otpPhoneAttempts" min="1" max="100">
        </div>
        <div class="form-group">
          <label for="otpPhoneCooldown">الانتظار قبل إعادة الإرسال (ثانية)</label>
          <input class="auth-input" type="number" id="otpPhoneCooldown" min="0" max="600">
        </div>
        <div class="form-group">
          <label for="otpPhoneResends">أقصى مرات إعادة الإرسال</label>
          <input class="auth-input" type="number" id="otpPhoneResends" min="1" max="50">
        </div>
      </div>
    </div>

    <!-- OTP البريد -->
    <div class="auth-card">
      <div class="auth-card-header">
        <div class="icon">✉</div>
        <div>
          <h3>OTP البريد الإلكتروني</h3>
          <small>خصائص رمز التحقق المرسل إلى البريد</small>
        </div>
      </div>
      <label class="auth-toggle" onclick="document.getElementById('otpEmailEnabled').click(); return false;">
        <input type="checkbox" id="otpEmailEnabled">
        <div class="toggle-label">تفعيل OTP البريد الإلكتروني</div>
      </label>
      <div class="form-group">
        <label for="otpEmailDelivery">وضع التسليم</label>
        <select class="auth-select" id="otpEmailDelivery">
          <option value="auto">تلقائي (SMTP / REST)</option>
          <option value="manual">يدوي (المدير يعرض الرمز)</option>
          <option value="auto_fallback">تلقائي مع تحول يدوي</option>
        </select>
        <span class="auth-hint">في الوضع اليدوي يظهر الرمز للمشرف في صفحة «طلبات تسجيل البريد».</span>
      </div>
      <div class="auth-grid-2">
        <div class="form-group">
          <label for="otpEmailExpiry">مدة الصلاحية (دقائق)</label>
          <input class="auth-input" type="number" id="otpEmailExpiry" min="1" max="60">
        </div>
        <div class="form-group">
          <label for="otpEmailAttempts">الحد الأقصى للمحاولات</label>
          <input class="auth-input" type="number" id="otpEmailAttempts" min="1" max="100">
        </div>
        <div class="form-group">
          <label for="otpEmailCooldown">الانتظار قبل إعادة الإرسال (ثانية)</label>
          <input class="auth-input" type="number" id="otpEmailCooldown" min="0" max="600">
        </div>
        <div class="form-group">
          <label for="otpEmailResends">أقصى مرات إعادة الإرسال</label>
          <input class="auth-input" type="number" id="otpEmailResends" min="1" max="50">
        </div>
      </div>
    </div>
  </div>

  <div class="auth-actions">
    <button class="btn-auth-primary" id="saveBtn" onclick="saveSettings()">
      💾 حفظ الإعدادات
    </button>
    <button class="btn-auth-secondary" id="reloadBtn" onclick="loadSettings()">
      🔄 تحديث
    </button>
  </div>
</div>

<script>
const API = '<?= $API ?>';
function token() { return localStorage.getItem('adminToken') || ''; }
function showAlert(msg, kind) {
  const a = document.getElementById('globalAlert');
  a.innerHTML = `<div class="alert alert-${kind === 'error' ? 'danger' : 'success'}" style="border-radius:12px; margin-bottom:16px;">${msg}</div>`;
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
    showAlert('تم تحديث البيانات', 'success');
  } catch (e) { showAlert('خطأ في الاتصال: ' + e.message, 'error'); }
}
async function saveSettings() {
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
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
  finally { btn.disabled = false; }
}
document.addEventListener('DOMContentLoaded', loadSettings);
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
