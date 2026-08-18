<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}
$admin = requireAdminLogin();
$pageTitle = 'مزودو البريد الإلكتروني';
$pdo = getAdminDB();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>مزودو البريد الإلكتروني</h2>
    <p>إدارة مزودي إرسال رموز التحقق بالبريد (SMTP / HTTP REST). تستخدم عند تفعيل «التسجيل/الدخول بالبريد» وOTP البريد.</p>
  </div>
  <?php if (hasPermission($admin, 'email.providers.create')): ?>
  <button class="btn primary" onclick="openModal('add')">＋ إضافة مزود</button>
  <?php endif; ?>
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
      <h3 id="modalTitle" style="margin:0;">إضافة مزود بريد</h3>
      <button onclick="closeModal()" style="font-size:20px;">✕</button>
    </div>
    <form id="providerForm" onsubmit="saveProvider(event)" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="id" id="pfId">
      <label style="font-size:13px;">اسم المزود <span style="color:var(--bad)">*</span>
        <input name="name" id="pfName" required maxlength="120" class="input" placeholder="مثال: Gmail SMTP">
      </label>
      <label style="font-size:13px;">نوع المزود <span style="color:var(--bad)">*</span>
        <select name="type" id="pfType" class="select" onchange="renderTypeFields()">
          <option value="smtp">SMTP</option>
          <option value="http_rest">HTTP REST (مزود مخصص)</option>
        </select>
      </label>
      <label style="font-size:13px;">الأولوية <span style="color:var(--muted)">(الأقل أولية أولًا — ترتيب الفال باك)</span>
        <input type="number" name="priority" id="pfPriority" value="1" min="0" max="99" class="input">
      </label>

      <div id="typeFields" style="display:flex; flex-direction:column; gap:10px;"></div>

      <label style="font-size:13px;">البريد المرسل منه (From) <span style="color:var(--bad)">*</span>
        <input name="from_email" id="pfFromEmail" required class="input" placeholder="noreply@yourapp.com" dir="ltr" style="text-align:right;">
      </label>
      <label style="font-size:13px;">الاسم الظاهر للمرسل <span style="color:var(--muted)">(اختياري)</span>
        <input name="from_name" id="pfFromName" class="input" placeholder="NOVA Messenger">
      </label>
      <label style="font-size:12px; display:flex; gap:6px; align-items:center;">
        <input type="checkbox" name="is_default" id="pfDefault" value="1"> الافتراضي لهذا النوع
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
      <p style="margin:0; font-size:13px; color:var(--muted);">سيتم إرسال رسالة تجريبية إلى البريد الإلكتروني للتأكد من صحة الإعدادات.</p>
      <label style="font-size:13px;">البريد الإلكتروني <span style="color:var(--bad)">*</span>
        <input name="email" id="testEmail" required class="input" placeholder="you@example.com" dir="ltr" style="text-align:right;">
      </label>
      <div id="testResult" style="display:none; padding:10px; border-radius:10px; font-size:13px;"></div>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
        <button type="button" class="btn" onclick="closeTestModal()">إغلاق</button>
        <button type="submit" class="btn primary" id="testBtn">إرسال تجريبي</button>
      </div>
    </form>
  </div>
</div>

<script>
const API = '/api/v1/admin/email-providers';
function token() { return localStorage.getItem('adminToken') || ''; }
function openModal(mode, provider = null) {
  document.getElementById('modalTitle').textContent = mode === 'add' ? 'إضافة مزود بريد' : 'تعديل المزود';
  document.getElementById('pfId').value = provider ? provider.id : '';
  document.getElementById('pfName').value = provider ? provider.name : '';
  document.getElementById('pfType').value = provider ? provider.type : 'smtp';
  document.getElementById('pfPriority').value = provider ? provider.priority : 1;
  document.getElementById('pfFromEmail').value = provider ? (provider.from_email || '') : '';
  document.getElementById('pfFromName').value = provider ? (provider.from_name || '') : '';
  document.getElementById('pfDefault').checked = provider ? !!Number(provider.is_default) : false;
  renderTypeFields(provider);
  document.getElementById('providerModal').classList.add('open');
}
function closeModal() { document.getElementById('providerModal').classList.remove('open'); }
function openTestModal(id) {
  document.getElementById('testId').value = id;
  document.getElementById('testEmail').value = '';
  document.getElementById('testResult').style.display = 'none';
  document.getElementById('testModal').classList.add('open');
}
function closeTestModal() { document.getElementById('testModal').classList.remove('open'); }
function renderTypeFields(provider) {
  const type = document.getElementById('pfType').value;
  const wrap = document.getElementById('typeFields');
  const cfg = provider ? JSON.parse(provider.extra_config || '{}') : {};
  if (type === 'smtp') {
    wrap.innerHTML = `
      <label style="font-size:13px;">خادم SMTP <span style="color:var(--bad)">*</span>
        <input name="host" id="pfHost" class="input" value="${provider ? provider.host || '' : 'smtp.gmail.com'}" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">المنفذ
        <input type="number" name="port" id="pfPort" class="input" value="${provider ? provider.port || 587 : 587}"></label>
      <label style="font-size:13px;">التشفير
        <select name="encryption" id="pfEncryption" class="select">
          <option value="none" ${provider?.encryption === 'none' ? 'selected' : ''}>بدون</option>
          <option value="tls" ${(!provider || provider.encryption === 'tls') ? 'selected' : ''}>TLS</option>
          <option value="ssl" ${provider?.encryption === 'ssl' ? 'selected' : ''}>SSL</option>
        </select></label>
      <label style="font-size:13px;">اسم المستخدم <span style="color:var(--bad)">*</span>
        <input name="username" id="pfUsername" class="input" value="${provider ? provider.username || '' : ''}" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">كلمة المرور
        <input type="password" name="password" id="pfPassword" class="input" placeholder="${provider ? '(اتركه فارغًا للإبقاء على القيمة الحالية)' : ''}" dir="ltr" style="text-align:right;"></label>`;
  } else {
    wrap.innerHTML = `
      <label style="font-size:13px;">رابط API <span style="color:var(--bad)">*</span>
        <input name="api_base_url" id="pfApiUrl" class="input" value="${provider ? provider.api_base_url || '' : 'https://api.provider.com/v1/send'}" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">API Key <span style="color:var(--muted)">(تُخزن مشفرة)</span>
        <input type="password" name="api_key" id="pfApiKey" class="input" placeholder="${provider ? '(اتركه فارغًا للإبقاء على القيمة الحالية)' : ''}" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">حقل البريد في API (default: to)
        <input name="to_field" id="pfToField" class="input" value="${cfg.to_field || ''}" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">قالب JSON
        <textarea name="template" id="pfTemplate" rows="3" class="input" placeholder='{"to":"{{TO}}","subject":"{{SUBJECT}}","body":"{{BODY}}"}'>${cfg.template || ''}</textarea>
        <small style="color:var(--muted)">{{TO}} البريد، {{SUBJECT}} العنوان، {{BODY}} النص، {OTP} الرمز.</small></label>`;
  }
}
async function loadProviders() {
  try {
    const r = await fetch(API, { headers: { Authorization: 'Bearer ' + token() } });
    if (!r.ok) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">فشل التحميل (HTTP ' + r.status + ') — تأكد من تسجيل الدخول</td></tr>'; return; }
    const j = await r.json();
    const list = j.providers || [];
    if (list.length === 0) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">لا توجد مزودات — أضف مزودًا لبدء الإرسال التلقائي</td></tr>'; return; }
    document.getElementById('providersBody').innerHTML = list.map(p => `
      <tr>
        <td>${p.id}</td>
        <td><b>${esc(p.name)}</b><br><small style="color:var(--muted)">${esc(p.from_email || '')}</small></td>
        <td>${p.type === 'smtp' ? 'SMTP' : 'HTTP REST'}</td>
        <td><span class="badge ${p.status === 'enabled' ? 'good' : 'bad'}">${p.status === 'enabled' ? 'مفعّل' : 'معطّل'}</span></td>
        <td>${Number(p.is_default) ? '✓' : '—'}</td>
        <td><span style="color:var(--good)">${p.success_count}</span> / <span style="color:var(--bad)">${p.failure_count}</span></td>
        <td>${p.last_used_at || '—'}</td>
        <td style="white-space:nowrap;">
          <button class="btn small" onclick='openModal("edit", ${JSON.stringify(p)})'>تعديل</button>
          <button class="btn small" onclick="toggle(${p.id}, '${p.status === 'enabled' ? 'disabled' : 'enabled'}')">${p.status === 'enabled' ? 'تعطيل' : 'تفعيل'}</button>
          <button class="btn small" onclick="openTestModal(${p.id})">اختبار</button>
          <button class="btn small danger" onclick="del(${p.id})">حذف</button>
        </td>
      </tr>`).join('');
  } catch (e) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">خطأ اتصال</td></tr>'; }
}
async function saveProvider(e) {
  e.preventDefault();
  const id = document.getElementById('pfId').value;
  const data = {
    name: document.getElementById('pfName').value.trim(),
    type: document.getElementById('pfType').value,
    priority: parseInt(document.getElementById('pfPriority').value) || 0,
    from_email: document.getElementById('pfFromEmail').value.trim(),
    from_name: document.getElementById('pfFromName').value.trim(),
    is_default: document.getElementById('pfDefault').checked ? 1 : 0,
  };
  if (data.type === 'smtp') {
    data.host = document.getElementById('pfHost').value.trim();
    data.port = parseInt(document.getElementById('pfPort').value) || 587;
    data.encryption = document.getElementById('pfEncryption').value;
    data.username = document.getElementById('pfUsername').value.trim();
    data.password = document.getElementById('pfPassword').value;
  } else {
    data.api_base_url = document.getElementById('pfApiUrl').value.trim();
    data.api_key = document.getElementById('pfApiKey').value;
    data.extra_config = JSON.stringify({
      to_field: document.getElementById('pfToField').value.trim() || 'to',
      template: document.getElementById('pfTemplate').value.trim(),
    });
  }
  const url = id ? API + '/' + id : API;
  const method = id ? 'PUT' : 'POST';
  const r = await fetch(url, { method, headers: { Authorization: 'Bearer ' + token(), 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
  const j = await r.json();
  if (!j.success) { alert(j.message || 'فشل الحفظ'); return; }
  closeModal();
  await loadProviders();
}
async function toggle(id, status) {
  const r = await fetch(API + '/' + id + '/toggle', { method: 'POST', headers: { Authorization: 'Bearer ' + token(), 'Content-Type': 'application/json' }, body: JSON.stringify({ status }) });
  const j = await r.json();
  if (!j.success) { alert(j.message || 'فشل التغيير'); return; }
  await loadProviders();
}
async function del(id) {
  if (!confirm('هل أنت متأكد من حذف هذا المزود؟')) return;
  const r = await fetch(API + '/' + id, { method: 'DELETE', headers: { Authorization: 'Bearer ' + token() } });
  const j = await r.json();
  if (!j.success) { alert(j.message || 'فشل الحذف'); return; }
  await loadProviders();
}
async function testProvider(e) {
  e.preventDefault();
  const id = document.getElementById('testId').value;
  const email = document.getElementById('testEmail').value.trim();
  const btn = document.getElementById('testBtn');
  btn.disabled = true;
  btn.textContent = 'جارٍ الإرسال...';
  const resDiv = document.getElementById('testResult');
  try {
    const r = await fetch(API + '/' + id + '/test', { method: 'POST', headers: { Authorization: 'Bearer ' + token(), 'Content-Type': 'application/json' }, body: JSON.stringify({ email }) });
    const j = await r.json();
    resDiv.style.display = 'block';
    resDiv.style.background = j.success ? 'var(--good-bg, #e6f7ee)' : 'var(--bad-bg, #fdecec)';
    resDiv.textContent = j.success ? '✓ تم الإرسال بنجاح' : '✗ فشل: ' + (j.message || '');
  } catch (err) {
    resDiv.style.display = 'block';
    resDiv.textContent = '✗ خطأ اتصال';
  }
  btn.disabled = false;
  btn.textContent = 'إرسال تجريبي';
}
function esc(s) { return String(s || '').replace(/[<>"']/g, c => ({ '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
document.addEventListener('DOMContentLoaded', loadProviders);
</script>
