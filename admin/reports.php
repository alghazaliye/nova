<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'reports.view');
$pageTitle = 'إدارة البلاغات';
$pdo       = getAdminDB();

$message = '';
$error   = '';

// معالجة الإجراءات: تحديث حالة / حظر / تعليق
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission($admin, 'reports.resolve')) {
    verifyCsrf();
    $reportId = (int)$_POST['report_id'];
    $action   = trim($_POST['action'] ?? '');

    // جلب البلاغ للتحقق من الصلاحية
    $reportStmt = $pdo->prepare('SELECT * FROM reports WHERE id = ?');
    $reportStmt->execute([$reportId]);
    $report = $reportStmt->fetch();

    if ($report && in_array($action, ['reviewing', 'resolved', 'rejected', 'closed'], true)) {
        $pdo->prepare(
            "UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = datetime('now') WHERE id = ?"
        )->execute([$action, $admin['id'], $reportId]);
        logAudit($admin, 'REPORT_' . strtoupper($action), 'report', $reportId);
        $message = 'تم تحديث حالة البلاغ';
    } elseif ($report && $action === 'ban') {
        // حظر نهائي للمستخدم المُبلَّغ عنه
        $pdo->prepare(
            "INSERT INTO user_bans (user_id, reason, banned_by, banned_at)
             VALUES (?, ?, ?, datetime('now'))"
        )->execute([(int)$report['reported_user_id'], 'بلاغ رقم ' . $reportId . ' — ' . ($report['description'] ?: $report['reason']), $admin['id']]);
        $pdo->prepare(
            "UPDATE users SET is_blocked = 1, blocked_at = datetime('now') WHERE id = ?"
        )->execute([(int)$report['reported_user_id']]);
        // إلغاء الجلسات النشطة للمستخدم المحظور
        $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([(int)$report['reported_user_id']]);
        $pdo->prepare(
            "UPDATE reports SET status = 'resolved', reviewed_by = ?, reviewed_at = datetime('now') WHERE id = ?"
        )->execute([$admin['id'], $reportId]);
        logAudit($admin, 'REPORT_BAN_USER', 'user', (int)$report['reported_user_id'], 'حظر نهائي من بلاغ رقم ' . $reportId);
        $message = 'تم حظر المستخدم نهائيًا وتم حل البلاغ';
    } elseif ($report && $action === 'suspend') {
        $hours = max(1, min(720, (int)($_POST['hours'] ?? 24)));
        $pdo->prepare(
            "INSERT INTO user_bans (user_id, reason, banned_by, banned_at, suspend_until)
             VALUES (?, ?, ?, datetime('now'), datetime('now', '+$hours hours'))"
        )->execute([(int)$report['reported_user_id'], 'تعليق مؤقت من بلاغ رقم ' . $reportId, $admin['id']]);
        $pdo->prepare(
            "UPDATE users SET is_blocked = 1, blocked_at = datetime('now') WHERE id = ?"
        )->execute([(int)$report['reported_user_id']]);
        $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([(int)$report['reported_user_id']]);
        $pdo->prepare(
            "UPDATE reports SET status = 'action_taken', reviewed_by = ?, reviewed_at = datetime('now') WHERE id = ?"
        )->execute([$admin['id'], $reportId]);
        logAudit($admin, 'REPORT_SUSPEND_USER', 'user', (int)$report['reported_user_id'], 'تعليق مؤقت ' . $hours . ' ساعة من بلاغ رقم ' . $reportId);
        $message = "تم تعليق المستخدم مؤقتًا لمدة {$hours} ساعة";
    }
}

// جلب تفاصيل البلاغ المحدد
$detail = null;
if (isset($_GET['id'])) {
    $detailStmt = $pdo->prepare(
        "SELECT r.*, u1.name AS reporter_name, u1.phone AS reporter_phone,
                u2.name AS reported_name, u2.phone AS reported_phone, u2.is_blocked AS reported_blocked
         FROM reports r
         JOIN users u1 ON u1.id = r.reporter_id
         JOIN users u2 ON u2.id = r.reported_user_id
         WHERE r.id = ?"
    );
    $detailStmt->execute([(int)$_GET['id']]);
    $detail = $detailStmt->fetch();
    if ($detail) {
        // الرسالة المرفقة (إن وجدت)
        if (!empty($detail['message_id'])) {
            $msgStmt = $pdo->prepare(
                "SELECT m.*, us.name AS sender_name FROM messages m
                 JOIN users us ON us.id = m.sender_id WHERE m.id = ?"
            );
            $msgStmt->execute([(int)$detail['message_id']]);
            $detail['message'] = $msgStmt->fetch();
        }
        // المرفقات
        $attStmt = $pdo->prepare('SELECT * FROM report_attachments WHERE report_id = ? ORDER BY created_at DESC');
        $attStmt->execute([(int)$detail['id']]);
        $detail['attachments'] = $attStmt->fetchAll();
        // أحدث بند للحظر على المستخدم المُبلَّغ عنه
        $banStmt = $pdo->prepare('SELECT * FROM user_bans WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $banStmt->execute([(int)$detail['reported_user_id']]);
        $detail['ban'] = $banStmt->fetch();
    }
}

// الفلاتر
$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 20;
$offset   = ($page - 1) * $limit;

$validStatuses = ['all', 'pending', 'reviewing', 'resolved', 'rejected', 'closed', 'action_taken'];
$validPriorities = ['all', 'high', 'medium', 'low'];
if (!in_array($status, $validStatuses, true)) {
    $status = 'all';
}
if (!in_array($priority, $validPriorities, true)) {
    $priority = 'all';
}

$where = [];
$params = [];
if ($status !== 'all') {
    $where[] = "r.status = ?";
    $params[] = $status;
}
if ($priority !== 'all') {
    $where[] = "r.priority = ?";
    $params[] = $priority;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reports r {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare(
    "SELECT r.*, u1.name AS reporter_name, u2.name AS reported_name
     FROM reports r
     JOIN users u1 ON u1.id = r.reporter_id
     JOIN users u2 ON u2.id = r.reported_user_id
     {$whereSql}
     ORDER BY r.priority DESC, r.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// إحصائيات سريعة للأدوات
$countsStmt = $pdo->query("SELECT status, COUNT(*) as c FROM reports GROUP BY status");
$counts = [];
foreach ($countsStmt as $row) {
    $counts[$row['status']] = (int)$row['c'];
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<style>
.badge-high{background:#fdecec;color:#c0392b;border:1px solid #c0392b}
.badge-medium{background:#fef6e7;color:#b8860b;border:1px solid #b8860b}
.badge-low{background:#eef7ee;color:#27ae60;border:1px solid #27ae60}
</style>

<div class="pagehead">
  <div>
    <h2>إدارة البلاغات</h2>
    <p>مراجعة تقارير المستخدمين والمحتوى المخالف مع خيارات الحظر والتعليق.</p>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($detail): ?>
<div class="card panel" style="margin:20px;padding:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
    <h3 style="margin:0;">تفاصيل البلاغ رقم <?= (int)$detail['id'] ?></h3>
    <a href="?<?= http_build_query(array_diff_key($_GET, ['id'=>true])) ?>" class="btn sm">← العودة للقائمة</a>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div>
	      <p><b>المُبلِّغ:</b> <?= htmlspecialchars($detail['reporter_name']) ?>
	         <?= $detail['reporter_phone'] ? '(' . htmlspecialchars($detail['reporter_phone']) . ')' : '' ?>
	      </p>
      <p><b>المُبلَّغ عنه:</b> <?= htmlspecialchars($detail['reported_name']) ?>
         <?= $detail['reported_phone'] ? '(' . htmlspecialchars($detail['reported_phone']) . ')' : '' ?>
         <?= $detail['reported_blocked'] ? '<span class="status blocked">محظور</span>' : '' ?>
      </p>
      <p><b>السبب:</b> <?= htmlspecialchars($detail['reason']) ?></p>
      <?php if (!empty($detail['description'])): ?>
      <p><b>التفاصيل:</b><br><small><?= nl2br(htmlspecialchars($detail['description'])) ?></small></p>
      <?php endif; ?>
      <p><b>الحالة:</b>
        <?php $labelMap = ['pending'=>'معلق','reviewing'=>'مراجعة','resolved'=>'محلول','rejected'=>'مرفوض','closed'=>'مغلق','action_taken'=>'تمت معاقبة']; ?>
        <span class="status <?= $detail['status'] === 'pending' ? 'warn' : ($detail['status'] === 'resolved' ? 'online' : ($detail['status'] === 'rejected' ? 'blocked' : 'online')) ?>"><?= $labelMap[$detail['status']] ?? $detail['status'] ?></span>
      </p>
	      <p><b>الأولوية:</b>
	        <?php $prioMap = ['high'=>'عالية','medium'=>'متوسطة','low'=>'منخفضة']; ?>
	        <span class="badge-<?= $detail['priority'] ?? 'medium' ?>"><?= $prioMap[$detail['priority']] ?? 'متوسطة' ?></span>
	      </p>
      <p><b>التاريخ:</b> <?= htmlspecialchars($detail['created_at']) ?></p>
      <?php if ($detail['ban']): ?>
      <p style="background:#fff3f3;padding:10px;border-radius:6px;border:1px solid #f5c6cb;"><b>حالة الحظر الحالية:</b>
        <?php if (!empty($detail['ban']['suspend_until'])): ?>
          معلق مؤقتًا حتى <?= htmlspecialchars($detail['ban']['suspend_until']) ?>
        <?php elseif (!empty($detail['ban']['unbanned_at'])): ?>
          محظور سابقًا ثم تم رفع الحظر
        <?php else: ?>
          محظور نهائيًا منذ <?= htmlspecialchars($detail['ban']['banned_at'] ?? '') ?>
        <?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
    <div>
      <?php if (!empty($detail['message'])): ?>
      <div style="background:#f8f9fa;padding:12px;border-radius:8px;border:1px solid #e9ecef;margin-bottom:15px;">
        <b style="font-size:13px;color:#666;">الرسالة المبلغ عنها:</b><br>
        <small><b><?= htmlspecialchars($detail['message']['sender_name']) ?></b> · <?= htmlspecialchars($detail['message']['created_at']) ?></small>
        <p style="margin:8px 0 0;padding:8px;background:white;border-radius:6px;"><?= htmlspecialchars($detail['message']['content'] ?? '(وسائط)') ?></p>
      </div>
      <?php endif; ?>
      <?php if (!empty($detail['attachments'])): ?>
      <div style="margin-bottom:15px;">
        <b style="font-size:13px;color:#666;">المرفقات (<?= count($detail['attachments']) ?>):</b>
        <div style="margin-top:8px;">
          <?php foreach ($detail['attachments'] as $att): ?>
          <div style="background:#f8f9fa;padding:8px 12px;border-radius:6px;margin-bottom:5px;font-size:13px;">
            مرفق رقم <?= (int)$att['id'] ?><?= !empty($att['message_id']) ? ' · رسالة رقم ' . (int)$att['message_id'] : '' ?><?= !empty($att['conversation_id']) ? ' · محادثة رقم ' . (int)$att['conversation_id'] : '' ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
	      <?php if (!empty($detail['conversation_id'])): ?>
	      <p>
	        <button class="btn sm" onclick="openReportChat(<?= (int)$detail['conversation_id'] ?>, <?= (int)($detail['message_id'] ?? 0) ?>)">👁️ عرض المحادثة في نافذة منبثقة</button>
	      </p>
	      <p><a class="btn sm" href="chats.php?conversation_id=<?= (int)$detail['conversation_id'] ?>">فتح المحادثة الكاملة</a></p>
	      <?php endif; ?>
    </div>
  </div>

  <?php if (in_array($detail['status'], ['pending', 'reviewing', 'action_taken'], true)): ?>
  <div style="margin-top:20px;padding-top:15px;border-top:1px solid #e9ecef;">
    <b style="font-size:13px;color:#666;">إجراءات على المستخدم المُبلَّغ عنه (<?= htmlspecialchars($detail['reported_name']) ?>):</b>
    <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;">
      <form method="POST" style="display:inline;" onsubmit="return confirm('حظر نهائي؟ سيتم حذف جميع جلسات المستخدم.');">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="report_id" value="<?= (int)$detail['id'] ?>">
        <input type="hidden" name="action" value="ban">
        <button class="btn danger sm">🚫 حظر نهائي وحل البلاغ</button>
      </form>
      <form method="POST" style="display:inline;" onsubmit="return confirm('تعليق مؤقت لمدة 24 ساعة؟');">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="report_id" value="<?= (int)$detail['id'] ?>">
        <input type="hidden" name="action" value="suspend">
        <input type="hidden" name="hours" value="24">
        <button class="btn warning sm">⏸️ تعليق 24 ساعة</button>
      </form>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="report_id" value="<?= (int)$detail['id'] ?>">
        <input type="hidden" name="action" value="reviewing">
        <button class="btn sm">👁️ بدء المراجعة</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php else: ?>

<div class="filters">
  <a href="?status=all&priority=<?= $priority ?>" class="btn <?= $status==='all' ? 'primary' : '' ?> sm">الكل (<?= ($counts['pending']??0)+($counts['reviewing']??0)+($counts['resolved']??0)+($counts['rejected']??0)+($counts['closed']??0)+($counts['action_taken']??0) ?>)</a>
  <?php foreach (['pending'=>'معلقة','reviewing'=>'مراجعة','resolved'=>'محلولة','rejected'=>'مرفوضة','action_taken'=>'عوقب','closed'=>'مغلقة'] as $s => $label): ?>
    <a href="?status=<?= $s ?>&priority=<?= $priority ?>" class="btn <?= $status===$s ? 'primary' : '' ?> sm"><?= $label ?> (<?= $counts[$s] ?? 0 ?>)</a>
  <?php endforeach; ?>
  <span style="margin:0 8px;">|</span>
  <?php foreach (['high'=>'عالية','medium'=>'متوسطة','low'=>'منخفضة'] as $p => $label): ?>
    <a href="?status=<?= $status ?>&priority=<?= $p ?>" class="btn <?= $priority===$p ? 'primary' : '' ?> sm">أولوية <?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>المُبلِّغ</th>
        <th>المُبلَّغ عنه</th>
        <th>السبب</th>
        <th>الأولوية</th>
        <th>الحالة</th>
        <th>التاريخ</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reports as $r): ?>
      <tr>
        <td><a href="?id=<?= (int)$r['id'] ?>&status=<?= urlencode($status) ?>"><?= (int)$r['id'] ?></a></td>
        <td><?= htmlspecialchars($r['reporter_name']) ?></td>
        <td><a href="?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['reported_name']) ?></a></td>
        <td><?= htmlspecialchars(mb_substr($r['reason'], 0, 50)) ?></td>
	        <td>
	          <?php $prioMap = ['high'=>'عالية','medium'=>'متوسطة','low'=>'منخفضة']; ?>
	          <span class="badge-<?= $r['priority'] ?? 'medium' ?>"><?= $prioMap[$r['priority']] ?? 'متوسطة' ?></span>
	        </td>
        <td>
          <?php $badgeMap = ['pending'=>'status warn','reviewing'=>'status online','resolved'=>'status online','rejected'=>'status blocked','closed'=>'status blocked','action_taken'=>'status online'];
                $labelMap = ['pending'=>'معلق','reviewing'=>'مراجعة','resolved'=>'محلول','rejected'=>'مرفوض','closed'=>'مغلق','action_taken'=>'عوقب']; ?>
          <span class="<?= $badgeMap[$r['status']] ?? 'status offline' ?>"><?= $labelMap[$r['status']] ?? $r['status'] ?></span>
        </td>
        <td><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
        <td><a href="?id=<?= (int)$r['id'] ?>&status=<?= urlencode($status) ?>" class="btn sm">تفاصيل</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($reports)): ?>
      <tr><td colspan="8" style="padding:20px;text-align:center;color:var(--muted);">لا توجد بلاغات مطابقة للفلتر</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="filters" style="margin-top:12px;">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?status=<?= urlencode($status) ?>&priority=<?= urlencode($priority) ?>&page=<?= $p ?>" class="btn <?= $p===$page ? 'primary' : '' ?> sm"><?= $p ?></a>
  <?php endfor; ?>
  <small style="line-height:32px;">صفحة <?= $page ?> من <?= $totalPages ?> (<?= $total ?> بلاغ)</small>
</div>
<?php endif; ?>

<?php endif; ?>

	<!-- Modal for Conversation Preview -->
	<div id="reportChatModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; padding:20px;">
	  <div class="card panel" style="max-width:800px; margin:50px auto; background:var(--surface); height:80vh; display:flex; flex-direction:column;">
	    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--line); padding-bottom:10px; margin-bottom:10px;">
	      <h3 style="margin:0;">معاينة المحادثة المبلغ عنها</h3>
	      <button onclick="document.getElementById('reportChatModal').style.display='none'" style="font-size:24px;">&times;</button>
	    </div>
	    <div id="reportChatContent" style="flex:1; overflow:auto; padding:10px; background:var(--bg); border-radius:8px;">
	      <div style="text-align:center; padding:20px; color:var(--muted);">جاري تحميل الرسائل...</div>
	    </div>
	  </div>
	</div>

	<script>
	function openReportChat(convId, highlightMsgId) {
	  const modal = document.getElementById('reportChatModal');
	  const content = document.getElementById('reportChatContent');
	  modal.style.display = 'block';
	  content.innerHTML = '<div style="text-align:center; padding:20px; color:var(--muted);">جاري تحميل الرسائل...</div>';

	  fetch(`api/admin_api.php?action=get_conversation_messages&conversation_id=${convId}&limit=50`)
	    .then(r => r.json())
	    .then(data => {
	      if (data.success && data.data) {
	        let html = '';
	        data.data.forEach(msg => {
	          const isHighlight = msg.id == highlightMsgId;
	          html += `
	            <div style="margin-bottom:10px; padding:10px; border-radius:10px; background:${isHighlight ? '#fff3cd' : 'white'}; border:1px solid ${isHighlight ? '#ffeeba' : '#eee'};">
	              <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
	                <b style="font-size:12px; color:var(--primary);">${msg.sender_name || 'مستخدم'}</b>
	                <small style="color:#999; font-size:10px;">${msg.created_at}</small>
	              </div>
	              <div style="font-size:13px; color:var(--text);">${msg.content || (msg.file_id ? '[وسائط]' : '')}</div>
	              ${isHighlight ? '<div style="font-size:9px; color:#856404; margin-top:4px; font-weight:bold;">⚠️ هذه هي الرسالة المبلغ عنها</div>' : ''}
	            </div>
	          `;
	        });
	        content.innerHTML = html || '<div style="text-align:center; padding:20px;">لا توجد رسائل</div>';
	      } else {
	        content.innerHTML = '<div style="text-align:center; padding:20px; color:var(--bad);">فشل تحميل الرسائل: ' + (data.message || 'خطأ غير معروف') + '</div>';
	      }
	    })
	    .catch(err => {
	      content.innerHTML = '<div style="text-align:center; padding:20px; color:var(--bad);">خطأ في الاتصال بالخادم</div>';
	    });
	}
	</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
