<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin=requireAdminLogin(); requirePermission($admin,'settings.manage');
$pageTitle='التخزين والوسائط'; $pdo=getAdminDB();
$stats=$pdo->query('SELECT COUNT(*) file_count,COALESCE(SUM(file_size),0) total_bytes,COUNT(DISTINCT uploader_id) uploaders FROM attachments')->fetch();
$files=$pdo->query('SELECT a.id,a.original_name,a.mime_type,a.file_size,a.storage_path,a.created_at,u.name uploader_name FROM attachments a JOIN users u ON u.id=a.uploader_id ORDER BY a.created_at DESC LIMIT 100')->fetchAll();
function formatBytes(int $bytes): string { $units=['B','KB','MB','GB']; $i=0; while($bytes>=1024 && $i<3){$bytes=(int)round($bytes/1024);$i++;} return number_format($bytes, $i?1:0).' '.$units[$i]; }
include __DIR__.'/includes/header.php'; include __DIR__.'/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>التخزين والوسائط</h2><p>ملخص الملفات المرفوعة ومسارات تخزينها.</p></div></div>
<div class="stats"><div class="card stat"><div class="ico">▤</div><div><b><?= number_format((int)$stats['file_count']) ?></b><small>إجمالي الملفات</small></div></div><div class="card stat"><div class="ico">◈</div><div><b><?= formatBytes((int)$stats['total_bytes']) ?></b><small>الحجم الإجمالي</small></div></div><div class="card stat"><div class="ico">♙</div><div><b><?= number_format((int)$stats['uploaders']) ?></b><small>المستخدمون الرافعون</small></div></div></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>الملف</th><th>النوع</th><th>الحجم</th><th>الرافع</th><th>المسار</th><th>التاريخ</th></tr></thead><tbody><?php foreach($files as $f): ?><tr><td><?= htmlspecialchars($f['original_name']??'بدون اسم') ?></td><td><?= htmlspecialchars($f['mime_type']) ?></td><td><?= formatBytes((int)$f['file_size']) ?></td><td><?= htmlspecialchars($f['uploader_name']) ?></td><td><code><?= htmlspecialchars($f['storage_path']) ?></code></td><td><?= date('d/m/Y H:i',strtotime($f['created_at'])) ?></td></tr><?php endforeach; ?><?php if(!$files): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">لا توجد ملفات مرفوعة</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__.'/includes/footer.php'; ?>
