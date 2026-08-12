<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'reports.view');
$pageTitle = 'إدارة البلاغات';
$pdo       = getAdminDB();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission($admin, 'reports.resolve')) {
    verifyCsrf();
    $reportId = (int)$_POST['report_id'];
    $action   = $_POST['action'] ?? '';

    if (in_array($action, ['resolved', 'rejected', 'reviewing'])) {
        $pdo->prepare(
            'UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
        )->execute([$action, $admin['id'], $reportId]);
        logAudit($admin, 'REPORT_' . strtoupper($action), 'report', $reportId);
        $message = 'تم تحديث حالة البلاغ';
    }
}

$status = $_GET['status'] ?? 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$whereStatus = in_array($status, ['pending','reviewing','resolved','rejected']) ? "WHERE r.status = '{$status}'" : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reports r {$whereStatus}");
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT r.*, u1.name AS reporter_name, u2.name AS reported_name
     FROM reports r
     JOIN users u1 ON u1.id = r.reporter_id
     JOIN users u2 ON u2.id = r.reported_user_id
     {$whereStatus}
     ORDER BY r.created_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$stmt->execute();
$reports = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>إدارة البلاغات</h2>
    <p>مراجعة تقارير المستخدمين والمحتوى المخالف.</p>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="filters">
  <?php foreach (['pending'=>'معلقة','reviewing'=>'مراجعة','resolved'=>'محلولة','rejected'=>'مرفوضة'] as $s => $label): ?>
    <a href="?status=<?= $s ?>" class="btn <?= $status===$s ? 'primary' : '' ?> sm"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>المُبلِّغ</th>
        <th>المُبلَّغ عنه</th>
        <th>السبب</th>
        <th>الحالة</th>
        <th>التاريخ</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reports as $r): ?>
      <tr>
        <td><b><?= htmlspecialchars($r['reporter_name']) ?></b></td>
        <td><b><?= htmlspecialchars($r['reported_name']) ?></b></td>
        <td><?= htmlspecialchars($r['reason']) ?></td>
        <td>
          <?php $badgeMap = ['pending'=>'status warn','reviewing'=>'status online','resolved'=>'status online','rejected'=>'status blocked'];
                $labelMap = ['pending'=>'معلق','reviewing'=>'مراجعة','resolved'=>'محلول','rejected'=>'مرفوض']; ?>
          <span class="<?= $badgeMap[$r['status']] ?? 'status offline' ?>"><?= $labelMap[$r['status']] ?? $r['status'] ?></span>
        </td>
        <td><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
        <td>
          <?php if ($r['status'] === 'pending' || $r['status'] === 'reviewing'): ?>
          <div style="display:flex; gap:5px;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="resolved">
              <button class="btn sm" style="color:var(--good)">حل</button>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="rejected">
              <button class="btn danger sm">رفض</button>
            </form>
          </div>
          <?php else: ?>
            <small style="color:var(--muted)">تمت المعالجة</small>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
