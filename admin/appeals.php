<?php
/**
 * NOVA Messenger Admin - الاعتراضات
 * قائمة اعتراضات المستخدمين المحظورين مع قبول/رفض وإلغاء الحظر تلقائيًا عند القبول.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
guardAdmin();
$pageTitle = 'الاعتراضات';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$pdo = getAdminDB();

// ===== معالجة POST (قبول/رفض) =====
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['appeal_id'] ?? 0);
    $note = mb_substr((string)($_POST['admin_note'] ?? ''), 0, 500);
    if (in_array($action, ['approved', 'rejected'], true) && $id > 0) {
        try {
            $stmt = $pdo->prepare('SELECT id, user_id, status FROM user_appeals WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                if ($row['status'] !== 'pending') {
                    $flash = ['warn', 'تمت مراجعة هذا الاعتراض مسبقًا.'];
                } else {
                    $pdo->beginTransaction();
                    $st = $pdo->prepare('UPDATE user_appeals SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = datetime(\'now\',\'localtime\') WHERE id = ?');
                    $st->execute([$action, $note ?: null, $admin['id'], $id]);
                    if ($action === 'approved') {
                        // إلغاء الحظر/التعليق وإعادة تفعيل الحساب وإلغاء الجلسات القديمة
                        $pdo->prepare('UPDATE user_bans SET unbanned_at = datetime(\'now\',\'localtime\'), unbanned_by = ? WHERE user_id = ? AND unbanned_at IS NULL')->execute([$admin['id'], $row['user_id']]);
                        $pdo->prepare('UPDATE users SET is_blocked = 0 WHERE id = ?')->execute([$row['user_id']]);
                        $pdo->prepare('UPDATE sessions SET revoked_at = datetime(\'now\',\'localtime\') WHERE user_id = ?')->execute([$row['user_id']]);
                        $flash = ['ok', 'تم قبول الاعتراض وإلغاء الحظر. يمكن للمستخدم تسجيل الدخول مجددًا.'];
                    } else {
                        $flash = ['err', 'تم رفض الاعتراض. سيبقى الحظر ساريًا.'];
                    }
                    $pdo->commit();
                }
            } else {
                $flash = ['warn', 'الاعتراض غير موجود.'];
            }
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $flash = ['err', 'حدث خطأ: ' . h((string)$e->getMessage())];
        }
    }
}

// ===== جلب الاعتراضات مع تفاصيل المستخدم =====
$appeals = [];
try {
    $rows = $pdo->query(
        "SELECT a.id, a.user_id, a.contact_value, a.reason, a.status, a.admin_note,
                a.reviewed_at, a.created_at,
                u.name, u.phone, u.email, u.uuid,
                (SELECT reason FROM user_bans b WHERE b.user_id = a.user_id AND b.unbanned_at IS NULL ORDER BY b.id DESC LIMIT 1) ban_reason,
                (SELECT banned_by FROM user_bans b WHERE b.user_id = a.user_id AND b.unbanned_at IS NULL ORDER BY b.id DESC LIMIT 1) ban_by,
                (SELECT suspend_until FROM user_bans b WHERE b.user_id = a.user_id AND b.unbanned_at IS NULL ORDER BY b.id DESC LIMIT 1) suspend_until
         FROM user_appeals a
         JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC LIMIT 200"
    )->fetchAll();
    $appeals = (array)$rows;
} catch (\Throwable $e) {
    $flash = ['warn', 'لا يمكن جلب الاعتراضات: ' . h((string)$e->getMessage())];
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$stats = [];
foreach (['pending', 'approved', 'rejected'] as $s) {
    try {
        $stats[$s] = (int)$pdo->query("SELECT COUNT(*) FROM user_appeals WHERE status = '{$s}'")->fetchColumn();
    } catch (\Throwable $e) { $stats[$s] = 0; }
}
?>
<style>
  .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:18px; }
  .stat-card { background:var(--surface2); border-radius:14px; padding:14px; text-align:center; }
  .stat-card b { font-size:22px; display:block; }
  .stat-card small { color:var(--muted); font-size:12px; }
  table{width:100%;border-collapse:collapse;background:var(--surface);border-radius:14px;overflow:hidden}
  th,td{padding:10px 12px;text-align:right;border-bottom:1px solid var(--border);font-size:14px;vertical-align:top}
  th{background:var(--surface2);font-size:12px;color:var(--muted)}
  .pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700}
  .pill-pending{background:#fff3cd;color:#856404}
  .pill-approved{background:#d4edda;color:#155724}
  .pill-rejected{background:#f8d7da;color:#721c24}
  .btn-row{display:flex;gap:6px}
  details{background:var(--surface2);border-radius:10px;padding:8px 12px;margin-top:6px;font-size:13px}
  summary{cursor:pointer;font-weight:600}
</style>

<h2>الاعتراضات على الحظر والتعليق</h2>
<div class="stat-grid">
  <div class="stat-card"><b style="color:#c77700"><?= $stats['pending'] ?></b><small>قيد الانتظار</small></div>
  <div class="stat-card"><b style="color:#28a745"><?= $stats['approved'] ?></b><small>مقبولة</small></div>
  <div class="stat-card"><b style="color:#dc3545"><?= $stats['rejected'] ?></b><small>مرفوضة</small></div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[0] ?>"><?= h($flash[1]) ?></div>
<?php endif; ?>

<?php if (empty($appeals)): ?>
  <div class="empty">لا توجد اعتراضات حتى الآن.</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th><th>المستخدم</th><th>وسيلة التواصل</th><th>سبب الاعتراض</th>
      <th>سبب الحظر/التعليق</th><th>حتى (تعليق)</th><th>التاريخ</th><th>الحالة</th><th>إجراء</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($appeals as $a): ?>
    <tr>
      <td><?= (int)$a['id'] ?></td>
      <td>
        <b><?= h((string)$a['name']) ?></b><br>
        <small style="color:var(--muted)">
          <?= h((string)($a['phone'] ?? '')) ?>
          <?php if (!empty($a['email'])): ?> · <?= h((string)$a['email']) ?><?php endif; ?>
        </small>
      </td>
      <td><?= h((string)($a['contact_value'] ?? '-')) ?></td>
      <td><?= nl2br(h((string)$a['reason'])) ?></td>
      <td><?= $a['ban_reason'] ? nl2br(h((string)$a['ban_reason'])) : '<span style="color:var(--muted)">غير مذكور</span>' ?></td>
      <td><?= $a['suspend_until'] ? h((string)$a['suspend_until']) : '-' ?></td>
      <td><small><?= h((string)$a['created_at']) ?></small></td>
      <td><span class="pill pill-<?= h((string)$a['status']) ?>"><?= h((string)$a['status']) === 'pending' ? 'قيد الانتظار' : (h((string)$a['status']) === 'approved' ? 'مقبول' : 'مرفوض') ?></span></td>
      <td>
        <?php if ((string)$a['status'] === 'pending'): ?>
          <div class="btn-row">
            <button class="btn btn-ok" onclick="openModal(<?= (int)$a['id'] ?>, 'approved', this)">✓ قبول</button>
            <button class="btn btn-danger" onclick="openModal(<?= (int)$a['id'] ?>, 'rejected', this)">✗ رفض</button>
          </div>
          <details><summary>ملاحظات المراجعة السابقة</summary>
            <?= $a['admin_note'] ? nl2br(h((string)$a['admin_note'])) : '<span style="color:var(--muted)">لا توجد</span>' ?>
            <?php if ($a['reviewed_at']): ?><br><small style="color:var(--muted)">مراجعة: <?= h((string)$a['reviewed_at']) ?></small><?php endif; ?>
          </details>
        <?php elseif ($a['admin_note']): ?>
          <details open><summary>ملاحظة المراجعة</summary><?= nl2br(h((string)$a['admin_note'])) ?></details>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- Modal القبول/الرفض -->
<div id="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center;">
  <div style="background:var(--surface); border-radius:16px; padding:22px; max-width:420px; width:92%;">
    <h3 id="modalTitle" style="margin-top:0">مراجعة الاعتراض</h3>
    <form id="modalForm" method="post">
      <input type="hidden" name="appeal_id" id="mId">
      <input type="hidden" name="action" id="mAction">
      <label style="font-size:13px; font-weight:600">ملاحظة إدارية (اختياري):</label>
      <textarea name="admin_note" id="mNote" rows="3" style="width:100%; border-radius:10px; padding:10px; margin-top:6px; background:var(--surface2); border:1px solid var(--border); color:inherit; resize:vertical" maxlength="500" placeholder="مثال: سُمح للمستخدم بالتسجيل مجددًا مع تنبيهه بالالتزام بالشروط"></textarea>
      <div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end">
        <button type="button" class="btn" onclick="closeModal()">إلغاء</button>
        <button type="submit" class="btn btn-primary" id="mSubmit">تأكيد</button>
      </div>
    </form>
  </div>
</div>
<script>
function openModal(id, action, btn){
  document.getElementById('mId').value = id;
  document.getElementById('mAction').value = action;
  document.getElementById('modalTitle').textContent = action === 'approved'
    ? 'قبول الاعتراض — سيتم إلغاء الحظر وإتاحة الدخول مجددًا'
    : 'رفض الاعتراض — سيبقى الحظر ساريًا';
  document.getElementById('mSubmit').className = action === 'approved' ? 'btn btn-ok' : 'btn btn-danger';
  const md = document.getElementById('modal');
  md.style.display = 'flex';
}
function closeModal(){ document.getElementById('modal').style.display = 'none'; }
document.getElementById('modal').addEventListener('click', function(e){ if (e.target === this) closeModal(); });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
