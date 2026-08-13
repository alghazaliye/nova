<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'settings.manage');
$pageTitle = 'إعدادات النظام';
$pdo       = getAdminDB();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $editableKeys = [
        'app_name', 'maintenance_mode', 'allow_registration', 'allow_calls',
        'allow_groups', 'allow_stories', 'max_file_size_mb', 'max_image_size_mb',
        'max_video_size_mb', 'story_duration_hrs', 'otp_expiry_minutes', 'otp_required',
        'session_duration_days', 'default_language', 'default_timezone',
        'fcm_enabled', 'edit_time_limit_minutes', 'delete_time_limit_minutes', 'message_type_default',
    ];
    foreach ($editableKeys as $key) {
        if (array_key_exists($key, $_POST)) {
            $val = htmlspecialchars(strip_tags(trim($_POST[$key])), ENT_QUOTES, 'UTF-8');
            $pdo->prepare(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()'
            )->execute([$key, $val, $val]);
        }
    }
    logAudit($admin, 'SETTING_UPDATE', 'app_settings', 0, 'تحديث إعدادات النظام');
    $message = 'تم حفظ الإعدادات بنجاح';
}

$stmt = $pdo->query('SELECT setting_key, setting_value FROM app_settings');
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
function s(array $settings, string $key, string $default = ''): string {
    return htmlspecialchars($settings[$key] ?? $default);
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
  <div>
    <h2>إعدادات النظام</h2>
    <p>تخصيص خيارات المنصة والتحكم في الميزات.</p>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<form method="POST">
  <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
  
  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:18px;">
    
    <!-- General Settings -->
    <div class="card panel">
      <h3>⚙️ الإعدادات العامة</h3>
      <div class="form-group">
        <label class="form-label">اسم التطبيق</label>
        <input type="text" name="app_name" class="form-control" value="<?= s($settings, 'app_name', 'NOVA Messenger') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">وضع الصيانة</label>
        <select name="maintenance_mode" class="form-control">
          <option value="0" <?= ($settings['maintenance_mode']??'0')==='0'?'selected':'' ?>>مفعّل (يعمل)</option>
          <option value="1" <?= ($settings['maintenance_mode']??'0')==='1'?'selected':'' ?>>صيانة (متوقف)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">اللغة الافتراضية</label>
        <select name="default_language" class="form-control">
          <option value="ar" <?= ($settings['default_language']??'ar')==='ar'?'selected':'' ?>>العربية</option>
          <option value="en" <?= ($settings['default_language']??'ar')==='en'?'selected':'' ?>>English</option>
        </select>
      </div>
    </div>

    <!-- Users & Security -->
    <div class="card panel">
      <h3>👥 المستخدمون والأمان</h3>
      <div class="form-group">
        <label class="form-label">السماح بالتسجيل الجديد</label>
        <select name="allow_registration" class="form-control">
          <option value="1" <?= ($settings['allow_registration']??'1')==='1'?'selected':'' ?>>نعم</option>
          <option value="0" <?= ($settings['allow_registration']??'1')==='0'?'selected':'' ?>>لا</option>
        </select>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <div><b>التحقق برمز OTP</b><small style="display:block; color:var(--muted); font-size:11px;">عند الإيقاف يدخل المستخدم مباشرة بدون رمز</small></div>
        <select name="otp_required" class="select">
          <option value="1" <?= ($settings['otp_required']??'1')==='1'?'selected':'' ?>>مفعّل</option>
          <option value="0" <?= ($settings['otp_required']??'1')==='0'?'selected':'' ?>>موقّف (بدون رمز)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">صلاحية رمز OTP (دقائق) — تطبّق فقط عند التفعيل</label>
        <input type="number" name="otp_expiry_minutes" class="form-control" value="<?= s($settings, 'otp_expiry_minutes', '5') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">مدة الجلسة (أيام)</label>
        <input type="number" name="session_duration_days" class="form-control" value="<?= s($settings, 'session_duration_days', '30') ?>">
      </div>
    </div>

    <!-- Features -->
    <div class="card panel">
      <h3>✨ تفعيل الميزات</h3>
      <div style="display:grid; gap:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div><b>المكالمات</b><small style="display:block; color:var(--muted); font-size:11px;">تفعيل الصوت والفيديو</small></div>
          <select name="allow_calls" class="select">
            <option value="1" <?= ($settings['allow_calls']??'1')==='1'?'selected':'' ?>>نعم</option>
            <option value="0" <?= ($settings['allow_calls']??'1')==='1'?'selected':'' ?>>لا</option>
          </select>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div><b>المجموعات</b><small style="display:block; color:var(--muted); font-size:11px;">تفعيل إنشاء المجموعات</small></div>
          <select name="allow_groups" class="select">
            <option value="1" <?= ($settings['allow_groups']??'1')==='1'?'selected':'' ?>>نعم</option>
            <option value="0" <?= ($settings['allow_groups']??'1')==='1'?'selected':'' ?>>لا</option>
          </select>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div><b>الحالات</b><small style="display:block; color:var(--muted); font-size:11px;">تفعيل ميزة القصص</small></div>
          <select name="allow_stories" class="select">
            <option value="1" <?= ($settings['allow_stories']??'1')==='1'?'selected':'' ?>>نعم</option>
            <option value="0" <?= ($settings['allow_stories']??'1')==='1'?'selected':'' ?>>لا</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Media -->
    <div class="card panel">
      <h3>📁 الوسائط والتخزين</h3>
      <div class="form-group">
        <label class="form-label">أقصى حجم للملف (MB)</label>
        <input type="number" name="max_file_size_mb" class="form-control" value="<?= s($settings, 'max_file_size_mb', '50') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">مدة الحالة (ساعة)</label>
        <input type="number" name="story_duration_hrs" class="form-control" value="<?= s($settings, 'story_duration_hrs', '24') ?>">
      </div>
    </div>

    </div>

    <!-- Message Lifecycle -->
    <div class="card panel">
      <h3>💬 دورة حياة الرسائل (التعديل والحذف)</h3>
      <div class="form-group">
        <label class="form-label">مدة السماح بتعديل الرسالة (دقيقة) — 0 = بلا حد</label>
        <input type="number" min="0" name="edit_time_limit_minutes" class="form-control" value="<?= s($settings, 'edit_time_limit_minutes', '30') ?>">
        <small style="color:var(--muted)">بعد انقضاء هذه المدة لا يمكن تعديل الرسالة حتى لدى الطرفين</small>
      </div>
      <div class="form-group">
        <label class="form-label">مدة السماح بحذف الرسالة لدى الطرفين (دقيقة) — 0 = بلا حد</label>
        <input type="number" min="0" name="delete_time_limit_minutes" class="form-control" value="<?= s($settings, 'delete_time_limit_minutes', '60') ?>">
        <small style="color:var(--muted)">بعد انقضاء هذه المدة لا يمكن حذف الرسالة لدى الطرفين (فقط للحذف الشخصي)</small>
      </div>
      <div class="form-group">
        <label class="form-label">الإعداد الافتراضي لاختفاء الرسائل (لجميع المستخدمين)</label>
        <select name="message_type_default" class="form-control">
          <option value="0" <?= ($settings['message_type_default']??'0')==='0'?'selected':'' ?>>دائم (لا تختفي)</option>
          <option value="3600" <?= ($settings['message_type_default']??'0')==='3600'?'selected':'' ?>>بعد ساعة</option>
          <option value="86400" <?= ($settings['message_type_default']??'0')==='86400'?'selected':'' ?>>بعد 24 ساعة</option>
          <option value="604800" <?= ($settings['message_type_default']??'0')==='604800'?'selected':'' ?>>بعد أسبوع</option>
          <option value="-1" <?= ($settings['message_type_default']??'0')==='-1'?'selected':'' ?>>بعد القراءة</option>
        </select>
        <small style="color:var(--muted)">يطبّق تلقائيًا على المحادثات الجديدة لكل مستخدم، ويمكن للمستخدم تغييره من إعداداته</small>
      </div>
    </div>

    <!-- Notifications -->
    <div class="card panel">
      <h3>🔔 الإشعارات الفورية (FCM)</h3>
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div><b>إشعارات FCM</b><small style="display:block; color:var(--muted); font-size:11px;">إرسال إشعارات الرسائل والمكالمات عبر Firebase</small></div>
        <select name="fcm_enabled" class="select">
          <option value="1" <?= ($settings['fcm_enabled']??'1')==='1'?'selected':'' ?>>نعم</option>
          <option value="0" <?= ($settings['fcm_enabled']??'1')==='0'?'selected':'' ?>>لا</option>
        </select>
      </div>
    </div>

  </div>

  <div style="margin-top:20px; text-align:left;">
    <button type="submit" class="btn primary">💾 حفظ التغييرات</button>
  </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
