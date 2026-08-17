<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'users.manage');
$pageTitle = 'الحسابات المميزة'; $pdo = getAdminDB();

$message = '';
$error   = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && !empty($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $planId = (int)($_POST['plan_id'] ?? 0);
        $days = max(0, (int)($_POST['sub_days'] ?? 30));
        $ends = $days > 0 ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null;
        try {
            $pdo->prepare('UPDATE user_subscriptions SET status = "cancelled" WHERE user_id = ? AND status = "active"')->execute([$userId]);
            $pdo->prepare(
                'INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, expires_at)
                 VALUES (?, ?, "active", NOW(), ?)'
            )->execute([$userId, $planId, $ends]);
            $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$userId]);
            logAudit($admin, 'SUBSCRIPTION_ACTIVATE', 'user', $userId, "تفعيل اشتراك على الباقة #{$planId} — حساب مميز");
            $message = 'تم تفعيل الاشتراك وعلامة التحقق الزرقاء';
        } catch (\Throwable $e) { $error = 'خطأ: ' . $e->getMessage(); }
    }

    if ($action === 'cancel' && !empty($_POST['sub_id'])) {
        $subId = (int)$_POST['sub_id'];
        $stmt = $pdo->prepare('SELECT user_id FROM user_subscriptions WHERE id = ? LIMIT 1');
        $stmt->execute([$subId]);
        $row = $stmt->fetch();
        $pdo->prepare('UPDATE user_subscriptions SET status = "cancelled" WHERE id = ?')->execute([$subId]);
        if ($row) {
            $active = (int)$pdo->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE user_id = ? AND status = "active"')->executeQuery([$row['user_id']])->fetchColumn();
            if ($active === 0) {
                $pdo->prepare('UPDATE users SET is_verified = 0 WHERE id = ?')->execute([$row['user_id']]);
            }
        }
        logAudit($admin, 'SUBSCRIPTION_CANCEL', 'subscription', $subId, 'إلغاء اشتراك مميز');
        $message = 'تم إلغاء الاشتراك';
    }
}

// Expire overdue
try { $pdo->exec("UPDATE user_subscriptions SET status = 'expired' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()"); } catch (\Throwable $e) {}

$search = trim($_GET['q'] ?? '');
$where = $search !== '' ? 'WHERE u.name LIKE ? OR u.phone LIKE ?' : '';
$params = $search !== '' ? ["%$search%", "%$search%"] : [];
$rows = $pdo->prepare(
    "SELECT u.id, u.name, u.phone, u.is_verified,
            us.id sub_id, us.plan_id, us.status, us.expires_at
     FROM users u
     LEFT JOIN user_subscriptions us ON us.user_id = u.id
       AND us.id = (SELECT MAX(id) FROM user_subscriptions us3 WHERE us3.user_id = u.id)
     {$where} ORDER BY u.created_at DESC LIMIT 200"
);
$rows->execute($params);
$subs = $rows->fetchAll();

$planList = $pdo->query('SELECT id, name, price, currency, period, max_devices FROM plans WHERE is_active = 1 ORDER BY price ASC')->fetchAll();
$users = $pdo->query('SELECT id, name, phone FROM users ORDER BY name LIMIT 500')->fetchAll();

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>الحسابات المميزة والاشتراكات</h2><p>تفعيل الاشتراكات المميزة والتحقق — يوصى باستخدام صفحة «الباقات والاشتراكات» الجديدة للتحكم الكامل.</p></div></div>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card panel" style="margin-bottom:20px">
  <form method="POST">
    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="add">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end">
      <div><label class="form-label">المستخدم</label>
        <select class="form-control" name="user_id" required><option value="">اختر المستخدم...</option>
          <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['phone']) ?>)</option><?php endforeach; ?>
        </select></div>
      <div><label class="form-label">الباقة</label>
        <select class="form-control" name="plan_id" required>
          <?php foreach ($planList as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['name']) ?> — <?= number_format((float)$p['price'],2) ?> <?= htmlspecialchars((string)$p['currency']) ?> (<?= (int)$p['max_devices'] ?> أجهزة)</option><?php endforeach; ?>
        </select></div>
      <div><label class="form-label">المدة بالأيام (0 = دائم)</label><input class="form-control" type="number" min="0" name="sub_days" value="30"></div>
      <button class="btn primary" type="submit">✓ تفعيل الاشتراك وعلامة التحقق</button>
    </div>
  </form>
</div>
<div class="filters"><form method="GET" class="search"><span>⌕</span><input name="q" placeholder="ابحث بالاسم أو الرقم..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"><button class="btn sm" type="submit">بحث</button></form></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المستخدم</th><th>الباقة</th><th>ينتهي في</th><th>الحالة</th><th>التحقق</th><th>إجراء</th></tr></thead><tbody>
<?php
$statusStyle = ['active'=>'rgba(34,197,94,.1);color:#16a34a','expired'=>'rgba(239,68,68,.1);color:#dc2626','cancelled'=>'rgba(245,158,11,.1);color:#d97706'];
foreach ($subs as $s):
  $status = $s['status'];
  $planRef = null; $price = null; $currency = '';
  foreach ($planList as $pl) { if ((int)$pl['id'] === (int)$s['plan_id']) { $planRef = $pl['name']; $price = $pl['price']; $currency = $pl['currency']; break; } }
?>
<tr>
  <td><b><?= htmlspecialchars((string)$s['name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)$s['phone']) ?></small></td>
  <td><?= $planRef ? htmlspecialchars((string)$planRef) : '<span style="color:var(--muted)">لا يوجد</span>' ?></td>
  <td><?= !empty($s['expires_at']) ? date('d/m/Y H:i', strtotime((string)$s['expires_at'])) : '—' ?></td>
  <td><span style="background:<?= $statusStyle[$status] ?? 'var(--surface2)' ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800"><?= ['active'=>'نشط','expired'=>'منتهي','cancelled'=>'ملغي'][$status] ?? $status ?></span></td>
  <td style="text-align:center"><?= (int)$s['is_verified'] ? '<span style="color:#2563eb">✓ موثق</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
  <td><?php if ($status === 'active'): ?>
      <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="sub_id" value="<?= (int)$s['sub_id'] ?>"><button class="btn sm" type="submit" style="background:rgba(239,68,68,.1);color:#dc2626">إلغاء</button></form>
      <?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$subs): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">لا توجد اشتراكات</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
