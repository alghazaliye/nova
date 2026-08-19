<?php
/**
 * NOVA Messenger Admin — مزودو OTP
 * Multi-provider OTP management (Twilio / Vonage / HTTP REST / Manual-Test)
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}
$admin = requireAdminLogin();
$pageTitle = 'مزودو OTP';
$pdo = getAdminDB();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>مزودو OTP</h2>
    <p>إدارة مزودي إرسال رموز التحقق (Twilio / Vonage / HTTP REST / اختباري). يتم الإرسال حسب الأولوية مع الإرجاع التلقائي (Fallback) عند فشل مزود.</p>
  </div>
  <?php if (hasPermission($admin, 'otp.providers.create')): ?>
  <button class="btn primary" onclick="openModal('add')">＋ إضافة مزود</button>
  <?php endif; ?>
</div>

<div class="card panel" style="margin-bottom:16px;">
  <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:13px;">
    <div><b>وضع التسليم:</b> <span id="statusMode">—</span></div>
    <div><b>الإرجاع اليدوي:</b> <span id="statusManual">—</span></div>
    <div><b>مدة صلاحية الرمز:</b> <span id="statusExpiry">—</span> دقيقة</div>
    <div><b>الحد الأقصى للمحاولات:</b> <span id="statusMaxAttempts">—</span></div>
  </div>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>المزود</th>
        <th>النوع</th>
        <th>الحالة</th>
        <th>الافتراضي</th>
        <th>الناجح / الفاشل</th>
        <th>آخر استخدام</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody id="providersBody">
      <tr><td colspan="8" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- إضافة/تعديل مزود -->
<div class="modal" id="providerModal">
  <div class="modal-box" style="max-width:560px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 id="modalTitle" style="margin:0;">إضافة مزود OTP</h3>
      <button onclick="closeModal()" style="font-size:20px;">✕</button>
    </div>
    <form id="providerForm" onsubmit="saveProvider(event)" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="id" id="pfId">
      <label style="font-size:13px;">اسم المزود <span style="color:var(--bad)">*</span>
        <input name="name" id="pfName" required maxlength="120" class="input" placeholder="مثال: مزود الرسائل السعودي">
      </label>
      <label style="font-size:13px;">نوع المزود <span style="color:var(--bad)">*</span>
        <select name="type" id="pfType" class="select" onchange="renderTypeFields()">
          <option value="twilio">Twilio</option>
          <option value="vonage">Vonage</option>
          <option value="http_rest">HTTP REST (مزود مخصص)</option>
          <option value="sms_mock">رسائل نصية SMS (قناة تجربة)</option>
          <option value="test">مزود اختباري (تطوير فقط)</option>
        </select>
      </label>
      <label style="font-size:13px;">الأولوية <span style="color:var(--muted)">(الأقل أولوية أولًا — ترتيب الفال باك)</span>
        <input type="number" name="priority" id="pfPriority" value="1" min="0" max="99" class="input">
      </label>

      <div id="typeFields" style="display:flex; flex-direction:column; gap:10px;"></div>

      <label style="font-size:13px;">قالب الرسالة
        <textarea name="message_template" id="pfTemplate" rows="3" class="input" placeholder="رمز التحقق: {OTP} صالح لمدة {MINUTES} دقيقة — {APP_NAME}"></textarea>
      </label>
      <label style="font-size:12px; display:flex; gap:6px; align-items:center;">
        <input type="checkbox" name="is_default" id="pfDefault" value="1"> افتراضي لهذا النوع
      </label>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
        <button type="button" class="btn" onclick="closeModal()">إلغاء</button>
        <button type="submit" class="btn primary">حفظ</button>
      </div>
    </form>
  </div>
</div>

<!-- اختبار مزود -->
<div class="modal" id="testModal">
  <div class="modal-box" style="max-width:420px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 style="margin:0;">اختبار المزود</h3>
      <button onclick="closeTestModal()" style="font-size:20px;">✕</button>
    </div>
    <form id="testForm" onsubmit="testProvider(event)" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="id" id="testId">
      <p style="margin:0; font-size:13px; color:var(--muted);">سيتم إرسال رمز تجريبي (000000) إلى رقم الهاتف لتحقق من الإعدادات.</p>
      <label style="font-size:13px;">رقم الهاتف <span style="color:var(--bad)">*</span>
        <input name="phone" id="testPhone" required class="input" placeholder="+9665XXXXXXXX" dir="ltr" style="text-align:right;">
      </label>
      <div id="testResult" style="display:none; padding:10px; border-radius:10px; font-size:13px;"></div>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
        <button type="button" class="btn" onclick="closeTestModal()">إغلاق</button>
        <button type="submit" class="btn primary" id="testBtn">إرسال رمز تجريبي</button>
      </div>
    </form>
  </div>
</div>

<style>
.modal{position:fixed; inset:0; background:rgba(16,24,40,.55); display:none; align-items:center; justify-content:center; z-index:999;}
.modal.open{display:flex;}
.modal-box{background:var(--surface); border-radius:18px; padding:22px; margin:14px; max-height:90vh; overflow:auto; box-shadow:var(--shadow);}
.input, .select{width:100%; height:40px; border:1px solid var(--line); background:var(--surface2); color:var(--text); border-radius:10px; padding:0 12px;}
textarea.input{height:auto; padding:10px 12px;}
.field-note{font-size:11px; color:var(--muted);}
</style>

<script>
const API = '/api/v1';
let providers = [];

function statusBadge(s){
  if (s === 'enabled') return '<span class="status online">مفعّل</span>';
  return '<span class="status offline">معطّل</span>';
}

function typeLabel(t){
  const labels = {twilio:'Twilio', vonage:'Vonage', http_rest:'HTTP REST', sms_mock:'رسائل نصية (تجربة)', test:'اختباري'};
  return labels[t] || t;
}

async function loadProviders(){
  try {
    const res = await fetch(API + '/admin/otp/providers', {headers: {'X-Admin-Auth': (localStorage.getItem('adminToken') || '')}});
    if (res.status === 401 || res.status === 403) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;">يجب تسجيل الدخول من التطبيق أو إعادة تسجيل دخول الإدارة مع JWT</td></tr>'; return; }
    const data = await res.json();
    providers = data.providers || [];
    renderProviders();
  } catch (e) {
    document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;">فشل التحميل: ' + e.message + '</td></tr>';
  }
  // Settings status line
  try {
    const s = await fetch(API + '/admin/otp/settings', {headers: {'X-Admin-Auth': (localStorage.getItem('adminToken') || '')}}).then(r => r.json());
    const st = s.settings || {};
    const modes = {auto:'تلقائي', manual:'يدوي', auto_fallback:'تلقائي + إرجاع يدوي'};
    document.getElementById('statusMode').textContent = modes[st.otp_delivery_mode] || st.otp_delivery_mode || '—';
    document.getElementById('statusManual').textContent = st.otp_enable_manual_fallback === '1' ? 'مفعّل' : 'معطّل';
    document.getElementById('statusExpiry').textContent = st.otp_expiry_minutes || '—';
    document.getElementById('statusMaxAttempts').textContent = st.otp_max_attempts || '—';
  } catch (e) {}
}

function renderProviders(){
  const tbody = document.getElementById('providersBody');
  if (providers.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:24px;">لا يوجد مزودون. أضف مزود اختباري أولًا (لا يتطلب أي مفاتيح).</td></tr>';
    return;
  }
  tbody.innerHTML = providers.map(p => `
    <tr>
      <td>${p.id}</td>
      <td><b>${esc(p.name)}</b></td>
      <td>${typeLabel(p.type)}</td>
      <td>${statusBadge(p.status)}</td>
      <td>${p.is_default ? '<span style="color:var(--primary); font-weight:800;">نعم</span>' : '—'}</td>
      <td><span style="color:var(--good)">${p.success_count || 0}</span> / <span style="color:var(--bad)">${p.failure_count || 0}</span></td>
      <td>${p.last_used_at ? new Date(p.last_used_at).toLocaleString('ar-SA') : '—'}</td>
      <td>
        <div style="display:flex; gap:5px; flex-wrap:wrap;">
          <button class="btn sm" onclick="openModal('edit', ${p.id})">✎ تعديل</button>
          <button class="btn sm" onclick="openTest(${p.id})">⚡ اختبار</button>
          <button class="btn sm" style="background:${p.status === 'enabled' ? 'rgba(240,68,56,.1);color:var(--bad)' : 'rgba(18,183,106,.1);color:var(--good)'}" onclick="toggle(${p.id}, '${p.status === 'enabled' ? 'disabled' : 'enabled'}')">${p.status === 'enabled' ? 'تعطيل' : 'تفعيل'}</button>
          <button class="btn danger sm" onclick="del(${p.id})">حذف</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function openModal(mode, id = null){
  document.getElementById('modalTitle').textContent = mode === 'add' ? 'إضافة مزود OTP' : 'تعديل مزود OTP';
  document.getElementById('pfId').value = id || '';
  if (mode === 'edit' && id) {
    const p = providers.find(x => x.id === id);
    if (p) {
      document.getElementById('pfName').value = p.name;
      document.getElementById('pfType').value = p.type;
      document.getElementById('pfPriority').value = p.priority ?? 1;
      document.getElementById('pfDefault').checked = !!p.is_default;
      document.getElementById('pfTemplate').value = p.message_template || '';
    }
  } else {
    document.getElementById('providerForm').reset();
    document.getElementById('pfDefault').checked = false;
  }
  renderTypeFields();
  document.getElementById('providerModal').classList.add('open');
}

function closeModal(){ document.getElementById('providerModal').classList.remove('open'); }

function renderTypeFields(){
  const type = document.getElementById('pfType').value;
  const host = document.getElementById('typeFields');
  let html = '';
  if (type === 'twilio') {
    html = `
      <label style="font-size:13px;">Account SID <span style="color:var(--bad)">*</span>
        <input name="account_sid" id="pfSid" class="input" placeholder="ACXXXXXXXXXXXXXXXX" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">Auth Token <span style="color:var(--bad)">*</span>
        <input name="api_secret" id="pfSecret" type="password" class="input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">المُرسل (From) <span style="color:var(--bad)">*</span>
        <input name="sender_id" id="pfFrom" class="input" placeholder="+1234567890" dir="ltr" style="text-align:right;"></label>
      <p class="field-note">يُرسل عبر Twilio Messages API. يُشفّر الرمز قبل الحفظ.</p>`;
  } else if (type === 'vonage') {
    html = `
      <label style="font-size:13px;">API Key <span style="color:var(--bad)">*</span>
        <input name="api_key" id="pfKey" class="input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">API Secret <span style="color:var(--bad)">*</span>
        <input name="api_secret" id="pfVSecret" type="password" class="input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">Sender ID (اختياري)
        <input name="sender_id" id="pfVFrom" class="input" placeholder="NOVA" dir="ltr" style="text-align:right;"></label>`;
  } else if (type === 'http_rest') {
    html = `
      <label style="font-size:13px;">رابط API <span style="color:var(--bad)">*</span>
        <input name="api_base_url" id="pfUrl" class="input" placeholder="https://sms-gateway.example.com/send" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">طريقة HTTP
        <select name="http_method" id="pfMethod" class="select">
          <option value="POST">POST</option><option value="GET">GET</option>
        </select></label>
      <label style="font-size:13px;">Content-Type
        <select name="content_type" id="pfCt" class="select">
          <option value="json">JSON</option><option value="form">Form (x-www-form-urlencoded)</option>
        </select></label>
      <label style="font-size:13px;">نوع المصادقة
        <select name="auth_type" id="pfAuth" class="select" onchange="toggleAuthFields()">
          <option value="none">بدون</option>
          <option value="bearer">Bearer Token</option>
          <option value="basic">Basic Auth</option>
          <option value="header">رأس مخصص</option>
          <option value="query">معلمة استعلام</option>
        </select></label>
      <div id="authFields"></div>
      <label style="font-size:13px;">حقل الهاتف في الطلب <span class="field-note">(يوضع {PHONE})</span>
        <input name="to_field" id="pfToField" value="to" class="input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">حقل الرمز في الطلب <span class="field-note">(يوضع {OTP})</span>
        <input name="otp_field" id="pfOtpField" value="code" class="input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">نمط القالب
        <select name="template_mode" id="pfTm" class="select">
          <option value="code_only">الرمز فقط ({OTP})</option>
          <option value="full_message">الرسالة كاملة ({OTP} {PHONE} ...)</option>
        </select></label>
      <label style="font-size:13px;">تعبير النجاح <span class="field-note">مثال: json.status=OK</span>
        <input name="success_expr" id="pfSx" class="input" placeholder="json.status=OK" dir="ltr" style="text-align:right;"></label>
      <p class="field-note">مزود عام لأي بوابة SMS. المتغيرات: {PHONE} رقم الهاتف، {OTP} الرمز، {MESSAGE} الرسالة كاملة.</p>`;
  } else if (type === 'sms_mock') {
    html = '<p class="field-note" style="color:var(--good);"><b>قناة تجربة داخلية:</b> رمز حقيقي (6 أرقام) يُولَّد ويُشفّر كالمزودات الحقيقية، لكن لا يُرسل SMS فعليًا. يمكنك قراءة الرمز من صفحة «طلبات التسجيل» (سجل التسليم) أو من الإشعار في لوحة التحكم. مثالي لتجربة مسار الرسائل النصية دون اشتراك في خدمة خارجية.</p>';
  } else if (type === 'test') {
    html = '<p class="field-note" style="color:var(--warn);"><b>تنبيه:</b> مزود الاختبار يعمل فقط في بيئة التطوير ويُعيد رمز OTP_TEST_CODE المحدد في الإعدادات (حاليًا: 123456).</p>';
  }
  host.innerHTML = html;
  toggleAuthFields();
}

function toggleAuthFields(){
  const auth = (document.getElementById('pfAuth') || {}).value || 'none';
  const host = document.getElementById('authFields');
  if (!host) return;
  let html = '';
  if (auth === 'bearer') {
    html = `<label style="font-size:13px;">Bearer Token <span style="color:var(--bad)">*</span>
      <input name="auth_token" id="pfAuthToken" class="input" dir="ltr" style="text-align:right;"></label>`;
  } else if (auth === 'basic') {
    html = `<label style="font-size:13px;">اسم المستخدم <span style="color:var(--bad)">*</span>
      <input name="auth_user" id="pfAuthUser" class="input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">كلمة المرور <span style="color:var(--bad)">*</span>
      <input name="auth_pass" id="pfAuthPass" type="password" class="input" dir="ltr" style="text-align:right;"></label>`;
  } else if (auth === 'header') {
    html = `<label style="font-size:13px;">اسم الرأس <span style="color:var(--bad)">*</span>
      <input name="auth_header_name" id="pfAHName" class="input" placeholder="X-API-Key" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">قيمة الرأس <span style="color:var(--bad)">*</span>
      <input name="auth_header_value" id="pfAHVal" class="input" dir="ltr" style="text-align:right;"></label>`;
  } else if (auth === 'query') {
    html = `<label style="font-size:13px;">اسم المعلمة <span style="color:var(--bad)">*</span>
      <input name="auth_param_name" id="pfAPName" class="input" placeholder="key" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">قيمة المعلمة <span style="color:var(--bad)">*</span>
      <input name="auth_param_value" id="pfAPVal" class="input" dir="ltr" style="text-align:right;"></label>`;
  }
  host.innerHTML = html;
}

async function saveProvider(e){
  e.preventDefault();
  const id = document.getElementById('pfId').value;
  const form = document.getElementById('providerForm');
  const fd = new FormData(form);
  const data = Object.fromEntries(fd.entries());
  data.is_default = fd.get('is_default') ? 1 : 0;
  data.priority = parseInt(data.priority || '1', 10);
  const url = id ? API + '/admin/otp/providers/' + id : API + '/admin/otp/providers';
  try {
    const res = await fetch(url, {
      method: id ? 'PUT' : 'POST',
      headers: {'Content-Type': 'application/json', 'X-Admin-Auth': (localStorage.getItem('adminToken') || '')},
      body: JSON.stringify(data)
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || j.error_code || 'فشل الحفظ');
    closeModal();
    await loadProviders();
  } catch (err) { alert(err.message); }
}

async function toggle(id, status){
  try {
    const res = await fetch(API + '/admin/otp/providers/' + id + '/toggle', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Admin-Auth': (localStorage.getItem('adminToken') || '')},
      body: JSON.stringify({status})
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل');
    await loadProviders();
  } catch (err) { alert(err.message); }
}

async function del(id){
  if (!confirm('حذف هذا المزود نهائيًا؟')) return;
  try {
    const res = await fetch(API + '/admin/otp/providers/' + id, {
      method: 'DELETE',
      headers: {'X-Admin-Auth': (localStorage.getItem('adminToken') || '')}
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل الحذف');
    await loadProviders();
  } catch (err) { alert(err.message); }
}

function openTest(id){
  document.getElementById('testId').value = id;
  document.getElementById('testPhone').value = '';
  document.getElementById('testResult').style.display = 'none';
  document.getElementById('testModal').classList.add('open');
}
function closeTestModal(){ document.getElementById('testModal').classList.remove('open'); }

async function testProvider(e){
  e.preventDefault();
  const btn = document.getElementById('testBtn');
  const box = document.getElementById('testResult');
  btn.disabled = true; btn.textContent = 'جاري الإرسال...';
  box.style.display = 'none';
  try {
    const res = await fetch(API + '/admin/otp/providers/' + document.getElementById('testId').value + '/test', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Admin-Auth': (localStorage.getItem('adminToken') || '')},
      body: JSON.stringify({phone: document.getElementById('testPhone').value})
    });
    const j = await res.json();
    box.style.display = 'block';
    box.style.background = res.ok ? 'rgba(18,183,106,.1)' : 'rgba(240,68,56,.1)';
    box.style.color = res.ok ? 'var(--good)' : 'var(--bad)';
    box.textContent = j.message + (j.error_code ? ' (' + j.error_code + ')' : '');
  } catch (err) { box.style.display = 'block'; box.textContent = err.message; }
  finally { btn.disabled = false; btn.textContent = 'إرسال رمز تجريبي'; }
}

loadProviders();
</script>
