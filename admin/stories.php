<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin=requireAdminLogin(); requirePermission($admin,'users.view');
$pageTitle='إدارة الحالات'; $pdo=getAdminDB(); $filter=$_GET['filter']??'active';
$where=$filter==='all'?'':'WHERE s.deleted_at IS NULL AND s.expires_at '.($filter==='expired'?'<':'').' '.($filter==='expired'?'NOW()':'>= NOW()');
$stmt=$pdo->query("SELECT s.id,s.type,s.text,s.privacy,s.created_at,s.expires_at,s.deleted_at,u.name AS user_name,
 (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id=s.id) view_count
 FROM stories s JOIN users u ON u.id=s.user_id $where ORDER BY s.created_at DESC LIMIT 100"); $stories=$stmt->fetchAll();
include __DIR__.'/includes/header.php'; include __DIR__.'/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>إدارة الحالات</h2><p>عرض الحالات المنشورة ومشاهداتها وتاريخ انتهائها.</p></div></div>
<div class="filters"><a class="btn sm <?= $filter==='active'?'primary':'' ?>" href="?filter=active">نشطة</a><a class="btn sm <?= $filter==='expired'?'primary':'' ?>" href="?filter=expired">منتهية</a><a class="btn sm <?= $filter==='all'?'primary':'' ?>" href="?filter=all">الكل</a></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>الناشر</th><th>النوع</th><th>النص</th><th>الخصوصية</th><th>المشاهدات</th><th>تنتهي في</th></tr></thead><tbody>
<?php foreach($stories as $story): ?><tr><td><?= htmlspecialchars($story['user_name']) ?></td><td><?= htmlspecialchars($story['type']) ?></td><td><?= htmlspecialchars(mb_strimwidth((string)($story['text']??'—'),0,80,'…')) ?></td><td><?= htmlspecialchars($story['privacy']) ?></td><td><?= number_format((int)$story['view_count']) ?></td><td><?= date('d/m/Y H:i',strtotime($story['expires_at'])) ?></td></tr><?php endforeach; ?>
<?php if(!$stories): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">لا توجد حالات في هذا التصنيف</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__.'/includes/footer.php'; ?>
