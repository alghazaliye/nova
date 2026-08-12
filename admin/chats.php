<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'messages.view');
$pageTitle = 'إدارة المحادثات'; $pdo = getAdminDB(); $q = trim($_GET['q'] ?? '');
$params=[]; $where=''; if($q!==''){ $where='WHERE c.title LIKE ? OR m.body LIKE ?'; $params=["%$q%","%$q%"]; }
$stmt=$pdo->prepare("SELECT c.id,c.type,c.title,c.created_at,c.updated_at,
 (SELECT COUNT(*) FROM conversation_members cm WHERE cm.conversation_id=c.id AND cm.left_at IS NULL) members,
 m.body last_body,u.name sender_name
 FROM conversations c LEFT JOIN messages m ON m.id=c.last_message_id LEFT JOIN users u ON u.id=m.sender_id
 $where ORDER BY c.updated_at DESC LIMIT 100"); $stmt->execute($params); $chats=$stmt->fetchAll();
include __DIR__.'/includes/header.php'; include __DIR__.'/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>إدارة المحادثات</h2><p>مراجعة المحادثات وآخر الرسائل وعدد المشاركين.</p></div></div>
<div class="filters"><form method="GET" class="search"><span>⌕</span><input name="q" placeholder="ابحث في المحادثات..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"><button class="btn sm" type="submit">بحث</button></form></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المحادثة</th><th>النوع</th><th>المشاركون</th><th>آخر رسالة</th><th>آخر تحديث</th></tr></thead><tbody>
<?php foreach($chats as $chat): ?><tr><td><b><?= htmlspecialchars($chat['title'] ?: 'محادثة بدون عنوان') ?></b><small style="display:block;color:var(--muted)">#<?= (int)$chat['id'] ?></small></td><td><span class="status <?= $chat['type']==='group'?'online':'offline' ?>"><?= $chat['type']==='group'?'مجموعة':'خاصة' ?></span></td><td><?= number_format((int)$chat['members']) ?></td><td><?= htmlspecialchars(mb_strimwidth((string)($chat['last_body'] ?? 'لا توجد رسائل'),0,80,'…')) ?><small style="display:block;color:var(--muted)"><?= htmlspecialchars($chat['sender_name'] ?? '') ?></small></td><td><?= date('d/m/Y H:i', strtotime($chat['updated_at'])) ?></td></tr><?php endforeach; ?>
<?php if(!$chats): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px">لا توجد محادثات حاليًا</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__.'/includes/footer.php'; ?>
