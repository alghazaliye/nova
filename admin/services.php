<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$admin = requireAdminLogin();
requirePermission($admin, 'settings.manage');

$pageTitle = 'إعدادات الخدمات والمزودات';
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

    // حفظ إعدادات WebRTC
    elseif ($action === 'save_webrtc') {
        $turn_server_url = trim($_POST['turn_server_url'] ?? '');
        $turn_server_user = trim($_POST['turn_server_user'] ?? '');
        $turn_server_pass = trim($_POST['turn_server_pass'] ?? '');

        try {
            // التأكد من وجود المفاتيح في قاعدة البيانات أولاً
            $keys = ['turn_server_url', 'turn_server_user', 'turn_server_pass'];
            foreach ($keys as $key) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM app_settings WHERE setting_key = ?');
                $check->execute([$key]);
                if ($check->fetchColumn() == 0) {
                    $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, "")')->execute([$key]);
                }
            }

            $stmt = $pdo->prepare('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?');
            $stmt->execute([$turn_server_url, 'turn_server_url']);
            $stmt->execute([$turn_server_user, 'turn_server_user']);
            $stmt->execute([$turn_server_pass, 'turn_server_pass']);

            $message = 'تم حفظ إعدادات WebRTC بنجاح';
            logAudit($admin, 'UPDATE', 'settings', 0, 'تحديث إعدادات WebRTC');

            $settings['turn_server_url'] = $turn_server_url;
            $settings['turn_server_user'] = $turn_server_user;
            $settings['turn_server_pass'] = $turn_server_pass;
        } catch (Exception $e) {
            $error = 'خطأ في حفظ الإعدادات: ' . $e->getMessage();
        }
    }
}

$activeTab = in_array($_GET['tab'] ?? '', ['otp', 'email', 'general'], true) ? $_GET['tab'] : 'general';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>إعدادات الخدمات والمزودات</h2>
    <p>من مكان واحد: الإعدادات العامة (OTP / SMTP / FCM)، مزودو رموز OTP، ومزودو إرسال البريد الإلكتروني.</p>
  </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success" style="margin: 0 0 16px; padding: 15px; background: #d4edda; color: #155724; border-radius: 4px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger" style="margin: 0 0 16px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- تبويبات -->
<div class="card panel" style="margin-bottom:16px;">
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <button class="tab-btn <?= $activeTab === 'general' ? 'active' : '' ?>" id="tabGeneral" onclick="svcSwitchTab('general')">⚙ الإعدادات العامة</button>
    <button class="tab-btn <?= $activeTab === 'otp' ? 'active' : '' ?>" id="tabOtp" onclick="svcSwitchTab('otp')">📱 مزودو OTP</button>
    <button class="tab-btn <?= $activeTab === 'email' ? 'active' : '' ?>" id="tabEmail" onclick="svcSwitchTab('email')">✉ مزودو البريد</button>
  </div>
</div>

<style>
.tab-btn{padding:10px 18px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:var(--bg-secondary,#f0f2f5); color:var(--text,#222);}
.tab-btn.active{background:var(--primary,#4f46e5); color:#fff;}
.svc-tab{display:none;}
.svc-tab.open{display:block;}
</style>

<!-- ===== تبويب 1: الإعدادات العامة ===== -->
<div class="svc-tab <?= $activeTab === 'general' ? 'open' : '' ?>" id="svcTabGeneral">

<!-- إعدادات OTP -->
<div class="card panel" style="margin: 0 0 20px; padding: 20px;">
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
            <input type="text" id="otp_test_code" name="otp_test_code" placeholder="مثال: 654321" value="<?= htmlspecialchars($settings['otp_test_code'] ?? '654321') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">هذا الرمز سيُستخدم في بيئة الاختبار</small>
        </div>

        <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">حفظ إعدادات OTP</button>
    </form>
</div>

<!-- إعدادات البريد الإلكتروني -->
<div class="card panel" style="margin: 0 0 20px; padding: 20px;">
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
<div class="card panel" style="margin: 0 0 20px; padding: 20px;">
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

<!-- إعدادات WebRTC / TURN -->
<div class="card panel" style="margin: 0 0 20px; padding: 20px;">
    <h3>إعدادات خادم المكالمات (WebRTC / TURN)</h3>
    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">إعداد خادم TURN ضروري لضمان عمل المكالمات خلف جدران الحماية والشبكات المقيدة.</p>
    <form method="POST" style="display: grid; gap: 15px;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="save_webrtc">

        <div>
            <label for="turn_server_url">رابط خادم TURN/STUN:</label>
            <input type="text" id="turn_server_url" name="turn_server_url" placeholder="مثال: turn:openrelay.metered.ca:80" value="<?= htmlspecialchars($settings['turn_server_url'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #888;">ابدأ بـ turn: أو turns: أو stun:</small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label for="turn_server_user">اسم المستخدم:</label>
                <input type="text" id="turn_server_user" name="turn_server_user" placeholder="username" value="<?= htmlspecialchars($settings['turn_server_user'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="turn_server_pass">كلمة المرور / السر:</label>
                <input type="text" id="turn_server_pass" name="turn_server_pass" placeholder="password" value="<?= htmlspecialchars($settings['turn_server_pass'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>

        <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">حفظ إعدادات المكالمات</button>
    </form>
</div>

<!-- معلومات مفيدة -->
<div class="card panel" style="margin: 0; padding: 20px; background: #f8f9fa;">
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

</div><!-- /svcTabGeneral -->

<!-- ===== تبويب 2: مزودو OTP ===== -->
<div class="svc-tab <?= $activeTab === 'otp' ? 'open' : '' ?>" id="svcTabOtp">

<style>
/* CSS مزودو OTP */
.svc-op-input, .svc-op-select{width:100%; height:40px; border:1px solid var(--line); background:var(--surface2); color:var(--text); border-radius:10px; padding:0 12px;}
textarea.svc-op-input{height:auto; padding:10px 12px;}
.svc-op-field-note{font-size:11px; color:var(--muted);}
</style>

<div class="pagehead" style="margin-top:0;">
  <div>
    <h2 style="font-size:18px;">مزودو OTP</h2>
    <p>إدارة مزودي إرسال رموز التحقق (Twilio / Vonage / HTTP REST / اختباري). يتم الإرسال حسب الأولوية مع الإرجاع التلقائي (Fallback) عند فشل مزود.</p>
  </div>
  <?php if (hasPermission($admin, 'otp.providers.create')): ?>
  <button class="btn primary" onclick="op_openModal('add')">＋ إضافة مزود</button>
  <?php endif; ?>
</div>

<div class="card panel" style="margin-bottom:16px;">
  <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:13px;">
    <div><b>وضع التسليم:</b> <span id="opStatusMode">—</span></div>
    <div><b>الإرجاع اليدوي:</b> <span id="opStatusManual">—</span></div>
    <div><b>مدة صلاحية الرمز:</b> <span id="opStatusExpiry">—</span> دقيقة</div>
    <div><b>الحد الأقصى للمحاولات:</b> <span id="opStatusMaxAttempts">—</span></div>
  </div>
</div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>المزود</th>
        <th>النوع</th>
        <th>الحالة</th>
        <th>الافتراضي</th>
        <th>الناجح / الفاشل</th>
        <th>آخر استخدام</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody id="opProvidersBody">
      <tr><td colspan="8" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>
    </tbody>
  </table>
</div>

<!-- مودال إضافة/تعديل مزود OTP -->
<div class="modal" id="opModal">
  <div class="modal-box" style="max-width:560px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 id="opModalTitle" style="margin:0;">إضافة مزود OTP</h3>
      <button onclick="op_closeModal()" style="font-size:20px;">✕</button>
    </div>
    <form id="opProviderForm" onsubmit="op_saveProvider(event)" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="id" id="opId">
      <label style="font-size:13px;">اسم المزود <span style="color:var(--bad)">*</span>
        <input name="name" id="opName" required maxlength="120" class="svc-op-input" placeholder="مثال: مزود الرسائل السعودي">
      </label>
      <label style="font-size:13px;">نوع المزود <span style="color:var(--bad)">*</span>
        <select name="type" id="opType" class="svc-op-select" onchange="op_renderTypeFields()">
          <option value="twilio">Twilio</option>
          <option value="vonage">Vonage</option>
          <option value="http_rest">HTTP REST (مزود مخصص)</option>
          <option value="sms_mock">رسائل نصية SMS (قناة تجربة)</option>
          <option value="whatsapp_mock">واتساب WhatsApp (قناة تجربة)</option>
          <option value="test">مزود اختباري (تطوير فقط)</option>
        </select>
      </label>
      <label style="font-size:13px;">الأولوية <span style="color:var(--muted)">(الأقل أولوية أولًا — ترتيب الفال باك)</span>
        <input type="number" name="priority" id="opPriority" value="1" min="0" max="99" class="svc-op-input">
      </label>

      <div id="opTypeFields" style="display:flex; flex-direction:column; gap:10px;"></div>

      <label style="font-size:13px;">قالب الرسالة
        <textarea name="message_template" id="opTemplate" rows="3" class="svc-op-input" placeholder="رمز التحقق: {OTP} صالح لمدة {MINUTES} دقيقة — {APP_NAME}"></textarea>
      </label>
      <label style="font-size:12px; display:flex; gap:6px; align-items:center;">
        <input type="checkbox" name="is_default" id="opDefault" value="1"> افتراضي لهذا النوع
      </label>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
        <button type="button" class="btn" onclick="op_closeModal()">إلغاء</button>
        <button type="submit" class="btn primary">حفظ</button>
      </div>
    </form>
  </div>
</div>

<!-- مودال اختبار مزود OTP -->
<div class="modal" id="opTestModal">
  <div class="modal-box" style="max-width:420px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 style="margin:0;">اختبار المزود</h3>
      <button onclick="op_closeTestModal()" style="font-size:20px;">✕</button>
    </div>
    <form id="opTestForm" onsubmit="op_testProvider(event)" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="id" id="opTestId">
      <p style="margin:0; font-size:13px; color:var(--muted);">سيتم إرسال رمز تجريبي (000000) إلى رقم الهاتف لتحقق من الإعدادات.</p>
      <label style="font-size:13px;">رقم الهاتف <span style="color:var(--bad)">*</span>
        <input name="phone" id="opTestPhone" required class="svc-op-input" placeholder="+9665XXXXXXXX" dir="ltr" style="text-align:right;">
      </label>
      <div id="opTestResult" style="display:none; padding:10px; border-radius:10px; font-size:13px;"></div>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
        <button type="button" class="btn" onclick="op_closeTestModal()">إغلاق</button>
        <button type="submit" class="btn primary" id="opTestBtn">إرسال رمز تجريبي</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ===== مزودو OTP (بادئة op_ لتجنب تعارض المعرفات مع تبويب البريد) ===== */
const OTP_API = '/api/v1';
let opProviders = [];

function op_statusBadge(s){
  if (s === 'enabled') return '<span class="status online">مفعّل</span>';
  return '<span class="status offline">معطّل</span>';
}

function op_typeLabel(t){
  const labels = {twilio:'Twilio', vonage:'Vonage', http_rest:'HTTP REST', sms_mock:'رسائل نصية (تجربة)', whatsapp_mock:'واتساب (تجربة)', whatsapp:'واتساب', test:'اختباري'};
  return labels[t] || t;
}

async function op_loadProviders(){
  try {
    const res = await fetch(OTP_API + '/admin/otp/providers', {headers: {'X-Admin-Auth': (localStorage.getItem('adminToken') || '')}});
    if (res.status === 401 || res.status === 403) { document.getElementById('opProvidersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;">يجب تسجيل الدخول من التطبيق أو إعادة تسجيل دخول الإدارة مع JWT</td></tr>'; return; }
    const data = await res.json();
    opProviders = data.providers || [];
    op_renderProviders();
  } catch (e) {
    document.getElementById('opProvidersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;">فشل التحميل: ' + e.message + '</td></tr>';
  }
  // Settings status line
  try {
    const s = await fetch(OTP_API + '/admin/otp/settings', {headers: {'X-Admin-Auth': (localStorage.getItem('adminToken') || '')}}).then(r => r.json());
    const st = s.settings || {};
    const modes = {auto:'تلقائي', manual:'يدوي', auto_fallback:'تلقائي + إرجاع يدوي'};
    document.getElementById('opStatusMode').textContent = modes[st.otp_delivery_mode] || st.otp_delivery_mode || '—';
    document.getElementById('opStatusManual').textContent = st.otp_enable_manual_fallback === '1' ? 'مفعّل' : 'معطّل';
    document.getElementById('opStatusExpiry').textContent = st.otp_expiry_minutes || '—';
    document.getElementById('opStatusMaxAttempts').textContent = st.otp_max_attempts || '—';
  } catch (e) {}
}

function op_renderProviders(){
  const tbody = document.getElementById('opProvidersBody');
  if (opProviders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:24px;">لا يوجد مزودون. أضف مزود اختباري أولًا (لا يتطلب أي مفاتيح).</td></tr>';
    return;
  }
  tbody.innerHTML = opProviders.map(p => `
    <tr>
      <td>${p.id}</td>
      <td><b>${esc(p.name)}</b></td>
      <td>${op_typeLabel(p.type)}</td>
      <td>${op_statusBadge(p.status)}</td>
      <td>${p.is_default ? '<span style="color:var(--primary); font-weight:800;">نعم</span>' : '—'}</td>
      <td><span style="color:var(--good)">${p.success_count || 0}</span> / <span style="color:var(--bad)">${p.failure_count || 0}</span></td>
      <td>${p.last_used_at ? (function(){ try { const raw = String(p.last_used_at).trim(); const iso = (raw.length >= 19 && raw[10] === ' ' && !raw.includes('T') && !raw.endsWith('Z')) ? raw + 'Z' : raw; return new Date(iso).toLocaleString('ar-SA', {timeZone: window.NovaTZ || 'Asia/Riyadh'}); } catch(e){ return new Date(p.last_used_at).toLocaleString('ar-SA'); } })() : '—'}</td>
      <td>
        <div style="display:flex; gap:5px; flex-wrap:wrap;">
          <button class="btn sm" onclick="op_openModal('edit', ${p.id})">✎ تعديل</button>
          <button class="btn sm" onclick="op_openTest(${p.id})">⚡ اختبار</button>
          <button class="btn sm" style="background:${p.status === 'enabled' ? 'rgba(240,68,56,.1);color:var(--bad)' : 'rgba(18,183,106,.1);color:var(--good)'}" onclick="op_toggle(${p.id}, '${p.status === 'enabled' ? 'disabled' : 'enabled'}')">${p.status === 'enabled' ? 'تعطيل' : 'تفعيل'}</button>
          <button class="btn danger sm" onclick="op_del(${p.id})">حذف</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function op_openModal(mode, id = null){
  document.getElementById('opModalTitle').textContent = mode === 'add' ? 'إضافة مزود OTP' : 'تعديل مزود OTP';
  document.getElementById('opId').value = id || '';
  if (mode === 'edit' && id) {
    const p = opProviders.find(x => x.id === id);
    if (p) {
      document.getElementById('opName').value = p.name;
      document.getElementById('opType').value = p.type;
      document.getElementById('opPriority').value = p.priority ?? 1;
      document.getElementById('opDefault').checked = !!p.is_default;
      document.getElementById('opTemplate').value = p.message_template || '';
    }
  } else {
    document.getElementById('opProviderForm').reset();
    document.getElementById('opDefault').checked = false;
  }
  op_renderTypeFields();
  document.getElementById('opModal').classList.add('open');
}

function op_closeModal(){ document.getElementById('opModal').classList.remove('open'); }

function op_renderTypeFields(){
  const type = document.getElementById('opType').value;
  const host = document.getElementById('opTypeFields');
  let html = '';
  if (type === 'twilio') {
    html = `
      <label style="font-size:13px;">Account SID <span style="color:var(--bad)">*</span>
        <input name="account_sid" id="opSid" class="svc-op-input" placeholder="ACXXXXXXXXXXXXXXXX" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">Auth Token <span style="color:var(--bad)">*</span>
        <input name="api_secret" id="opSecret" type="password" class="svc-op-input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">المُرسل (From) <span style="color:var(--bad)">*</span>
        <input name="sender_id" id="opFrom" class="svc-op-input" placeholder="+1234567890" dir="ltr" style="text-align:right;"></label>
      <p class="svc-op-field-note">يُرسل عبر Twilio Messages API. يُشفّر الرمز قبل الحفظ.</p>`;
  } else if (type === 'vonage') {
    html = `
      <label style="font-size:13px;">API Key <span style="color:var(--bad)">*</span>
        <input name="api_key" id="opKey" class="svc-op-input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">API Secret <span style="color:var(--bad)">*</span>
        <input name="api_secret" id="opVSecret" type="password" class="svc-op-input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">Sender ID (اختياري)
        <input name="sender_id" id="opVFrom" class="svc-op-input" placeholder="NOVA" dir="ltr" style="text-align:right;"></label>`;
  } else if (type === 'http_rest') {
    html = `
      <label style="font-size:13px;">رابط API <span style="color:var(--bad)">*</span>
        <input name="api_base_url" id="opUrl" class="svc-op-input" placeholder="https://sms-gateway.example.com/send" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">طريقة HTTP
        <select name="http_method" id="opMethod" class="svc-op-select">
          <option value="POST">POST</option><option value="GET">GET</option>
        </select></label>
      <label style="font-size:13px;">Content-Type
        <select name="content_type" id="opCt" class="svc-op-select">
          <option value="json">JSON</option><option value="form">Form (x-www-form-urlencoded)</option>
        </select></label>
      <label style="font-size:13px;">نوع المصادقة
        <select name="auth_type" id="opAuth" class="svc-op-select" onchange="op_toggleAuthFields()">
          <option value="none">بدون</option>
          <option value="bearer">Bearer Token</option>
          <option value="basic">Basic Auth</option>
          <option value="header">رأس مخصص</option>
          <option value="query">معلمة استعلام</option>
        </select></label>
      <div id="opAuthFields"></div>
      <label style="font-size:13px;">حقل الهاتف في الطلب <span class="svc-op-field-note">(يوضع {PHONE})</span>
        <input name="to_field" id="opToField" value="to" class="svc-op-input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">حقل الرمز في الطلب <span class="svc-op-field-note">(يوضع {OTP})</span>
        <input name="otp_field" id="opOtpField" value="code" class="svc-op-input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">نمط القالب
        <select name="template_mode" id="opTm" class="svc-op-select">
          <option value="code_only">الرمز فقط ({OTP})</option>
          <option value="full_message">الرسالة كاملة ({OTP} {PHONE} ...)</option>
        </select></label>
      <label style="font-size:13px;">تعبير النجاح <span class="svc-op-field-note">مثال: json.status=OK</span>
        <input name="success_expr" id="opSx" class="svc-op-input" placeholder="json.status=OK" dir="ltr" style="text-align:right;"></label>
      <p class="svc-op-field-note" style="color:var(--good);"><b>قناة عامة متكاملة:</b> تعمل مع أي مزود SMS يدعم HTTP REST (Unifonic, Jawwal, STC, SMS Global...). الرمز يُشفّر قبل الإرسال.</p>`;
  } else if (type === 'sms_mock') {
    html = '<p class="svc-op-field-note" style="color:var(--good);"><b>قناة تجربة داخلية:</b> رمز حقيقي (6 أرقام) يُولَّد ويُشفّر كالمزودات الحقيقية، لكن لا يُرسل SMS فعليًا. يمكنك قراءة الرمز من صفحة «طلبات التسجيل» (سجل التسليم) أو من الإشعار في لوحة التحكم. مثالي لتجربة مسار الرسائل النصية دون اشتراك في خدمة خارجية.</p>';
  } else if (type === 'whatsapp_mock') {
    html = '<p class="svc-op-field-note" style="color:var(--good);"><b>قناة تجربة داخلية عبر واتساب:</b> رمز حقيقي (6 أرقام) يُولَّد ويُشفّر كالمزودات الحقيقية، لكن لا يُرسل واتساب فعليًا. يمكنك قراءة الرمز من صفحة «طلبات التسجيل» (سجل التسليم) أو من الإشعار في لوحة التحكم. مثالي لتجربة مسار تحقق واتساب لأي شخص يسجل دون اشتراك في خدمة خارجية. للإنتاج: استخدم نوع HTTP REST مع WhatsApp Cloud API (Meta) أو Twilio WhatsApp.</p>';
  } else if (type === 'test') {
    html = '<p class="svc-op-field-note" style="color:var(--warn);"><b>تنبيه:</b> مزود الاختبار يعمل فقط في بيئة التطوير ويعيد رمز OTP_TEST_CODE المحدد في الإعدادات (حاليًا: 654321).</p>';
  }
  host.innerHTML = html;
  op_toggleAuthFields();
}

function op_toggleAuthFields(){
  const auth = (document.getElementById('opAuth') || {}).value || 'none';
  const host = document.getElementById('opAuthFields');
  if (!host) return;
  let html = '';
  if (auth === 'bearer') {
    html = `<label style="font-size:13px;">Bearer Token <span style="color:var(--bad)">*</span>
      <input name="auth_token" id="opAuthToken" class="svc-op-input" dir="ltr" style="text-align:right;"></label>`;
  } else if (auth === 'basic') {
    html = `<label style="font-size:13px;">اسم المستخدم <span style="color:var(--bad)">*</span>
      <input name="auth_user" id="opAuthUser" class="svc-op-input" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">كلمة المرور <span style="color:var(--bad)">*</span>
      <input name="auth_pass" id="opAuthPass" type="password" class="svc-op-input" dir="ltr" style="text-align:right;"></label>`;
  } else if (auth === 'header') {
    html = `<label style="font-size:13px;">اسم الرأس <span style="color:var(--bad)">*</span>
      <input name="auth_header_name" id="opAHName" class="svc-op-input" placeholder="X-API-Key" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">قيمة الرأس <span style="color:var(--bad)">*</span>
      <input name="auth_header_value" id="opAHVal" class="svc-op-input" dir="ltr" style="text-align:right;"></label>`;
  } else if (auth === 'query') {
    html = `<label style="font-size:13px;">اسم المعلمة <span style="color:var(--bad)">*</span>
      <input name="auth_param_name" id="opAPName" class="svc-op-input" placeholder="key" dir="ltr" style="text-align:right;"></label>
      <label style="font-size:13px;">قيمة المعلمة <span style="color:var(--bad)">*</span>
      <input name="auth_param_value" id="opAPVal" class="svc-op-input" dir="ltr" style="text-align:right;"></label>`;
  }
  host.innerHTML = html;
}

async function op_saveProvider(e){
  e.preventDefault();
  const id = document.getElementById('opId').value;
  const form = document.getElementById('opProviderForm');
  const fd = new FormData(form);
  const data = Object.fromEntries(fd.entries());
  data.is_default = fd.get('is_default') ? 1 : 0;
  data.priority = parseInt(data.priority || '1', 10);
  const url = id ? OTP_API + '/admin/otp/providers/' + id : OTP_API + '/admin/otp/providers';
  try {
    const res = await fetch(url, {
      method: id ? 'PUT' : 'POST',
      headers: {'Content-Type': 'application/json', 'X-Admin-Auth': (localStorage.getItem('adminToken') || '')},
      body: JSON.stringify(data)
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || j.error_code || 'فشل الحفظ');
    op_closeModal();
    await op_loadProviders();
  } catch (err) { alert(err.message); }
}

async function op_toggle(id, status){
  try {
    const res = await fetch(OTP_API + '/admin/otp/providers/' + id + '/toggle', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Admin-Auth': (localStorage.getItem('adminToken') || '')},
      body: JSON.stringify({status})
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل');
    await op_loadProviders();
  } catch (err) { alert(err.message); }
}

async function op_del(id){
  if (!confirm('حذف هذا المزود نهائيًا؟')) return;
  try {
    const res = await fetch(OTP_API + '/admin/otp/providers/' + id, {
      method: 'DELETE',
      headers: {'X-Admin-Auth': (localStorage.getItem('adminToken') || '')}
    });
    const j = await res.json();
    if (!res.ok) throw new Error(j.message || 'فشل الحذف');
    await op_loadProviders();
  } catch (err) { alert(err.message); }
}

function op_openTest(id){
  document.getElementById('opTestId').value = id;
  document.getElementById('opTestPhone').value = '';
  document.getElementById('opTestResult').style.display = 'none';
  document.getElementById('opTestModal').classList.add('open');
}
function op_closeTestModal(){ document.getElementById('opTestModal').classList.remove('open'); }

async function op_testProvider(e){
  e.preventDefault();
  const btn = document.getElementById('opTestBtn');
  const box = document.getElementById('opTestResult');
  btn.disabled = true; btn.textContent = 'جاري الإرسال...';
  box.style.display = 'none';
  try {
    const res = await fetch(OTP_API + '/admin/otp/providers/' + document.getElementById('opTestId').value + '/test', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Admin-Auth': (localStorage.getItem('adminToken') || '')},
      body: JSON.stringify({phone: document.getElementById('opTestPhone').value})
    });
    const j = await res.json();
    box.style.display = 'block';
    box.style.background = res.ok ? 'rgba(18,183,106,.1)' : 'rgba(240,68,56,.1)';
    box.style.color = res.ok ? 'var(--good)' : 'var(--bad)';
    box.textContent = j.message + (j.error_code ? ' (' + j.error_code + ')' : '');
  } catch (err) { box.style.display = 'block'; box.textContent = err.message; }
  finally { btn.disabled = false; btn.textContent = 'إرسال رمز تجريبي'; }
}
</script>

</div><!-- /svcTabOtp -->

<!-- ===== تبويب 3: مزودو البريد ===== -->
<div class="svc-tab <?= $activeTab === 'email' ? 'open' : '' ?>" id="svcTabEmail">

<style>
  /* ===== CSS مزودو البريد (ep-*) ===== */
  .modal{position:fixed; inset:0; background:rgba(16,24,40,.55); display:none; align-items:center; justify-content:center; z-index:999;}
  .modal.open{display:flex;}
  .modal-box{background:var(--surface); border-radius:18px; padding:22px; margin:14px; max-height:90vh; overflow:auto; box-shadow:var(--shadow);}
  .badge{display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:800;}
  .badge.good{background:#dcfae6; color:#087443;}
  .badge.bad{background:#fee4e2; color:#b42318;}
  .btn.small, .btn.sm{padding:6px 11px; font-size:12px; border-radius:8px;}
  .ep-fieldset { border: 1px solid var(--line); border-radius: 14px; padding: 16px; margin-top: 4px; background: var(--surface2, #f8fafc) }
  [data-theme=dark] .ep-fieldset { background: #0f1726 }
  .ep-fieldset-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 800; margin: 0 0 12px; color: var(--text) }
  .ep-fieldset-title .ep-ico { width: 30px; height: 30px; border-radius: 9px; background: linear-gradient(135deg, #5b5ce2, #7c3aed); color: #fff; display: grid; place-items: center; font-size: 14px; font-weight: 900 }
  .ep-field { display: flex; flex-direction: column; gap: 5px; font-size: 13px; margin-bottom: 10px }
  .ep-field:last-child { margin-bottom: 0 }
  .ep-field .ep-label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--muted) }
  .ep-field .ep-label .req { color: var(--bad); font-weight: 900 }
  .ep-field .ep-label .opt { color: var(--muted); font-weight: 400; font-size: 11px }
  .ep-input { height: 42px; border: 1px solid var(--line); background: var(--surface); color: var(--text); border-radius: 10px; padding: 0 12px; outline: 0; width: 100% }
  .ep-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(91, 92, 226, .15) }
  .ep-input[readonly] { background: var(--surface2); cursor: default }
  .ep-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px }
  .ep-passwrap { position: relative; }
  .ep-passwrap .ep-input { padding-left: 40px; }
  .ep-eye { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 15px; color: var(--muted); background: none; border: 0; padding: 4px; line-height: 1 }
  .ep-eye:hover { color: var(--primary) }
  .ep-quick { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 9px 12px; border-radius: 10px; background: #eef2ff; border: 1px dashed #a5b4fc; font-size: 12.5px }
  [data-theme=dark] .ep-quick { background: #1b2550; border-color: #4f5fc9 }
  .ep-quick button { font-size: 12px; font-weight: 800; padding: 6px 12px; border-radius: 8px; background: #fff; color: #4f46e5; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  [data-theme=dark] .ep-quick button { background: #373f5c; color: #c7d2fe }
  .ep-quick button:hover { background: #eef2ff }
  .ep-toggle { display: flex; gap: 6px; align-items: center; font-size: 12.5px; }
  .ep-toggle input { margin: 0; }
  .ep-toast { position: fixed; top: 24px; left: 50%; transform: translateX(-50%) translateY(-120%); z-index: 200; padding: 12px 20px; border-radius: 12px; font-size: 13.5px; font-weight: 700; box-shadow: var(--shadow); transition: transform .3s cubic-bezier(.2, .9, .3, 1.2); max-width: 90vw }
  .ep-toast.show { transform: translateX(-50%) translateY(0) }
  .ep-toast.ok { background: #059669; color: #fff }
  .ep-toast.err { background: #dc2626; color: #fff }
  .ep-hint { color: var(--muted); font-size: 11px; line-height: 1.5; margin-top: 4px }
  @media (max-width: 760px) { .ep-row2 { grid-template-columns: 1fr } }
</style>

<div class="pagehead" style="margin-top:0;">
  <div>
    <h2 style="font-size:18px;">مزودو البريد الإلكتروني</h2>
    <p>إدارة مزودي إرسال رموز التحقق بالبريد (SMTP / HTTP REST). تستخدم عند تفعيل «التسجيل/الدخول بالبريد» وOTP البريد.</p>
  </div>
  <?php if (hasPermission($admin, 'email.providers.create')): ?>
  <button class="btn primary" onclick="openModal('add')">＋ إضافة مزود</button>
  <?php endif; ?>
</div>

<div class="stats" id="epStats"></div>

<div class="card panel tablewrap">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>المزود</th>
        <th>النوع</th>
        <th>الحالة</th>
        <th>الافتراضي</th>
        <th>الناجح / الفاشل</th>
        <th>آخر استخدام</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody id="providersBody">
      <tr><td colspan="8" style="text-align:center; padding:24px;">جاري التحميل...</td></tr>
    </tbody>
  </table>
</div>

<!-- Toast -->
<div class="ep-toast" id="epToast"></div>

<!-- إضافة/تعديل مزود بريد -->
<div class="modal" id="providerModal">
  <div class="modal-box" style="max-width:620px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 id="modalTitle" style="margin:0;">إضافة مزود بريد</h3>
      <button onclick="closeModal()" style="font-size:20px;">✕</button>
    </div>
        <form id="providerForm" onsubmit="saveProvider(event)">
      <input type="hidden" id="pfId" value="">
      <div class="ep-field">
        <span class="ep-label">اسم المزود <span class="req">*</span></span>
        <input class="ep-input" name="name" id="pfName" required maxlength="120" placeholder="مثال: Gmail SMTP">
      </div>

      <div class="ep-row2">
        <div class="ep-field">
          <span class="ep-label">نوع المزود <span class="req">*</span></span>
          <select class="ep-input" name="type" id="pfType" onchange="renderTypeFields()">
            <option value="smtp">SMTP</option>
            <option value="http_rest">HTTP REST (مزود مخصص)</option>
          </select>
        </div>
        <div class="ep-field">
          <span class="ep-label">الأولوية <span class="opt">(الأقل أولية يُرسل أولًا — ترتيب الاحتياط)</span></span>
          <input type="number" class="ep-input" name="priority" id="pfPriority" value="1" min="0" max="99">
        </div>
      </div>

      <div id="typeFields"></div>

      <div class="ep-row2">
        <div class="ep-field">
          <span class="ep-label">البريد المرسل منه (From) <span class="req">*</span></span>
          <input class="ep-input" name="from_email" id="pfFromEmail" required dir="ltr" placeholder="noreply@yourapp.com">
        </div>
        <div class="ep-field">
          <span class="ep-label">الاسم الظاهر للمرسل <span class="opt">(اختياري)</span></span>
          <input class="ep-input" name="from_name" id="pfFromName" placeholder="NOVA Messenger">
        </div>
      </div>

      <div class="ep-toggle">
        <input type="checkbox" name="is_default" id="pfDefault" value="1">
        <label for="pfDefault">الافتراضي لهذا النوع (يُستخدم أولًا عند الإرسال)</label>
      </div>

      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
        <button type="button" class="btn" onclick="closeModal()">إلغاء</button>
        <button type="submit" class="btn primary" id="saveBtn">حفظ</button>
      </div>
    </form>
  </div>
</div>

<!-- اختبار مزود بريد -->
<div class="modal" id="testModal">
  <div class="modal-box" style="max-width:420px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h3 style="margin:0;">اختبار المزود</h3>
      <button onclick="closeTestModal()" style="font-size:20px;">✕</button>
    </div>
    <form id="testForm" onsubmit="testProvider(event)" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="id" id="testId">
      <p style="margin:0; font-size:13px; color:var(--muted);">سيتم إرسال رسالة تجريبية فعلية عبر إعدادات المزود للتأكد من سلامتها.</p>
      <div class="ep-field">
        <span class="ep-label">البريد الإلكتروني <span class="req">*</span></span>
        <input class="ep-input" name="email" id="testEmail" required dir="ltr" placeholder="you@example.com">
      </div>
      <div id="testResult" style="display:none; padding:10px; border-radius:10px; font-size:13px;"></div>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
        <button type="button" class="btn" onclick="closeTestModal()">إغلاق</button>
        <button type="submit" class="btn primary" id="testBtn">إرسال تجريبي</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ===== مزودو البريد الإلكتروني (كامل من email-providers.php) ===== */
const EMAIL_API = '/api/v1/admin/email-providers';
function epToken() { return localStorage.getItem('adminToken') || ''; }
function esc(s) { return String(s || '').replace(/[<>"']/g, c => ({ '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

/* ===== Toast ===== */
let toastTimer = null;
function toast(msg, kind) {
  const t = document.getElementById('epToast');
  t.textContent = msg;
  t.className = 'ep-toast ' + (kind === 'ok' ? 'ok' : 'err');
  clearTimeout(toastTimer);
  requestAnimationFrame(() => { t.classList.add('show'); toastTimer = setTimeout(() => t.classList.remove('show'), 3500); });
}

/* ===== Modal ===== */
function openModal(mode, provider = null) {
  document.getElementById('modalTitle').textContent = mode === 'add' ? 'إضافة مزود بريد' : 'تعديل المزود';
  document.getElementById('pfId').value = provider ? provider.id : '';
  document.getElementById('pfName').value = provider ? provider.name : '';
  document.getElementById('pfType').value = provider ? provider.type : 'smtp';
  document.getElementById('pfPriority').value = provider ? provider.priority : 1;
  document.getElementById('pfFromEmail').value = provider ? (provider.from_email || '') : '';
  document.getElementById('pfFromName').value = provider ? (provider.from_name || '') : '';
  document.getElementById('pfDefault').checked = provider ? !!Number(provider.is_default) : false;
  renderTypeFields(provider);
  /* إعادة تعيين حقول النوع بعد بناء الحقول (innerHTML يمسح القيم) */
  if (provider) {
    const h = document.getElementById('pfHost');    if (h) h.value = provider.host || '';
    const p = document.getElementById('pfPort');    if (p) p.value = provider.port || 587;
    const e = document.getElementById('pfEncryption'); if (e) e.value = provider.encryption || 'tls';
    const u = document.getElementById('pfUsername'); if (u) u.value = provider.username || '';
    const w = document.getElementById('pfPassword'); if (w) w.value = '';
    const a = document.getElementById('pfApiUrl');   if (a) a.value = provider.api_base_url || '';
    const k = document.getElementById('pfApiKey');   if (k) k.value = '';
    const cfg = JSON.parse(provider.extra_config || '{}');
    const tf = document.getElementById('pfToField'); if (tf) tf.value = cfg.to_field || '';
    const tm = document.getElementById('pfTemplate'); if (tm) tm.value = cfg.template || '';
  }
  document.getElementById('providerModal').classList.add('open');
  document.getElementById('saveBtn').disabled = false;
  document.getElementById('saveBtn').textContent = 'حفظ';
}
function closeModal() {
  document.getElementById('providerModal').classList.remove('open');
  document.getElementById('providerForm').reset();
  document.getElementById('pfId').value = '';
}
function openTestModal(id, name) {
  document.getElementById('testId').value = id;
  document.getElementById('testEmail').value = '';
  document.getElementById('testResult').style.display = 'none';
  document.getElementById('testBtn').textContent = 'إرسال تجريبي';
  document.getElementById('testBtn').disabled = false;
  const t = document.querySelector('#testModal h3');
  if (t) t.textContent = 'اختبار: ' + (name || 'المزود');
  document.getElementById('testModal').classList.add('open');
}
function closeTestModal() { document.getElementById('testModal').classList.remove('open'); }

/* ===== حقول النوع (SMTP / REST) ===== */
function renderTypeFields(provider) {
  const type = document.getElementById('pfType').value;
  const wrap = document.getElementById('typeFields');
  const cfg = provider ? JSON.parse(provider.extra_config || '{}') : {};
  if (type === 'smtp') {
    const host = provider ? (provider.host || '') : 'smtp.gmail.com';
    const port = provider ? (provider.port || 587) : 587;
    const isGmail = !provider || host === 'smtp.gmail.com';
    wrap.innerHTML = `
      <div class="ep-fieldset">
        <p class="ep-fieldset-title"><span class="ep-ico">📧</span> إعدادات خادم SMTP</p>
        <div class="ep-quick" id="smtpPresets" style="display:${isGmail ? 'flex' : 'none'}; flex-wrap:wrap;">
          <span style="margin-inline-end:auto">⚡ تعبئة سريعة:</span>
          <button type="button" onclick="fillSmtpPreset('gmail')">Gmail</button>
          <button type="button" onclick="fillSmtpPreset('outlook')">Outlook</button>
          <button type="button" onclick="fillSmtpPreset('yahoo')">Yahoo</button>
        </div>
        <div class="ep-hint" style="margin-bottom:10px">💡 لإرسال Gmail فعّل «كلمة المرور التطبيقية» من حساب جوجل (الإعدادات ← الأمان)، ثم املأ المستخدم وكلمة المرور فقط.</div>
        <div class="ep-field">
          <span class="ep-label">خادم SMTP <span class="req">*</span> <span class="opt">host</span></span>
          <input class="ep-input" name="host" id="pfHost" value="${esc(host)}" dir="ltr" placeholder="smtp.gmail.com" oninput="autoFillSmtpHint()">
          <div class="ep-hint">خادم الإرسال. أمثلة: smtp.gmail.com — smtp-mail.outlook.com — smtp.mail.yahoo.com</div>
        </div>
        <div class="ep-row2">
          <div class="ep-field">
            <span class="ep-label">المنفذ <span class="req">*</span></span>
            <input type="number" class="ep-input" name="port" id="pfPort" value="${port}">
            <div class="ep-hint">587 مع TLS أو 465 مع SSL عادةً</div>
          </div>
          <div class="ep-field">
            <span class="ep-label">التشفير</span>
            <select class="ep-input" name="encryption" id="pfEncryption">
              <option value="tls" ${(!provider || provider.encryption === 'tls') ? 'selected' : ''}>TLS (STARTTLS)</option>
              <option value="ssl" ${provider?.encryption === 'ssl' ? 'selected' : ''}>SSL</option>
              <option value="none" ${provider?.encryption === 'none' ? 'selected' : ''}>بدون</option>
            </select>
          </div>
        </div>
        <div class="ep-field">
          <span class="ep-label">اسم المستخدم <span class="req">*</span> <span class="opt">غالبًا البريد الكامل</span></span>
          <input class="ep-input" name="username" id="pfUsername" value="${esc(provider ? provider.username || '' : '')}" dir="ltr" placeholder="you@gmail.com">
        </div>
        <div class="ep-field">
          <span class="ep-label">كلمة المرور <span class="req">${provider ? '<span class="opt">(اتركه فارغًا للإبقاء على القيمة الحالية)</span>' : '* <span class="opt">كلمة مرور تطبيقية App Password لمستخدمي Gmail</span>'}</span></span>
          <div class="ep-passwrap">
            <input type="password" class="ep-input" name="password" id="pfPassword" placeholder="${provider ? '(اتركه فارغًا للإبقاء على القيمة الحالية)' : ''}" dir="ltr">
            <button type="button" class="ep-eye" onclick="togglePass('pfPassword', this)" aria-label="إظهار/إخفاء">👁</button>
          </div>
          <div class="ep-hint">لن تُعرض في أي مكان — تُخزن مشفرة (AES-256-GCM) ولا تعود للواجهة أبدًا.</div>
        </div>
      </div>`;
    autoFillSmtpHint();
  } else {
    wrap.innerHTML = `
      <div class="ep-fieldset">
        <p class="ep-fieldset-title"><span class="ep-ico">⚡</span> إعدادات HTTP REST</p>
        <div class="ep-field">
          <span class="ep-label">رابط API <span class="req">*</span> <span class="opt">base url</span></span>
          <input class="ep-input" name="api_base_url" id="pfApiUrl" value="${esc(provider ? provider.api_base_url || '' : 'https://api.provider.com/v1/send')}" dir="ltr" placeholder="https://api.provider.com/v1/send">
        </div>
        <div class="ep-field">
          <span class="ep-label">API Key <span class="opt">(تُخزن مشفرة)</span> <span class="req">${provider ? '<span class="opt">(اتركه فارغًا للإبقاء على القيمة الحالية)</span>' : '*'}</span></span>
          <div class="ep-passwrap">
            <input type="password" class="ep-input" name="api_key" id="pfApiKey" placeholder="${provider ? '(اتركه فارغًا للإبقاء على القيمة الحالية)' : ''}" dir="ltr">
            <button type="button" class="ep-eye" onclick="togglePass('pfApiKey', this)" aria-label="إظهار/إخفاء">👁</button>
          </div>
        </div>
        <div class="ep-field">
          <span class="ep-label">حقل البريد في الـJSON <span class="opt">(default: to)</span></span>
          <input class="ep-input" name="to_field" id="pfToField" value="${esc(cfg.to_field || '')}" dir="ltr" placeholder="to">
        </div>
        <div class="ep-field">
          <span class="ep-label">قالب JSON <span class="opt">يُرسل كما هو في Body</span></span>
          <textarea name="template" id="pfTemplate" rows="3" class="ep-input" placeholder='{"to":"{{TO}}","subject":"{{SUBJECT}}","body":"{{BODY}}"}'>${esc(cfg.template || '')}</textarea>
          <div class="ep-hint">{{TO}} البريد · {{SUBJECT}} العنوان · {{BODY}} النص · {OTP} الرمز</div>
        </div>
      </div>`;
  }
}
/* ===== تعبئة سريعة لمزودات SMTP شائعة ===== */
const SMTP_PRESETS = {
  gmail:   { host: 'smtp.gmail.com',   port: 587, encryption: 'tls', note: 'استخدم كلمة المرور التطبيقية من حساب جوجل' },
  outlook: { host: 'smtp-mail.outlook.com', port: 587, encryption: 'tls', note: 'استخدم كلمة مرور حسابك أو كلمة مرور تطبيقية' },
  yahoo:   { host: 'smtp.mail.yahoo.com', port: 465, encryption: 'ssl', note: 'استخدم كلمة مرور تطبيقية من حساب Yahoo' },
};
function fillSmtpPreset(name) {
  const p = SMTP_PRESETS[name]; if (!p) return;
  const h = document.getElementById('pfHost');
  const pt = document.getElementById('pfPort');
  const enc = document.getElementById('pfEncryption');
  if (h) h.value = p.host;
  if (pt) pt.value = p.port;
  if (enc) enc.value = p.encryption;
  toast('تمت تعبئة ' + name + ' — ' + p.note, 'ok');
  autoFillSmtpHint();
}

function autoFillSmtpHint() {
  const host = (document.getElementById('pfHost')?.value || '').trim().toLowerCase();
  const hints = {
    'smtp.gmail.com': { port: 587, encryption: 'tls' },
    'smtp-mail.outlook.com': { port: 587, encryption: 'tls' },
    'smtp.office365.com': { port: 587, encryption: 'tls' },
    'smtp.mail.yahoo.com': { port: 465, encryption: 'ssl' },
    'smtp.hostinger.com': { port: 465, encryption: 'ssl' },
  };
  const h = hints[host];
  if (h) {
    const port = document.getElementById('pfPort');
    const enc = document.getElementById('pfEncryption');
    if (!document.getElementById('pfId').value) { port.value = h.port; enc.value = h.encryption; }
  }
}
function togglePass(id, btn) {
  const el = document.getElementById(id);
  if (el.type === 'password') { el.type = 'text'; btn.textContent = '🙈'; }
  else { el.type = 'password'; btn.textContent = '👁'; }
}

/* ===== تحميل المزودات ===== */
async function loadProviders() {
  try {
    const r = await fetch(EMAIL_API, { headers: { Authorization: 'Bearer ' + epToken() } });
    if (!r.ok) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">فشل التحميل (HTTP ' + r.status + ') — تأكد من تسجيل الدخول</td></tr>'; return; }
    const j = await r.json();
    const list = j.providers || [];
    renderStats(list);
    if (list.length === 0) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">لا توجد مزودات — أضف مزودًا لبدء الإرسال التلقائي</td></tr>'; return; }
    document.getElementById('providersBody').innerHTML = list.map(p => `
      <tr>
        <td>${p.id}</td>
        <td><b>${esc(p.name)}</b><br><small style="color:var(--muted)">${esc(p.from_email || '')}${p.host ? ' · ' + esc(p.host) + ':' + esc(p.port || '') : ''}</small></td>
        <td>${p.type === 'smtp' ? 'SMTP' : 'HTTP REST'}</td>
        <td><span class="badge ${p.status === 'enabled' ? 'good' : 'bad'}">${p.status === 'enabled' ? 'مفعّل' : 'معطّل'}</span></td>
        <td>${Number(p.is_default) ? '✓' : '—'}</td>
        <td><span style="color:var(--good)">${p.success_count}</span> / <span style="color:var(--bad)">${p.failure_count}</span></td>
        <td>${p.last_used_at || '—'}</td>
        <td style="white-space:nowrap;">
          <button class="btn small" onclick='openModal("edit", ${JSON.stringify(p).replace(/'/g, "\\'")})'>تعديل</button>
          <button class="btn small" onclick="toggle(${p.id}, '${p.status === 'enabled' ? 'disabled' : 'enabled'}')">${p.status === 'enabled' ? 'تعطيل' : 'تفعيل'}</button>
          <button class="btn small" onclick="openTestModal(${p.id}, '${esc(p.name).replace(/'/g, "\\'")}')">اختبار</button>
          <button class="btn small danger" onclick="del(${p.id})">حذف</button>
        </td>
      </tr>`).join('');
  } catch (e) { document.getElementById('providersBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">خطأ اتصال</td></tr>'; }
}
function renderStats(list) {
  const total = list.length;
  const active = list.filter(p => p.status === 'enabled').length;
  const ok = list.reduce((s, p) => s + Number(p.success_count), 0);
  const fail = list.reduce((s, p) => s + Number(p.failure_count), 0);
  document.getElementById('epStats').innerHTML = `
    <div class="stat"><div class="ico" style="background:var(--surface2)">📨</div><div><b>${total}</b><small>إجمالي المزودات</small></div></div>
    <div class="stat"><div class="ico" style="background:#dcfae6">✓</div><div><b>${active}</b><small>مزود مفعّل</small></div></div>
    <div class="stat"><div class="ico" style="background:#dcfae6">📤</div><div><b>${ok}</b><small>إرسال ناجح</small></div></div>
    <div class="stat"><div class="ico" style="background:#fee4e2">✗</div><div><b>${fail}</b><small>إرسال فاشل</small></div></div>`;
}

/* ===== حفظ ===== */
async function saveProvider(e) {
  e.preventDefault();
  const id = document.getElementById('pfId').value;
  const data = {
    name: document.getElementById('pfName').value.trim(),
    type: document.getElementById('pfType').value,
    priority: parseInt(document.getElementById('pfPriority').value) || 0,
    from_email: document.getElementById('pfFromEmail').value.trim(),
    from_name: document.getElementById('pfFromName').value.trim(),
    is_default: document.getElementById('pfDefault').checked ? 1 : 0,
  };
  if (data.type === 'smtp') {
    data.host = document.getElementById('pfHost').value.trim();
    data.port = parseInt(document.getElementById('pfPort').value) || 587;
    data.encryption = document.getElementById('pfEncryption').value;
    data.username = document.getElementById('pfUsername').value.trim();
    data.password = document.getElementById('pfPassword').value;
  } else {
    data.api_base_url = document.getElementById('pfApiUrl').value.trim();
    data.api_key = document.getElementById('pfApiKey').value;
    data.extra_config = JSON.stringify({
      to_field: document.getElementById('pfToField').value.trim() || 'to',
      template: document.getElementById('pfTemplate').value.trim(),
    });
  }
  const saveBtn = document.getElementById('saveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'جارٍ الحفظ...';
  try {
    const url = id ? EMAIL_API + '/' + id : EMAIL_API;
    const method = id ? 'PUT' : 'POST';
    const r = await fetch(url, { method, headers: { Authorization: 'Bearer ' + epToken(), 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
    const j = await r.json();
    if (!j.success) { toast(j.message || 'فشل الحفظ', 'err'); saveBtn.disabled = false; saveBtn.textContent = 'حفظ'; return; }
    toast(id ? 'تم تحديث المزود بنجاح' : 'تمت إضافة المزود بنجاح', 'ok');
    closeModal();
    await loadProviders();
  } catch (err) {
    toast('خطأ اتصال — أعد المحاولة', 'err');
    saveBtn.disabled = false;
    saveBtn.textContent = 'حفظ';
  }
}

/* ===== تبديل / حذف / اختبار ===== */
async function toggle(id, status) {
  try {
    const r = await fetch(EMAIL_API + '/' + id + '/toggle', { method: 'POST', headers: { Authorization: 'Bearer ' + epToken(), 'Content-Type': 'application/json' }, body: JSON.stringify({ status }) });
    const j = await r.json();
    if (!j.success) { toast(j.message || 'فشل التغيير', 'err'); return; }
    toast(status === 'enabled' ? 'تم تفعيل المزود' : 'تم تعطيل المزود', 'ok');
    await loadProviders();
  } catch (err) { toast('خطأ اتصال', 'err'); }
}
async function del(id) {
  if (!confirm('هل أنت متأكد من حذف هذا المزود؟')) return;
  try {
    const r = await fetch(EMAIL_API + '/' + id, { method: 'DELETE', headers: { Authorization: 'Bearer ' + epToken() } });
    const j = await r.json();
    if (!j.success) { toast(j.message || 'فشل الحذف', 'err'); return; }
    toast('تم حذف المزود', 'ok');
    await loadProviders();
  } catch (err) { toast('خطأ اتصال', 'err'); }
}
async function testProvider(e) {
  e.preventDefault();
  const id = document.getElementById('testId').value;
  const email = document.getElementById('testEmail').value.trim();
  const btn = document.getElementById('testBtn');
  btn.disabled = true;
  btn.textContent = 'جارٍ الإرسال...';
  const resDiv = document.getElementById('testResult');
  try {
    const r = await fetch(EMAIL_API + '/' + id + '/test', { method: 'POST', headers: { Authorization: 'Bearer ' + epToken(), 'Content-Type': 'application/json' }, body: JSON.stringify({ email }) });
    const j = await r.json();
    resDiv.style.display = 'block';
    if (j.success) {
      resDiv.style.background = '#dcfae6';
      resDiv.style.color = '#087443';
      const ms = j.response_time_ms ? ` (${j.response_time_ms} مللي ثانية)` : '';
      resDiv.textContent = '✓ تم إرسال رسالة الاختبار بنجاح' + ms + ' — تحقق من صندوق الوارد (قد يصل إلى Spam).';
    } else {
      resDiv.style.background = '#fee4e2';
      resDiv.style.color = '#b42318';
      resDiv.textContent = '✗ فشل الاختبار: ' + (j.message || 'خطأ غير معروف');
    }
  } catch (err) {
    resDiv.style.display = 'block';
    resDiv.style.background = '#fee4e2';
    resDiv.style.color = '#b42318';
    resDiv.textContent = '✗ خطأ اتصال بالخادم';
  }
  btn.disabled = false;
  btn.textContent = 'إرسال تجريبي';
}

/* ===== تبديلات التبويبات ===== */
let svcTab = '<?= $activeTab ?>';
function svcSwitchTab(t){
  svcTab = t;
  document.getElementById('tabGeneral').classList.toggle('active', t === 'general');
  document.getElementById('tabOtp').classList.toggle('active', t === 'otp');
  document.getElementById('tabEmail').classList.toggle('active', t === 'email');
  document.getElementById('svcTabGeneral').classList.toggle('open', t === 'general');
  document.getElementById('svcTabOtp').classList.toggle('open', t === 'otp');
  document.getElementById('svcTabEmail').classList.toggle('open', t === 'email');
  const url = new URL(window.location.href);
  url.searchParams.set('tab', t);
  history.replaceState(null, '', url.pathname + url.search);
  if (t === 'otp') { if (opProviders.length === 0) op_loadProviders(); }
  if (t === 'email') { if (!document.getElementById('providersBody').innerHTML.includes('<tr><td colspan')) loadProviders(); }
}

/* تحميل أولي: تبويب البريد يحمّل موارده فورًا إذا كان هو الافتراضي،
   وبقية التبويبات عند فتحها أول مرة */
if (svcTab === 'email') {
  document.addEventListener('DOMContentLoaded', loadProviders);
} else if (svcTab === 'otp') {
  document.addEventListener('DOMContentLoaded', op_loadProviders);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
