<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin=requireAdminLogin(); requirePermission($admin,'admins.manage');
$pageTitle='المشرفون والصلاحيات'; $pdo=getAdminDB();
$admins=$pdo->query('SELECT a.id,a.name,a.email,a.is_active,a.last_login_at,a.created_at,r.name role_name FROM admins a JOIN roles r ON r.id=a.role_id ORDER BY a.created_at DESC')->fetchAll();
$roles=$pdo->query('SELECT r.id,r.name,r.description,COUNT(rp.permission_id) permission_count FROM roles r LEFT JOIN role_permissions rp ON rp.role_id=r.id GROUP BY r.id ORDER BY r.id')->fetchAll();
include __DIR__.'/includes/header.php'; include __DIR__.'/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>المشرفون والصلاحيات</h2><p>الحسابات الإدارية والأدوار المرتبطة بها.</p></div></div>
<div class="grid2"><div class="card panel tablewrap"><h3>حسابات المشرفين</h3><table class="table"><thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>الحالة</th><th>آخر دخول</th></tr></thead><tbody><?php foreach($admins as $a): ?><tr><td><?= htmlspecialchars($a['name']) ?></td><td><?= htmlspecialchars($a['email']) ?></td><td><?= htmlspecialchars($a['role_name']) ?></td><td><span class="status <?= $a['is_active']?'online':'blocked' ?>"><?= $a['is_active']?'نشط':'معطل' ?></span></td><td><?= $a['last_login_at']?date('d/m/Y H:i',strtotime($a['last_login_at'])):'لم يدخل بعد' ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="card panel tablewrap"><h3>الأدوار</h3><table class="table"><thead><tr><th>الدور</th><th>الوصف</th><th>الصلاحيات</th></tr></thead><tbody><?php foreach($roles as $r): ?><tr><td><b><?= htmlspecialchars($r['name']) ?></b></td><td><?= htmlspecialchars($r['description']??'') ?></td><td><?= (int)$r['permission_count'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
