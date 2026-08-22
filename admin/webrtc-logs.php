<?php
/**
 * NOVA Messenger Admin - WebRTC Logs
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'calls.view');
$pageTitle = 'سجلات WebRTC';
$pdo = getAdminDB();

$callId = isset($_GET['call_id']) ? (int)$_GET['call_id'] : null;
$level  = trim((string)($_GET['level'] ?? 'all'));
$type   = trim((string)($_GET['type'] ?? 'all'));

$allowedLevels = ['info', 'warning', 'error'];
$where = [];
$bind  = [];
if ($callId) { $where[] = 'l.call_id = ?'; $bind[] = $callId; }
if (in_array($level, $allowedLevels, true)) { $where[] = 'l.log_level = ?'; $bind[] = $level; }
if ($type !== 'all' && $type !== '') { $where[] = 'l.event_type = ?'; $bind[] = $type; }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT l.*, u.name as user_name, c.uuid as call_uuid
     FROM webrtc_logs l 
     JOIN users u ON u.id = l.user_id 
     JOIN calls c ON c.id = l.call_id
     {$sqlWhere}
     ORDER BY l.created_at DESC LIMIT 200"
);
$stmt->execute($bind);
$logs = $stmt->fetchAll();

// ===== Stats =====
$stats = ['total' => 0, 'errors' => 0, 'warnings' => 0, 'today' => 0];
try {
    $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM webrtc_logs")->fetchColumn();
    $stats['errors'] = (int)$pdo->query("SELECT COUNT(*) FROM webrtc_logs WHERE log_level = 'error'")->fetchColumn();
    $stats['warnings'] = (int)$pdo->query("SELECT COUNT(*) FROM webrtc_logs WHERE log_level = 'warning'")->fetchColumn();
    $stats['today'] = (int)$pdo->query("SELECT COUNT(*) FROM webrtc_logs WHERE DATE(created_at) = DATE('now','localtime')")->fetchColumn();
} catch (\Throwable $e) {}

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
  .log-level{padding:2px 6px;border-radius:4px;font-size:11px;font-weight:bold;text-transform:uppercase;}
  .level-info{background:#d1ecf1;color:#0c5460;}
  .level-warning{background:#fff3cd;color:#856404;}
  .level-error{background:#f8d7da;color:#721c24;}
</style>

<div class="pagehead"><div><h2>سجلات WebRTC المخصصة</h2><p>مراقبة أحداث وأخطاء المكالمات في الوقت الفعلي للتشخيص.</p></div></div>

<div class="stat-grid">
  <div class="stat-card"><b><?= number_format($stats['total']) ?></b><small>إجمالي السجلات</small></div>
  <div class="stat-card"><b style="color:#dc3545"><?= $stats['errors'] ?></b><small>الأخطاء</small></div>
  <div class="stat-card"><b style="color:#ffc107"><?= $stats['warnings'] ?></b><small>التحذيرات</small></div>
  <div class="stat-card"><b style="color:var(--primary)"><?= $stats['today'] ?></b><small>سجلات اليوم</small></div>
</div>

<div class="filters">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;flex:1">
    <input name="call_id" type="number" placeholder="رقم المكالمة" value="<?= $callId ?>">
    <select name="level">
      <option value="all" <?= $level==='all'?'selected':'' ?>>كل المستويات</option>
      <option value="info" <?= $level==='info'?'selected':'' ?>>Info</option>
      <option value="warning" <?= $level==='warning'?'selected':'' ?>>Warning</option>
      <option value="error" <?= $level==='error'?'selected':'' ?>>Error</option>
    </select>
    <input name="type" type="text" placeholder="نوع الحدث" value="<?= htmlspecialchars($type) ?>">
    <button class="btn btn-primary" type="submit">تصفية</button>
    <a class="btn" href="webrtc-logs.php">إعادة تعيين</a>
  </form>
</div>

<div class="card panel tablewrap"><table class="table">
  <thead><tr><th>المستخدم</th><th>المكالمة</th><th>الحدث</th><th>المستوى</th><th>الرسالة</th><th>التفاصيل</th><th>IP</th><th>التاريخ</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $log): ?>
    <tr>
      <td><b><?= htmlspecialchars((string)$log['user_name']) ?></b></td>
      <td><small><?= htmlspecialchars(substr((string)$log['call_uuid'], 0, 8)) ?>...</small></td>
      <td><code><?= htmlspecialchars((string)$log['event_type']) ?></code></td>
      <td><span class="log-level level-<?= $log['log_level'] ?>"><?= $log['log_level'] ?></span></td>
      <td><?= htmlspecialchars((string)$log['message']) ?></td>
      <td><small style="font-size:10px; color:var(--muted);"><?= htmlspecialchars((string)$log['details']) ?></small></td>
      <td><small><?= htmlspecialchars((string)$log['ip_address']) ?></small></td>
      <td><?= date('d/m H:i:s', strtotime((string)$log['created_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$logs): ?>
    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">لا توجد سجلات مطابقة</td></tr>
  <?php endif; ?>
  </tbody>
</table></div>

<?php include __DIR__ . '/includes/footer.php'; ?>
