<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Polyfill: avoid fatal error when php-mbstring is not installed
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        if ($length === null) {
            return substr($str, $start);
        }
        return substr($str, $start, $length);
    }
}
$admin     = requireAdminLogin();
requirePermission($admin, 'users.view');
$pageTitle = 'إدارة المستخدمين';
$pdo       = getAdminDB();

$message = '';
$error   = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'block' && hasPermission($admin, 'users.block')) {
        $reason = trim((string)($_POST['ban_reason'] ?? ''));
        $pdo->prepare('UPDATE sessions SET revoked_at = datetime("now") WHERE user_id = ? AND revoked_at IS NULL')->execute([$userId]);
        $pdo->prepare('UPDATE device_registrations SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
        try {
            $pdo->prepare('INSERT INTO user_bans (user_id, reason, banned_by) VALUES (?, ?, ?)')->execute([$userId, $reason ?: null, $admin['id']]);
        } catch (\Throwable $e) {}
        $pdo->prepare('UPDATE users SET is_blocked = 1, blocked_at = datetime("now") WHERE id = ?')->execute([$userId]);
        logAudit($admin, 'USER_BLOCK', 'user', $userId, "حظر المستخدم #{$userId}" . ($reason ? " : {$reason}" : ''));
        $message = 'تم حظر المستخدم ومنع الدخول للتطبيق';
    } elseif ($action === 'unblock' && hasPermission($admin, 'users.block')) {
        try {
            $pdo->prepare('UPDATE user_bans SET unbanned_at = datetime("now"), unbanned_by = ? WHERE user_id = ? AND unbanned_at IS NULL')->execute([$admin['id'], $userId]);
        } catch (\Throwable $e) {}
        $pdo->prepare('UPDATE users SET is_blocked = 0, blocked_at = NULL WHERE id = ?')->execute([$userId]);
        logAudit($admin, 'USER_UNBLOCK', 'user', $userId, "فك حظر المستخدم #{$userId}");
        $message = 'تم فك الحظر بنجاح';
    } elseif ($action === 'verify' && hasPermission($admin, 'users.manage')) {
        $pdo->prepare('UPDATE users SET is_verified = CASE WHEN is_verified = 1 THEN 0 ELSE 1 END, updated_at = datetime("now") WHERE id = ?')->execute([$userId]);
        $row = $pdo->prepare('SELECT is_verified FROM users WHERE id = ? LIMIT 1');
        $row->execute([$userId]);
        $isV = (int)($row->fetch()['is_verified'] ?? 0);
        logAudit($admin, $isV ? 'USER_VERIFY' : 'USER_UNVERIFY', 'user', $userId, $isV ? 'توثيق الحساب (العلامة الزرقاء)' : 'إلغاء التوثيق');
        $message = $isV ? 'تم توثيق الحساب — ستظهر العلامة الزرقاء في التطبيق' : 'تم إلغاء التوثيق';
    } elseif ($action === 'delete' && hasPermission($admin, 'users.delete')) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        logAudit($admin, 'USER_DELETE', 'user', $userId, "حذف المستخدم #{$userId}");
        $message = 'تم حذف المستخدم';
    }
}

// Pagination & Search
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where  = '1=1';
$params = [];

if ($search) {
    $where   .= ' AND (name LIKE ? OR phone LIKE ?)';
    $s        = "%{$search}%";
    $params   = array_merge($params, [$s, $s]);
}
if ($filter === 'blocked') { $where .= ' AND is_blocked = 1'; }
if ($filter === 'online')  { $where .= ' AND is_online = 1'; }

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.*, p.name plan_name, p.max_devices plan_max_devices,
           (SELECT COUNT(*) FROM device_registrations d WHERE d.user_id = u.id AND d.is_active = 1) active_devices
    FROM users u
    LEFT JOIN (
        SELECT us.user_id, p.name, p.max_devices
        FROM user_subscriptions us
        JOIN plans p ON p.id = us.plan_id
        WHERE us.status = 'active'
        GROUP BY us.user_id
    ) p ON p.user_id = u.id
    WHERE {$where} ORDER BY u.created_at DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>إدارة المستخدمين</h2>
    <p>إدارة الحسابات والحالات والصلاحيات.</p>
  </div>
  <button class="btn primary">＋ إضافة مستخدم</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="filters">
  <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; width:100%;">
    <div class="search">
      <span>⌕</span>
      <input name="q" placeholder="ابحث بالاسم أو الرقم..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="filter" class="select">
      <option value="all" <?= $filter==='all' ? 'selected' : '' ?>>كل الحالات</option>
      <option value="online" <?= $filter==='online' ? 'selected' : '' ?>>نشط</option>
      <option value="blocked" <?= $filter==='blocked' ? 'selected' : '' ?>>محظور</option>
    </select>
    <button type="submit" class="btn">تطبيق</button>
  </form>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>المستخدم</th>
        <th>الهاتف</th>
        <th>التوثيق</th>
        <th>الباقة</th>
        <th>الأجهزة</th>
        <th>الحالة</th>
        <th>تاريخ التسجيل</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <div class="user" style="display: flex; align-items: center; gap: 10px;">
            <?php 
              $avatarUrl = $u['avatar'];
              if ($avatarUrl && !str_starts_with($avatarUrl, 'http')) {
                  $avatarUrl = '/api/v1/media/' . ltrim($avatarUrl, '/');
              }
            ?>
            <?php if ($avatarUrl): ?>
              <img src="<?= htmlspecialchars($avatarUrl) ?>" class="avatar" style="object-fit:cover; width:38px; height:38px; border-radius:10px; display:block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="avatar" style="width:38px; height:38px; border-radius:10px; display:none; align-items:center; justify-content:center; background:var(--surface2); color:var(--text);"><?= mb_substr($u['name'] ?: 'U', 0, 1) ?></div>
            <?php else: ?>
              <div class="avatar" style="width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:var(--surface2); color:var(--text);"><?= mb_substr($u['name'] ?: 'U', 0, 1) ?></div>
            <?php endif; ?>
            <div>
              <b style="display: block;"><?= htmlspecialchars($u['name'] ?: 'مستخدم NOVA') ?> <?php if ((int)$u['is_verified']): ?><span style="color:#2563eb; margin-right:4px;" title="موثق">✔</span><?php endif; ?></b>
              <small style="color: var(--muted); font-size: 11px;">#<?= $u['id'] ?></small>
            </div>
          </div>
        </td>
        <td dir="ltr" style="text-align:right;"><code><?= htmlspecialchars($u['phone']) ?></code></td>
        <td style="text-align:center"><?= (int)$u['is_verified'] ? '<span style="color:#2563eb;font-weight:800">✔ موثق</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
        <td><?= $u['plan_name'] ? htmlspecialchars((string)$u['plan_name']) : '<span style="color:var(--muted)">مجاني</span>' ?></td>
        <td style="text-align:center;"><?= (int)($u['active_devices'] ?? 0) ?><?= $u['plan_max_devices'] ? '/' . (int)$u['plan_max_devices'] : '' ?></td>
        <td style="vertical-align: middle;">
          <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
            <?php if ($u['is_blocked']): ?>
              <span class="status blocked" style="background:#f8d7da; color:#721c24; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; width: 70px; text-align:center;">محظور</span>
            <?php elseif ($u['is_online']): ?>
              <span class="status online" style="background:#d4edda; color:#155724; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; width: 70px; text-align:center;">نشط</span>
            <?php else: ?>
              <span class="status offline" style="background:#e2e3e5; color:#383d41; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; width: 70px; text-align:center;">أوفلاين</span>
            <?php endif; ?>
            <div style="font-size:10px; color:var(--muted); white-space:nowrap;"><?= $u['last_seen'] ? date('d/m H:i', strtotime($u['last_seen'])) : '—' ?></div>
          </div>
        </td>
        <td style="vertical-align: middle; white-space:nowrap; text-align:center;">
          <div style="font-weight:600; font-size:13px;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></div>
          <div style="font-size:10px; color:var(--muted);"><?= date('H:i', strtotime($u['created_at'])) ?></div>
        </td>
        <td>
          <div style="display:flex; gap:5px; align-items:center; justify-content: center;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="verify">
              <button type="submit" class="btn sm" style="background:<?= (int)$u['is_verified'] ? 'rgba(245,158,11,.1);color:#d97706' : 'rgba(37,99,235,.1);color:#2563eb' ?>" onclick="return confirm('<?= (int)$u['is_verified'] ? 'إلغاء التوثيق؟' : 'توثيق الحساب وإظهار العلامة الزرقاء؟' ?>')"><?= (int)$u['is_verified'] ? '✖ إلغاء' : '✔ توثيق' ?></button>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <?php if ($u['is_blocked']): ?>
                <input type="hidden" name="action" value="unblock">
                <button type="submit" class="btn sm" style="background:rgba(18,183,106,.1);color:#12b76a;">فك الحظر</button>
              <?php else: ?>
                <input type="hidden" name="action" value="block">
                <button type="submit" class="btn sm" style="background:rgba(240,68,56,.1);color:#f04438;" onclick="return confirm('هل تريد حظر هذا المستخدم؟ سيمتنع من الدخول للتطبيق')">حظر</button>
              <?php endif; ?>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn danger sm" onclick="return confirm('حذف نهائي؟')">حذف</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php $totalPages = (int)ceil($total / $limit); if ($totalPages > 1): ?>
<div class="pagination" style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&filter=<?= $filter ?>"
       class="page-btn <?= $i === $page ? 'active' : '' ?>" style="padding: 8px 12px; border-radius: 8px; text-decoration: none; <?= $i === $page ? 'background: var(--primary); color: white;' : 'background: var(--surface2); color: var(--text);' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
