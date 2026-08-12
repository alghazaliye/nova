<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'messages.view');
$pageTitle = 'الرسائل المعدلة'; $pdo = getAdminDB();

$q = trim($_GET['q'] ?? '');
$params = []; $where = '';
if ($q !== '') {
    $where = 'WHERE u.name LIKE ? OR me.old_body LIKE ? OR me.new_body LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$stmt = $pdo->prepare(
    "SELECT me.id, me.message_id, me.conversation_id, me.user_id,
            me.old_body, me.new_body, me.edited_at,
            u.name AS editor_name, u.phone AS editor_phone,
            c.title AS conv_title, c.type AS conv_type
     FROM message_edits me
     JOIN users u ON u.id = me.user_id
     LEFT JOIN conversations c ON c.id = me.conversation_id
     $where ORDER BY me.edited_at DESC LIMIT 200"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>تتبع الرسائل المعدلة</h2><p>سجل كامل للرسائل التي تم تعديلها مع النص قبل وبعد التعديل.</p></div></div>
<div class="filters"><form method="GET" class="search"><span>⌕</span><input name="q" placeholder="ابحث بالاسم أو نص الرسالة..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"><button class="btn sm" type="submit">بحث</button></form></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المحادثة</th><th>المعدِّل</th><th>النص قبل التعديل</th><th>النص بعد التعديل</th><th>الوقت</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><b><?= htmlspecialchars(mb_strimwidth((string)($r['conv_title'] ?? 'خاصة #' . (int)$r['conversation_id']), 0, 28, '…')) ?></b><br><small style="color:var(--muted)"><?= $r['conv_type'] === 'group' ? 'مجموعة' : 'خاصة' ?> #<?= (int)$r['conversation_id'] ?></small></td>
  <td><b><?= htmlspecialchars((string)$r['editor_name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)($r['editor_phone'] ?? '')) ?></small></td>
  <td><span style="background:rgba(239,68,68,.1);padding:4px 8px;border-radius:6px;display:block"><?= htmlspecialchars((string)($r['old_body'] ?? '—')) ?></span></td>
  <td><span style="background:rgba(34,197,94,.1);padding:4px 8px;border-radius:6px;display:block"><?= htmlspecialchars((string)($r['new_body'] ?? '—')) ?></span></td>
  <td><?= date('d/m/Y H:i', strtotime((string)$r['edited_at'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px">لا توجد رسائل معدلة</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
