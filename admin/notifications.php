<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin=requireAdminLogin(); requirePermission($admin,'users.view');
$pageTitle='الإشعارات'; $pdo=getAdminDB(); $filter=$_GET['filter']??'all'; $where='';
if($filter==='read')$where='WHERE n.is_read=1'; elseif($filter==='unread')$where='WHERE n.is_read=0';
$stmt=$pdo->query("SELECT n.id,n.type,n.title,n.body,n.is_read,n.created_at,u.name AS user_name FROM notifications n JOIN users u ON u.id=n.user_id $where ORDER BY n.created_at DESC LIMIT 100"); $notifications=$stmt->fetchAll();
include __DIR__.'/includes/header.php'; include __DIR__.'/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>الإشعارات</h2><p>مراجعة الإشعارات المرسلة وحالتها للمستخدمين.</p></div></div>
<div class="filters"><a class="btn sm <?= $filter==='all'?'primary':'' ?>" href="?filter=all">الكل</a><a class="btn sm <?= $filter==='unread'?'primary':'' ?>" href="?filter=unread">غير مقروءة</a><a class="btn sm <?= $filter==='read'?'primary':'' ?>" href="?filter=read">مقروءة</a></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المستخدم</th><th>العنوان</th><th>المحتوى</th><th>النوع</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>
<?php foreach($notifications as $n): ?><tr><td><?= htmlspecialchars($n['user_name']) ?></td><td><b><?= htmlspecialchars($n['title']) ?></b></td><td><?= htmlspecialchars(mb_strimwidth((string)($n['body']??'—'),0,100,'…')) ?></td><td><?= htmlspecialchars($n['type']) ?></td><td><span class="status <?= $n['is_read']?'offline':'online' ?>"><?= $n['is_read']?'مقروء':'غير مقروء' ?></span></td><td><?= date('d/m/Y H:i',strtotime($n['created_at'])) ?></td></tr><?php endforeach; ?>
<?php if(!$notifications): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">لا توجد إشعارات</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__.'/includes/footer.php'; ?>
