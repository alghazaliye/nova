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
        $pdo->prepare('UPDATE users SET is_blocked = 1, blocked_at = NOW() WHERE id = ?')->execute([$userId]);
        logAudit($admin, 'USER_BLOCK', 'user', $userId, "حظر المستخدم #{$userId}");
        $message = 'تم حظر المستخدم بنجاح';
    } elseif ($action === 'unblock' && hasPermission($admin, 'users.block')) {
        $pdo->prepare('UPDATE users SET is_blocked = 0, blocked_at = NULL WHERE id = ?')->execute([$userId]);
        logAudit($admin, 'USER_UNBLOCK', 'user', $userId, "فك حظر المستخدم #{$userId}");
        $message = 'تم فك الحظر بنجاح';
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
    $where   .= ' AND (name LIKE ? OR phone LIKE ? OR username LIKE ?)';
    $s        = "%{$search}%";
    $params   = array_merge($params, [$s, $s, $s]);
}
if ($filter === 'blocked') { $where .= ' AND is_blocked = 1'; }
if ($filter === 'online')  { $where .= ' AND is_online = 1'; }

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM users WHERE {$where} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
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
        <th>آخر ظهور</th>
        <th>الحالة</th>
        <th>تاريخ التسجيل</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <div class="user">
            <?php if ($u['avatar']): ?>
              <img src="<?= htmlspecialchars($u['avatar']) ?>" class="avatar" style="object-fit:cover;">
            <?php else: ?>
              <div class="avatar"><?= mb_substr($u['name'], 0, 1) ?></div>
            <?php endif; ?>
            <div>
              <b><?= htmlspecialchars($u['name']) ?></b>
              <small style="display:block; color:var(--muted); font-size:11px;"><?= $u['username'] ? '@'.$u['username'] : '' ?></small>
            </div>
          </div>
        </td>
        <td><?= htmlspecialchars($u['phone']) ?></td>
        <td><?= $u['last_seen'] ? date('d/m H:i', strtotime($u['last_seen'])) : '—' ?></td>
        <td>
          <?php if ($u['is_blocked']): ?>
            <span class="status blocked">محظور</span>
          <?php elseif ($u['is_online']): ?>
            <span class="status online">نشط</span>
          <?php else: ?>
            <span class="status offline">أوفلاين</span>
          <?php endif; ?>
        </td>
        <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        <td>
          <div style="display:flex; gap:5px;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <?php if ($u['is_blocked']): ?>
                <input type="hidden" name="action" value="unblock">
                <button type="submit" class="btn sm">فك الحظر</button>
              <?php else: ?>
                <input type="hidden" name="action" value="block">
                <button type="submit" class="btn sm" data-confirm="هل تريد حظر هذا المستخدم؟">حظر</button>
              <?php endif; ?>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn danger sm" data-confirm="حذف نهائي؟">حذف</button>
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
<div class="pagination">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&filter=<?= $filter ?>"
       class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
