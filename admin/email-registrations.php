<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}
$admin = requireAdminLogin();
$pageTitle = 'طلبات تسجيل البريد';
$pdo = getAdminDB();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>طلبات تسجيل البريد الإلكتروني</h2>
    <p>طلبات التحقق برمز OTP المرسلة للبريد الإلكتروني. في وضع التسليم اليدوي يمكنك عرض الرمز وإرساله يدويًا.</p>
  </div>
  <button class="btn" onclick="loadRegs()">⟳ تحديث</button>
</div>

<div class="card panel" style="margin-bottom:16px;">
  <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:13px;">
    <div><b>إجمالي الطلبات اليوم:</b> <span id="statToday">—</span></div>
    <div><b>المعلّقة:</b> <span id="statPending">—</span></div>
    <div><b>تم التحقق:</b> <span id="statVerified">—</span></div>
    <div><b>منتهية/فشل:</b> <span id="statFailed">—</span></div>
  </div>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>البريد الإلكتروني</th>
        <th>الاسم</th>
        <th>الوضع</th>
        <th>المحاولات</th>
        <th>الصلاحية</th>
        <th>IP</th>
        <th>الوقت</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody id="regsBody">
      <tr><td colspan="9" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>
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
    <p style="font-size:13px; color:var(--muted); margin:0 0 8px;">أرسل هذا الرمز للمستخدم (بريد/تطبيق آخر):</p>
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
.status{padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;}
.status.pending{background:#fff3cd; color:#856404;}
.status.sent{background:#d1ecf1; color:#0c5460;}
.status.verified{background:#d4edda; color:#155724;}
.status.expired{background:#f8d7da; color:#721c24;}
.status.manual{background:#e2e3e5; color:#383d41;}
</style>

<script>
const API = '/api/v1/admin/email-registrations';
function token() { return localStorage.getItem('adminToken') || ''; }
let currentCode = '';

function statusLabel(s){
  const map = { pending: ['معلّق', 'pending'], sent: ['أُرسل', 'sent'], verified: ['تم التحقق', 'verified'], expired: ['منتهي', 'expired'], manual: ['يدوي', 'manual'], cancelled: ['مُلغي', 'expired'], delivery_failed: ['فشل التسليم', 'expired'] };
  const [label, cls] = map[s] || [s, 'manual'];
  return '<span class="status ' + cls + '">' + label + '</span>';
}

async function loadRegs() {
  document.getElementById('regsBody').innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>';
  try {
    const r = await fetch(API, { headers: { Authorization: 'Bearer ' + token() } });
    const j = await r.json();
    const regs = j.rows || [];
    const stats = j.stats || {};
    document.getElementById('statToday').textContent = stats.today ?? '—';
    document.getElementById('statPending').textContent = stats.pending ?? '—';
    document.getElementById('statVerified').textContent = stats.verified ?? '—';
    document.getElementById('statFailed').textContent = stats.failed ?? '—';
    if (regs.length === 0) { document.getElementById('regsBody').innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px;">لا توجد طلبات</td></tr>'; return; }
    document.getElementById('regsBody').innerHTML = regs.map(x => {
      const actions = ['pending', 'sent', 'manual', 'delivery_failed'].includes(x.status)
        ? `<button class="btn small" onclick="showCode(${x.id})">عرض الرمز</button>`
        : '';
      return `<tr>
        <td>${x.id}</td>
        <td dir="ltr" style="text-align:right;">${esc(x.email)}</td>
        <td>${esc(x.name || '—')}</td>
        <td>${statusLabel(x.status)}</td>
        <td>${x.attempts}</td>
        <td>${x.expires_at || '—'}</td>
        <td dir="ltr" style="text-align:right;">${esc(x.ip || '—')}</td>
        <td>${x.created_at || '—'}</td>
        <td style="white-space:nowrap;">${actions}
          <button class="btn small danger" onclick="cancel(${x.id})">إلغاء</button>
        </td>
      </tr>`;
    }).join('');
  } catch (e) { document.getElementById('regsBody').innerHTML = '<tr><td colspan="9" style="text-align:center;">خطأ اتصال</td></tr>'; }
}

async function showCode(id) {
  try {
    const r = await fetch(API + '/' + id + '/code', { headers: { Authorization: 'Bearer ' + token() } });
    const j = await r.json();
    if (!j.success) { alert(j.message || 'فشل عرض الرمز'); return; }
    currentCode = j.otp_code || '';
    document.getElementById('codeDisplay').textContent = currentCode || '—';
    document.getElementById('codeExpiry').textContent = j.expires_at || '—';
    document.getElementById('codeModal').classList.add('open');
  } catch (e) { alert('خطأ اتصال'); }
}

function copyCode() {
  if (!currentCode) return;
  navigator.clipboard.writeText(currentCode).then(() => {
    const d = document.getElementById('codeDisplay');
    const old = d.textContent; d.textContent = 'تم النسخ ✓';
    setTimeout(() => d.textContent = old, 1200);
  }).catch(() => {});
}

async function cancel(id) {
  if (!confirm('إلغاء هذا الطلب؟')) return;
  const r = await fetch(API + '/' + id + '/cancel', { method: 'POST', headers: { Authorization: 'Bearer ' + token(), 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
  const j = await r.json();
  if (!j.success) { alert(j.message || 'فشل الإلغاء'); return; }
  await loadRegs();
}

function esc(s) { return String(s || '').replace(/[<>"']/g, c => ({ '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

document.addEventListener('DOMContentLoaded', loadRegs);
setInterval(loadRegs, 30000);
</script>
