<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Polyfill: avoid fatal error when php-mbstring is not installed
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        if ($length === null) {
            return substr($str, $start);
        }
        return substr($str, $start, $length);
    }
}

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
$todayMessages  = statCount($pdo, "SELECT COUNT(*) FROM messages WHERE date(created_at) = date('now')");
$todayCalls     = statCount($pdo, "SELECT COUNT(*) FROM calls WHERE date(created_at) = date('now')");
$verifiedUsers  = statCount($pdo, 'SELECT COUNT(*) FROM users WHERE is_verified = 1');
$blockedUsers   = statCount($pdo, 'SELECT COUNT(*) FROM users WHERE is_blocked = 1');
$newUsersToday  = statCount($pdo, "SELECT COUNT(*) FROM users WHERE date(created_at) = date('now')");
$totalConversations = statCount($pdo, 'SELECT COUNT(*) FROM conversations');
$totalStories   = statCount($pdo, 'SELECT COUNT(*) FROM stories');
$todayStories   = statCount($pdo, "SELECT COUNT(*) FROM stories WHERE date(created_at) = date('now')");
$totalErrors    = statCount($pdo, 'SELECT COUNT(*) FROM audit_logs WHERE action IN ("ERROR", "FAILED")');
$todayErrors    = statCount($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action IN ('ERROR', 'FAILED') AND date(created_at) = date('now')");

// Chart Data (Last 7 days) — SQLite-safe: use PHP dates so no MySQL functions are needed
$sevenDaysAgo = date('Y-m-d H:i:s', time() - 7 * 86400);
$stmt = $pdo->query(
    "SELECT date(created_at) AS day, COUNT(*) AS cnt
     FROM messages
     WHERE created_at >= '{$sevenDaysAgo}'
     GROUP BY date(created_at)
     ORDER BY day ASC"
);
$chartData = $stmt->fetchAll();
$maxMsg = 0;
foreach($chartData as $d) if($d['cnt'] > $maxMsg) $maxMsg = $d['cnt'];
if($maxMsg == 0) $maxMsg = 1;

// Recent users
$stmt = $pdo->query('SELECT id, name, phone, is_online, is_blocked, created_at FROM users ORDER BY created_at DESC LIMIT 5');
$recentUsers = $stmt->fetchAll();

// Recent errors
$stmt = $pdo->query('SELECT * FROM audit_logs WHERE action IN ("ERROR", "FAILED", "WARNING") ORDER BY created_at DESC LIMIT 10');
$recentErrors = $stmt->fetchAll();

// System health
$dbSizeDb = $_ENV['DB_PATH'] ?? realpath(__DIR__ . '/../../backend/config/nova.sqlite');
$dbSize = ($dbSizeDb !== false && is_file($dbSizeDb)) ? round(filesize($dbSizeDb) / 1024 / 1024, 2) : 0;

// Get disk space (cross-platform for Linux)
$diskFree = "غير معروف";
if (function_exists('disk_free_space')) {
    $free = disk_free_space(__DIR__);
    if ($free !== false) {
        $diskFree = round($free / 1024 / 1024 / 1024, 2) . " GB";
    }
}

// Server uptime/load (simplified for display)
$serverLoad = "مستقر";
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    if ($load) $serverLoad = round($load[0], 2);
}

$systemHealth = [
    'database' => 'متصل ✓',
    'api' => 'متشغل ✓',
    'storage' => $diskFree,
    'server' => $serverLoad
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>مرحبًا بك في <?= APP_NAME ?> 👋</h2>
    <p>نظرة عامة على نشاط منصة المراسلة وحالة النظام</p>
  </div>
  <button class="btn" onclick="location.reload()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">↻ تحديث البيانات</button>
</div>

<!-- حالة النظام الحية -->
<div style="margin: 0 0 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; width: 100%;">
    <div class="card panel" style="padding: 18px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px;">
        <h4 style="margin: 0 0 10px 0; opacity: 0.9; font-size: 14px;">قاعدة البيانات</h4>
        <p style="font-size: 22px; font-weight: bold; margin: 0;">✓ متصلة</p>
        <small style="opacity: 0.8; display: block; margin-top: 5px;">الحجم: <?= number_format((float)$dbSize, 2) ?> MB</small>
    </div>
    <div class="card panel" style="padding: 18px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none; border-radius: 12px;">
        <h4 style="margin: 0 0 10px 0; opacity: 0.9; font-size: 14px;">الخادم</h4>
        <p style="font-size: 22px; font-weight: bold; margin: 0;">✓ متشغل</p>
        <small style="opacity: 0.8; display: block; margin-top: 5px;">الحمل: <?= $systemHealth['server'] ?></small>
    </div>
    <div class="card panel" style="padding: 18px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none; border-radius: 12px;">
        <h4 style="margin: 0 0 10px 0; opacity: 0.9; font-size: 14px;">التخزين</h4>
        <p style="font-size: 22px; font-weight: bold; margin: 0;">✓ متاح</p>
        <small style="opacity: 0.8; display: block; margin-top: 5px;">المساحة: <?= $systemHealth['storage'] ?></small>
    </div>
    <div class="card panel" style="padding: 18px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; border: none; border-radius: 12px;">
        <h4 style="margin: 0 0 10px 0; opacity: 0.9; font-size: 14px;">الأخطاء</h4>
        <p style="font-size: 22px; font-weight: bold; margin: 0; color: #fff;"><?= $todayErrors ?></p>
        <small style="opacity: 0.8; display: block; margin-top: 5px;">اليوم: <?= $todayErrors ?> | الإجمالي: <?= $totalErrors ?></small>
    </div>
</div>

<!-- الإحصائيات الرئيسية -->
<div class="stats" style="margin: 20px 0; gap: 14px;">
  <div class="card stat" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="font-size: 32px; margin-bottom: 10px;">👥</div>
    <div>
      <b style="font-size: 24px; color: #667eea;"><?= number_format($totalUsers) ?></b>
      <small style="display: block; color: #666; margin-top: 5px;">إجمالي المستخدمين</small>
      <span style="display: inline-block; background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 3px; font-size: 11px; margin-top: 8px;">↑ <?= $newUsersToday ?> جديد اليوم</span>
    </div>
  </div>
  <div class="card stat" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="font-size: 32px; margin-bottom: 10px;">🟢</div>
    <div>
      <b style="font-size: 24px; color: #28a745;"><?= number_format($onlineUsers) ?></b>
      <small style="display: block; color: #666; margin-top: 5px;">متصلون الآن</small>
      <span style="display: inline-block; background: #cfe2ff; color: #084298; padding: 4px 8px; border-radius: 3px; font-size: 11px; margin-top: 8px;">نسبة: <?= $totalUsers > 0 ? round(($onlineUsers / $totalUsers) * 100, 1) : 0 ?>%</span>
    </div>
  </div>
  <div class="card stat" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="font-size: 32px; margin-bottom: 10px;">✉️</div>
    <div>
      <b style="font-size: 24px; color: #007bff;"><?= number_format($todayMessages) ?></b>
      <small style="display: block; color: #666; margin-top: 5px;">الرسائل اليوم</small>
      <span style="display: inline-block; background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 3px; font-size: 11px; margin-top: 8px;">📅 نشاط مستمر</span>
    </div>
  </div>
  <div class="card stat" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="font-size: 32px; margin-bottom: 10px;">☎️</div>
    <div>
      <b style="font-size: 24px; color: #dc3545;"><?= number_format($todayCalls) ?></b>
      <small style="display: block; color: #666; margin-top: 5px;">المكالمات اليوم</small>
      <span style="display: inline-block; background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 3px; font-size: 11px; margin-top: 8px;">📞 نشط</span>
    </div>
  </div>
</div>

<!-- الإحصائيات الإضافية -->
<div style="margin: 0 0 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; width: 100%; min-width: 0;">
  <div style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h4 style="margin-top: 0; color: #333;">💬 المحادثات</h4>
    <p style="font-size: 28px; font-weight: bold; color: #667eea; margin: 10px 0;"><?= number_format($totalConversations) ?></p>
    <small style="color: #666;">إجمالي المحادثات النشطة</small>
  </div>
  <div style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h4 style="margin-top: 0; color: #333;">📊 الحالات</h4>
    <p style="font-size: 28px; font-weight: bold; color: #f5576c; margin: 10px 0;"><?= number_format($todayStories) ?></p>
    <small style="color: #666;">حالات اليوم من إجمالي <?= number_format($totalStories) ?></small>
  </div>
  <div style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h4 style="margin-top: 0; color: #333;">🔒 الأمان</h4>
    <p style="font-size: 28px; font-weight: bold; color: #28a745; margin: 10px 0;"><?= number_format($verifiedUsers) ?></p>
    <small style="color: #666;">مستخدمون موثقون | محظورون: <?= $blockedUsers ?></small>
  </div>
</div>

<div class="grid2" style="margin: 20px 0; gap: 18px;">
  <!-- نشاط الرسائل -->
  <div class="card panel" style="background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px;">
    <h3 style="margin-top: 0; color: #333;">📈 نشاط الرسائل — آخر 7 أيام</h3>
    <div style="display: flex; align-items: flex-end; gap: 8px; height: 150px; margin: 20px 0;">
      <?php foreach($chartData as $d): ?>
        <div style="flex: 1; background: linear-gradient(to top, #667eea, #764ba2); border-radius: 4px; height: <?= ($d['cnt'] / $maxMsg) * 100 ?>%; position: relative; min-height: 10px;" title="<?= $d['day'] ?>: <?= $d['cnt'] ?> رسالة">
          <span style="position: absolute; bottom: -25px; left: 0; right: 0; text-align: center; font-size: 11px; color: #666;"><?= date('d/m', strtotime($d['day'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p style="color: #666; margin-top: 40px; font-size: 12px;">أقصى: <?= number_format($maxMsg) ?> رسالة في يوم واحد</p>
  </div>

  <!-- سجل الأخطاء -->
  <div class="card panel" style="background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px;">
    <h3 style="margin-top: 0; color: #333;">🚨 سجل الأخطاء الأخيرة</h3>
    <div style="max-height: 250px; overflow-y: auto;">
      <?php if (!empty($recentErrors)): ?>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
          <tbody>
            <?php foreach ($recentErrors as $error): ?>
              <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px;">
                  <span style="background: <?php 
                    $action = $error['action'];
                    echo ($action === 'ERROR') ? '#f8d7da' : (($action === 'FAILED') ? '#fff3cd' : '#d1ecf1');
                  ?>; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                    <?= htmlspecialchars($action) ?>
                  </span>
                </td>
                <td style="padding: 8px;"><small><?= htmlspecialchars(mb_substr($error['description'] ?? '-', 0, 40)) ?></small></td>
                <td style="padding: 8px; text-align: left;"><small style="color: #999;"><?= date('H:i', strtotime($error['created_at'])) ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color: #666; text-align: center; padding: 20px;">✓ لا توجد أخطاء أو تحذيرات</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- المستخدمون الأخيرون -->
<div class="card panel" style="margin: 20px 0; background: var(--surface); border-radius: 18px; box-shadow: var(--shadow); padding: 18px;">
  <h3 style="margin-top: 0; color: #333;">👥 المستخدمون الأخيرون</h3>
  <table style="width: 100%; border-collapse: collapse;">
    <thead style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
      <tr>
        <th style="padding: 12px; text-align: right; font-weight: bold;">الاسم</th>
        <th style="padding: 12px; text-align: right; font-weight: bold;">الهاتف</th>
        <th style="padding: 12px; text-align: right; font-weight: bold;">الحالة</th>
        <th style="padding: 12px; text-align: right; font-weight: bold;">تاريخ الإنشاء</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recentUsers as $user): ?>
        <tr style="border-bottom: 1px solid #eee;">
          <td style="padding: 12px;"><?= htmlspecialchars($user['name']) ?></td>
          <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;"><?= htmlspecialchars($user['phone']) ?></code></td>
          <td style="padding: 12px;">
            <span style="background: <?= $user['is_online'] ? '#d4edda' : '#f8f9fa' ?>; color: <?= $user['is_online'] ? '#155724' : '#666' ?>; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">
              <?= $user['is_online'] ? '🟢 متصل' : '⚫ غير متصل' ?>
              <?= $user['is_blocked'] ? ' | 🚫 محظور' : '' ?>
            </span>
          </td>
          <td style="padding: 12px;"><small><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></small></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- نصائح وإجراءات سريعة -->
<div style="margin: 0 0 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; width: 100%; min-width: 0;">
  <div style="padding: 15px; background: #e7f3ff; border-left: 4px solid #0066cc; border-radius: 4px;">
    <h4 style="margin: 0 0 10px 0; color: #0066cc;">💡 نصيحة</h4>
    <p style="margin: 0; color: #004085; font-size: 12px;">تحقق من <a href="monitoring.php" style="color: #0066cc; text-decoration: none;">المراقبة الحية</a> للحصول على معلومات مفصلة عن الأداء</p>
  </div>
  <div style="padding: 15px; background: #fff3cd; border-left: 4px solid #856404; border-radius: 4px;">
    <h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ تنبيه</h4>
    <p style="margin: 0; color: #664d03; font-size: 12px;">تأكد من تفعيل <a href="services.php" style="color: #856404; text-decoration: none;">خدمات OTP والبريد</a> للعمل الأمثل</p>
  </div>
  <div style="padding: 15px; background: #d1ecf1; border-left: 4px solid #0c5460; border-radius: 4px;">
    <h4 style="margin: 0 0 10px 0; color: #0c5460;">ℹ️ معلومة</h4>
    <p style="margin: 0; color: #0c5460; font-size: 12px;">تحديث البيانات يتم تلقائياً كل 30 ثانية في صفحة <a href="monitoring.php" style="color: #0c5460; text-decoration: none;">المراقبة</a></p>
  </div>
</div>

<script>
// تحديث تلقائي للصفحة الرئيسية كل دقيقة
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            
            // تحديث الإحصائيات
            const newStats = newDoc.querySelectorAll('.stats .card');
            const oldStats = document.querySelectorAll('.stats .card');
            
            if (newStats.length > 0 && oldStats.length > 0) {
                oldStats.forEach((el, i) => {
                    if (newStats[i]) {
                        el.innerHTML = newStats[i].innerHTML;
                    }
                });
            }
        })
        .catch(err => console.log('Auto-update error: ' + err));
}, 60000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
