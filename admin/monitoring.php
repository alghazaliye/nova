<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$admin = requireAdminLogin();
requirePermission($admin, 'settings.view');

$pageTitle = 'المراقبة الحية';
$pdo = getAdminDB();

// جلب إحصائيات النظام
$stats = [];

try {
    // إحصائيات المستخدمين
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $stats['users_total'] = $stmt->fetch()['count'] ?? 0;
    
    // is_online قد لا يُحدّث دائمًا؛ نحسب المتصلين بالنشاط الأخير خلال 5 دقائق أيضًا
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE is_online = 1 OR last_seen >= datetime("now","-5 minutes")');
    $stats['users_online'] = $stmt->fetch()['count'] ?? 0;
    
    // إحصائيات المحادثات
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM conversations');
    $stats['conversations_total'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM conversations WHERE type = "group"');
    $stats['groups_total'] = $stmt->fetch()['count'] ?? 0;
    
    // إحصائيات الرسائل
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM messages');
    $stats['messages_total'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM messages WHERE date(created_at) = date("now")');
    $stats['messages_today'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM messages WHERE date(created_at) = date("now","-1 day")');
    $stats['messages_yesterday'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM messages WHERE created_at >= datetime("now","-7 days")');
    $stats['messages_week'] = $stmt->fetch()['count'] ?? 0;

    // إحصائيات المستخدمين: جديد اليوم/الأسبوع/الشهر + نسب التفعيل
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE date(created_at) = date("now")');
    $stats['users_today'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE created_at >= datetime("now","-7 days")');
    $stats['users_week'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE created_at >= datetime("now","-30 days")');
    $stats['users_month'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE last_seen >= datetime("now","-1 hour")');
    $stats['users_active_hour'] = $stmt->fetch()['count'] ?? 0;
    
    // إحصائيات المكالمات
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM calls');
    $stats['calls_total'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM calls WHERE date(created_at) = date("now")');
    $stats['calls_today'] = $stmt->fetch()['count'] ?? 0;

    // إحصائيات البلاغات والاعتراضات والحظر
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('pending','reviewing')");
    $stats['reports_pending'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_appeals WHERE status = 'pending'");
    $stats['appeals_pending'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_blocked = 1");
    $stats['bans_active'] = $stmt->fetch()['count'] ?? 0;
    
    // إحصائيات الملفات
    $stmt = $pdo->query('SELECT COUNT(*) as count, SUM(file_size) as total_size FROM attachments');
    $result = $stmt->fetch();
    $stats['files_total'] = $result['count'] ?? 0;
    $stats['storage_used'] = $result['total_size'] ?? 0;
    
    // إحصائيات الأخطاء
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM audit_logs WHERE action IN ("ERROR", "FAILED")');
    $stats['errors_total'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE action IN ('ERROR', 'FAILED') AND date(created_at) = date('now')");
    $stats['errors_today'] = $stmt->fetch()['count'] ?? 0;
    
} catch (Exception $e) {
    error_log('Stats error: ' . $e->getMessage());
}

// سجل الأخطاء والتحذيرات
$error_logs = $pdo->query(
    'SELECT * FROM audit_logs WHERE action IN ("ERROR", "FAILED", "WARNING") ORDER BY created_at DESC LIMIT 30'
)->fetchAll() ?: [];

// أنشط المستخدمين
$active_users = $pdo->query(
    "SELECT u.id, u.name, u.avatar, u.last_seen, (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND date(m.created_at) = date('now')) as message_count
     FROM users u
     ORDER BY u.last_seen DESC NULLS LAST LIMIT 20"
)->fetchAll() ?: [];

// الأجهزة المتصلة (الجدول الفعلي: device_registrations بأعمدة device_uuid, device_name, os, os_version, app_version, last_seen)
$connected_devices = $pdo->query(
    "SELECT d.id, d.device_name, d.os, d.device_uuid,
            d.app_version, d.last_seen, u.name as user_name
     FROM device_registrations d
     JOIN users u ON u.id = d.user_id
     WHERE d.last_seen >= datetime('now','-1 hour') AND d.is_active = 1
     ORDER BY d.last_seen DESC LIMIT 20"
)->fetchAll() ?: [];

// استخدام قاعدة البيانات — SQLite-safe: physical file size
$db_size_db = $_ENV['DB_PATH'] ?? realpath(__DIR__ . '/../../backend/config/nova.sqlite');
$db_size = ($db_size_db !== false && is_file($db_size_db)) ? round(filesize($db_size_db) / 1024 / 1024, 2) : 0;

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<style>
.grid4{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.grid3{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
</style>

<div class="pagehead">
    <div>
        <h2>المراقبة الحية</h2>
        <p>مراقبة حالة النظام والأخطاء والأداء</p>
    </div>
</div>

<!-- إحصائيات النظام الرئيسية -->
<div class="grid4" style="margin: 20px;">
    <div class="card" style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="opacity: 0.9; margin-bottom: 5px;">المستخدمون</p>
                <h3 style="font-size: 28px; margin: 0;"><?= $stats['users_total'] ?></h3>
                <small style="opacity: 0.8;">🟢 <?= $stats['users_online'] ?> متصل الآن<?= $stats['users_total'] > 0 ? ' (' . round(($stats['users_online'] / max((int)$stats['users_total'], 1)) * 100) . '%)' : '' ?></small>
            </div>
            <div style="font-size: 40px; opacity: 0.3;">👥</div>
        </div>
    </div>
    
    <div class="card" style="padding: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="opacity: 0.9; margin-bottom: 5px;">المحادثات</p>
                <h3 style="font-size: 28px; margin: 0;"><?= $stats['conversations_total'] ?></h3>
                <small style="opacity: 0.8;">📁 <?= $stats['groups_total'] ?> مجموعة</small>
            </div>
            <div style="font-size: 40px; opacity: 0.3;">💬</div>
        </div>
    </div>
    
    <div class="card" style="padding: 20px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="opacity: 0.9; margin-bottom: 5px; color:#555">نشاط المستخدمين</p>
                <h3 style="font-size: 28px; margin: 0;"><?= $stats['users_today'] ?> جديد اليوم</h3>
                <small style="opacity: 0.8;">📅 <?= $stats['users_week'] ?> هذا الأسبوع · <?= $stats['users_month'] ?> هذا الشهر</small>
                <small style="opacity: 0.8; display:block">🟢 <?= $stats['users_active_hour'] ?> نشط خلال الساعة</small>
            </div>
            <div style="font-size: 40px; opacity: 0.3;">👤</div>
        </div>
    </div>
    
    <div class="card" style="padding: 20px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="opacity: 0.9; margin-bottom: 5px;">الرسائل</p>
                <h3 style="font-size: 28px; margin: 0;"><?= $stats['messages_total'] ?></h3>
                <small style="opacity: 0.8;">📅 <?= $stats['messages_today'] ?> اليوم · <?= $stats['messages_yesterday'] ?> أمس · <?= $stats['messages_week'] ?> أسبوع</small>
            </div>
            <div style="font-size: 40px; opacity: 0.3;">✉️</div>
        </div>
    </div>
    
    <div class="card" style="padding: 20px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="opacity: 0.9; margin-bottom: 5px;">الأخطاء</p>
                <h3 style="font-size: 28px; margin: 0; color: #ff4757;"><?= $stats['errors_total'] ?></h3>
                <small style="opacity: 0.8;">⚠️ <?= $stats['errors_today'] ?> اليوم</small>
            </div>
            <div style="font-size: 40px; opacity: 0.3;">🚨</div>
        </div>
    </div>
</div>

<!-- إحصائيات إضافية -->
<div class="grid3" style="margin: 20px;">
    <div class="card" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h4 style="margin-bottom: 15px; color: #333;">🔒 الحظر والاعتراضات</h4>
        <p style="font-size: 24px; font-weight: bold; color: #dc3545; margin: 10px 0;"><?= number_format($stats['bans_active'] ?? 0) ?> محظور نشط</p>
        <small style="color: #666;">⚖️ <?= number_format($stats['appeals_pending'] ?? 0) ?> اعتراض معلق · 📋 <?= number_format($stats['reports_pending'] ?? 0) ?> بلاغ معلق</small>
    </div>
    
    <div class="card" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h4 style="margin-bottom: 15px; color: #333;">☎️ المكالمات</h4>
        <p style="font-size: 24px; font-weight: bold; color: #f5576c; margin: 10px 0;"><?= $stats['calls_total'] ?></p>
        <small style="color: #666;">إجمالي المكالمات · <?= $stats['calls_today'] ?? 0 ?> اليوم</small>
    </div>
    
    <div class="card" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h4 style="margin-bottom: 15px; color: #333;">💾 التخزين</h4>
        <p style="font-size: 24px; font-weight: bold; color: #00f2fe; margin: 10px 0;"><?= $stats['files_total'] ?></p>
        <small style="color: #666;">📦 <?= number_format($stats['storage_used'] / 1024 / 1024, 2) ?> MB</small>
    </div>
</div>

<!-- سجل الأخطاء والتحذيرات -->
<div class="card panel" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">🚨 سجل الأخطاء والتحذيرات (آخر 30)</h3>
    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead style="position: sticky; top: 0; background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <tr>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الوقت</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">النوع</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الوصف</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($error_logs)): ?>
                    <?php foreach ($error_logs as $log): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><small><?= date('H:i:s', strtotime($log['created_at'])) ?></small></td>
                            <td style="padding: 10px;">
                                <span style="background: <?php 
                                    $action = $log['action'];
                                    echo ($action === 'ERROR') ? '#f8d7da' : (($action === 'FAILED') ? '#fff3cd' : '#d1ecf1');
                                ?>; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                    <?= htmlspecialchars($action) ?>
                                </span>
                            </td>
                            <td style="padding: 10px;"><small><?= htmlspecialchars($log['description'] ?? '-') ?></small></td>
                            <td style="padding: 10px;"><small><code><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding: 20px; text-align: center; color: #666;">✓ لا توجد أخطاء أو تحذيرات</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- أنشط المستخدمين -->
<div class="card panel" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">👥 أنشط المستخدمين (آخر 20)</h3>
    <div style="max-height: 400px; overflow-y: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <tr>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">المستخدم</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الحالة</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">آخر نشاط</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الرسائل اليوم</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($active_users)): ?>
                    <?php foreach ($active_users as $user): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 40px; height: 40px; background: #667eea; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 12px;">
                                <span style="background: <?= $user['is_online'] ? '#d4edda' : '#f8f9fa' ?>; color: <?= $user['is_online'] ? '#155724' : '#666' ?>; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">
                                    <?= $user['is_online'] ? '🟢 متصل' : '⚫ غير متصل' ?>
                                </span>
                            </td>
                            <td style="padding: 12px;"><small><?= $user['last_seen'] ? date('H:i', strtotime($user['last_seen'])) : '-' ?></small></td>
                            <td style="padding: 12px;"><small><strong><?= $user['message_count'] ?></strong></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding: 20px; text-align: center; color: #666;">لا توجد بيانات</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- الأجهزة المتصلة -->
<div class="card panel" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">📱 الأجهزة المتصلة (آخر 20)</h3>
    <div style="max-height: 400px; overflow-y: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <tr>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">المستخدم</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">اسم الجهاز</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">المنصة</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الإصدار</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">آخر نشاط</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($connected_devices)): ?>
                    <?php foreach ($connected_devices as $device): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><small><?= htmlspecialchars($device['user_name']) ?></small></td>
                            <td style="padding: 12px;"><small><?= htmlspecialchars($device['device_name'] ?? 'غير معروف') ?></small></td>
                            <td style="padding: 12px;">
                                <span style="background: #e7f3ff; color: #0066cc; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                    <?= htmlspecialchars($device['platform'] ?? '-') ?>
                                </span>
                            </td>
                            <td style="padding: 12px;"><small><?= htmlspecialchars($device['app_version'] ?? '-') ?></small></td>
                            <td style="padding: 12px;"><small><?= !empty($device['last_active_at']) ? date('H:i', strtotime($device['last_active_at'])) : '-' ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center; color: #666;">لا توجد أجهزة متصلة</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- معلومات قاعدة البيانات -->
<div class="card panel" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">💾 معلومات قاعدة البيانات</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
        <div style="padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <p style="color: #666; margin-bottom: 5px;">حجم قاعدة البيانات</p>
            <h4 style="font-size: 20px; color: #333; margin: 0;"><?= number_format((float)$db_size, 2) ?> MB</h4>
        </div>
        <div style="padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <p style="color: #666; margin-bottom: 5px;">عدد الجداول</p>
            <h4 style="font-size: 20px; color: #333; margin: 0;">25+</h4>
        </div>
        <div style="padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <p style="color: #666; margin-bottom: 5px;">الترميز</p>
            <h4 style="font-size: 20px; color: #333; margin: 0;">UTF-8</h4>
        </div>
    </div>
</div>

<!-- تحديث تلقائي -->
<script>
// تحديث الصفحة تلقائياً كل 30 ثانية
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            
            // تحديث الإحصائيات
            const oldStats = document.querySelectorAll('.grid4 .card');
            const newStats = newDoc.querySelectorAll('.grid4 .card');
            
            if (newStats.length > 0) {
                oldStats.forEach((el, i) => {
                    if (newStats[i]) {
                        el.innerHTML = newStats[i].innerHTML;
                    }
                });
            }
        })
        .catch(err => console.log('Live update error: ' + err));
}, 30000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
