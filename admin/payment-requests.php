<?php
/**
 * NOVA Messenger Admin - طلبات الاشتراك (الباقات)
 * عرض طلبات الاشتراك المرسلة من المستخدمين مع قبول/رفض وتفعيل الاشتراك.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
requirePermission($admin, 'payment_requests.view');
$pageTitle = 'طلبات الاشتراك';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$pdo = getAdminDB();

$message = '';
$error   = '';

// ===== جلب الطلبات (GET only — عرض) =====
$statusFilter = (string)($_GET['status'] ?? '');
$query = 'SELECT pr.id, pr.user_id, pr.plan_id, pr.status, pr.receipt_path,
                 pr.admin_note, pr.reviewed_by, pr.reviewed_at, pr.created_at,
                 COALESCE(u.name, u.phone, "—") AS user_name,
                 COALESCE(u.phone, "—") AS user_phone,
                 COALESCE(p.name, "—") AS plan_name,
                 COALESCE(p.price, 0) AS plan_price,
                 COALESCE(p.currency, "SAR") AS plan_currency
          FROM payment_requests pr
          LEFT JOIN users u ON u.id = pr.user_id
          LEFT JOIN plans p ON p.id = pr.plan_id
          ORDER BY CASE pr.status WHEN \'pending\' THEN 0 ELSE 1 END, pr.created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute();
$rows = $stmt->fetchAll();

$pending  = 0;
$approved = 0;
$rejected = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'pending') ++$pending;
    elseif ($r['status'] === 'approved') ++$approved;
    elseif ($r['status'] === 'rejected') ++$rejected;
}

$canResolve = hasPermission($admin, 'payment_requests.approve') || hasPermission($admin, 'payment_requests.reject');
?>

<main class="main">
  <header class="top">
    <button class="menu" onclick="document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebarBackdrop').classList.add('open')">☰</button>
    <h1>طلبات الاشتراك (الباقات)</h1>
  </header>

  <div class="content">
    <div class="pagehead">
      <div>
        <h2>الطلبات الواردة من المستخدمين</h2>
        <p>مراجعة طلبات الاشتراك في الباقات والموافقة عليها أو رفضها</p>
      </div>
    </div>

    <div class="stats">
      <div class="card stat">
        <div class="ico">⏳</div>
        <div><b><?= $pending ?></b><small>قيد الانتظار</small></div>
      </div>
      <div class="card stat">
        <div class="ico">✅</div>
        <div><b><?= $approved ?></b><small>مقبولة</small></div>
      </div>
      <div class="card stat">
        <div class="ico">❌</div>
        <div><b><?= $rejected ?></b><small>مرفوضة</small></div>
      </div>
      <div class="card stat">
        <div class="ico">📋</div>
        <div><b><?= count($rows) ?></b><small>إجمالي الطلبات</small></div>
      </div>
    </div>

    <?php if ($message !== ''): ?>
      <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card panel">
      <div class="tablewrap">
        <table class="table">
          <thead>
            <tr>
              <th>#</th><th>المستخدم</th><th>الهاتف</th><th>الباقة</th><th>السعر</th><th>الإيصال</th><th>تاريخ الطلب</th><th>الحالة</th>
              <?php if ($canResolve): ?><th style="min-width:180px">إجراء</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="<?= $canResolve ? 9 : 8 ?>" style="text-align:center;color:var(--muted);padding:30px">لا توجد طلبات حاليًا.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>">
                <td><?= (int)$r['id'] ?></td>
                <td><strong><?= htmlspecialchars((string)$r['user_name']) ?></strong></td>
                <td dir="ltr"><?= htmlspecialchars((string)$r['user_phone']) ?></td>
                <td><?= htmlspecialchars((string)$r['plan_name']) ?></td>
                <td><?= htmlspecialchars((string)$r['plan_price']) ?> <?= htmlspecialchars((string)$r['plan_currency']) ?></td>
                <td>
                  <?php if (!empty($r['receipt_path'])): ?>
                    <a href="../backend/storage/receipts/<?= rawurlencode((string)$r['receipt_path']) ?>" target="_blank" style="color:var(--primary);font-weight:700">عرض الإيصال</a>
                  <?php else: ?>
                    <span style="color:var(--muted)">لا يوجد</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap"><?= htmlspecialchars((string)$r['created_at']) ?></td>
                <td>
                  <?php
                  $pillClass = match ($r['status']) {
                      'pending'  => 'background:#fef3cd;color:#856404',
                      'approved' => 'background:#d4edda;color:#155724',
                      'rejected' => 'background:#f8d7da;color:#721c24',
                      default    => 'background:var(--surface2);color:var(--muted)',
                  };
                  $statusAr = match ($r['status']) {
                      'pending'  => 'قيد الانتظار',
                      'approved' => 'مقبول',
                      'rejected' => 'مرفوض',
                      default    => htmlspecialchars($r['status']),
                  };
                  ?>
                  <span class="status" style="<?= $pillClass ?>"><?= $statusAr ?></span>
                </td>
                <?php if ($canResolve): ?>
                <td>
                  <?php if ($r['status'] === 'pending'): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <?php if (hasPermission($admin, 'payment_requests.approve')): ?>
                        <button class="btn primary sm" data-action="approve" data-id="<?= (int)$r['id'] ?>">✓ قبول وتفعيل</button>
                      <?php endif; ?>
                      <?php if (hasPermission($admin, 'payment_requests.reject')): ?>
                        <button class="btn danger sm" data-action="reject" data-id="<?= (int)$r['id'] ?>">✗ رفض</button>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span style="color:var(--muted);font-size:12px">تمت المراجعة</span>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
(function(){
  const API = '<?= rtrim((string)($_ENV["API_BASE_URL"] ?? ""), "/") ?>/api/v1';
  document.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', function(){
      const id = this.dataset.id;
      const action = this.dataset.action; // approve | reject
      const label = action === 'approve' ? 'قبول' : 'رفض';
      if (!confirm('هل أنت متأكد من ' + label + ' هذا الطلب؟')) return;
      const token = localStorage.getItem('adminToken');
      fetch(API + '/admin/payment-requests/' + id + '/' + action, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + (token || ''), 'Content-Type': 'application/json' },
        body: JSON.stringify({})
      })
      .then(r => r.json())
      .then(d => {
        if (d && d.success) { location.reload(); }
        else { alert(d && d.message ? d.message : 'فشل التنفيذ'); }
      })
      .catch(() => alert('خطأ في الاتصال بالخادم'));
    });
  });
})();
</script>
