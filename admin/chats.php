<?php
/**
 * NOVA Messenger Admin - إدارة المحادثات (مترقية)
 * فلاتر متقدمة: النوع، البحث بالمرسل/المستلم/نص الرسالة/رقم محادثة، نطاق التاريخ.
 * حذف إداري للرسائل، عرض أعضاء المحادثة، ترقيم صفحات.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'chats.view');
$pageTitle = 'إدارة المحادثات';
$pdo = getAdminDB();

// ===== الفلاتر =====
$fType   = trim((string)($_GET['type'] ?? ''));
$fUser   = trim((string)($_GET['user'] ?? ''));
$fFrom   = trim((string)($_GET['from'] ?? ''));
$fTo     = trim((string)($_GET['to'] ?? ''));
$fMsgId  = trim((string)($_GET['msg_id'] ?? ''));
$fConvId = trim((string)($_GET['conv_id'] ?? ''));
$fQ      = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$where = [];
$bind  = [];

if (in_array($fType, ['private', 'group'], true)) {
    $where[] = 'c.type = ?'; $bind[] = $fType;
}
if ($fUser !== '') {
    $where[] = "(c.title LIKE ? OR m.body LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)";
    $bind[] = "%$fUser%"; $bind[] = "%$fUser%"; $bind[] = "%$fUser%"; $bind[] = "%$fUser%";
}
if ($fQ !== '') {
    $where[] = 'm.body LIKE ?';
    $bind[] = "%$fQ%";
}
if ($fMsgId !== '') {
    $where[] = 'm.id = ?';
    $bind[] = (int)$fMsgId;
}
if ($fConvId !== '') {
    $where[] = 'c.id = ?';
    $bind[] = (int)$fConvId;
}
if ($fFrom !== '') {
    $where[] = 'c.updated_at >= ?';
    $bind[] = $fFrom;
}
if ($fTo !== '') {
    $where[] = 'c.updated_at < ?';
    $bind[] = date('Y-m-d', strtotime($fTo . ' +1 day'));
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT c.id, c.type, c.title, c.created_at, c.updated_at,
       (SELECT COUNT(*) FROM conversation_members cm WHERE cm.conversation_id = c.id AND cm.left_at IS NULL) members,
       m.id last_msg_id, m.body last_body, m.created_at last_msg_at, u.name sender_name, u.id sender_id
     FROM conversations c
     LEFT JOIN messages m ON m.id = c.last_message_id
     LEFT JOIN users u ON u.id = m.sender_id
     {$sqlWhere}
     ORDER BY c.updated_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($bind);
$chats = $stmt->fetchAll();

// ===== إجمالي الصفحات =====
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM conversations c LEFT JOIN messages m ON m.id = c.last_message_id LEFT JOIN users u ON u.id = m.sender_id {$sqlWhere}");
$countStmt->execute($bind);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// ===== حذف إداري للرسائل =====
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_msg_id'])) {
    $mid = (int)$_POST['delete_msg_id'];
    $cid = (int)$_POST['conv_id'];
    try {
        $stmt = $pdo->prepare('SELECT id, body FROM messages WHERE id = ? LIMIT 1');
        $stmt->execute([$mid]);
        $msg = $stmt->fetch();
        if ($msg) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE messages SET deleted_at = datetime(\'now\',\'localtime\') WHERE id = ?')->execute([$mid]);
            $pdo->prepare('INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$admin['id'], 'admin_delete_message', 'message', $mid, mb_substr((string)$msg['body'], 0, 200), (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
            $pdo->commit();
            $flash = ['ok', 'تم حذف الرسالة بنجاح من المحادثة #' . $cid];
        } else {
            $flash = ['warn', 'الرسالة غير موجودة.'];
        }
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $flash = ['err', 'حدث خطأ: ' . htmlspecialchars((string)$e->getMessage(), ENT_QUOTES, 'UTF-8')];
    }
}

// ===== معلومات تفاصيل محادثة (عبر AJAX أو مباشر) =====
$convDetails = null;
$detId = trim((string)($_GET['conv'] ?? ''));
if ($detId !== '') {
    $stmt = $pdo->prepare(
        "SELECT u.name, u.phone, cm.role, cm.left_at, u.is_blocked
         FROM conversation_members cm
         JOIN users u ON u.id = cm.user_id
         WHERE cm.conversation_id = ? ORDER BY cm.id ASC"
    );
    $stmt->execute([(int)$detId]);
    $convDetails = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<style>
  .filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:var(--surface2);border-radius:14px;padding:12px;margin-bottom:14px}
  .filters input,.filters select{border:1px solid var(--border);border-radius:10px;padding:7px 10px;background:var(--surface);color:inherit;font-size:13px}
  .filters .btn{margin-left:auto}
  .msg-body{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
  .actions{display:flex;gap:5px}
  .paging{display:flex;gap:6px;justify-content:center;margin-top:14px;flex-wrap:wrap}
  .paging a,.paging span{padding:5px 11px;border-radius:9px;background:var(--surface2);text-decoration:none;font-size:13px;color:inherit}
  .paging a.active{background:var(--primary);color:#fff}
  .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center}
  .modal .box{background:var(--surface);border-radius:16px;padding:20px;max-width:520px;width:92%;max-height:80vh;overflow:auto}
  table th{white-space:nowrap}
</style>

<div class="pagehead"><div><h2>إدارة المحادثات</h2><p>مراجعة المحادثات، بحث متقدم، حذف الرسائل إداريًا، وعرض المشاركين.</p></div></div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[0] ?>"><?= h2($flash[1]) ?></div>
<?php endif; ?>

<div class="filters">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;flex:1">
    <select name="type"><option value="">كل الأنواع</option><option value="private" <?= $fType==='private'?'selected':'' ?>>خاصة</option><option value="group" <?= $fType==='group'?'selected':'' ?>>مجموعات</option></select>
    <input name="q" placeholder="بحث في نص الرسائل..." value="<?= h2($fQ) ?>" style="min-width:180px">
    <input name="user" placeholder="اسم/هاتف مستخدم أو عنوان" value="<?= h2($fUser) ?>" style="min-width:160px">
    <input name="conv_id" type="number" placeholder="رقم محادثة" value="<?= h2($fConvId) ?>" style="width:110px">
    <input name="msg_id" type="number" placeholder="رقم رسالة" value="<?= h2($fMsgId) ?>" style="width:110px">
    <label style="font-size:12px;color:var(--muted)">من</label>
    <input name="from" type="date" value="<?= h2($fFrom) ?>">
    <label style="font-size:12px;color:var(--muted)">إلى</label>
    <input name="to" type="date" value="<?= h2($fTo) ?>">
    <button class="btn btn-primary" type="submit">بحث</button>
    <a class="btn" href="chats.php">إعادة تعيين</a>
  </form>
</div>

<div class="card panel tablewrap"><table class="table">
  <thead><tr>
    <th>#</th><th>المحادثة</th><th>النوع</th><th>المشاركون</th><th>آخر رسالة</th><th>رقم الرسالة</th><th>المرسل</th><th>آخر تحديث</th><th>إجراءات</th>
  </tr></thead>
  <tbody>
  <?php foreach ($chats as $chat): ?>
    <tr>
      <td><?= (int)$chat['id'] ?></td>
      <td><b><?= h2($chat['title'] ?: 'محادثة خاصة #' . (int)$chat['id']) ?></b></td>
      <td><span class="status <?= $chat['type']==='group'?'online':'offline' ?>"><?= $chat['type']==='group'?'مجموعة':'خاصة' ?></span></td>
      <td><?= number_format((int)$chat['members']) ?></td>
      <td><span class="msg-body" title="<?= h2((string)($chat['last_body'] ?? '')) ?>"><?= $chat['last_body'] ? h2(mb_strimwidth((string)$chat['last_body'], 0, 90, '…')) : '<span style="color:var(--muted)">لا توجد رسائل</span>' ?></span></td>
      <td><small style="color:var(--muted)"><?= $chat['last_msg_id'] ? '#' . (int)$chat['last_msg_id'] : '-' ?></small></td>
      <td><?= h2($chat['sender_name'] ?? '') ?: '<span style="color:var(--muted)">—</span>' ?></td>
      <td><?= date('d/m/Y H:i', strtotime($chat['updated_at'])) ?></td>
      <td class="actions">
        <a class="btn sm" href="chats.php?<?= http_build_query(array_merge($_GET, ['conv' => (int)$chat['id']])) ?>">أعضاء</a>
        <?php if ($chat['last_msg_id']): ?>
          <button class="btn sm btn-danger" onclick="confirmDelete(<?= (int)$chat['last_msg_id'] ?>, <?= (int)$chat['id'] ?>)">حذف الرسالة</button>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$chats): ?>
    <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:30px">لا توجد محادثات مطابقة</td></tr>
  <?php endif; ?>
  </tbody>
</table></div>

<div class="paging">
  <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">« السابق</a><?php endif; ?>
  <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
    <?= $p === $page ? '<span class="active">' . $p . '</span>' : '<a href="?'.http_build_query(array_merge($_GET,['page'=>$p])).'">'.$p.'</a>' ?>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">التالي »</a><?php endif; ?>
  <span style="color:var(--muted); font-size:12px; align-self:center">إجمالي: <?= number_format($total) ?> محادثة</span>
</div>

<?php if ($convDetails !== null): ?>
<h3 style="margin-top:24px">أعضاء المحادثة #<?= h2($detId) ?> (<?= count($convDetails) ?>)</h3>
<div class="card panel tablewrap"><table class="table">
  <thead><tr><th>المستخدم</th><th>الهاتف</th><th>الدور</th><th>غادر؟</th><th>محظور؟</th></tr></thead>
  <tbody>
  <?php foreach ($convDetails as $m): ?>
    <tr>
      <td><b><?= h2((string)$m['name']) ?></b></td>
      <td><?= h2((string)($m['phone'] ?? '')) ?></td>
      <td><?= h2((string)($m['role'] ?? 'عضو')) ?></td>
      <td><?= $m['left_at'] ? '<span style="color:#dc3545">نعم</span>' : 'لا' ?></td>
      <td><?= $m['is_blocked'] ? '<span style="color:#dc3545">نعم</span>' : 'لا' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<!-- Modal تأكيد الحذف -->
<div id="delModal" class="modal">
  <div class="box">
    <h3 style="margin-top:0">حذف الرسالة إداريًا</h3>
    <p>سيتم حذف الرسالة نهائيًا وتسجيل العملية في سجل التدقيق. هل أنت متأكد؟</p>
    <form method="post" style="display:flex;gap:8px;justify-content:flex-end">
      <input type="hidden" name="delete_msg_id" id="dMid">
      <input type="hidden" name="conv_id" id="dCid">
      <button type="button" class="btn" onclick="closeDel()">إلغاء</button>
      <button type="submit" class="btn btn-danger">نعم، احذف</button>
    </form>
  </div>
</div>
<script>
function confirmDelete(mid, cid){
  document.getElementById('dMid').value = mid;
  document.getElementById('dCid').value = cid;
  document.getElementById('delModal').style.display = 'flex';
}
function closeDel(){ document.getElementById('delModal').style.display = 'none'; }
document.getElementById('delModal').addEventListener('click', function(e){ if(e.target===this) closeDel(); });
</script>
<?php
function h2(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
include __DIR__ . '/includes/footer.php'; ?>
