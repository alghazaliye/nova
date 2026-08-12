<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
requirePermission($admin, 'groups.view');
$pageTitle = 'إدارة المجموعات';
$pdo = getAdminDB();
$q = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($q !== '') { $where = 'WHERE g.name LIKE ? OR g.description LIKE ?'; $params = ["%$q%", "%$q%"] ; }
$stmt = $pdo->prepare("SELECT g.id,g.name,g.description,g.created_at,u.name AS owner_name,
    (SELECT COUNT(*) FROM conversation_members cm WHERE cm.conversation_id=g.conversation_id AND cm.left_at IS NULL) AS members
    FROM `groups` g JOIN users u ON u.id=g.created_by $where ORDER BY g.created_at DESC LIMIT 100");
$stmt->execute($params); $groups = $stmt->fetchAll();
include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>إدارة المجموعات</h2><p>عرض المجموعات وعدد أعضائها ومالك كل مجموعة.</p></div></div>
<div class="filters"><form method="GET" class="search"><span>⌕</span><input name="q" placeholder="ابحث باسم المجموعة..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"><button class="btn sm" type="submit">بحث</button></form></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المجموعة</th><th>المالك</th><th>الأعضاء</th><th>تاريخ الإنشاء</th><th>المعرف</th></tr></thead><tbody>
<?php foreach ($groups as $group): ?><tr><td><b><?= htmlspecialchars($group['name']) ?></b><small style="display:block;color:var(--muted)"><?= htmlspecialchars($group['description'] ?? '') ?></small></td><td><?= htmlspecialchars($group['owner_name']) ?></td><td><?= number_format((int)$group['members']) ?></td><td><?= date('d/m/Y H:i', strtotime($group['created_at'])) ?></td><td>#<?= (int)$group['id'] ?></td></tr><?php endforeach; ?>
<?php if (!$groups): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px">لا توجد مجموعات حاليًا</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
