<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
$pageTitle = 'لوحة التحكم';
$pdo       = getAdminDB();

// Fetch stats
function statCount(PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$totalUsers     = statCount($pdo, 'SELECT COUNT(*) FROM users');
$onlineUsers    = statCount($pdo, 'SELECT COUNT(*) FROM users WHERE is_online = 1');
$todayMessages  = statCount($pdo, 'SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURDATE()');
$todayCalls     = statCount($pdo, 'SELECT COUNT(*) FROM calls WHERE DATE(created_at) = CURDATE()');
$verifiedUsers  = statCount($pdo, 'SELECT COUNT(*) FROM users WHERE is_verified = 1');
$blockedUsers   = statCount($pdo, 'SELECT COUNT(*) FROM users WHERE is_blocked = 1');
$newUsersToday  = statCount($pdo, 'SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()');

// Chart Data (Last 7 days)
$stmt = $pdo->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
     FROM messages
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);
$chartData = $stmt->fetchAll();
$maxMsg = 0;
foreach($chartData as $d) if($d['cnt'] > $maxMsg) $maxMsg = $d['cnt'];
if($maxMsg == 0) $maxMsg = 1;

// Recent users
$stmt = $pdo->query('SELECT id, name, phone, is_online, is_blocked, created_at FROM users ORDER BY created_at DESC LIMIT 5');
$recentUsers = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>مرحبًا بك في <?= APP_NAME ?> 👋</h2>
    <p>نظرة عامة على نشاط منصة المراسلة.</p>
  </div>
  <button class="btn" onclick="location.reload()">↻ تحديث البيانات</button>
</div>

<div class="stats">
  <div class="card stat">
    <div class="ico">♙</div>
    <div>
      <b><?= number_format($totalUsers) ?></b>
      <small>إجمالي المستخدمين</small>
      <span class="trend">↑ نشط</span>
    </div>
  </div>
  <div class="card stat">
    <div class="ico">🟢</div>
    <div>
      <b><?= number_format($onlineUsers) ?></b>
      <small>متصلون الآن</small>
      <span class="trend">↑ اليوم</span>
    </div>
  </div>
  <div class="card stat">
    <div class="ico">✉</div>
    <div>
      <b><?= number_format($todayMessages) ?></b>
      <small>الرسائل اليوم</small>
      <span class="trend">↑ نشط</span>
    </div>
  </div>
  <div class="card stat">
    <div class="ico">☎</div>
    <div>
      <b><?= number_format($todayCalls) ?></b>
      <small>المكالمات اليوم</small>
      <span class="trend">↑ نشط</span>
    </div>
  </div>
</div>

<div class="grid2">
  <div class="card panel">
    <h3>نشاط الرسائل — آخر 7 أيام</h3>
    <div class="chart">
      <?php 
      $days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
      foreach ($chartData as $index => $row): 
        $h = ($row['cnt'] / $maxMsg) * 100;
        $dayName = date('D', strtotime($row['day']));
      ?>
        <i class="bar" style="height:<?= $h ?>%">
          <span><?= $row['day'] ?></span>
        </i>
      <?php endforeach; ?>
      <?php if(empty($chartData)): ?>
        <div style="width:100%; text-align:center; color:var(--muted); padding-bottom:50px;">لا توجد بيانات كافية للرسم البياني</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card panel">
    <h3>أنواع الحسابات</h3>
    <div style="height:170px; display:grid; place-items:center; background:var(--surface2); border-radius:50%; width:170px; margin:12px auto; font-weight:800; font-size:20px; color:var(--primary);">
      NOVA
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; color:var(--muted); font-size:12px; margin-top:20px;">
      <span><i style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--primary); margin-left:5px;"></i>نشط</span>
      <span><i style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--primary2); margin-left:5px;"></i>جديد</span>
      <span><i style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--good); margin-left:5px;"></i>موثق</span>
      <span><i style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--bad); margin-left:5px;"></i>محظور</span>
    </div>
  </div>
</div>

<div class="card panel" style="margin-top:18px">
  <h3>آخر المستخدمين</h3>
  <div class="tablewrap">
    <table class="table">
      <thead>
        <tr>
          <th>المستخدم</th>
          <th>رقم الهاتف</th>
          <th>التسجيل</th>
          <th>الحالة</th>
          <th>الإجراء</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentUsers as $u): ?>
        <tr>
          <td>
            <div class="user">
              <div class="avatar"><?= mb_substr($u['name'], 0, 1) ?></div>
              <b><?= htmlspecialchars($u['name']) ?></b>
            </div>
          </td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($u['is_blocked']): ?>
              <span class="status blocked">محظور</span>
            <?php elseif ($u['is_online']): ?>
              <span class="status online">نشط</span>
            <?php else: ?>
              <span class="status offline">غير متصل</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/admin/users.php?id=<?= $u['id'] ?>" class="btn sm">عرض</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
