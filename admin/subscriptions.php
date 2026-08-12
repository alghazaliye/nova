<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'users.manage');
$pageTitle = 'الحسابات المميزة'; $pdo = getAdminDB();

$plans = [
    'monthly'  => ['label' => 'شهري', 'days' => 30, 'price' => 19.99],
    'quarterly'=> ['label' => 'ربع سنوي', 'days' => 90, 'price' => 49.99],
    'yearly'   => ['label' => 'سنوي', 'days' => 365, 'price' => 149.99],
];

// Add / update subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && !empty($_POST['user_id']) && !empty($_POST['plan_type'])) {
        $userId = (int)$_POST['user_id'];
        $plan = $plans[$_POST['plan_type']] ?? null;
        if ($plan) {
            $starts = date('Y-m-d H:i:s');
            $expires = date('Y-m-d H:i:s', strtotime("+{$plan['days']} days"));
            $stmt = $pdo->prepare(
                'INSERT INTO subscriptions (user_id, plan_type, starts_at, expires_at, price, status, created_by)
                 VALUES (?, ?, ?, ?, ?, "active", ?)
                 ON DUPLICATE KEY UPDATE plan_type = VALUES(plan_type), starts_at = VALUES(starts_at),
                 expires_at = VALUES(expires_at), price = VALUES(price), status = "active", created_by = ?'
            );
            $stmt->execute([$userId, $plan['type'] ?? $_POST['plan_type'], $starts, $expires, $plan['price'], $admin['id'], $admin['id']]);
            $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$userId]);
        }
    }
    if ($_POST['action'] === 'cancel' && !empty($_POST['sub_id'])) {
        $subId = (int)$_POST['sub_id'];
        $pdo->prepare('UPDATE subscriptions SET status = "cancelled" WHERE id = ?')->execute([$subId]);
        $row = $pdo->prepare('SELECT user_id, status FROM subscriptions WHERE id = ?');
        $row->execute([$subId]);
        $r = $row->fetch();
        if ($r) {
            $active = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE user_id = {$r['user_id']} AND status = 'active' AND expires_at > NOW()")->fetchColumn();
            if ($active === 0) {
                $pdo->prepare('UPDATE users SET is_verified = 0 WHERE id = ?')->execute([(int)$r['user_id']]);
            }
        }
    }
    header('Location: subscriptions.php');
    exit;
}

// Expire overdue subscriptions automatically
$pdo->exec("UPDATE subscriptions SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()");

$search = trim($_GET['q'] ?? '');
$where = $search !== '' ? 'WHERE u.name LIKE ? OR u.phone LIKE ?' : '';
$params = $search !== '' ? ["%$search%", "%$search%"] : [];
$rows = $pdo->prepare(
    "SELECT u.id, u.name, u.phone, u.is_verified,
            s.id sub_id, s.plan_type, s.starts_at, s.expires_at, s.price, s.status
     FROM users u
     LEFT JOIN subscriptions s ON s.user_id = u.id AND s.id = (SELECT s2.id FROM subscriptions s2 WHERE s2.user_id = u.id ORDER BY s2.created_at DESC LIMIT 1)
     $where ORDER BY u.created_at DESC LIMIT 200"
);
$rows->execute($params);
$subs = $rows->fetchAll();
$users = $pdo->query('SELECT id, name, phone FROM users ORDER BY name LIMIT 500')->fetchAll();

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>الحسابات المميزة والاشتراكات</h2><p>إدارة خطط الاشتراك الشهري وتفعيل علامة التحقق (العلامة الزرقاء) للمستخدمين.</p></div></div>
<div class="card panel" style="margin-bottom:20px">
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end">
      <div><label style="font-size:12px;color:var(--muted)">المستخدم</label>
        <select name="user_id" required style="width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--surface1);color:inherit">
          <option value="">اختر المستخدم...</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['phone']) ?>)</option>
          <?php endforeach; ?>
        </select></div>
      <div><label style="font-size:12px;color:var(--muted)">الخطة</label>
        <select name="plan_type" required style="width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--surface1);color:inherit">
          <option value="monthly">شهري — $19.99</option>
          <option value="quarterly">ربع سنوي — $49.99</option>
          <option value="yearly">سنوي — $149.99</option>
        </select></div>
      <div></div>
      <button class="btn" type="submit">✓ تفعيل الاشتراك وعلامة التحقق</button>
    </div>
  </form>
</div>
<div class="filters"><form method="GET" class="search"><span>⌕</span><input name="q" placeholder="ابحث بالاسم أو الرقم..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"><button class="btn sm" type="submit">بحث</button></form></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المستخدم</th><th>الخطة</th><th>البداية</th><th>الانتهاء</th><th>السعر</th><th>الحالة</th><th>التحقق</th><th>إجراء</th></tr></thead><tbody>
<?php
$statusStyle = ['active'=>'rgba(34,197,94,.1);color:#16a34a','expired'=>'rgba(239,68,68,.1);color:#dc2626','cancelled'=>'rgba(245,158,11,.1);color:#d97706'];
foreach ($subs as $s): ?>
<tr>
  <td><b><?= htmlspecialchars((string)$s['name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)$s['phone']) ?></small></td>
  <td><?= $s['plan_type'] ? htmlspecialchars($plans[(string)$s['plan_type']]['label'] ?? (string)$s['plan_type']) : '<span style="color:var(--muted)">لا يوجد</span>' ?></td>
  <td><?= $s['starts_at'] ? date('d/m/Y H:i', strtotime((string)$s['starts_at'])) : '—' ?></td>
  <td><?= $s['expires_at'] ? date('d/m/Y H:i', strtotime((string)$s['expires_at'])) : '—' ?></td>
  <td>$<?= $s['price'] ? number_format((float)$s['price'], 2) : '—' ?></td>
  <td><span style="background:<?= $statusStyle[$s['status']] ?? 'var(--surface2)' ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800"><?= ['active'=>'نشط','expired'=>'منتهي','cancelled'=>'ملغي'][$s['status']] ?? $s['status'] ?></span></td>
  <td style="text-align:center"><?= (int)$s['is_verified'] ? '<span style="color:#2563eb">✓ موثق</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
  <td><?php if ($s['sub_id'] && $s['status'] === 'active'): ?>
      <form method="POST" style="display:inline"><input type="hidden" name="action" value="cancel"><input type="hidden" name="sub_id" value="<?= (int)$s['sub_id'] ?>"><button class="btn sm" type="submit" style="background:rgba(239,68,68,.1);color:#dc2626">إلغاء</button></form>
      <?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$subs): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">لا توجد اشتراكات</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
