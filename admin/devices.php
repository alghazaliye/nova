<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'users.view');
$pageTitle = 'الأجهزة المسجلة';
$pdo       = getAdminDB();

$message = '';
// جدول الأجهزة الفعلي في قاعدة البيانات: device_registrations
// (id, user_id, device_uuid, device_name, os, os_version, app_version, fcm_token, last_seen, is_active, created_at, updated_at)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $devId = (int)($_POST['device_id'] ?? 0);

    if ($action === 'delete' && hasPermission($admin, 'users.manage') && $devId) {
        $pdo->prepare('DELETE FROM device_registrations WHERE id = ?')->execute([$devId]);
        logAudit($admin, 'DEVICE_DELETE', 'device', $devId, "حذف الجهاز #{$devId}");
        $message = 'تم حذف الجهاز';
    }
    if ($action === 'clear_fcm' && hasPermission($admin, 'users.manage') && $devId) {
        $pdo->prepare('UPDATE device_registrations SET fcm_token = NULL WHERE id = ?')->execute([$devId]);
        logAudit($admin, 'DEVICE_CLEAR_FCM', 'device', $devId, "مسح رمز الإشعار للجهاز #{$devId}");
        $message = 'تم مسح رمز الإشعار';
    }
}

$search = trim($_GET['q'] ?? '');
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (u.name LIKE ? OR u.phone LIKE ? OR d.device_name LIKE ? OR d.device_uuid LIKE ? OR d.os LIKE ?)';
    $s      = "%{$search}%";
    $params = array_fill(0, 5, $s);
}
$stmt = $pdo->prepare(
    "SELECT d.id, d.device_uuid, d.device_name, d.os AS platform, d.app_version, d.last_seen AS last_active_at, d.created_at,
            d.fcm_token, d.user_id, u.name user_name, u.phone user_phone, u.is_online,
            (SELECT COALESCE(MAX(p.max_devices),1)
               FROM user_subscriptions us
               JOIN plans p ON p.id = us.plan_id
               WHERE us.user_id = d.user_id AND us.status = 'active'
               ORDER BY us.id DESC LIMIT 1) user_max_devices
     FROM device_registrations d
     JOIN users u ON u.id = d.user_id
     WHERE {$where} ORDER BY d.last_seen DESC LIMIT 300"
);
$stmt->execute($params);
$devices = $stmt->fetchAll();

$recentCount = 0;
foreach ($devices as $d) {
    if ($d['last_active_at'] && (time() - strtotime($d['last_active_at'])) < 3600) $recentCount++;
}

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>الأجهزة المسجلة</h2><p>تفاصيل الأجهزة التي سجلت عند تثبيت التطبيق أو الدخول، وحد الباقة لكل مستخدم.</p></div></div>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="ico">📱</div><div><b><?= count($devices) ?></b><small>إجمالي الأجهزة المسجلة</small></div></div>
  <div class="stat"><div class="ico">⚡</div><div><b><?= $recentCount ?></b><small>نشطة خلال آخر ساعة</small></div></div>
  <div class="stat"><div class="ico">🚫</div><div><b><?= count(array_filter($devices, fn($d) => empty($d['last_active_at']))) ?></b><small>لم تنشط بعد</small></div></div>
  <div class="stat"><div class="ico">👥</div><div><b><?= count(array_unique(array_column($devices, 'user_id'))) ?></b><small>مستخدمون بأجهزة مسجلة</small></div></div>
</div>

<div class="filters">
  <form method="GET" style="display:flex;gap:8px;width:100%">
    <div class="search"><span>⌕</span><input name="q" placeholder="ابحث باسم المستخدم أو الجهاز أو المعرف..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"></div>
    <button type="submit" class="btn">بحث</button>
  </form>
</div>

<div class="card panel tablewrap">
  <table class="table" style="min-width:900px">
    <thead><tr><th>المستخدم</th><th>الجهاز</th><th>معرف الجهاز (UUID)</th><th>المنصة</th><th>نسخة التطبيق</th><th>الحد المسموح</th><th>آخر نشاط</th><th>حالة المستخدم</th><th>إجراء</th></tr></thead>
    <tbody>
    <?php foreach ($devices as $d): ?>
    <?php
      $isActive = $d['last_active_at'] && (time() - strtotime($d['last_active_at'])) < 3600;
    ?>
    <tr>
      <td><b><?= htmlspecialchars((string)$d['user_name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)$d['user_phone']) ?></small></td>
      <td><?= $d['device_name'] ? htmlspecialchars((string)$d['device_name']) : '<span style="color:var(--muted)">غير معروف</span>' ?></td>
      <td style="font-family:monospace;font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars((string)$d['device_uuid']) ?>"><?= htmlspecialchars((string)$d['device_uuid']) ?></td>
      <td><?= ($d['platform'] ?? '') ? htmlspecialchars((string)$d['platform']) : '—' ?></td>
      <td><?= htmlspecialchars((string)($d['app_version'] ?? '—')) ?></td>
      <td><b><?= (int)($d['user_max_devices'] ?? 1) ?></b> جهاز</td>
      <td><?= $d['last_active_at'] ? date('d/m/Y H:i', strtotime((string)$d['last_active_at'])) : '—' ?></td>
      <td><span style="background:<?= $d['is_online'] ? 'rgba(34,197,94,.1);color:#16a34a' : 'rgba(108,117,125,.1);color:#6c757d' ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800"><?= $d['is_online'] ? 'متصل' : 'غير متصل' ?></span></td>
      <td><div style="display:flex;gap:5px">
        <?php if ($isActive): ?>
        <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="clear_fcm"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn sm" type="submit" data-confirm="مسح رمز الإشعار لهذا الجهاز؟">🔕 مسح الإشعار</button></form>
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
