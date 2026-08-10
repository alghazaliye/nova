<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'audit.view');
$pageTitle = 'سجل العمليات';
$pdo       = getAdminDB();

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->query('SELECT COUNT(*) FROM audit_logs');
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT al.*, a.name AS admin_name
     FROM audit_logs al
     JOIN admins a ON a.id = al.admin_id
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$limit, $offset]);
$logs = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>سجل العمليات</h2>
    <p>تتبع نشاط المشرفين والتغييرات في النظام.</p>
  </div>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>التاريخ</th>
        <th>المشرف</th>
        <th>العملية</th>
        <th>النوع</th>
        <th>المعرف</th>
        <th>الوصف</th>
        <th>IP</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td><small><?= date('d/m H:i:s', strtotime($log['created_at'])) ?></small></td>
        <td><b><?= htmlspecialchars($log['admin_name']) ?></b></td>
        <td><code style="background:var(--surface2); padding:3px 6px; border-radius:6px; font-size:11px;"><?= htmlspecialchars($log['action']) ?></code></td>
        <td><?= htmlspecialchars($log['entity_type'] ?? '—') ?></td>
        <td><?= $log['entity_id'] ?? '—' ?></td>
        <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
        <td><small style="color:var(--muted)"><?= htmlspecialchars($log['ip_address'] ?? '') ?></small></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php $totalPages = (int)ceil($total / $limit); if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
    <a href="?page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
