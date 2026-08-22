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
    $stats['users_total'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['users_online'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_online = 1 OR last_seen >= datetime("now","-5 minutes")')->fetchColumn();
    $stats['users_today'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE date(created_at) = date("now")')->fetchColumn();
    $stats['messages_today'] = (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE date(created_at) = date("now")')->fetchColumn();
    $stats['calls_today'] = (int)$pdo->query('SELECT COUNT(*) FROM calls WHERE date(created_at) = date("now")')->fetchColumn();
    $stats['errors_today'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('ERROR', 'FAILED') AND date(created_at) = date('now')")->fetchColumn();
} catch (Exception $e) {
    error_log('Monitoring Stats Error: ' . $e->getMessage());
}

// 2. حالة النظام (من خلال الـ API الجديد)
$api_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/api/v1/system/status";
$api_status = null;
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
if ($response) {
    $api_status = json_decode($response, true);
}
curl_close($ch);

$db_type = $_ENV['DB_TYPE'] ?? 'sqlite';
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
    <div style="display: flex; gap: 10px;">
        <a href="/api/v1/system/status" target="_blank" class="btn" style="background: #f8f9fa; color: #333; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; font-size: 13px;">🔗 رابط فحص الـ API</a>
        <button class="btn" onclick="location.reload()" style="background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">↻ تحديث الحالة</button>
    </div>
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
            <?php if ($api_status && $api_status['success']): ?>
                <p style="font-size: 20px; font-weight: bold; margin: 5px 0;">
                    <?= $api_status['data']['database']['status'] === 'online' ? '🟢 متصلة بنجاح' : '🔴 خطأ في الاتصال' ?>
                </p>
                <p style="color: #666; font-size: 14px;">وقت الاستجابة: <span style="color: #28a745; font-weight: bold;"><?= $api_status['data']['database']['latency_ms'] ?> ms</span></p>
            <?php else: ?>
                <p style="font-size: 20px; font-weight: bold; margin: 5px 0;">⚪ غير متاح</p>
                <small style="color: #999;">تعذر الاتصال بنقطة فحص الصحة</small>
            <?php endif; ?>
        </div>
        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; font-size: 12px;">
            💡 يتم تخزين البيانات سحابياً لضمان عدم فقدانها عند إعادة تشغيل الخادم.
        </div>
    </div>

    <!-- حالة Render -->
    <div class="card" style="padding: 20px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #f5576c;">
        <h4 style="margin-top: 0; color: #333;">🚀 حالة الخادم (Render)</h4>
        <div style="margin: 15px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <?php if ($api_status && $api_status['success']): ?>
                <div>
                    <small style="color: #999; display: block;">حمل الخادم (CPU)</small>
                    <b style="font-size: 18px;"><?= $api_status['data']['server']['load'] ?></b>
                </div>
                <div>
                    <small style="color: #999; display: block;">استهلاك الذاكرة</small>
                    <b style="font-size: 18px;"><?= $api_status['data']['server']['memory_usage'] ?></b>
                </div>
            <?php else: ?>
                <div colspan="2">
                    <b style="color: #999;">بيانات الخادم غير متاحة</b>
                </div>
            <?php endif; ?>
        </div>
        <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 8px; font-size: 12px;">
            ⚠️ تنبيه: نظام الملفات على Render مؤقت، الاعتماد كلياً على Turso.
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
