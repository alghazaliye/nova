<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$admin = requireAdminLogin();
requirePermission($admin, 'settings.manage');

$pageTitle = 'إعدادات الخدمات';
$pdo = getAdminDB();
$message = '';
$error = '';

// جلب الإعدادات الحالية
$settings = [];
$settingsResult = $pdo->query('SELECT setting_key, setting_value FROM app_settings');
foreach ($settingsResult as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    // حفظ إعدادات OTP
    if ($action === 'save_otp') {
        $otp_provider = trim($_POST['otp_provider'] ?? '');
        $otp_api_key = trim($_POST['otp_api_key'] ?? '');
        $otp_api_secret = trim($_POST['otp_api_secret'] ?? '');
        $otp_test_code = trim($_POST['otp_test_code'] ?? '');
        
        try {
            $stmt = $pdo->prepare('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?');
            
            $stmt->execute([$otp_provider, 'otp_provider']);
            if ($otp_api_key) $stmt->execute([$otp_api_key, 'otp_api_key']);
            if ($otp_api_secret) $stmt->execute([$otp_api_secret, 'otp_api_secret']);
            if ($otp_test_code) $stmt->execute([$otp_test_code, 'otp_test_code']);
            
            $message = 'تم حفظ إعدادات OTP بنجاح';
            logAudit($admin, 'UPDATE', 'settings', 0, 'تحديث إعدادات OTP');
            
            // تحديث المتغيرات المحلية
            $settings['otp_provider'] = $otp_provider;
            $settings['otp_api_key'] = $otp_api_key;
            $settings['otp_api_secret'] = $otp_api_secret;
            $settings['otp_test_code'] = $otp_test_code;
        } catch (Exception $e) {
            $error = 'خطأ في حفظ الإعدادات: ' . $e->getMessage();
        }
    }
    
    // حفظ إعدادات البريد الإلكتروني
    elseif ($action === 'save_email') {
        $email_provider = trim($_POST['email_provider'] ?? '');
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_password = trim($_POST['smtp_password'] ?? '');
        $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
        $smtp_from_name = trim($_POST['smtp_from_name'] ?? '');
        
        try {
            $stmt = $pdo->prepare('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?');
            
            $stmt->execute([$email_provider, 'email_provider']);
            $stmt->execute([$smtp_host, 'smtp_host']);
            $stmt->execute([$smtp_port, 'smtp_port']);
            $stmt->execute([$smtp_user, 'smtp_user']);
            if ($smtp_password) $stmt->execute([$smtp_password, 'smtp_password']);
            $stmt->execute([$smtp_from_email, 'smtp_from_email']);
            $stmt->execute([$smtp_from_name, 'smtp_from_name']);
            
            $message = 'تم حفظ إعدادات البريد الإلكتروني بنجاح';
            logAudit($admin, 'UPDATE', 'settings', 0, 'تحديث إعدادات البريد الإلكتروني');
            
            // تحديث المتغيرات المحلية
            $settings['email_provider'] = $email_provider;
            $settings['smtp_host'] = $smtp_host;
            $settings['smtp_port'] = $smtp_port;
            $settings['smtp_user'] = $smtp_user;
            $settings['smtp_from_email'] = $smtp_from_email;
            $settings['smtp_from_name'] = $smtp_from_name;
        } catch (Exception $e) {
            $error = 'خطأ في حفظ الإعدادات: ' . $e->getMessage();
        }
    }
    
    // حفظ إعدادات FCM
    elseif ($action === 'save_fcm') {
        $fcm_enabled = (int)($_POST['fcm_enabled'] ?? 0);
        $fcm_server_key = trim($_POST['fcm_server_key'] ?? '');
        $fcm_project_id = trim($_POST['fcm_project_id'] ?? '');
        
        try {
            $stmt = $pdo->prepare('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?');
            
            $stmt->execute([$fcm_enabled, 'fcm_enabled']);
            if ($fcm_server_key) $stmt->execute([$fcm_server_key, 'fcm_server_key']);
            if ($fcm_project_id) $stmt->execute([$fcm_project_id, 'fcm_project_id']);
            
            $message = 'تم حفظ إعدادات FCM بنجاح';
            logAudit($admin, 'UPDATE', 'settings', 0, 'تحديث إعدادات FCM');
            
            // تحديث المتغيرات المحلية
            $settings['fcm_enabled'] = $fcm_enabled;
            $settings['fcm_server_key'] = $fcm_server_key;
            $settings['fcm_project_id'] = $fcm_project_id;
        } catch (Exception $e) {
            $error = 'خطأ في حفظ الإعدادات: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
    <div>
        <h2>إعدادات الخدمات</h2>
        <p>إدارة خدمات OTP والبريد الإلكتروني و FCM</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success" style="margin: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 4px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger" style="margin: 20px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- إعدادات OTP -->
<div class="card panel" style="margin: 20px; padding: 20px;">
    <h3>إعدادات التحقق بالرسائل القصيرة (OTP)</h3>
    <form method="POST" style="display: grid; gap: 15px;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="save_otp">
        
        <div>
            <label for="otp_provider">مزود الخدمة:</label>
            <select id="otp_provider" name="otp_provider" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="test" <?= ($settings['otp_provider'] ?? '') === 'test' ? 'selected' : '' ?>>اختبار (للتطوير فقط)</option>
                <option value="twilio" <?= ($settings['otp_provider'] ?? '') === 'twilio' ? 'selected' : '' ?>>Twilio</option>
                <option value="aws_sns" <?= ($settings['otp_provider'] ?? '') === 'aws_sns' ? 'selected' : '' ?>>AWS SNS</option>
                <option value="nexmo" <?= ($settings['otp_provider'] ?? '') === 'nexmo' ? 'selected' : '' ?>>Nexmo/Vonage</option>
            </select>
            <small style="color: #666;">اختر مزود الخدمة المستخدم لإرسال رموز التحقق</small>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label for="otp_api_key">مفتاح API:</label>
                <input type="password" id="otp_api_key" name="otp_api_key" placeholder="أدخل مفتاح API" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="otp_api_secret">سر API:</label>
                <input type="password" id="otp_api_secret" name="otp_api_secret" placeholder="أدخل سر API" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
        
        <div>
            <label for="otp_test_code">رمز الاختبار (للتطوير):</label>
            <input type="text" id="otp_test_code" name="otp_test_code" placeholder="مثال: 123456" value="<?= htmlspecialchars($settings['otp_test_code'] ?? '123456') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">هذا الرمز سيُستخدم في بيئة الاختبار</small>
        </div>
        
        <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">حفظ إعدادات OTP</button>
    </form>
</div>

<!-- إعدادات البريد الإلكتروني -->
<div class="card panel" style="margin: 20px; padding: 20px;">
    <h3>إعدادات البريد الإلكتروني (SMTP)</h3>
    <form method="POST" style="display: grid; gap: 15px;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="save_email">
        
        <div>
            <label for="email_provider">مزود الخدمة:</label>
            <select id="email_provider" name="email_provider" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="smtp" <?= ($settings['email_provider'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                <option value="gmail" <?= ($settings['email_provider'] ?? '') === 'gmail' ? 'selected' : '' ?>>Gmail</option>
                <option value="sendgrid" <?= ($settings['email_provider'] ?? '') === 'sendgrid' ? 'selected' : '' ?>>SendGrid</option>
                <option value="mailgun" <?= ($settings['email_provider'] ?? '') === 'mailgun' ? 'selected' : '' ?>>Mailgun</option>
            </select>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label for="smtp_host">خادم SMTP:</label>
                <input type="text" id="smtp_host" name="smtp_host" placeholder="مثال: smtp.gmail.com" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="smtp_port">المنفذ:</label>
                <input type="number" id="smtp_port" name="smtp_port" placeholder="587" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label for="smtp_user">اسم المستخدم/البريد:</label>
                <input type="email" id="smtp_user" name="smtp_user" placeholder="your-email@gmail.com" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="smtp_password">كلمة المرور:</label>
                <input type="password" id="smtp_password" name="smtp_password" placeholder="أدخل كلمة المرور" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label for="smtp_from_email">البريد الإلكتروني للإرسال:</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" placeholder="noreply@nova-messenger.com" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="smtp_from_name">اسم المرسل:</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" placeholder="NOVA Messenger" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
        
        <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">حفظ إعدادات البريد</button>
    </form>
</div>

<!-- إعدادات FCM -->
<div class="card panel" style="margin: 20px; padding: 20px;">
    <h3>إعدادات إشعارات Firebase Cloud Messaging (FCM)</h3>
    <form method="POST" style="display: grid; gap: 15px;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="save_fcm">
        
        <div>
            <label>
                <input type="checkbox" name="fcm_enabled" value="1" <?= ($settings['fcm_enabled'] ?? 0) ? 'checked' : '' ?>>
                تفعيل إشعارات FCM
            </label>
            <small style="color: #666;">تفعيل هذا الخيار سيسمح بإرسال إشعارات فورية للمستخدمين</small>
        </div>
        
        <div>
            <label for="fcm_server_key">مفتاح الخادم (Server Key):</label>
            <textarea id="fcm_server_key" name="fcm_server_key" placeholder="أدخل مفتاح الخادم من Firebase Console" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; min-height: 100px;"><?= htmlspecialchars($settings['fcm_server_key'] ?? '') ?></textarea>
        </div>
        
        <div>
            <label for="fcm_project_id">معرّف المشروع (Project ID):</label>
            <input type="text" id="fcm_project_id" name="fcm_project_id" placeholder="your-project-id" value="<?= htmlspecialchars($settings['fcm_project_id'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">حفظ إعدادات FCM</button>
    </form>
</div>

<!-- معلومات مفيدة -->
<div class="card panel" style="margin: 20px; padding: 20px; background: #f8f9fa;">
    <h3>معلومات مفيدة</h3>
    
    <h4>Twilio:</h4>
    <ul>
        <li>زر موقع: <a href="https://www.twilio.com" target="_blank">twilio.com</a></li>
        <li>احصل على Account SID و Auth Token من لوحة التحكم</li>
    </ul>
    
    <h4>Gmail SMTP:</h4>
    <ul>
        <li>Host: smtp.gmail.com</li>
        <li>Port: 587</li>
        <li>استخدم <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a> بدلاً من كلمة المرور الأساسية</li>
    </ul>
    
    <h4>Firebase FCM:</h4>
    <ul>
        <li>زر موقع: <a href="https://console.firebase.google.com" target="_blank">Firebase Console</a></li>
        <li>احصل على Server Key من Project Settings</li>
    </ul>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
