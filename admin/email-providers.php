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

<style>
  /* ===== تحسينات صفحة مزودي البريد ===== */
  .modal{position:fixed; inset:0; background:rgba(16,24,40,.55); display:none; align-items:center; justify-content:center; z-index:999;}
  .modal.open{display:flex;}
  .modal-box{background:var(--surface); border-radius:18px; padding:22px; margin:14px; max-height:90vh; overflow:auto; box-shadow:var(--shadow);}
  .badge{display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:800;}
  .badge.good{background:#dcfae6; color:#087443;}
  .badge.bad{background:#fee4e2; color:#b42318;}
  .btn.small, .btn.sm{padding:6px 11px; font-size:12px; border-radius:8px;}
  .ep-fieldset { border: 1px solid var(--line); border-radius: 14px; padding: 16px; margin-top: 4px; background: var(--surface2, #f8fafc) }
  [data-theme=dark] .ep-fieldset { background: #0f1726 }
  .ep-fieldset-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 800; margin: 0 0 12px; color: var(--text) }
  .ep-fieldset-title .ep-ico { width: 30px; height: 30px; border-radius: 9px; background: linear-gradient(135deg, #5b5ce2, #7c3aed); color: #fff; display: grid; place-items: center; font-size: 14px; font-weight: 900 }
  .ep-field { display: flex; flex-direction: column; gap: 5px; font-size: 13px; margin-bottom: 10px }
  .ep-field:last-child { margin-bottom: 0 }
  .ep-field .ep-label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--muted) }
  .ep-field .ep-label .req { color: var(--bad); font-weight: 900 }
  .ep-field .ep-label .opt { color: var(--muted); font-weight: 400; font-size: 11px }
  .ep-input { height: 42px; border: 1px solid var(--line); background: var(--surface); color: var(--text); border-radius: 10px; padding: 0 12px; outline: 0; width: 100% }
  .ep-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(91, 92, 226, .15) }
  .ep-input[readonly] { background: var(--surface2); cursor: default }
  .ep-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px }
  .ep-passwrap { position: relative; }
  .ep-passwrap .ep-input { padding-left: 40px; }
  .ep-eye { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 15px; color: var(--muted); background: none; border: 0; padding: 4px; line-height: 1 }
  .ep-eye:hover { color: var(--primary) }
  .ep-quick { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 9px 12px; border-radius: 10px; background: #eef2ff; border: 1px dashed #a5b4fc; font-size: 12.5px }
  [data-theme=dark] .ep-quick { background: #1b2550; border-color: #4f5fc9 }
  .ep-quick button { font-size: 12px; font-weight: 800; padding: 6px 12px; border-radius: 8px; background: #fff; color: #4f46e5; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  [data-theme=dark] .ep-quick button { background: #373f5c; color: #c7d2fe }
  .ep-quick button:hover { background: #eef2ff }
  .ep-toggle { display: flex; gap: 6px; align-items: center; font-size: 12.5px; }
  .ep-toggle input { margin: 0; }
  /* Toast */
  .ep-toast { position: fixed; top: 24px; left: 50%; transform: translateX(-50%) translateY(-120%); z-index: 200; padding: 12px 20px; border-radius: 12px; font-size: 13.5px; font-weight: 700; box-shadow: var(--shadow); transition: transform .3s cubic-bezier(.2, .9, .3, 1.2); max-width: 90vw }
  .ep-toast.show { transform: translateX(-50%) translateY(0) }
  .ep-toast.ok { background: #059669; color: #fff }
  .ep-toast.err { background: #dc2626; color: #fff }
  .ep-hint { color: var(--muted); font-size: 11px; line-height: 1.5; margin-top: 4px }
  @media (max-width: 760px) { .ep-row2 { grid-template-columns: 1fr } }
</style>

<div class="pagehead">
  <div>
    <h2>مزودو البريد الإلكتروني</h2>
    <p>إدارة مزودي إرسال رموز التحقق بالبريد (SMTP / HTTP REST). تستخدم عند تفعيل «التسجيل/الدخول بالبريد» وOTP البريد.</p>
  </div>
  <?php if (hasPermission($admin, 'email.providers.create')): ?>
  <button class="btn primary" onclick="openModal('add')">＋ إضافة مزود</button>
  <?php endif; ?>
</div>

<div class="stats" id="epStats"></div>

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

<!-- Toast -->
<div class="ep-toast" id="epToast"></div>

<!-- إضافة/تعديل مزود -->
<div class="modal" id="providerModal">
  <div class="modal-box" style="max-width:620px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 id="modalTitle" style="margin:0;">إضافة مزود بريد</h3>
      <button onclick="closeModal()" style="font-size:20px;">✕</button>
    </div>
        <form id="providerForm" onsubmit="saveProvider(event)">
      <input type="hidden" id="pfId" value="">
      <div class="ep-field">
        <span class="ep-label">اسم المزود <span class="req">*</span></span>
        <input class="ep-input" name="name" id="pfName" required maxlength="120" placeholder="مثال: Gmail SMTP">
      </div>

      <div class="ep-row2">
        <div class="ep-field">
          <span class="ep-label">نوع المزود <span class="req">*</span></span>
          <select class="ep-input" name="type" id="pfType" onchange="renderTypeFields()">
            <option value="smtp">SMTP</option>
            <option value="http_rest">HTTP REST (مزود مخصص)</option>
          </select>
        </div>
        <div class="ep-field">
          <span class="ep-label">الأولوية <span class="opt">(الأقل أولية يُرسل أولًا — ترتيب الاحتياط)</span></span>
          <input type="number" class="ep-input" name="priority" id="pfPriority" value="1" min="0" max="99">
        </div>
      </div>

      <div id="typeFields"></div>

      <div class="ep-row2">
        <div class="ep-field">
          <span class="ep-label">البريد المرسل منه (From) <span class="req">*</span></span>
          <input class="ep-input" name="from_email" id="pfFromEmail" required dir="ltr" placeholder="noreply@yourapp.com">
        </div>
        <div class="ep-field">
          <span class="ep-label">الاسم الظاهر للمرسل <span class="opt">(اختياري)</span></span>
          <input class="ep-input" name="from_name" id="pfFromName" placeholder="NOVA Messenger">
        </div>
      </div>

      <div class="ep-toggle">
        <input type="checkbox" name="is_default" id="pfDefault" value="1">
        <label for="pfDefault">الافتراضي لهذا النوع (يُستخدم أولًا عند الإرسال)</label>
      </div>

      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
        <button type="button" class="btn" onclick="closeModal()">إلغاء</button>
        <button type="submit" class="btn primary" id="saveBtn">حفظ</button>
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
      <p style="margin:0; font-size:13px; color:var(--muted);">سيتم إرسال رسالة تجريبية فعلية عبر إعدادات المزود للتأكد من سلامتها.</p>
      <div class="ep-field">
        <span class="ep-label">البريد الإلكتروني <span class="req">*</span></span>
        <input class="ep-input" name="email" id="testEmail" required dir="ltr" placeholder="you@example.com">
      </div>
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
function esc(s) { return String(s || '').replace(/[<>"']/g, c => ({ '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

/* ===== Toast ===== */
let toastTimer = null;
function toast(msg, kind) {
  const t = document.getElementById('epToast');
  t.textContent = msg;
  t.className = 'ep-toast ' + (kind === 'ok' ? 'ok' : 'err');
  clearTimeout(toastTimer);
  requestAnimationFrame(() => { t.classList.add('show'); toastTimer = setTimeout(() => t.classList.remove('show'), 3500); });
}

/* ===== Modal ===== */
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
  /* إعادة تعيين حقول النوع بعد بناء الحقول (innerHTML يمسح القيم) */
  if (provider) {
    const h = document.getElementById('pfHost');    if (h) h.value = provider.host || '';
    const p = document.getElementById('pfPort');    if (p) p.value = provider.port || 587;
    const e = document.getElementById('pfEncryption'); if (e) e.value = provider.encryption || 'tls';
    const u = document.getElementById('pfUsername'); if (u) u.value = provider.username || '';
    const w = document.getElementById('pfPassword'); if (w) w.value = '';
    const a = document.getElementById('pfApiUrl');   if (a) a.value = provider.api_base_url || '';
    const k = document.getElementById('pfApiKey');   if (k) k.value = '';
    const cfg = JSON.parse(provider.extra_config || '{}');
    const tf = document.getElementById('pfToField'); if (tf) tf.value = cfg.to_field || '';
    const tm = document.getElementById('pfTemplate'); if (tm) tm.value = cfg.template || '';
  }
  document.getElementById('providerModal').classList.add('open');
  document.getElementById('saveBtn').disabled = false;
  document.getElementById('saveBtn').textContent = 'حفظ';
}
function closeModal() {
  document.getElementById('providerModal').classList.remove('open');
  document.getElementById('providerForm').reset();
  document.getElementById('pfId').value = '';
}
function openTestModal(id, name) {
  document.getElementById('testId').value = id;
  document.getElementById('testEmail').value = '';
  document.getElementById('testResult').style.display = 'none';
  document.getElementById('testBtn').textContent = 'إرسال تجريبي';
  document.getElementById('testBtn').disabled = false;
  const t = document.querySelector('#testModal h3');
  if (t) t.textContent = 'اختبار: ' + (name || 'المزود');
  document.getElementById('testModal').classList.add('open');
}
function closeTestModal() { document.getElementById('testModal').classList.remove('open'); }

/* ===== حقول النوع (SMTP / REST) ===== */
function renderTypeFields(provider) {
  const type = document.getElementById('pfType').value;
  const wrap = document.getElementById('typeFields');
  const cfg = provider ? JSON.parse(provider.extra_config || '{}') : {};
  if (type === 'smtp') {
    const host = provider ? (provider.host || '') : 'smtp.gmail.com';
    const port = provider ? (provider.port || 587) : 587;
    const isGmail = !provider || host === 'smtp.gmail.com';
    wrap.innerHTML = `
      <div class="ep-fieldset">
        <p class="ep-fieldset-title"><span class="ep-ico">📧</span> إعدادات خادم SMTP</p>
        <div class="ep-quick" id="smtpPresets" style="display:${isGmail ? 'flex' : 'none'}; flex-wrap:wrap;">
          <span style="margin-inline-end:auto">⚡ تعبئة سريعة:</span>
          <button type="button" onclick="fillSmtpPreset('gmail')">Gmail</button>
          <button type="button" onclick="fillSmtpPreset('outlook')">Outlook</button>
          <button type="button" onclick="fillSmtpPreset('yahoo')">Yahoo</button>
        </div>
        <div class="ep-hint" style="margin-bottom:10px">💡 لإرسال Gmail فعّل «كلمة المرور التطبيقية» من حساب جوجل (الإعدادات ← الأمان)، ثم املأ المستخدم وكلمة المرور فقط.</div>
        <div class="ep-field">
          <span class="ep-label">خادم SMTP <span class="req">*</span> <span class="opt">host</span></span>
          <input class="ep-input" name="host" id="pfHost" value="${esc(host)}" dir="ltr" placeholder="smtp.gmail.com" oninput="autoFillSmtpHint()">
          <div class="ep-hint">خادم الإرسال. أمثلة: smtp.gmail.com — smtp-mail.outlook.com — smtp.mail.yahoo.com</div>
        </div>
        <div class="ep-row2">
          <div class="ep-field">
            <span class="ep-label">المنفذ <span class="req">*</span></span>
            <input type="number" class="ep-input" name="port" id="pfPort" value="${port}">
            <div class="ep-hint">587 مع TLS أو 465 مع SSL عادةً</div>
          </div>
          <div class="ep-field">
            <span class="ep-label">التشفير</span>
            <select class="ep-input" name="encryption" id="pfEncryption">
              <option value="tls" ${(!provider || provider.encryption === 'tls') ? 'selected' : ''}>TLS (STARTTLS)</option>
              <option value="ssl" ${provider?.encryption === 'ssl' ? 'selected' : ''}>SSL</option>
              <option value="none" ${provider?.encryption === 'none' ? 'selected' : ''}>بدون</option>
            </select>
          </div>
        </div>
        <div class="ep-field">
          <span class="ep-label">اسم المستخدم <span class="req">*</span> <span class="opt">غالبًا البريد الكامل</span></span>
          <input class="ep-input" name="username" id="pfUsername" value="${esc(provider ? provider.username || '' : '')}" dir="ltr" placeholder="you@gmail.com">
        </div>
        <div class="ep-field">
          <span class="ep-label">كلمة المرور <span class="req">${provider ? '<span class="opt">(اتركه فارغًا للإبقاء على القيمة الحالية)</span>' : '* <span class="opt">كلمة مرور تطبيقية App Password لمستخدمي Gmail</span>'}</span></span>
          <div class="ep-passwrap">
            <input type="password" class="ep-input" name="password" id="pfPassword" placeholder="${provider ? '(اتركه فارغًا للإبقاء على القيمة الحالية)' : ''}" dir="ltr">
            <button type="button" class="ep-eye" onclick="togglePass('pfPassword', this)" aria-label="إظهار/إخفاء">👁</button>
          </div>
          <div class="ep-hint">لن تُعرض في أي مكان — تُخزن مشفرة (AES-256-GCM) ولا تعود للواجهة أبدًا.</div>
        </div>
      </div>`;
    autoFillSmtpHint();
  } else {
    wrap.innerHTML = `
      <div class="ep-fieldset">
        <p class="ep-fieldset-title"><span class="ep-ico">⚡</span> إعدادات HTTP REST</p>
        <div class="ep-field">
          <span class="ep-label">رابط API <span class="req">*</span> <span class="opt">base url</span></span>
          <input class="ep-input" name="api_base_url" id="pfApiUrl" value="${esc(provider ? provider.api_base_url || '' : 'https://api.provider.com/v1/send')}" dir="ltr" placeholder="https://api.provider.com/v1/send">
        </div>
        <div class="ep-field">
          <span class="ep-label">API Key <span class="opt">(تُخزن مشفرة)</span> <span class="req">${provider ? '<span class="opt">(اتركه فارغًا للإبقاء على القيمة الحالية)</span>' : '*'}</span></span>
          <div class="ep-passwrap">
            <input type="password" class="ep-input" name="api_key" id="pfApiKey" placeholder="${provider ? '(اتركه فارغًا للإبقاء على القيمة الحالية)' : ''}" dir="ltr">
            <button type="button" class="ep-eye" onclick="togglePass('pfApiKey', this)" aria-label="إظهار/إخفاء">👁</button>
          </div>
        </div>
        <div class="ep-field">
          <span class="ep-label">حقل البريد في الـJSON <span class="opt">(default: to)</span></span>
          <input class="ep-input" name="to_field" id="pfToField" value="${esc(cfg.to_field || '')}" dir="ltr" placeholder="to">
        </div>
        <div class="ep-field">
          <span class="ep-label">قالب JSON <span class="opt">يُرسل كما هو في Body</span></span>
          <textarea name="template" id="pfTemplate" rows="3" class="ep-input" placeholder='{"to":"{{TO}}","subject":"{{SUBJECT}}","body":"{{BODY}}"}'>${esc(cfg.template || '')}</textarea>
          <div class="ep-hint">{{TO}} البريد · {{SUBJECT}} العنوان · {{BODY}} النص · {OTP} الرمز</div>
        </div>
      </div>`;
  }
}
/* ===== تعبئة سريعة لمزودات SMTP شائعة ===== */
const SMTP_PRESETS = {
  gmail:   { host: 'smtp.gmail.com',   port: 587, encryption: 'tls', note: 'استخدم كلمة المرور التطبيقية من حساب جوجل' },
  outlook: { host: 'smtp-mail.outlook.com', port: 587, encryption: 'tls', note: 'استخدم كلمة مرور حسابك أو كلمة مرور تطبيقية' },
  yahoo:   { host: 'smtp.mail.yahoo.com', port: 465, encryption: 'ssl', note: 'استخدم كلمة مرور تطبيقية من حساب Yahoo' },
};
function fillSmtpPreset(name) {
  const p = SMTP_PRESETS[name]; if (!p) return;
  const h = document.getElementById('pfHost');
  const pt = document.getElementById('pfPort');
  const enc = document.getElementById('pfEncryption');
  if (h) h.value = p.host;
  if (pt) pt.value = p.port;
  if (enc) enc.value = p.encryption;
  toast('تمت تعبئة ' + name + ' — ' + p.note, 'ok');
  autoFillSmtpHint();
}

function autoFillSmtpHint() {
  const host = (document.getElementById('pfHost')?.value || '').trim().toLowerCase();
  const hints = {
    'smtp.gmail.com': { port: 587, encryption: 'tls' },
    'smtp-mail.outlook.com': { port: 587, encryption: 'tls' },
    'smtp.office365.com': { port: 587, encryption: 'tls' },
    'smtp.mail.yahoo.com': { port: 465, encryption: 'ssl' },
    'smtp.hostinger.com': { port: 465, encryption: 'ssl' },
  };
  const h = hints[host];
  if (h && document.getElementById('pfPort').value === '587' && document.getElementById('pfEncryption').value === 'tls') {
    // only suggest when defaults are untouched for new provider
  }
  if (h) {
    const port = document.getElementById('pfPort');
    const enc = document.getElementById('pfEncryption');
    if (!document.getElementById('pfId').value) { port.value = h.port; enc.value = h.encryption; }
  }
}
function togglePass(id, btn) {
  const el = document.getElementById(id);
  if (el.type === 'password') { el.type = 'text'; btn.textContent = '🙈'; }
  else { el.type = 'password'; btn.textContent = '👁'; }
}

/* ===== تحميل المزودات ===== */
async function loadProviders() {
  try {
    const r = await fetch(API, { headers: { Authorization: 'Bearer ' + token() } });
    if (!r.ok) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">فشل التحميل (HTTP ' + r.status + ') — تأكد من تسجيل الدخول</td></tr>'; return; }
    const j = await r.json();
    const list = j.providers || [];
    renderStats(list);
    if (list.length === 0) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">لا توجد مزودات — أضف مزودًا لبدء الإرسال التلقائي</td></tr>'; return; }
    document.getElementById('providersBody').innerHTML = list.map(p => `
      <tr>
        <td>${p.id}</td>
        <td><b>${esc(p.name)}</b><br><small style="color:var(--muted)">${esc(p.from_email || '')}${p.host ? ' · ' + esc(p.host) + ':' + esc(p.port || '') : ''}</small></td>
        <td>${p.type === 'smtp' ? 'SMTP' : 'HTTP REST'}</td>
        <td><span class="badge ${p.status === 'enabled' ? 'good' : 'bad'}">${p.status === 'enabled' ? 'مفعّل' : 'معطّل'}</span></td>
        <td>${Number(p.is_default) ? '✓' : '—'}</td>
        <td><span style="color:var(--good)">${p.success_count}</span> / <span style="color:var(--bad)">${p.failure_count}</span></td>
        <td>${p.last_used_at || '—'}</td>
        <td style="white-space:nowrap;">
          <button class="btn small" onclick='openModal("edit", ${JSON.stringify(p).replace(/'/g, "\\'")})'>تعديل</button>
          <button class="btn small" onclick="toggle(${p.id}, '${p.status === 'enabled' ? 'disabled' : 'enabled'}')">${p.status === 'enabled' ? 'تعطيل' : 'تفعيل'}</button>
          <button class="btn small" onclick="openTestModal(${p.id}, '${esc(p.name).replace(/'/g, "\\'")}')">اختبار</button>
          <button class="btn small danger" onclick="del(${p.id})">حذف</button>
        </td>
      </tr>`).join('');
  } catch (e) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">خطأ اتصال</td></tr>'; }
}
function renderStats(list) {
  const total = list.length;
  const active = list.filter(p => p.status === 'enabled').length;
  const ok = list.reduce((s, p) => s + Number(p.success_count), 0);
  const fail = list.reduce((s, p) => s + Number(p.failure_count), 0);
  document.getElementById('epStats').innerHTML = `
    <div class="stat"><div class="ico" style="background:var(--surface2)">📨</div><div><b>${total}</b><small>إجمالي المزودات</small></div></div>
    <div class="stat"><div class="ico" style="background:#dcfae6">✓</div><div><b>${active}</b><small>مزود مفعّل</small></div></div>
    <div class="stat"><div class="ico" style="background:#dcfae6">📤</div><div><b>${ok}</b><small>إرسال ناجح</small></div></div>
    <div class="stat"><div class="ico" style="background:#fee4e2">✗</div><div><b>${fail}</b><small>إرسال فاشل</small></div></div>`;
}

/* ===== حفظ ===== */
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
  const saveBtn = document.getElementById('saveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'جارٍ الحفظ...';
  try {
    const url = id ? API + '/' + id : API;
    const method = id ? 'PUT' : 'POST';
    const r = await fetch(url, { method, headers: { Authorization: 'Bearer ' + token(), 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
    const j = await r.json();
    if (!j.success) { toast(j.message || 'فشل الحفظ', 'err'); saveBtn.disabled = false; saveBtn.textContent = 'حفظ'; return; }
    toast(id ? 'تم تحديث المزود بنجاح' : 'تمت إضافة المزود بنجاح', 'ok');
    closeModal();
    await loadProviders();
  } catch (err) {
    toast('خطأ اتصال — أعد المحاولة', 'err');
    saveBtn.disabled = false;
    saveBtn.textContent = 'حفظ';
  }
}

/* ===== تبديل / حذف / اختبار ===== */
async function toggle(id, status) {
  try {
    const r = await fetch(API + '/' + id + '/toggle', { method: 'POST', headers: { Authorization: 'Bearer ' + token(), 'Content-Type': 'application/json' }, body: JSON.stringify({ status }) });
    const j = await r.json();
    if (!j.success) { toast(j.message || 'فشل التغيير', 'err'); return; }
    toast(status === 'enabled' ? 'تم تفعيل المزود' : 'تم تعطيل المزود', 'ok');
    await loadProviders();
  } catch (err) { toast('خطأ اتصال', 'err'); }
}
async function del(id) {
  if (!confirm('هل أنت متأكد من حذف هذا المزود؟')) return;
  try {
    const r = await fetch(API + '/' + id, { method: 'DELETE', headers: { Authorization: 'Bearer ' + token() } });
    const j = await r.json();
    if (!j.success) { toast(j.message || 'فشل الحذف', 'err'); return; }
    toast('تم حذف المزود', 'ok');
    await loadProviders();
  } catch (err) { toast('خطأ اتصال', 'err'); }
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
    if (j.success) {
      resDiv.style.background = '#dcfae6';
      resDiv.style.color = '#087443';
      const ms = j.response_time_ms ? ` (${j.response_time_ms} مللي ثانية)` : '';
      resDiv.textContent = '✓ تم إرسال رسالة الاختبار بنجاح' + ms + ' — تحقق من صندوق الوارد (قد يصل إلى Spam).';
    } else {
      resDiv.style.background = '#fee4e2';
      resDiv.style.color = '#b42318';
      resDiv.textContent = '✗ فشل الاختبار: ' + (j.message || 'خطأ غير معروف');
    }
  } catch (err) {
    resDiv.style.display = 'block';
    resDiv.style.background = '#fee4e2';
    resDiv.style.color = '#b42318';
    resDiv.textContent = '✗ خطأ اتصال بالخادم';
  }
  btn.disabled = false;
  btn.textContent = 'إرسال تجريبي';
}

document.addEventListener('DOMContentLoaded', loadProviders);
</script>
