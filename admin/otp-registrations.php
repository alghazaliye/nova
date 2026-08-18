<?php
/**
 * NOVA Messenger Admin — طلبات التسجيل (OTP)
 * عرض الطلبات المعلقة، عرض رمز OTP اليدوي، التأكيد اليدوي، الإلغاء
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}
$admin = requireAdminLogin();
$pageTitle = 'طلبات التسجيل';
$pdo = getAdminDB();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>طلبات التسجيل</h2>
    <p>طلبات التحقق بالـOTP المعلقة والمكتملة. في وضع التسليم اليدوي يظهر زر عرض الرمز لإرساله للمستخدم يدويًا.</p>
  </div>
  <button class="btn" onclick="loadRegs()">⟳ تحديث</button>
</div>

<div class="card panel" style="margin-bottom:16px;">
  <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:13px;">
    <div><b>إجمالي الطلبات اليوم:</b> <span id="statToday">—</span></div>
    <div><b>المعلّقة:</b> <span id="statPending">—</span></div>
    <div><b>تم التحقق:</b> <span id="statVerified">—</span></div>
    <div><b>فشل الإرسال:</b> <span id="statFailed">—</span></div>
  </div>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>الرقم</th>
        <th>الاسم</th>
        <th>الوضع</th>
        <th>حالة التسليم</th>
        <th>المحاولات</th>
        <th>الصلاحية</th>
        <th>IP</th>
        <th>الوقت</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody id="regsBody">
      <tr><td colspan="10" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>
    </tbody>
  </table>
</div>

<?php if ($totalPages ?? 0 > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= ($totalPages ?? 0); $i++): ?>
    <a href="?page=<?= $i ?>" class="page-btn <?= $i === ($page ?? 1) ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Modal عرض رمز OTP -->
<div class="modal" id="codeModal">
  <div class="modal-box" style="max-width:400px; text-align:center;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
      <h3 style="margin:0;">رمز التحقق</h3>
      <button onclick="document.getElementById('codeModal').classList.remove('open')" style="font-size:20px;">✕</button>
    </div>
    <p style="font-size:13px; color:var(--muted); margin:0 0 8px;">أرسل هذا الرمز للمستخدم (هاتف/واتساب):</p>
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
.dcode{font-size:13px; color:var(--muted);}
</style>

<script>
let regs = [], currentPage = 1;

function b(s, cls){ return s ? '<span class="status ' + cls + '">' + s + '</span>' : '<span style="color:var(--muted)">—</span>'; }

function modeLabel(m){
  const m2 = {auto:'تلقائي', manual:'يدوي', auto_fallback:'تلقائي+يدوي'};
  return m2[m] || m || '—';
}

async function loadRegs(page = 1){
  currentPage = page;
  try {
    const res = await fetch(API + '/admin/otp/registrations?page=' + page, {headers: {'Authorization': 'Bearer ' + (localStorage.getItem('adminToken') || '')}});
    const data = await res.json();
    regs = data.items || [];
    renderRegs();
    const s = data.stats || {};
    document.getElementById('statToday').textContent = s.today_total ?? '—';
    document.getElementById('statPending').textContent = s.pending ?? '—';
    document.getElementById('statVerified').textContent = s.verified ?? '—';
    document.getElementById('statFailed').textContent = s.delivery_failed ?? '—';
    renderPagination(data);
  } catch (e) {
    document.getElementById('regsBody').innerHTML = '<tr><td colspan="10" style="text-align:center;padding:24px;">فشل التحميل</td></tr>';
  }
}

function renderRegs(){
  const tbody = document.getElementById('regsBody');
  if (regs.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:24px;">لا توجد طلبات.</td></tr>';
    return;
  }
  tbody.innerHTML = regs.map(r => {
    const pending = ['pending','sent','manual','delivery_failed'].includes(r.status);
    const canView = pending && (r.delivery_mode === 'manual' || r.status === 'manual');
    return `<tr style="${r.status === 'pending' ? '' : ''}">
      <td>${r.id}</td>
      <td dir="ltr" style="text-align:right;">${esc(r.phone_number)}</td>
      <td>${esc(r.name || '—')}</td>
      <td>${b(modeLabel(r.delivery_mode), '')}</td>
      <td>${statusFor(r.status)}</td>
      <td>${r.attempts ?? 0}/${r.max_attempts ?? 5}</td>
      <td style="font-size:12px; color:var(--muted);">${r.expires_at ? fmt(r.expires_at) : '—'}</td>
      <td dir="ltr" style="text-align:right; font-size:11px;">${r.ip_address || '—'}</td>
      <td style="font-size:12px; color:var(--muted);">${fmt(r.created_at)}</td>
      <td>
        <div style="display:flex; gap:5px; flex-wrap:wrap;">
          ${pending ? `<button class="btn sm" onclick="viewCode(${r.id})" ${canView ? '' : 'disabled style=opacity:.4'} title="عرض الرمز اليدوي">🔑 عرض الرمز</button>` : ''}
          ${pending ? `<button class="btn sm" style="background:rgba(18,183,106,.1);color:var(--good);" onclick="manualVerify(${r.id})">✔ تأكيد</button>` : ''}
          ${pending ? `<button class="btn danger sm" onclick="cancel(${r.id})">✖ إلغاء</button>` : ''}
        </div>
      </td>
    </tr>`;
  }).join('');
}

function statusFor(s){
  const map = {
    pending: ['قيد الإنشاء', 'offline'],
    sent: ['مُرسل', 'online'],
    manual: ['يدوي', 'warn'],
    delivery_failed: ['فشل التسليم', 'bad'],
    verified: ['تم التحقق', 'online'],
    expired: ['منتهي', 'muted'],
    blocked: ['محظور', 'bad'],
    cancelled: ['ملغي', 'muted']
  };
  const [label, cls] = map[s] || [s, ''];
  return '<span class="status ' + cls + '">' + label + '</span>';
}

function fmt(d){ return d ? new Date(d).toLocaleString('ar-SA', {hour12:false}) : ''; }
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function viewCode(id){
  try {
    const res = await fetch(API + '/admin/otp/registrations/' + id + '/code', {headers: {'Authorization': 'Bearer ' + (localStorage.getItem('adminToken') || '')}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'لا يمكن عرض الرمز');
    document.getElementById('codeDisplay').textContent = j.otp_code;
    document.getElementById('codeExpiry').textContent = j.expires_at ? fmt(j.expires_at) : '—';
    document.getElementById('codeModal').classList.add('open');
  } catch (e) { alert(e.message); }
}

function copyCode(){
  navigator.clipboard.writeText(document.getElementById('codeDisplay').textContent.trim())
    .then(() => alert('تم نسخ الرمز'))
    .catch(() => {});
}

async function manualVerify(id){
  if (!confirm('تأكيد هذا التسجيل يدويًا وإنشاء الحساب؟')) return;
  try {
    const res = await fetch(API + '/admin/otp/registrations/' + id + '/verify', {
      method: 'POST', headers: {'Authorization': 'Bearer ' + (localStorage.getItem('adminToken') || '')}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل التأكيد');
    await loadRegs(currentPage);
  } catch (e) { alert(e.message); }
}

async function cancel(id){
  if (!confirm('إلغاء هذا الطلب؟')) return;
  try {
    const res = await fetch(API + '/admin/otp/registrations/' + id + '/cancel', {
      method: 'POST', headers: {'Authorization': 'Bearer ' + (localStorage.getItem('adminToken') || '')}});
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل الإلغاء');
    await loadRegs(currentPage);
  } catch (e) { alert(e.message); }
}

function renderPagination(data){
  // handled by default one page for simplicity
}

loadRegs();
</script>
