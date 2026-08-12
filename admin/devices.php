<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'users.view');
$pageTitle = 'الأجهزة المسجلة';
$pdo       = getAdminDB();

$message = '';
try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS device_registrations (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id BIGINT UNSIGNED NOT NULL,
          device_fingerprint VARCHAR(255) NULL,
          device_model VARCHAR(255) NULL,
          os_name VARCHAR(50) NULL,
          os_version VARCHAR(100) NULL,
          app_version VARCHAR(50) NULL,
          platform VARCHAR(30) NULL,
          barcode_hash VARCHAR(255) NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          last_seen DATETIME NULL,
          KEY idx_user (user_id),
          UNIQUE KEY uq_fingerprint (user_id, device_fingerprint))'
    );
} catch (\Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $devId = (int)($_POST['device_id'] ?? 0);

    if ($action === 'deactivate' && hasPermission($admin, 'users.manage') && $devId) {
        $stmt = $pdo->prepare('SELECT id FROM device_registrations WHERE id = ? LIMIT 1');
        $stmt->execute([$devId]);
        if ($stmt->fetch()) {
            $pdo->prepare('UPDATE device_registrations SET is_active = 0 WHERE id = ?')->execute([$devId]);
            logAudit($admin, 'DEVICE_DEACTIVATE', 'device', $devId, "إيقاف الجهاز #{$devId}");
            $message = 'تم إيقاف الجهاز';
        }
    }
    if ($action === 'delete' && hasPermission($admin, 'users.manage') && $devId) {
        $pdo->prepare('DELETE FROM device_registrations WHERE id = ?')->execute([$devId]);
        logAudit($admin, 'DEVICE_DELETE', 'device', $devId, "حذف الجهاز #{$devId}");
        $message = 'تم حذف الجهاز';
    }
}

$search = trim($_GET['q'] ?? '');
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (u.name LIKE ? OR u.phone LIKE ? OR dr.device_model LIKE ? OR dr.device_fingerprint LIKE ?)';
    $s      = "%{$search}%";
    $params = array_fill(0, 4, $s);
}
$stmt = $pdo->prepare(
    "SELECT dr.id, dr.device_fingerprint, dr.device_model, dr.os_name, dr.os_version,
            dr.app_version, dr.platform, dr.barcode_hash, dr.is_active, dr.first_seen, dr.last_seen,
            dr.user_id, u.name user_name, u.phone user_phone,
            (SELECT COALESCE(MAX(p.max_devices),1)
               FROM user_subscriptions us
               JOIN plans p ON p.id = us.plan_id
               WHERE us.user_id = dr.user_id AND us.status = 'active'
               ORDER BY us.id DESC LIMIT 1) user_max_devices
     FROM device_registrations dr
     JOIN users u ON u.id = dr.user_id
     WHERE {$where} ORDER BY dr.id DESC LIMIT 300"
);
$stmt->execute($params);
$devices = $stmt->fetchAll();

$activeCount = 0;
foreach ($devices as $d) { if ($d['is_active']) $activeCount++; }

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>الأجهزة المسجلة</h2><p>تفاصيل الأجهزة التي سجلت عند تثبيت التطبيق أو الدخول، وحد الباقة لكل مستخدم.</p></div></div>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="ico">📱</div><div><b><?= count($devices) ?></b><small>إجمالي الأجهزة المسجلة</small></div></div>
  <div class="stat"><div class="ico">⚡</div><div><b><?= $activeCount ?></b><small>أجهزة نشطة</small></div></div>
  <div class="stat"><div class="ico">🚫</div><div><b><?= count($devices) - $activeCount ?></b><small>أجهزة موقوفة</small></div></div>
  <div class="stat"><div class="ico">👥</div><div><b><?= count(array_unique(array_column($devices, 'user_id'))) ?></b><small>مستخدمون بأجهزة مسجلة</small></div></div>
</div>

<div class="filters">
  <form method="GET" style="display:flex;gap:8px;width:100%">
    <div class="search"><span>⌕</span><input name="q" placeholder="ابحث باسم المستخدم أو الجهاز أو المعرف..." value="<?= htmlspecialchars($search) ?>"></div>
    <button type="submit" class="btn">بحث</button>
  </form>
</div>

<div class="card panel tablewrap">
  <table class="table" style="min-width:900px">
    <thead><tr><th>المستخدم</th><th>الجهاز</th><th>نظام التشغيل</th><th>نسخة التطبيق</th><th>معرف الجهاز (Fingerprint)</th><th>الحد المسموح</th><th>الحالة</th><th>تاريخ التسجيل</th><th>إجراء</th></tr></thead>
    <tbody>
    <?php foreach ($devices as $d): ?>
    <tr>
      <td><b><?= htmlspecialchars((string)$d['user_name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)$d['user_phone']) ?></small></td>
      <td><?= $d['device_model'] ? htmlspecialchars((string)$d['device_model']) : '<span style="color:var(--muted)">غير معروف</span>' ?><br><small style="color:var(--muted)"><?= htmlspecialchars((string)($d['platform'] ?? '')) ?></small></td>
      <td><?= ($d['os_name'] ?? '') ? htmlspecialchars((string)$d['os_name'] . ' ' . ($d['os_version'] ?? '')) : '—' ?></td>
      <td><?= htmlspecialchars((string)($d['app_version'] ?? '—')) ?></td>
      <td style="font-family:monospace;font-size:11px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars((string)$d['device_fingerprint']) ?>"><?= htmlspecialchars((string)substr((string)$d['device_fingerprint'], 0, 22)) ?><?= strlen((string)$d['device_fingerprint']) > 22 ? '…' : '' ?></td>
      <td><b><?= (int)($d['user_max_devices'] ?? 1) ?></b> جهاز</td>
      <td><span style="background:<?= $d['is_active'] ? 'rgba(34,197,94,.1);color:#16a34a' : 'rgba(245,158,11,.1);color:#d97706' ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800"><?= $d['is_active'] ? 'نشط' : 'موقوف' ?></span></td>
      <td><?= $d['first_seen'] ? date('d/m/Y H:i', strtotime((string)$d['first_seen'])) : '—' ?></td>
      <td><div style="display:flex;gap:5px">
        <?php if ($d['is_active']): ?>
        <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn sm" type="submit" data-confirm="إيقاف هذا الجهاز؟">⏸ إيقاف</button></form>
        <?php endif; ?>
        <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn danger sm" type="submit" data-confirm="حذف نهائي؟">🗑</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$devices): ?><tr><td colspan="9" style="text-align:center;color:var(--muted);padding:30px">لا توجد أجهزة مسجلة بعد — ستظهر الأجهزة تلقائيًا عند دخول المستخدمين للتطبيق</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
