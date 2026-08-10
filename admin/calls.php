<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin(); requirePermission($admin, 'messages.view');
$pageTitle='سجل المكالمات'; $pdo=getAdminDB(); $status=$_GET['status']??'all';
$allowed=['calling','ringing','answered','missed','rejected','ended','failed']; $where=''; $params=[];
if(in_array($status,$allowed,true)){ $where='WHERE c.status=?'; $params=[$status]; }
$stmt=$pdo->prepare("SELECT c.*,u.name caller_name,(SELECT COUNT(*) FROM call_participants cp WHERE cp.call_id=c.id) participant_count FROM calls c JOIN users u ON u.id=c.caller_id $where ORDER BY c.created_at DESC LIMIT 100"); $stmt->execute($params); $calls=$stmt->fetchAll();
include __DIR__.'/includes/header.php'; include __DIR__.'/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>سجل المكالمات</h2><p>متابعة حالة المكالمات ومدتها والمشاركين.</p></div></div>
<div class="filters"><a class="btn sm <?= $status==='all'?'primary':'' ?>" href="calls.php">الكل</a><?php foreach($allowed as $s): ?><a class="btn sm <?= $status===$s?'primary':'' ?>" href="?status=<?= $s ?>"><?= htmlspecialchars($s) ?></a><?php endforeach; ?></div>
<div class="card panel tablewrap"><table class="table"><thead><tr><th>المتصل</th><th>النوع</th><th>الحالة</th><th>المشاركون</th><th>المدة</th><th>التاريخ</th></tr></thead><tbody>
<?php foreach($calls as $call): ?><tr><td><?= htmlspecialchars($call['caller_name']) ?></td><td><?= $call['call_type']==='video'?'فيديو':'صوتية' ?></td><td><span class="status <?= in_array($call['status'],['answered','ended'],true)?'online':($call['status']==='failed'?'blocked':'offline') ?>"><?= htmlspecialchars($call['status']) ?></span></td><td><?= (int)$call['participant_count'] ?></td><td><?= $call['duration']!==null ? gmdate('H:i:s',(int)$call['duration']) : '—' ?></td><td><?= date('d/m/Y H:i',strtotime($call['created_at'])) ?></td></tr><?php endforeach; ?>
<?php if(!$calls): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">لا توجد مكالمات حاليًا</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__.'/includes/footer.php'; ?>
