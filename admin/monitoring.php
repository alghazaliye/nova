<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$admin = requireAdminLogin();
requirePermission($admin, 'settings.view');

$pageTitle = 'المراقبة الحية المتقدمة';
$pdo = getAdminDB();

// 1. إحصائيات النظام الأساسية
$stats = [];
try {
    // إحصائيات المستخدمين
    $stats['users_total'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['users_online'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_online = 1 OR last_seen >= datetime("now","-5 minutes")')->fetchColumn();
    $stats['users_today'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE date(created_at) = date("now")')->fetchColumn();
    
    // إحصائيات الرسائل والمكالمات
    $stats['messages_total'] = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    $stats['messages_today'] = (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE date(created_at) = date("now")')->fetchColumn();
    $stats['calls_today'] = (int)$pdo->query('SELECT COUNT(*) FROM calls WHERE date(created_at) = date("now")')->fetchColumn();

    // إحصائيات الأخطاء
    $stats['errors_today'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('ERROR', 'FAILED') AND date(created_at) = date('now')")->fetchColumn();
    $stats['errors_total'] = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action IN ("ERROR", "FAILED")')->fetchColumn();
    
    // التخزين
    $res = $pdo->query('SELECT COUNT(*) as count, SUM(file_size) as total_size FROM attachments')->fetch();
    $stats['storage_used'] = (float)($res['total_size'] ?? 0);
} catch (Exception $e) {
    error_log('Monitoring Stats Error: ' . $e->getMessage());
}

// 2. فحص حالة الاتصال بـ Turso
$db_type = $_ENV['DB_TYPE'] ?? 'sqlite';
$turso_status = '🔴 غير متصل';
$turso_latency = 'N/A';
if ($db_type === 'turso') {
    try {
        $start = microtime(true);
        $pdo->query('SELECT 1')->execute();
        $end = microtime(true);
        $turso_status = '🟢 متصل بنجاح';
        $turso_latency = round(($end - $start) * 1000, 2) . ' ms';
    } catch (Exception $e) {
        $turso_status = '🔴 خطأ في الاتصال';
    }
} else {
    $turso_status = '⚪ محلي (SQLite)';
}

// 3. حالة الخادم (Render)
$server_load = "غير متاح";
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    if ($load) $server_load = round($load[0], 2);
}

$disk_free = "غير معروف";
if (function_exists('disk_free_space')) {
    $free = disk_free_space(__DIR__);
    if ($free !== false) $disk_free = round($free / 1024 / 1024 / 1024, 2) . " GB";
}

// 4. سجل الأخطاء الأخيرة
$error_logs = $pdo->query(
    'SELECT * FROM audit_logs WHERE action IN ("ERROR", "FAILED", "WARNING") ORDER BY created_at DESC LIMIT 15'
)->fetchAll() ?: [];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
<div class="pagehead">
    <div>
        <h2>📊 المراقبة الحية المتقدمة</h2>
        <p>متابعة أداء الخادم (Render) وقاعدة البيانات (Turso) لحظياً</p>
    </div>
    <button class="btn" onclick="location.reload()" style="background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">↻ تحديث الحالة</button>
</div>

<!-- بطاقات حالة البنية التحتية -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px;">
    <!-- حالة Turso -->
    <div class="card" style="padding: 20px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #4facfe;">
        <h4 style="margin-top: 0; color: #333; display: flex; align-items: center; gap: 10px;">
            🗄️ قاعدة البيانات (Turso)
            <span style="font-size: 12px; background: #e7f3ff; color: #0066cc; padding: 2px 8px; border-radius: 10px;"><?= strtoupper($db_type) ?></span>
        </h4>
        <div style="margin: 15px 0;">
            <p style="font-size: 20px; font-weight: bold; margin: 5px 0;"><?= $turso_status ?></p>
            <p style="color: #666; font-size: 14px;">وقت الاستجابة: <span style="color: #28a745; font-weight: bold;"><?= $turso_latency ?></span></p>
        </div>
        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; font-size: 12px;">
            💡 يتم تخزين البيانات الآن سحابياً لضمان عدم فقدانها عند إعادة تشغيل الخادم.
        </div>
    </div>

    <!-- حالة Render -->
    <div class="card" style="padding: 20px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #f5576c;">
        <h4 style="margin-top: 0; color: #333;">🚀 حالة الخادم (Render)</h4>
        <div style="margin: 15px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
                <small style="color: #999; display: block;">حمل الخادم (CPU)</small>
                <b style="font-size: 18px;"><?= $server_load ?></b>
            </div>
            <div>
                <small style="color: #999; display: block;">مساحة القرص المتاحة</small>
                <b style="font-size: 18px;"><?= $disk_free ?></b>
            </div>
        </div>
        <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 8px; font-size: 12px;">
            ⚠️ تنبيه: مساحة القرص على Render مجانية ومؤقتة، استخدم Turso دائماً للبيانات المهمة.
        </div>
    </div>
</div>

<!-- إحصائيات سريعة -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px;">
    <div class="card" style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center;">
        <small style="color: #666;">المتصلون الآن</small>
        <h3 style="margin: 5px 0; color: #28a745;"><?= $stats['users_online'] ?></h3>
    </div>
    <div class="card" style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center;">
        <small style="color: #666;">رسائل اليوم</small>
        <h3 style="margin: 5px 0; color: #007bff;"><?= $stats['messages_today'] ?></h3>
    </div>
    <div class="card" style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center;">
        <small style="color: #666;">مكالمات اليوم</small>
        <h3 style="margin: 5px 0; color: #dc3545;"><?= $stats['calls_today'] ?></h3>
    </div>
    <div class="card" style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center;">
        <small style="color: #666;">أخطاء اليوم</small>
        <h3 style="margin: 5px 0; color: <?= $stats['errors_today'] > 0 ? '#dc3545' : '#28a745' ?>;"><?= $stats['errors_today'] ?></h3>
    </div>
</div>

<!-- سجل الأخطاء والتحذيرات -->
<div class="card panel" style="margin: 20px; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
    <h3 style="margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
        🚨 سجل الأخطاء والتحذيرات الأخير
        <?php if ($stats['errors_today'] > 0): ?>
            <span style="background: #dc3545; color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; animation: pulse 2s infinite;">تنبيه نشط</span>
        <?php endif; ?>
    </h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: right; border-bottom: 2px solid #eee;">
                    <th style="padding: 12px;">الوقت</th>
                    <th style="padding: 12px;">النوع</th>
                    <th style="padding: 12px;">الوصف</th>
                    <th style="padding: 12px;">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($error_logs)): ?>
                    <?php foreach ($error_logs as $log): ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 12px; font-size: 13px; color: #666;"><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
                            <td style="padding: 12px;">
                                <span style="background: <?= ($log['action'] === 'ERROR') ? '#f8d7da' : '#fff3cd' ?>; color: <?= ($log['action'] === 'ERROR') ? '#721c24' : '#856404' ?>; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px; font-size: 13px;"><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                            <td style="padding: 12px; font-size: 12px; color: #999;"><code><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding: 30px; text-align: center; color: #999;">✓ النظام يعمل بكفاءة ولا توجد أخطاء مسجلة حالياً.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>

</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
