<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'settings.manage');
$pageTitle = 'إحصائيات الوسائط والتخزين'; $pdo = getAdminDB();

// Download handler
if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    $f = $pdo->prepare('SELECT storage_path, original_name, mime_type FROM attachments WHERE id = ?');
    $f->execute([$id]);
    $row = $f->fetch();
    if ($row && $row['storage_path']) {
        $path = realpath((string)$row['storage_path']);
        if ($path !== false && is_file($path) && str_starts_with($path, dirname(__DIR__) . '/storage')) {
            header('Content-Type: ' . ($row['mime_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename((string)$row['original_name'] ?: 'file') . '"');
            readfile($path);
            exit;
        }
    }
    header('Location: storage.php?err=notfound');
    exit;
}

function formatBytes(int $bytes): string {
    $units = ['B','KB','MB','GB']; $i = 0;
    while ($bytes >= 1024 && $i < 3) { $bytes = (int)round($bytes / 1024); $i++; }
    return number_format($bytes, $i ? 1 : 0) . ' ' . $units[$i];
}

$totals = $pdo->query('SELECT COUNT(*) file_count, COALESCE(SUM(file_size),0) total_bytes, COUNT(DISTINCT uploader_id) uploaders FROM attachments')->fetch();
$typeStats = $pdo->query(
    "SELECT a.type, COUNT(*) cnt, COALESCE(SUM(a.file_size),0) bytes,
            (SELECT COUNT(*) FROM attachments a2 WHERE a2.type = a.type AND a2.created_at >= CURDATE()) today
     FROM attachments a GROUP BY a.type ORDER BY cnt DESC"
)->fetchAll();
$typeIcons = ['image'=>'🖼️','video'=>'🎥','audio'=>'🎧','voice'=>'🎙️','file'=>'📎','location'=>'📍','contact'=>'👤','poll'=>'📊'];
$todayTotal = (int)$pdo->query('SELECT COUNT(*) FROM attachments WHERE created_at >= CURDATE()')->fetchColumn();

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>إحصائيات الوسائط والتخزين</h2><p>توزيع الوسائط حسب النوع مع إمكانية السحب والتحميل المباشر من النظام.</p></div></div>
<div class="stats">
  <div class="card stat"><div class="ico">▤</div><div><b><?= number_format((int)$totals['file_count']) ?></b><small>إجمالي الملفات</small></div></div>
  <div class="card stat"><div class="ico">◈</div><div><b><?= formatBytes((int)$totals['total_bytes']) ?></b><small>الحجم الإجمالي</small></div></div>
  <div class="card stat"><div class="ico">♙</div><div><b><?= number_format((int)$totals['uploaders']) ?></b><small>المستخدمون الرافعون</small></div></div>
  <div class="card stat"><div class="ico">⚡</div><div><b><?= number_format($todayTotal) ?></b><small>مرفوعات اليوم</small></div></div>
</div>
<div class="stats">
<?php foreach ($typeStats as $ts): ?>
  <div class="card stat"><div class="ico"><?= $typeIcons[$ts['type']] ?? '📄' ?></div>
    <div><b><?= number_format((int)$ts['cnt']) ?></b><small><?= htmlspecialchars((string)$ts['type']) ?> (<?= formatBytes((int)$ts['bytes']) ?>)<br>اليوم: <?= (int)$ts['today'] ?></small></div>
  </div>
<?php endforeach; ?>
<?php if (!$typeStats): ?><div class="card stat"><div class="ico">📄</div><div><b>0</b><small>لا توجد وسائط حتى الآن</small></div></div><?php endif; ?>
</div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>الملف</th><th>النوع</th><th>الحجم</th><th>الرافع</th><th>المسار</th><th>التاريخ</th><th>سحب</th></tr></thead><tbody>
<?php
$files = $pdo->query('SELECT a.id, a.type, a.original_name, a.mime_type, a.file_size, a.storage_path, a.created_at, u.name uploader_name FROM attachments a JOIN users u ON u.id = a.uploader_id ORDER BY a.created_at DESC LIMIT 200')->fetchAll();
foreach ($files as $f): ?>
<tr><td><?= htmlspecialchars((string)($f['original_name'] ?? 'بدون اسم')) ?></td>
<td><?= $typeIcons[$f['type']] ?? '📄' ?> <?= htmlspecialchars((string)($f['type'] ?? 'file')) ?></td>
<td><?= formatBytes((int)$f['file_size']) ?></td>
<td><?= htmlspecialchars((string)$f['uploader_name']) ?></td>
<td><code style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block"><?= htmlspecialchars((string)$f['storage_path']) ?></code></td>
<td><?= date('d/m/Y H:i', strtotime((string)$f['created_at'])) ?></td>
<td><?php if ($f['storage_path'] && is_file((string)$f['storage_path'])): ?><a class="btn sm" href="?download=<?= (int)$f['id'] ?>">⬇ تحميل</a><?php else: ?><small style="color:var(--muted)">محذوف</small><?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$files): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">لا توجد ملفات مرفوعة</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
