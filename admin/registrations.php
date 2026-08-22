<?php
/**
 * NOVA Messenger Admin — طلبات التسجيل (موحد: الرسائل + البريد)
 * تبويبان في صفحة واحدة: OTP الرسائل النصية | OTP البريد الإلكتروني
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}
requireAdminLogin();
$pageTitle = 'طلبات التسجيل';
$currentPage = 'registrations';

// Mark all pending as seen when this page is opened
try {
    $pdo = getAdminDB();
    $pdo->exec("UPDATE otp_verifications SET seen_at = datetime('now') WHERE seen_at IS NULL AND status IN ('pending', 'sent', 'manual', 'delivery_failed')");
    $pdo->exec("UPDATE email_verification_codes SET seen_at = datetime('now') WHERE seen_at IS NULL AND status IN ('pending', 'sent', 'manual', 'delivery_failed')");
} catch (\Throwable $e) {}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>طلبات التسجيل</h2>
    <p>طلبات التحقق بالـOTP للرسائل النصية والبريد الإلكتروني في مكان واحد. في وضع التسليم اليدوي يمكنك عرض الرمز وإرساله يدويًا.</p>
  </div>
  <button class="btn" onclick="loadRegs()">⟳ تحديث</button>
</div>

<!-- تبويبات: رسائل / بريد -->
<div class="card panel" style="margin-bottom:16px;">
  <div style="display:flex; gap:10px;">
    <button class="tab-btn active" id="tabPhone" onclick="switchTab('phone')">📱 OTP الرسائل النصية</button>
    <button class="tab-btn" id="tabEmail" onclick="switchTab('email')">✉ OTP البريد الإلكتروني</button>
  </div>
</div>

<!-- إحصائيات -->
<div class="card panel" style="margin-bottom:16px;">
  <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:13px;">
    <div><b>إجمالي الطلبات:</b> <span id="statToday">—</span></div>
    <div><b>المعلّقة:</b> <span id="statPending">—</span></div>
    <div><b>تم التحقق:</b> <span id="statVerified">—</span></div>
    <div><b>منتهية/فشل:</b> <span id="statFailed">—</span></div>
  </div>
</div>

<!-- جدول موحد -->
<div class="card panel tablewrap">
  <table class="table">
    <thead id="regsHead"></thead>
    <tbody id="regsBody">
      <tr><td colspan="10" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Modal عرض رمز OTP -->
<div class="modal" id="codeModal">
  <div class="modal-box" style="max-width:400px; text-align:center;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
      <h3 style="margin:0;">رمز التحقق</h3>
      <button onclick="document.getElementById('codeModal').classList.remove('open')" style="font-size:20px;">✕</button>
    </div>
    <p style="font-size:13px; color:var(--muted); margin:0 0 8px;" id="codeHint">أرسل هذا الرمز للمستخدم:</p>
    <div id="codeDisplay" style="font-size:42px; font-weight:900; letter-spacing:8px; color:var(--primary); background:var(--surface2); border-radius:14px; padding:18px; margin:12px 0;">—</div>
    <p style="font-size:12px; color:var(--muted);">صالح حتى: <b id="codeExpiry">—</b></p>
    <div style="display:flex; gap:8px; justify-content:center; margin-top:10px;">
      <button class="btn primary" onclick="copyCode()">⧉ نسخ الرمز</button>
      <button class="btn" onclick="document.getElementById('codeModal').classList.remove('open')">إغلاق</button>
    </div>
  </div>
</div>

<style>
.modal{position:fixed; inset:0; background:rgba(16,24,40,.55); display:none; align-items:center; justify-content:center; z-index:999;}
.modal.open{display:flex;}
.modal-box{background:var(--surface); border-radius:18px; padding:22px; margin:14px; max-height:90vh; overflow:auto; box-shadow:var(--shadow);}
.tab-btn{padding:10px 18px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:var(--bg-secondary,#f0f2f5); color:var(--text,#222);}
.tab-btn.active{background:var(--primary,#4f46e5); color:#fff;}
.status{padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;}
.status.pending,.status.offline{background:#fff3cd; color:#856404;}
.status.sent{background:#d1ecf1; color:#0c5460;}
.status.manual,.status.warn{background:#e2e3e5; color:#383d41;}
.status.verified,.status.online{background:#d4edda; color:#155724;}
.status.expired,.status.muted{background:#f8d7da; color:#721c24;}
.status.bad,.status.delivery_failed{background:#f8d7da; color:#721c24;}
</style>

<script>
const OTP_API = '/admin/otp/registrations';
const EMAIL_API = '/admin/email-registrations';
function token() { return localStorage.getItem('adminToken') || ''; }
let tab = '<?= in_array($_GET['tab'] ?? '', ['phone', 'email'], true) ? $_GET['tab'] : 'phone' ?>';
let currentCode = '';
let pinnedId = null;

const PHONE_HEAD = `<tr><th>#</th><th>الرقم</th><th>الاسم</th><th>وضع التسليم</th><th>الحالة</th><th>المحاولات</th><th>الصلاحية</th><th>IP</th><th>الوقت</th><th>الإجراءات</th></tr>`;
const EMAIL_HEAD = `<tr><th>#</th><th>البريد الإلكتروني</th><th>الاسم</th><th>الحالة</th><th>المحاولات</th><th>الصلاحية</th><th>IP</th><th>الوقت</th><th>الإجراءات</th></tr>`;

function switchTab(t){
  tab = t;
  document.getElementById('tabPhone').classList.toggle('active', t === 'phone');
  document.getElementById('tabEmail').classList.toggle('active', t === 'email');
  loadRegs();
}

function phoneStatusLabel(s){
  const map = {
    pending: ['قيد الإنشاء', 'pending'],
    sent: ['مُرسل', 'sent'],
    manual: ['يدوي', 'manual'],
    delivery_failed: ['فشل التسليم', 'delivery_failed'],
    verified: ['تم التحقق', 'verified'],
    expired: ['منتهي', 'expired'],
    blocked: ['محظور', 'expired'],
    cancelled: ['ملغي', 'expired']
  };
  const [label, cls] = map[s] || [s || '—', 'manual'];
  return `<span class="status ${cls}">${label}</span>`;
}

function emailStatusLabel(s){
  const map = { pending: ['معلّق', 'pending'], sent: ['أُرسل', 'sent'], verified: ['تم التحقق', 'verified'], expired: ['منتهي', 'expired'], manual: ['يدوي', 'manual'], cancelled: ['مُلغي', 'expired'], delivery_failed: ['فشل التسليم', 'delivery_failed'] };
  const [label, cls] = map[s] || [s || '—', 'manual'];
  return `<span class="status ${cls}">${label}</span>`;
}

async function loadRegs(){
  const tbody = document.getElementById('regsBody');
  const thead = document.getElementById('regsHead');
  thead.innerHTML = tab === 'phone' ? PHONE_HEAD : EMAIL_HEAD;
  tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>`;
  try {
    if (tab === 'phone') {
      const res = await fetch(OTP_API, {headers: {'X-Admin-Auth': token()}});
      const data = await res.json();
      if (!res.ok) { tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;padding:24px;">${data.message || 'فشل التحميل (HTTP ' + res.status + ')'}</td></tr>`; return; }
      const regs = data.rows || [];
      const statFrom = (st) => regs.filter(r => r.status === st).length;
      const pending = regs.filter(r => ['pending','sent','manual','delivery_failed'].includes(r.status));
      document.getElementById('statToday').textContent = regs.length;
      document.getElementById('statPending').textContent = pending.length;
      document.getElementById('statVerified').textContent = statFrom('verified');
      document.getElementById('statFailed').textContent = statFrom('expired') + statFrom('blocked') + statFrom('delivery_failed');
      if (regs.length === 0) { tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:24px;">لا توجد طلبات.</td></tr>'; return; }
      tbody.innerHTML = regs.map(r => {
        const pending = ['pending','sent','manual','delivery_failed'].includes(r.status);
        // عرض الرمز متاح لأي طلب نشط (معلق / مُرسل / يدوي / فشل التسليم) سواء كان التسليم تلقائيًا أو يدويًا
        const canView = pending && (r.delivery_mode === 'manual' || r.delivery_mode === 'auto_fallback' || r.status === 'manual' || r.status === 'sent' || r.status === 'pending' || r.status === 'delivery_failed');
        // الاسم يظهر فقط بعد إتمام التحقق بنجاح
        const nameCell = pending ? '<td>—</td>' : `<td>${esc(r.name || '—')}</td>`;
        return `<tr>
          <td>${r.id}</td>
          <td dir="ltr" style="text-align:right;">${esc(r.phone_number)}</td>
          ${nameCell}
          <td>${modeLabel(r.delivery_mode)}</td>
          <td>${phoneStatusLabel(r.status)}</td>
	          <td>${r.attempts ?? 0}/${r.max_attempts ?? 5}</td>
	          <td style="font-size:12px; color:var(--muted);">${r.expires_at ? fmt(r.expires_at) : '—'}</td>
	          <td dir="ltr" style="text-align:center; font-size:11px; color:var(--muted);">${r.ip_address || '—'}</td>
          <td style="font-size:12px; color:var(--muted);">${fmt(r.created_at)}</td>
          <td>
            <div style="display:flex; gap:5px; flex-wrap:wrap;">
              ${pending ? `<button class="btn sm" onclick="viewPhoneCode(${r.id})" title="عرض الرمز">🔑 عرض الرمز</button>` : ''}
              ${pending ? `<button class="btn danger sm" onclick="cancelPhone(${r.id})">✖ إلغاء</button>` : ''}
            </div>
          </td>
        </tr>`;
      }).join('');
    } else {
      const res = await fetch(EMAIL_API, {headers: {'Authorization': 'Bearer ' + token()}});
      const j = await res.json();
      const regs = j.rows || [];
      const stats = j.stats || {};
      document.getElementById('statToday').textContent = stats.today ?? regs.length;
      document.getElementById('statPending').textContent = stats.pending ?? '—';
      document.getElementById('statVerified').textContent = stats.verified ?? '—';
      document.getElementById('statFailed').textContent = stats.failed ?? '—';
      if (regs.length === 0) { tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px;">لا توجد طلبات.</td></tr>'; return; }
      tbody.innerHTML = regs.map(x => {
        const actions = ['pending', 'sent', 'manual', 'delivery_failed'].includes(x.status)
          ? `<button class="btn small" onclick="viewEmailCode(${x.id})">عرض الرمز</button>`
          : '';
        return `<tr>
          <td>${x.id}</td>
          <td dir="ltr" style="text-align:right;">${esc(x.email)}</td>
          <td>${esc(x.name || '—')}</td>
          <td>${emailStatusLabel(x.status)}</td>
          <td>${x.attempts ?? 0}</td>
          <td>${x.expires_at ? fmt(x.expires_at) : '—'}</td>
          <td dir="ltr" style="text-align:right;">${esc(x.ip || '—')}</td>
          <td>${x.created_at ? fmt(x.created_at) : '—'}</td>
          <td style="white-space:nowrap;">${actions}
            <button class="btn small danger" onclick="cancelEmail(${x.id})">إلغاء</button>
          </td>
        </tr>`;
      }).join('');
    }
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:24px;">خطأ اتصال</td></tr>';
  }
}

function modeLabel(m){
  const m2 = {auto:'تلقائي', manual:'يدوي', auto_fallback:'تلقائي+يدوي'};
  return m2[m] || m || '—';
}

async function viewPhoneCode(id){
  try {
    const res = await fetch(OTP_API + '/' + id + '/code', {headers: {'X-Admin-Auth': token()}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'لا يمكن عرض الرمز');
    currentCode = j.otp_code || '';
    pinnedId = id;
    document.getElementById('codeDisplay').textContent = currentCode || '—';
    document.getElementById('codeExpiry').textContent = j.expires_at ? fmt(j.expires_at) : '—';
    document.getElementById('codeHint').textContent = 'أرسل هذا الرمز للمستخدم (هاتف/واتساب):';
    document.getElementById('codeModal').classList.add('open');
  } catch (e) { alert(e.message); }
}

async function viewEmailCode(id){
  try {
    const res = await fetch(EMAIL_API + '/' + id + '/code', {headers: {'Authorization': 'Bearer ' + token()}});
    const j = await res.json();
    if (!j.success) { alert(j.message || 'فشل عرض الرمز'); return; }
    currentCode = j.otp_code || '';
    pinnedId = id;
    document.getElementById('codeDisplay').textContent = currentCode || '—';
    document.getElementById('codeExpiry').textContent = j.expires_at || '—';
    document.getElementById('codeHint').textContent = 'أرسل هذا الرمز للمستخدم (بريد/تطبيق آخر):';
    document.getElementById('codeModal').classList.add('open');
  } catch (e) { alert('خطأ اتصال'); }
}

function copyCode(){
  if (!currentCode) return;
  navigator.clipboard.writeText(currentCode).then(() => {
    const d = document.getElementById('codeDisplay');
    const old = d.textContent; d.textContent = 'تم النسخ ✓';
    setTimeout(() => d.textContent = old, 1200);
  }).catch(() => {});
}

async function manualVerify(id){
  if (!confirm('تأكيد هذا التسجيل يدويًا وإنشاء الحساب؟')) return;
  try {
    const res = await fetch(OTP_API + '/' + id + '/verify', {method: 'POST', headers: {'X-Admin-Auth': token()}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل التأكيد');
    await loadRegs();
    // إبقاء الرمز ظاهرًا حتى يسلمه المشرف للمستخدم — إعادة فتح النافذة إذا كانت مفتوحة
    if (pinnedId === id && document.getElementById('codeModal').classList.contains('open')) {
      document.getElementById('codeModal').classList.add('open');
      showHint('تم تأكيد التسجيل؛ الرمز ما زال ظاهرًا لتسليمه للمستخدم ✓');
    }
  } catch (e) { alert(e.message); }
}

function showHint(msg){
  const el = document.getElementById('codeHint');
  const old = el.textContent;
  el.textContent = msg;
  el.style.color = 'var(--good,#12b76a)';
  setTimeout(() => { el.textContent = old; el.style.color = ''; }, 4000);
}

async function cancelPhone(id){
  if (!confirm('إلغاء هذا الطلب؟')) return;
  try {
    const res = await fetch(OTP_API + '/' + id + '/cancel', {method: 'POST', headers: {'X-Admin-Auth': token()}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل الإلغاء');
    await loadRegs();
  } catch (e) { alert(e.message); }
}

async function cancelEmail(id){
  if (!confirm('إلغاء هذا الطلب؟')) return;
  try {
    const res = await fetch(EMAIL_API + '/' + id + '/cancel', {method: 'POST', headers: {'Authorization': 'Bearer ' + token(), 'Content-Type': 'application/json'}, body: '{}'});
    const j = await res.json();
    if (!j.success) { alert(j.message || 'فشل الإلغاء'); return; }
    await loadRegs();
  } catch (e) { alert('خطأ اتصال'); }
}

// التواريخ في قاعدة البيانات UTC نصية بصيغة 'Y-m-d H:i:s' — نطبعها إلى UTC أولًا ثم نحولها للمنطقة الزمنية المعتمدة
function fmt(d){ if(!d) return ''; try { const raw = String(d).trim(); const iso = (raw.length >= 19 && raw[10] === ' ' && !raw.includes('T') && !raw.endsWith('Z')) ? raw + 'Z' : raw; return new Date(iso).toLocaleString('ar-SA', {timeZone: window.NovaTZ || 'Asia/Riyadh', hour12:false}); } catch(e){ return new Date(d).toLocaleString('ar-SA', {hour12:false}); } }
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

document.addEventListener('DOMContentLoaded', loadRegs);
setInterval(loadRegs, 30000);
</script>
