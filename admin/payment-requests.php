<?php
/**
 * NOVA Messenger — Admin: Subscription Requests (Payment Requests)
 * List, approve, and reject user subscription requests.
 */
declare(strict_types=1);
require_once __DIR__ . '/../backend/public/router.php';
$adminToken = $_COOKIE['nova_admin_token'] ?? '';
$adminApiBase = trim((string)($_ENV['API_BASE_URL'] ?? ''), '/') ?: '';

// Fetch requests via admin API
$rows = [];
$json = '';
try {
    $ch = curl_init($adminApiBase . '/api/v1/admin/payment-requests');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $adminToken],
    ]);
    $json = (string)curl_exec($ch);
    curl_close($ch);
    $data = json_decode($json, true);
    if (!empty($data['success'])) {
        $rows = (array)$data['data'];
    }
} catch (\Throwable $e) {
    $json = 'error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>طلبات الاشتراك — لوحة نوفا</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0b1120;--card:#121a2e;--card2:#1a2440;--line:#25304e;--tx:#e6ecff;--tx2:#8fa0c8;--acc:#5b8def;--ok:#2ecc71;--no:#e74c5e;--warn:#f0a23b;}
  *{margin:0;padding:0;box-sizing:border-box;font-family:'Tajawal',sans-serif}
  body{background:var(--bg);color:var(--tx);min-height:100vh}
  .wrap{max-width:1100px;margin:0 auto;padding:28px 16px}
  .head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
  h1{font-size:22px;font-weight:700}
  .btn{background:var(--acc);color:#fff;border:none;border-radius:10px;padding:10px 18px;font-size:15px;font-weight:700;cursor:pointer}
  .btn.ok{background:var(--ok)}.btn.no{background:var(--no)}.btn.ghost{background:transparent;border:1px solid var(--line)}
  table{width:100%;border-collapse:collapse;background:var(--card);border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.35)}
  th,td{padding:12px 14px;text-align:right;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
  th{background:var(--card2);color:var(--tx2);font-weight:700;font-size:13px}
  tr:last-child td{border-bottom:none}
  .pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700}
  .pill.pending{background:#3a3a1a;color:#f0a23b}
  .pill.approved{background:#12352a;color:#2ecc71}
  .pill.rejected{background:#3a1520;color:#e74c5e}
  .acts{display:flex;gap:8px;flex-wrap:wrap}
  .mini{padding:7px 13px;font-size:13px}
  .modal{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:50;padding:16px}
  .modal.open{display:flex}
  .box{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;max-width:420px;width:100%}
  .box h3{margin-bottom:14px;font-size:17px}
  .box input,.box textarea,.box select{width:100%;background:var(--card2);border:1px solid var(--line);border-radius:10px;color:var(--tx);padding:10px 12px;font-size:14px;margin-bottom:12px}
  .note{margin-bottom:12px;font-size:14px;color:var(--tx2)}
  .toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--card2);border:1px solid var(--line);padding:12px 20px;border-radius:12px;font-size:14px;display:none;z-index:60}
  .receipt a{color:var(--acc)}
  .empty{padding:36px;text-align:center;color:var(--tx2)}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>طلبات الاشتراك (الباقات)</h1>
    <button class="btn ghost" onclick="location.href='admin-panel.php'">← لوحة التحكم</button>
  </div>

  <table id="tbl">
    <thead><tr>
      <th>#</th><th>المستخدم</th><th>الهاتف</th><th>الباقة</th><th>السعر</th><th>تاريخ الطلب</th><th>الإيصال</th><th>الحالة</th><th>إجراء</th>
    </tr></thead>
    <tbody id="rows">
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="empty">لا توجد طلبات حاليًا</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars((string)($r['user_name'] ?? '')) ?></td>
          <td dir="ltr"><?= htmlspecialchars((string)($r['user_phone'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['plan_name'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['price'] ?? '')) ?> <?= htmlspecialchars((string)($r['currency'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['created_at'] ?? '')) ?></td>
          <td class="receipt">
            <?php if (!empty($r['receipt_path'])): ?>
              <a href="../backend/storage/receipts/<?= rawurlencode((string)$r['receipt_path']) ?>" target="_blank">عرض الإيصال</a>
            <?php else: ?>
              <span style="color:var(--tx2)">لا يوجد</span>
            <?php endif; ?>
          </td>
          <td><span class="pill <?= htmlspecialchars((string)($r['status'] ?? '')) ?>"><?= htmlspecialchars((string)($r['status'] ?? '')) ?></span></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
              <div class="acts">
                <button class="btn ok mini" onclick="doAction(<?= (int)$r['id'] ?>,'approve')">قبول وتفعيل</button>
                <button class="btn no mini" onclick="openReject(<?= (int)$r['id'] ?>)">رفض</button>
              </div>
            <?php else: ?>
              <span style="color:var(--tx2);font-size:13px">مُراجع</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Reject modal -->
<div class="modal" id="rejectModal">
  <div class="box">
    <h3>رفض طلب الاشتراك</h3>
    <div class="note">سيُشعر المستخدم بالرفض مع ملاحظة السبب (اختياري).</div>
    <textarea id="rejectNote" rows="3" placeholder="ملاحظة للمشرف (اختياري)"></textarea>
    <div class="acts">
      <button class="btn no" onclick="submitReject()">إرسال الرفض</button>
      <button class="btn ghost" onclick="closeReject()">إلغاء</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const API='<?= $adminApiBase ?>/api/v1';
let pendingId=null;
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.style.display='block';setTimeout(()=>t.style.display='none',3000)}
function doAction(id,act){
  fetch(API+'/admin/payment-requests/'+id+'/'+act,{method:'POST',headers:{'Authorization':'Bearer <?= $adminToken ?>'}})
  .then(r=>r.json()).then(d=>{
    if(d&&d.success){toast(act==='approve'?'تم قبول الطلب وتفعيل الاشتراك':'تم رفض الطلب');location.reload()}
    else toast(d&&d.message?d.message:'فشل التنفيذ')
  }).catch(()=>toast('خطأ في الاتصال'))
}
function openReject(id){pendingId=id;document.getElementById('rejectModal').classList.add('open')}
function closeReject(){pendingId=null;document.getElementById('rejectModal').classList.remove('open')}
function submitReject(){
  fetch(API+'/admin/payment-requests/'+pendingId+'/reject',{method:'POST',headers:{'Authorization':'Bearer <?= $adminToken ?>','Content-Type':'application/json'},body:JSON.stringify({admin_note:document.getElementById('rejectNote').value})})
  .then(r=>r.json()).then(d=>{closeReject();toast(d&&d.success?'تم رفض الطلب':'فشل التنفيذ');if(d&&d.success)setTimeout(()=>location.reload(),1200)})
  .catch(()=>toast('خطأ في الاتصال'))
}
</script>
</body>
</html>
