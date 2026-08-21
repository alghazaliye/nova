<?php
/**
 * NOVA Messenger Admin - إدارة الحالات (Status)
 * إحصائيات + عرض الحالات + تفاعلات وردود + حذف إداري مع Audit
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'statuses.view');
$pageTitle = 'إدارة الحالات';
$pdo = getAdminDB();

// ===== معالجة POST: حذف إداري =====
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['story_id'] ?? 0);
    $reason = mb_substr((string)($_POST['admin_note'] ?? ''), 0, 500);
    if ($action === 'admin_delete' && $id > 0) {
        try {
            $stmt = $pdo->prepare('SELECT id, user_id, deleted_at, deleted_by FROM stories WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $story = $stmt->fetch();
            if (!$story) {
                $flash = ['warn', 'الحالة غير موجودة.'];
            } elseif ($story['deleted_at'] !== null && $story['deleted_by'] !== null) {
                $flash = ['warn', 'الحالة محذوفة إداريًا مسبقًا.'];
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE stories SET deleted_at = datetime(\'now\',\'localtime\'), deleted_by = ? WHERE id = ?')
                    ->execute([0 - (int)$admin['id'], $id]);
                $pdo->prepare(
                    'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\',\'localtime\'))'
                )->execute([
                    (int)$admin['id'], 'statuses.admin_deleted', 'story', $id,
                    'حذف إداري للحالة #' . $id . ' — صاحبها: ' . (int)$story['user_id'] . ' — السبب: ' . ($reason ?: 'لم يُذكر'),
                    $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
                $pdo->commit();
                logAudit($admin, 'statuses.admin_deleted', 'story', $id, 'حذف إداري للحالة #' . $id . ' — السبب: ' . ($reason ?: 'لم يُذكر'));
                $flash = ['ok', 'تم حذف الحالة إداريًا وتسجيل العملية في السجل.'];
            }
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $flash = ['err', 'حدث خطأ: ' . h((string)$e->getMessage())];
        }
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ===== الإحصائيات =====
$stats = [
    'total' => 0, 'active' => 0, 'today' => 0, 'expired' => 0,
    'deleted' => 0, 'admin_deleted' => 0, 'views' => 0, 'reactions' => 0, 'replies' => 0
];
try {
    $q = function($w) use ($pdo): int {
        try {
            return (int)($pdo->query("SELECT COUNT(*) FROM stories s {$w}")->fetchColumn() ?: 0);
        } catch (\Throwable $e) { return 0; }
    };
    $stats = [
        'total'         => $q(''),
        'active'        => $q("WHERE s.expires_at > datetime('now','localtime') AND s.deleted_at IS NULL AND s.deleted_by IS NULL"),
        'today'         => $q("WHERE DATE(s.created_at) = DATE('now','localtime')"),
        'expired'       => $q("WHERE s.expires_at <= datetime('now','localtime')"),
        'deleted'       => $q('WHERE s.deleted_at IS NOT NULL AND s.deleted_by IS NULL'),
        'admin_deleted' => $q('WHERE s.deleted_at IS NOT NULL AND s.deleted_by IS NOT NULL'),
        'views'         => 0,
        'reactions'     => 0,
        'replies'       => 0
    ];
    try { $stats['views'] = (int)($pdo->query('SELECT COUNT(*) FROM story_views')->fetchColumn() ?: 0); } catch (\Throwable $e) {}
    try { $stats['reactions'] = (int)($pdo->query('SELECT COUNT(*) FROM story_reactions')->fetchColumn() ?: 0); } catch (\Throwable $e) {}
    try { $stats['replies'] = (int)($pdo->query('SELECT COUNT(*) FROM story_replies')->fetchColumn() ?: 0); } catch (\Throwable $e) {}
} catch (\Throwable $e) {
    $flash = ['warn', 'تعذر جلب الإحصائيات: ' . h((string)$e->getMessage())];
}

// ===== جدول الحالات =====
$filter = $_GET['filter'] ?? 'active';
$where = match ($filter) {
    'expired' => "WHERE s.expires_at <= datetime('now','localtime')",
    'deleted' => 'WHERE s.deleted_at IS NOT NULL AND s.deleted_by IS NULL',
    'admin_deleted' => 'WHERE s.deleted_at IS NOT NULL AND s.deleted_by IS NOT NULL',
    'all' => '',
    default => "WHERE s.expires_at > datetime('now','localtime') AND s.deleted_at IS NULL AND s.deleted_by IS NULL",
};
try {
    $stmt = $pdo->query(
        "SELECT s.id, s.user_id, s.type, s.text, s.privacy, s.created_at, s.expires_at, s.deleted_at, s.deleted_by,
                u.name AS user_name, u.phone AS user_phone,
                (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id) AS view_count,
                (SELECT COUNT(*) FROM story_reactions sr WHERE sr.story_id = s.id) AS reaction_count,
                (SELECT COUNT(*) FROM story_replies sr WHERE sr.story_id = s.id) AS reply_count,
                (SELECT COUNT(*) FROM reports r WHERE r.story_id = s.id AND r.status = 'pending') AS pending_reports
         FROM stories s JOIN users u ON u.id = s.user_id
         {$where} ORDER BY s.created_at DESC LIMIT 200"
    );
    $stories = (array)$stmt->fetchAll();
} catch (\Throwable $e) {
    $stories = [];
    $flash = ['warn', 'تعذر جلب الحالات: ' . h((string)$e->getMessage())];
}
$canDelete = hasPermission($admin, 'statuses.delete');
?>
<style>
  .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin-bottom:18px; }
  .stat-card { background:var(--surface2); border-radius:14px; padding:14px; text-align:center; }
  .stat-card b { font-size:22px; display:block; }
  .stat-card small { color:var(--muted); font-size:12px; }
  table{width:100%;border-collapse:collapse;background:var(--surface);border-radius:14px;overflow:hidden}
  th,td{padding:10px 12px;text-align:right;border-bottom:1px solid var(--border);font-size:14px;vertical-align:top}
  th{background:var(--surface2);font-size:12px;color:var(--muted)}
  .pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700}
  .pill-active{background:#d4edda;color:#155724}
  .pill-expired{background:#fff3cd;color:#856404}
  .pill-deleted{background:#f8d7da;color:#721c24}
  .pill-admin_deleted{background:#f5c6cb;color:#721c24}
  .btn-row{display:flex;gap:6px}
  details{background:var(--surface2);border-radius:10px;padding:8px 12px;margin-top:6px;font-size:13px}
  summary{cursor:pointer;font-weight:600}
</style>

<h2>إدارة الحالات (Status)</h2>
<div class="stat-grid">
  <div class="stat-card"><b><?= $stats['total'] ?></b><small>إجمالي الحالات</small></div>
  <div class="stat-card"><b style="color:#28a745"><?= $stats['active'] ?></b><small>نشطة</small></div>
  <div class="stat-card"><b style="color:#c77700"><?= $stats['today'] ?></b><small>نشرت اليوم</small></div>
  <div class="stat-card"><b style="color:#fd7e14"><?= $stats['expired'] ?></b><small>منتهية</small></div>
  <div class="stat-card"><b style="color:#dc3545"><?= $stats['deleted'] ?></b><small>محذوفة من الناشر</small></div>
  <div class="stat-card"><b style="color:#dc3545"><?= $stats['admin_deleted'] ?></b><small>محذوفة إداريًا</small></div>
  <div class="stat-card"><b><?= $stats['views'] ?></b><small>إجمالي المشاهدات</small></div>
  <div class="stat-card"><b><?= $stats['reactions'] ?></b><small>التفاعلات</small></div>
  <div class="stat-card"><b><?= $stats['replies'] ?></b><small>الردود</small></div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[0] ?>"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="filters">
  <a class="btn sm <?= $filter==='active'?'primary':'' ?>" href="?filter=active">نشطة</a>
  <a class="btn sm <?= $filter==='expired'?'primary':'' ?>" href="?filter=expired">منتهية</a>
  <a class="btn sm <?= $filter==='deleted'?'primary':'' ?>" href="?filter=deleted">محذوفة من الناشر</a>
  <a class="btn sm <?= $filter==='admin_deleted'?'primary':'' ?>" href="?filter=admin_deleted">محذوفة إداريًا</a>
  <a class="btn sm <?= $filter==='all'?'primary':'' ?>" href="?filter=all">الكل</a>
</div>

<?php if (empty($stories)): ?>
  <div class="empty">لا توجد حالات في هذا التصنيف.</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th><th>الناشر</th><th>النوع</th><th>النص</th><th>الخصوصية</th>
      <th>مشاهدات</th><th>تفاعلات</th><th>ردود</th><th>بلاغات</th>
      <th>نُشرت في</th><th>تنتهي في</th><th>الحالة</th><th>إجراء</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($stories as $s):
    $statusPill = $s['deleted_at'] === null
      ? ['active', 'نشطة']
      : ($s['deleted_by'] !== null ? ['admin_deleted', 'محذوفة إداريًا'] : ['deleted', 'محذوفة']);
  ?>
    <tr>
      <td><?= (int)$s['id'] ?></td>
      <td>
        <b><?= h((string)$s['user_name']) ?></b><br>
        <small style="color:var(--muted)"><?= h((string)($s['user_phone'] ?? '')) ?></small>
      </td>
      <td><?= h((string)$s['type']) ?></td>
      <td style="max-width:260px"><?= $s['text'] ? nl2br(h(mb_strimwidth((string)$s['text'], 0, 80, '…'))) : '<span style="color:var(--muted)">بدون نص</span>' ?></td>
      <td><span class="pill pill-active"><?= h((string)($s['privacy'] ?? 'contacts')) ?></span></td>
      <td><?= number_format((int)$s['view_count']) ?></td>
      <td><?= number_format((int)$s['reaction_count']) ?></td>
      <td><?= number_format((int)$s['reply_count']) ?></td>
      <td><?= (int)$s['pending_reports'] > 0 ? '<span style="color:#dc3545;font-weight:700">' . (int)$s['pending_reports'] . '</span>' : '0' ?></td>
      <td><small><?= h((string)$s['created_at']) ?></small></td>
      <td><small><?= h((string)($s['expires_at'] ?? '-')) ?></small></td>
      <td><span class="pill pill-<?= $statusPill[0] ?>"><?= $statusPill[1] ?></span></td>
      <td>
        <div class="btn-row">
          <button class="btn sm" onclick="openDetails(<?= (int)$s['id'] ?>, '<?= h(addslashes((string)$s['user_name'])) ?>', '<?= h((string)$s['type']) ?>', '<?= h(addslashes((string)($s['text'] ?? ''))) ?>', '<?= h((string)$s['created_at']) ?>', '<?= h((string)($s['expires_at'] ?? '')) ?>')">تفاصيل</button>
          <?php if ($canDelete && $statusPill[0] === 'active'): ?>
            <button class="btn sm btn-danger" onclick="openDelete(<?= (int)$s['id'] ?>)">حذف إداري</button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- Modal التفاصيل -->
<div id="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center;">
  <div style="background:var(--surface); border-radius:16px; padding:22px; max-width:460px; width:92%;">
    <h3 id="modalTitle" style="margin-top:0">تفاصيل الحالة</h3>
    <div id="modalBody" style="font-size:14px; line-height:1.8"></div>
    <div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end">
      <button type="button" class="btn" onclick="closeModal()">إغلاق</button>
    </div>
  </div>
</div>

<!-- Modal الحذف الإداري -->
<div id="deleteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;">
  <div style="background:var(--surface); border-radius:16px; padding:22px; max-width:420px; width:92%;">
    <h3 style="margin-top:0; color:#dc3545">حذف إداري للحالة</h3>
    <form id="deleteForm" method="post">
      <input type="hidden" name="action" value="admin_delete">
      <input type="hidden" name="story_id" id="dId">
      <label style="font-size:13px; font-weight:600">سبب الحذف (اختياري):</label>
      <textarea name="admin_note" id="dNote" rows="3" style="width:100%; border-radius:10px; padding:10px; margin-top:6px; background:var(--surface2); border:1px solid var(--border); color:inherit; resize:vertical" maxlength="500" placeholder="مثال: مخالفة سياسة المحتوى"></textarea>
      <div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end">
        <button type="button" class="btn" onclick="closeDelete()">إلغاء</button>
        <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
      </div>
    </form>
  </div>
</div>

<script>
function closeModal(){ document.getElementById('modal').style.display = 'none'; }
function closeDelete(){ document.getElementById('deleteModal').style.display = 'none'; }
function openDetails(id, user, type, text, created, expires){
  document.getElementById('modalTitle').textContent = 'تفاصيل الحالة #' + id;
  document.getElementById('modalBody').innerHTML =
    '<b>الناشر:</b> ' + user + '<br>' +
    '<b>النوع:</b> ' + type + '<br>' +
    '<b>النص:</b> ' + (text || 'لا يوجد') + '<br>' +
    '<b>نُشرت:</b> ' + created + '<br>' +
    '<b>تنتهي:</b> ' + (expires || 'غير محدد') + '<br>' +
    '<b>ملاحظة:</b> يمكن فتح صفحة الحالة من لوحة التحكم عبر <a href="api-docs.php">وثائق API</a>';
  document.getElementById('modal').style.display = 'flex';
}
function openDelete(id){
  document.getElementById('dId').value = id;
  document.getElementById('deleteModal').style.display = 'flex';
}
document.getElementById('modal').addEventListener('click', function(e){ if (e.target === this) closeModal(); });
document.getElementById('deleteModal').addEventListener('click', function(e){ if (e.target === this) closeDelete(); });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
