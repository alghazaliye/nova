<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'plans.view');
$pageTitle = 'إدارة الباقات والاشتراكات';
$pdo       = getAdminDB();

$message = '';
$error   = '';

// Ensure tables exist and have badge_color column
try {
    $pdo->exec("ALTER TABLE plans ADD COLUMN badge_color TEXT DEFAULT 'blue'");
} catch (\Throwable $e) {}

// ---- Form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && hasPermission($admin, 'plans.manage')) {
        $name = trim((string)($_POST['plan_name'] ?? ''));
        $planType = in_array($_POST['plan_type'] ?? '', ['free', 'verification', 'premium', 'pro', 'custom'], true) ? $_POST['plan_type'] : 'premium';
        $enableVerification = !empty($_POST['enable_verification']) ? 1 : 0;
        $verificationDays = max(1, (int)($_POST['verification_duration_days'] ?? 30));
        $price = is_numeric($_POST['plan_price'] ?? null) ? (float)$_POST['plan_price'] : 0.0;
        $period = in_array($_POST['plan_period'] ?? '', ['monthly', 'yearly', 'lifetime'], true) ? $_POST['plan_period'] : 'monthly';
        $maxDevices = max(1, (int)($_POST['plan_max_devices'] ?? 1));
        $currency = strtoupper(trim((string)($_POST['plan_currency'] ?? 'SAR')));
        $color = in_array($_POST['badge_color'] ?? '', ['blue', 'gold', 'platinum', 'green', 'gray'], true) ? $_POST['badge_color'] : 'blue';
        $features = array_filter(array_map('trim', explode("\n", (string)($_POST['plan_features'] ?? ''))));
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO plans (name, description, price, currency, period, max_devices, features, badge_color, is_active,
                 plan_type, enable_verification, verification_duration_days)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
            );
            $stmt->execute([$name, trim((string)($_POST['plan_description'] ?? '')), $price, $currency, $period, $maxDevices, $features ? json_encode($features, JSON_UNESCAPED_UNICODE) : null, $color,
                            $planType, $enableVerification, $verificationDays]);
            logAudit($admin, 'PLAN_CREATE', 'plan', (int)$pdo->lastInsertId(), "إنشاء باقة: {$name}");
            $message = 'تم إنشاء الباقة بنجاح';
        } catch (\Throwable $e) { $error = 'خطأ: ' . $e->getMessage(); }
    }

    if ($action === 'edit' && hasPermission($admin, 'plans.manage')) {
        $id = (int)$_POST['plan_id'];
        $name = trim((string)($_POST['plan_name'] ?? ''));
        $enableVerification = !empty($_POST['enable_verification']) ? 1 : 0;
        $verificationDays = max(1, (int)($_POST['verification_duration_days'] ?? 30));
        $price = is_numeric($_POST['plan_price'] ?? null) ? (float)$_POST['plan_price'] : 0.0;
        $color = in_array($_POST['badge_color'] ?? '', ['blue', 'gold', 'platinum', 'green', 'gray'], true) ? $_POST['badge_color'] : 'blue';
        
        try {
            $stmt = $pdo->prepare(
                'UPDATE plans SET name=?, price=?, badge_color=?, enable_verification=?, verification_duration_days=? WHERE id=?'
            );
            $stmt->execute([$name, $price, $color, $enableVerification, $verificationDays, $id]);
            logAudit($admin, 'PLAN_EDIT', 'plan', $id, "تعديل باقة: {$name}");
            $message = 'تم تحديث الباقة بنجاح';
        } catch (\Throwable $e) { $error = 'خطأ: ' . $e->getMessage(); }
    }

    if ($action === 'toggle' && hasPermission($admin, 'plans.manage')) {
        $planId = (int)$_POST['plan_id'];
        $pdo->prepare('UPDATE plans SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$planId]);
        logAudit($admin, 'PLAN_TOGGLE', 'plan', $planId, "تبديل حالة الباقة #{$planId}");
        $message = 'تم تغيير حالة الباقة';
    }

    if ($action === 'delete' && hasPermission($admin, 'plans.manage')) {
        $planId = (int)$_POST['plan_id'];
        $pdo->prepare("UPDATE user_subscriptions SET plan_id = NULL WHERE plan_id = ?")->execute([$planId]);
        $pdo->prepare('DELETE FROM plans WHERE id = ?')->execute([$planId]);
        logAudit($admin, 'PLAN_DELETE', 'plan', $planId, "حذف باقة #{$planId}");
        $message = 'تم حذف الباقة';
    }

    // ---- Subscription actions ----
    if ($action === 'subscribe' && !empty($_POST['sub_user_id']) && !empty($_POST['sub_plan_id'])) {
        $userId = (int)$_POST['sub_user_id'];
        $planId = (int)$_POST['sub_plan_id'];
        $days = (int)$_POST['sub_days'];
        $ends = $days > 0 ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null;
        $stmt = $pdo->prepare(
            "INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, expires_at)
             VALUES (?, ?, 'active', datetime('now'), ?)"
        );
        $stmt->execute([$userId, $planId, $ends]);
        
        // تحديث حالة التوثيق بناءً على الباقة والمدة
        $plan = $pdo->prepare("SELECT enable_verification, verification_duration_days FROM plans WHERE id = ?");
        $plan->execute([$planId]);
        $planData = $plan->fetch();
        
        if ($planData && (int)$planData['enable_verification']) {
            $vEnds = $ends; // تنتهي مع الاشتراك
            if ((int)$planData['verification_duration_days'] > 0) {
                $vEnds = date('Y-m-d H:i:s', strtotime("+{$planData['verification_duration_days']} days"));
            }
            $pdo->prepare('UPDATE users SET is_verified = 1, verified_until = ? WHERE id = ?')->execute([$vEnds, $userId]);
        } else {
            $pdo->prepare('UPDATE users SET is_verified = 0, verified_until = NULL WHERE id = ?')->execute([$userId]);
        }
        
        logAudit($admin, 'SUBSCRIPTION_ACTIVATE', 'user', $userId, "تفعيل اشتراك للمستخدم #{$userId} على الباقة #{$planId}");
        $message = 'تم تفعيل الاشتراك وعلامة التحقق';
    }

    if ($action === 'cancel' && !empty($_POST['sub_id'])) {
        $subId = (int)$_POST['sub_id'];
        $stmt = $pdo->prepare('SELECT user_id FROM user_subscriptions WHERE id = ? LIMIT 1');
        $stmt->execute([$subId]);
        $row = $stmt->fetch();
        $pdo->prepare('UPDATE user_subscriptions SET status = "cancelled" WHERE id = ?')->execute([$subId]);
        if ($row) {
            $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE user_id = ? AND status = "active"');
            $cntStmt->execute([$row['user_id']]);
            $count = (int)$cntStmt->fetchColumn();
            if ($count === 0) {
                $pdo->prepare('UPDATE users SET is_verified = 0, verified_until = NULL WHERE id = ?')->execute([$row['user_id']]);
            }
        }
        logAudit($admin, 'SUBSCRIPTION_CANCEL', 'subscription', $subId, "إلغاء اشتراك #{$subId}");
        $message = 'تم إلغاء الاشتراك';
    }
}

// Expire overdue
try {
    $pdo->exec("UPDATE user_subscriptions SET status = 'expired' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < datetime('now')");
} catch (\Throwable $e) {}

$plans = $pdo->query('SELECT * FROM plans ORDER BY id ASC')->fetchAll();
$users = $pdo->query('SELECT id, name, phone, is_verified FROM users ORDER BY created_at DESC LIMIT 500')->fetchAll();

$subs = $pdo->query(
    'SELECT us.id, us.status, us.starts_at, us.expires_at, us.plan_id,
            u.id user_id, u.name, u.phone, u.is_verified,
            p.name plan_name, p.price, p.currency, p.period, p.max_devices
     FROM user_subscriptions us
     JOIN users u ON u.id = us.user_id
     LEFT JOIN plans p ON p.id = us.plan_id
     ORDER BY us.id DESC LIMIT 200'
)->fetchAll();

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>إدارة الباقات والاشتراكات</h2><p>إنشاء وعرض الباقات، وتفعيل الاشتراكات مع التوثيق الملون.</p></div></div>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="ico">💳</div><div><b><?= count($plans) ?></b><small>إجمالي الباقات</small></div></div>
  <div class="stat"><div class="ico">✓</div><div><b><?= (int)array_sum(array_column($plans, 'is_active')) ?></b><small>باقات مفعلة</small></div></div>
  <div class="stat"><div class="ico">👥</div><div><b><?= count($subs) ?></b><small>إجمالي الاشتراكات</small></div></div>
  <div class="stat"><div class="ico">⚡</div><div><b><?= (int)array_sum(array_map(fn($s) => $s['status'] === 'active' ? 1 : 0, $subs)) ?></b><small>اشتراكات نشطة</small></div></div>
</div>

<div class="grid2">
  <div>
    <div class="card panel" style="margin-bottom:18px"><h3>➕ إضافة باقة جديدة</h3>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div><label class="form-label">اسم الباقة *</label><input class="form-control" name="plan_name" required maxlength="150" placeholder="مثال: الباقة الذهبية"></div>
          <div><label class="form-label">السعر *</label><input class="form-control" type="number" step="0.01" min="0" name="plan_price" required value="0"></div>
          <div><label class="form-label">لون شارة التوثيق</label>
            <select class="form-control" name="badge_color">
              <option value="blue">أزرق</option><option value="gold">ذهبي</option><option value="platinum">بلاتيني</option><option value="green">أخضر</option><option value="gray">رمادي</option>
            </select></div>
          <div><label class="form-label">نوع الباقة</label>
            <select class="form-control" name="plan_type">
              <option value="verification">توثيق</option><option value="premium">مميزة</option><option value="pro">احترافية</option><option value="free">مجانية</option><option value="custom">مخصصة</option>
            </select></div>
          <div style="display:flex;align-items:flex-end;gap:8px">
            <div style="flex:1"><label class="form-label">مدة التوثيق (يوم)</label><input class="form-control" type="number" min="1" name="verification_duration_days" value="30"></div>
            <div style="flex:1;padding-bottom:0"><label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;height:42px;padding-top:0"><input type="checkbox" name="enable_verification" value="1" checked style="width:18px;height:18px"> تفعيل التوثيق</label></div>
          </div>
          <div><label class="form-label">حد الأجهزة</label><input class="form-control" type="number" min="1" name="plan_max_devices" required value="1"></div>
        </div>
        <button class="btn primary" type="submit" style="margin-top:14px">✓ إنشاء الباقة</button>
      </form>
    </div>

    <div class="card panel tablewrap"><h3>📦 الباقةات الحالية</h3>
      <table class="table"><thead><tr><th>الباقة</th><th>اللون</th><th>السعر</th><th>التوثيق</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td><b><?= htmlspecialchars((string)$p['name']) ?></b></td>
        <td><span style="display:inline-block;width:15px;height:15px;border-radius:50%;background:<?= $p['badge_color'] ?? 'blue' ?>;vertical-align:middle;margin-left:5px"></span> <?= ['blue'=>'أزرق','gold'=>'ذهبي','platinum'=>'بلاتيني','green'=>'أخضر','gray'=>'رمادي'][$p['badge_color'] ?? 'blue'] ?></td>
        <td><?= number_format((float)$p['price'], 2) ?> <?= htmlspecialchars((string)$p['currency']) ?></td>
        <td><?= !empty($p['enable_verification']) ? '✓ ' . (int)$p['verification_duration_days'] . ' يوم' : '—' ?></td>
        <td><span style="background:<?= $p['is_active'] ? 'rgba(34,197,94,.1);color:#16a34a' : 'rgba(239,68,68,.1);color:#dc2626' ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800"><?= $p['is_active'] ? 'مفعلة' : 'معطلة' ?></span></td>
        <td><div style="display:flex;gap:5px">
          <button class="btn sm" onclick='editPlan(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️</button>
          <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn sm" type="submit"><?= $p['is_active'] ? '⏸' : '▶' ?></button></form>
          <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn danger sm" type="submit" data-confirm="حذف الباقة؟">🗑</button></form>
        </div></td>
      </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>

  <div>
    <div class="card panel"><h3>👥 تفعيل اشتراك</h3>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="subscribe">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div><label class="form-label">المستخدم</label>
            <select class="form-control" name="sub_user_id" required><option value="">اختر...</option>
              <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars((string)$u['name']) ?> (<?= htmlspecialchars((string)$u['phone']) ?>)</option><?php endforeach; ?>
            </select></div>
          <div><label class="form-label">الباقة</label>
            <select class="form-control" name="sub_plan_id" required>
              <?php foreach ($plans as $p): if ((int)$p['is_active']): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['name']) ?> (<?= $p['badge_color'] ?>)</option><?php endif; endforeach; ?>
            </select></div>
          <div><label class="form-label">المدة (يوم)</label><input class="form-control" type="number" name="sub_days" value="30" required></div>
          <button class="btn primary" type="submit">✓ تفعيل الآن</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div class="card panel" style="width:90%;max-width:500px">
    <h3>✏️ تعديل الباقة</h3>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="plan_id" id="edit_plan_id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div style="grid-column:span 2"><label class="form-label">اسم الباقة</label><input class="form-control" name="plan_name" id="edit_plan_name" required></div>
        <div><label class="form-label">السعر</label><input class="form-control" type="number" step="0.01" name="plan_price" id="edit_plan_price" required></div>
        <div><label class="form-label">لون الشارة</label>
          <select class="form-control" name="badge_color" id="edit_badge_color">
            <option value="blue">أزرق</option><option value="gold">ذهبي</option><option value="platinum">بلاتيني</option><option value="green">أخضر</option><option value="gray">رمادي</option>
          </select></div>
        <div><label class="form-label">مدة التوثيق</label><input class="form-control" type="number" name="verification_duration_days" id="edit_verification_days"></div>
        <div style="display:flex;align-items:center;padding-top:25px"><label class="form-label" style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="enable_verification" id="edit_enable_verification" value="1"> تفعيل التوثيق</label></div>
      </div>
      <div style="margin-top:20px;display:flex;gap:10px">
        <button class="btn primary" type="submit">حفظ التعديلات</button>
        <button class="btn" type="button" onclick="document.getElementById('editModal').style.display='none'">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<script>
function editPlan(p) {
    document.getElementById('edit_plan_id').value = p.id;
    document.getElementById('edit_plan_name').value = p.name;
    document.getElementById('edit_plan_price').value = p.price;
    document.getElementById('edit_badge_color').value = p.badge_color || 'blue';
    document.getElementById('edit_verification_days').value = p.verification_duration_days;
    document.getElementById('edit_enable_verification').checked = parseInt(p.enable_verification) === 1;
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
