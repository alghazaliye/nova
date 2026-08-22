<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'messages.view');
$pageTitle = 'الرسائل المحذوفة'; $pdo = getAdminDB();

$q = trim($_GET['q'] ?? '');
$params = []; $where = '';
if ($q !== '') {
    $where = 'WHERE u.name LIKE ? OR md.original_body LIKE ?';
    $params = ["%$q%", "%$q%"];
}
// The message_deletions table is not part of the deployed schema yet:
// show an empty state instead of a fatal error when visiting this page.
// Check table existence compatibly for SQLite/Turso
$tableExists = false;
try {
    $pdo->query("SELECT 1 FROM message_deletions LIMIT 1");
    $tableExists = true;
} catch (Exception $e) {}
$rows = [];
if ($tableExists) {
    $stmt = $pdo->prepare(
        "SELECT md.id, md.message_id, md.conversation_id, md.deleted_by,
                md.original_body, md.original_type, md.scope_type, md.deleted_at,
                u.name AS deleter_name, u.phone AS deleter_phone,
                c.title AS conv_title, c.type AS conv_type
         FROM message_deletions md
         JOIN users u ON u.id = md.deleted_by
         LEFT JOIN conversations c ON c.id = md.conversation_id
         $where ORDER BY md.deleted_at DESC LIMIT 200"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>تتبع الرسائل المحذوفة</h2><p>سجل كامل للرسائل المحذوفة لدى الطرفين (حذف لدى المستخدم فقط، أو حذف لدى الجميع).</p></div></div>
<div class="filters"><form method="GET" class="search"><span>⌕</span><input name="q" placeholder="ابحث بالاسم أو نص الرسالة..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"><button class="btn sm" type="submit">بحث</button></form></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المحادثة</th><th>الحاذف</th><th>النص الأصلي</th><th>النوع</th><th>النطاق</th><th>الوقت</th></tr></thead><tbody>
<?php
$typeIcons = ['text'=>'📝','image'=>'🖼️','video'=>'🎥','audio'=>'🎧','voice'=>'🎙️','file'=>'📎','location'=>'📍','contact'=>'👤','poll'=>'📊'];
$scopeLabel = ['self'=>'لدى المستخدم فقط','everyone'=>'لدى الجميع'];
foreach ($rows as $r): ?>
<tr>
  <td><b><?= htmlspecialchars(mb_strimwidth((string)($r['conv_title'] ?? 'خاصة #' . (int)$r['conversation_id']), 0, 28, '…')) ?></b><br><small style="color:var(--muted)"><?= $r['conv_type'] === 'group' ? 'مجموعة' : 'خاصة' ?> #<?= (int)$r['conversation_id'] ?></small></td>
  <td><b><?= htmlspecialchars((string)$r['deleter_name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)($r['deleter_phone'] ?? '')) ?></small></td>
  <td><span style="background:rgba(239,68,68,.1);padding:4px 8px;border-radius:6px;display:block"><?= htmlspecialchars((string)($r['original_body'] ?? '—')) ?></span></td>
  <td><?= $typeIcons[$r['original_type']] ?? '📄' ?> <?= htmlspecialchars((string)$r['original_type']) ?></td>
  <td><span style="background:<?= $r['scope_type'] === 'everyone' ? 'rgba(239,68,68,.1)' : 'rgba(245,158,11,.1)' ?>;padding:4px 8px;border-radius:6px;white-space:nowrap"><?= $scopeLabel[$r['scope_type']] ?? $r['scope_type'] ?></span></td>
  <td><?= date('d/m/Y H:i', strtotime((string)$r['deleted_at'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">لا توجد رسائل محذوفة</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
