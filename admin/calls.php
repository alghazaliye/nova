<?php
/**
 * NOVA Messenger Admin - سجل المكالمات (مترقية)
 * إحصائيات: مكالمات اليوم/الأسبوع، الصوتية/المرئية، مسموعة/مرحوضة، متوسط المدة.
 * فلاتر: الحالة، النوع، تاريخ.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'chats.view');
$pageTitle = 'سجل المكالمات';
$pdo = getAdminDB();

$status = trim((string)($_GET['status'] ?? 'all'));
$type   = trim((string)($_GET['type'] ?? 'all'));
$day    = trim((string)($_GET['day'] ?? ''));

$allowedStatus = ['calling', 'ringing', 'answered', 'missed', 'rejected', 'ended', 'failed'];
$where = [];
$bind  = [];
if (in_array($status, $allowedStatus, true)) { $where[] = 'c.status = ?'; $bind[] = $status; }
if (in_array($type, ['voice', 'video'], true))   { $where[] = 'c.call_type = ?'; $bind[] = $type; }
if ($day !== '') { $where[] = 'DATE(c.created_at) = ?'; $bind[] = $day; }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT c.*, u.name caller_name,
       (SELECT COUNT(*) FROM call_participants cp WHERE cp.call_id = c.id) participant_count
     FROM calls c JOIN users u ON u.id = c.caller_id {$sqlWhere}
     ORDER BY c.created_at DESC LIMIT 200"
);
$stmt->execute($bind);
$calls = $stmt->fetchAll();

// ===== الإحصائيات =====
$stats = ['today' => 0, 'today_voice' => 0, 'today_video' => 0, 'today_answered' => 0, 'today_missed' => 0, 'week' => 0, 'avg_duration' => '00:00'];
try {
    $r = $pdo->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at) = DATE('now','localtime')")->fetchColumn();
    $stats['today'] = (int)$r;
    $r = $pdo->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at) = DATE('now','localtime') AND call_type = 'voice'")->fetchColumn();
    $stats['today_voice'] = (int)$r;
    $r = $pdo->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at) = DATE('now','localtime') AND call_type = 'video'")->fetchColumn();
    $stats['today_video'] = (int)$r;
    $r = $pdo->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at) = DATE('now','localtime') AND status = 'answered'")->fetchColumn();
    $stats['today_answered'] = (int)$r;
    $r = $pdo->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at) = DATE('now','localtime') AND status = 'missed'")->fetchColumn();
    $stats['today_missed'] = (int)$r;
    $r = $pdo->query("SELECT COUNT(*) FROM calls WHERE created_at >= datetime('now','-7 days','localtime')")->fetchColumn();
    $stats['week'] = (int)$r;
    $r = $pdo->query("SELECT AVG(duration) FROM calls WHERE duration IS NOT NULL AND created_at >= datetime('now','-7 days','localtime')")->fetchColumn();
    if ($r !== null && $r !== false) {
        $avg = (int)$r;
        $stats['avg_duration'] = gmdate('H:i:s', $avg);
    }
} catch (\Throwable $e) {
    error_log('calls stats error: ' . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<style>
  .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:16px}
  .stat-card{background:var(--surface2);border-radius:14px;padding:14px;text-align:center}
  .stat-card b{font-size:20px;display:block}
  .stat-card small{color:var(--muted);font-size:11px}
  .filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:var(--surface2);border-radius:14px;padding:12px;margin-bottom:14px}
  .filters select,.filters input{border:1px solid var(--border);border-radius:10px;padding:7px 10px;background:var(--surface);color:inherit;font-size:13px}
  .filters a{margin-left:auto}
</style>

<div class="pagehead"><div><h2>سجل المكالمات</h2><p>متابعة المكالمات الصوتية والمرئية مع إحصائيات يومية وأسبوعية.</p></div></div>

<div class="stat-grid">
  <div class="stat-card"><b><?= number_format($stats['today']) ?></b><small>مكالمات اليوم</small></div>
  <div class="stat-card"><b style="color:var(--primary)"><?= $stats['today_voice'] ?></b><small>صوتية اليوم</small></div>
  <div class="stat-card"><b style="color:#6f42c1"><?= $stats['today_video'] ?></b><small>مرئية اليوم</small></div>
  <div class="stat-card"><b style="color:#28a745"><?= $stats['today_answered'] ?></b><small>مسموعة اليوم</small></div>
  <div class="stat-card"><b style="color:#dc3545"><?= $stats['today_missed'] ?></b><small>مرحوضة اليوم</small></div>
  <div class="stat-card"><b><?= number_format($stats['week']) ?></b><small>مكالمات 7 أيام</small></div>
  <div class="stat-card"><b><?= $stats['avg_duration'] ?></b><small>متوسط المدة (7 أيام)</small></div>
</div>

<div class="filters">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;flex:1">
    <select name="status">
      <option value="all" <?= $status==='all'?'selected':'' ?>>كل الحالات</option>
      <?php foreach ($allowedStatus as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type">
      <option value="all" <?= $type==='all'?'selected':'' ?>>كل الأنواع</option>
      <option value="voice" <?= $type==='voice'?'selected':'' ?>>صوتية</option>
      <option value="video" <?= $type==='video'?'selected':'' ?>>مرئية</option>
    </select>
    <label style="font-size:12px;color:var(--muted)">تاريخ</label>
    <input name="day" type="date" value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn btn-primary" type="submit">بحث</button>
    <a class="btn" href="calls.php">إعادة تعيين</a>
  </form>
</div>

<div class="card panel tablewrap"><table class="table">
  <thead><tr><th>المتصل</th><th>النوع</th><th>الحالة</th><th>المشاركون</th><th>المدة</th><th>بدأت</th><th>أنهيت</th><th>التاريخ</th></tr></thead>
  <tbody>
  <?php foreach ($calls as $call): ?>
    <tr>
      <td><b><?= htmlspecialchars((string)$call['caller_name'], ENT_QUOTES, 'UTF-8') ?></b></td>
      <td><?= $call['call_type']==='video' ? 'فيديو' : 'صوتية' ?></td>
      <td><span class="status <?= in_array($call['status'],['answered','ended'],true)?'online':($call['status']==='failed'?'blocked':'offline') ?>"><?= htmlspecialchars((string)$call['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
      <td><?= (int)$call['participant_count'] ?></td>
      <td><?= $call['duration'] !== null ? gmdate('H:i:s', (int)$call['duration']) : '—' ?></td>
      <td><small style="color:var(--muted)"><?= $call['started_at'] ? date('H:i', strtotime((string)$call['started_at'])) : '—' ?></small></td>
      <td><small style="color:var(--muted)"><?= $call['ended_at'] ? date('H:i', strtotime((string)$call['ended_at'])) : '—' ?></small></td>
      <td><?= date('d/m/Y H:i', strtotime((string)$call['created_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$calls): ?>
    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">لا توجد مكالمات مطابقة</td></tr>
  <?php endif; ?>
  </tbody>
</table></div>

<?php include __DIR__ . '/includes/footer.php'; ?>
