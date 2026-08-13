<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin     = requireAdminLogin();
requirePermission($admin, 'users.manage');
$pageTitle = 'إدارة الباقات والاشتراكات';
$pdo       = getAdminDB();

$message = '';
$error   = '';

// Ensure tables exist
try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS plans (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(150) NOT NULL, description VARCHAR(500) NULL,
          price DECIMAL(10,2) NOT NULL DEFAULT 0, currency VARCHAR(10) NOT NULL DEFAULT "SAR",
          period ENUM("monthly","yearly","lifetime") NOT NULL DEFAULT "monthly",
          max_devices INT UNSIGNED NOT NULL DEFAULT 1, features JSON NULL,
          badge_color VARCHAR(20) NULL DEFAULT "blue", is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_subscriptions (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id BIGINT UNSIGNED NOT NULL, plan_id INT UNSIGNED NULL,
          status ENUM("active","expired","cancelled") NOT NULL DEFAULT "active",
          activated_by INT UNSIGNED NULL, started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          ends_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_user_active (user_id, plan_id), KEY idx_user (user_id))'
    );
} catch (\Throwable $e) {}

// ---- Form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && hasPermission($admin, 'users.manage')) {
        $name = trim((string)($_POST['plan_name'] ?? ''));
        $price = is_numeric($_POST['plan_price'] ?? null) ? (float)$_POST['plan_price'] : 0.0;
        $period = in_array($_POST['plan_period'] ?? '', ['monthly', 'yearly', 'lifetime'], true) ? $_POST['plan_period'] : 'monthly';
        $maxDevices = max(1, (int)($_POST['plan_max_devices'] ?? 1));
        $currency = strtoupper(trim((string)($_POST['plan_currency'] ?? 'SAR')));
        $color = in_array($_POST['badge_color'] ?? '', ['blue', 'gold', 'platinum', 'gray', 'green'], true) ? $_POST['badge_color'] : 'blue';
        $features = array_filter(array_map('trim', explode("\n", (string)($_POST['plan_features'] ?? ''))));
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO plans (name, description, price, currency, period, max_devices, features, badge_color, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([$name, trim((string)($_POST['plan_description'] ?? '')), $price, $currency, $period, $maxDevices, $features ? json_encode($features, JSON_UNESCAPED_UNICODE) : null, $color]);
            logAudit($admin, 'PLAN_CREATE', 'plan', (int)$pdo->lastInsertId(), "إنشاء باقة: {$name}");
            $message = 'تم إنشاء الباقة بنجاح';
        } catch (\Throwable $e) { $error = 'خطأ: ' . $e->getMessage(); }
    }

    if ($action === 'toggle' && hasPermission($admin, 'users.manage')) {
        $planId = (int)$_POST['plan_id'];
        $pdo->prepare('UPDATE plans SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$planId]);
        logAudit($admin, 'PLAN_TOGGLE', 'plan', $planId, "تبديل حالة الباقة #{$planId}");
        $message = 'تم تغيير حالة الباقة';
    }

    if ($action === 'delete' && hasPermission($admin, 'users.manage')) {
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
            'INSERT INTO user_subscriptions (user_id, plan_id, status, activated_by, ends_at)
             VALUES (?, ?, "active", ?, ?)
             ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), status = "active",
             activated_by = VALUES(activated_by), ends_at = VALUES(ends_at)'
        );
        $stmt->execute([$userId, $planId, $admin['id'], $ends]);
        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$userId]);
        logAudit($admin, 'SUBSCRIPTION_ACTIVATE', 'user', $userId, "تفعيل اشتراك للمستخدم #{$userId} على الباقة #{$planId}");
        $message = 'تم تفعيل الاشتراك وعلامة التحقق الزرقاء';
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
                $pdo->prepare('UPDATE users SET is_verified = 0 WHERE id = ?')->execute([$row['user_id']]);
            }
        }
        logAudit($admin, 'SUBSCRIPTION_CANCEL', 'subscription', $subId, "إلغاء اشتراك #{$subId}");
        $message = 'تم إلغاء الاشتراك';
    }
}

// Expire overdue
try {
    $pdo->exec("UPDATE user_subscriptions SET status = 'expired' WHERE status = 'active' AND ends_at IS NOT NULL AND ends_at < NOW()");
} catch (\Throwable $e) {}

$plans = $pdo->query('SELECT * FROM plans ORDER BY id ASC')->fetchAll();
$users = $pdo->query('SELECT id, name, phone, is_verified FROM users ORDER BY created_at DESC LIMIT 500')->fetchAll();

$subs = $pdo->query(
    'SELECT us.id, us.status, us.started_at, us.ends_at, us.plan_id,
            u.id user_id, u.name, u.phone, u.is_verified,
            p.name plan_name, p.price, p.currency, p.period, p.max_devices
     FROM user_subscriptions us
     JOIN users u ON u.id = us.user_id
     LEFT JOIN plans p ON p.id = us.plan_id
     ORDER BY us.id DESC LIMIT 200'
)->fetchAll();

include __DIR__ . '/includes/header.php'; include __DIR__ . '/includes/sidebar.php';
?>
<div class="pagehead"><div><h2>إدارة الباقات والاشتراكات</h2><p>إنشاء وعرض الباقات، وتفعيل الاشتراكات مع التوثيق وحد الأجهزة للباركود.</p></div></div>
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
          <div><label class="form-label">الوصف</label><input class="form-control" name="plan_description" maxlength="500" placeholder="وصف مختصر"></div>
          <div><label class="form-label">السعر *</label><input class="form-control" type="number" step="0.01" min="0" name="plan_price" required value="0"></div>
          <div><label class="form-label">العملة</label><input class="form-control" name="plan_currency" value="SAR" maxlength="10"></div>
          <div><label class="form-label">الفترة</label>
            <select class="form-control" name="plan_period">
              <option value="monthly">شهري</option><option value="yearly">سنوي</option><option value="lifetime">مدى الحياة</option>
            </select></div>
          <div><label class="form-label">حد الأجهزة (للباركود) *</label><input class="form-control" type="number" min="1" name="plan_max_devices" required value="1"></div>
          <div><label class="form-label">لون شارة التوثيق</label>
            <select class="form-control" name="badge_color">
              <option value="blue">أزرق</option><option value="gold">ذهبي</option><option value="platinum">بلاتيني</option><option value="green">أخضر</option><option value="gray">رمادي</option>
            </select></div>
          <div><label class="form-label">المميزات (سطر لكل ميزة)</label><textarea class="form-control" name="plan_features" rows="2" style="height:auto;padding:10px" placeholder="شارة توثيق زرقاء&#10;حتى 3 أجهزة&#10;بدون إعلانات"></textarea></div>
        </div>
        <button class="btn primary" type="submit" style="margin-top:14px">✓ إنشاء الباقة</button>
      </form>
    </div>

    <div class="card panel tablewrap"><h3>📦 الباقات الحالية</h3>
      <table class="table"><thead><tr><th>الباقة</th><th>السعر</th><th>الفترة</th><th>حد الأجهزة</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td><b><?= htmlspecialchars((string)$p['name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)($p['description'] ?? '')) ?></small></td>
        <td><?= number_format((float)$p['price'], 2) ?> <?= htmlspecialchars((string)$p['currency']) ?></td>
        <td><?= ['monthly'=>'شهري','yearly'=>'سنوي','lifetime'=>'مدى الحياة'][$p['period']] ?? $p['period'] ?></td>
        <td><b><?= (int)$p['max_devices'] ?></b> جهاز</td>
        <td><span style="background:<?= $p['is_active'] ? 'rgba(34,197,94,.1);color:#16a34a' : 'rgba(239,68,68,.1);color:#dc2626' ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800"><?= $p['is_active'] ? 'مفعلة' : 'معطلة' ?></span></td>
        <td><div style="display:flex;gap:5px">
          <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn sm" type="submit"><?= $p['is_active'] ? '⏸ تعطيل' : '▶ تفعيل' ?></button></form>
          <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn danger sm" type="submit" data-confirm="حذف الباقة نهائيًا؟">🗑</button></form>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$plans): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">لا توجد باقات — أضف أول باقة من الأعلى</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>

  <div>
    <div class="card panel" style="margin-bottom:18px"><h3>👥 تفعيل اشتراك لمستخدم</h3>
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
              <?php foreach ($plans as $p): if ((int)$p['is_active']): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['name']) ?> — <?= number_format((float)$p['price'],2) ?> <?= htmlspecialchars((string)$p['currency']) ?> (<?= (int)$p['max_devices'] ?> أجهزة)</option><?php endif; endforeach; ?>
            </select></div>
          <div><label class="form-label">المدة بالأيام (0 = حتى الإلغاء يدويًا)</label><input class="form-control" type="number" min="0" name="sub_days" value="30"></div>
          <button class="btn primary" type="submit">✓ تفعيل الاشتراك + التوثيق</button>
        </div>
      </form>
    </div>

    <div class="card panel tablewrap"><h3>📋 الاشتراكات</h3>
      <table class="table" style="min-width:400px"><thead><tr><th>المستخدم</th><th>الباقة</th><th>ينتهي في</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>
      <?php foreach ($subs as $s): ?>
      <tr>
        <td><b><?= htmlspecialchars((string)$s['name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars((string)$s['phone']) ?></small></td>
        <td><?= $s['plan_name'] ? htmlspecialchars((string)$s['plan_name']) : '<span style="color:var(--muted)">—</span>' ?></td>
        <td><?= $s['ends_at'] ? date('d/m/Y', strtotime((string)$s['ends_at'])) : '<span style="color:var(--muted)">حتى الإلغاء</span>' ?></td>
        <td><span style="background:<?= ['active'=>'rgba(34,197,94,.1);color:#16a34a','expired'=>'rgba(239,68,68,.1);color:#dc2626','cancelled'=>'rgba(245,158,11,.1);color:#d97706'][$s['status']] ?? 'var(--surface2)' ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800"><?= ['active'=>'نشط','expired'=>'منتهي','cancelled'=>'ملغي'][$s['status']] ?? $s['status'] ?></span></td>
        <td><?php if ($s['status'] === 'active'): ?>
          <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="sub_id" value="<?= (int)$s['id'] ?>"><button class="btn danger sm" type="submit" data-confirm="إلغاء الاشتراك؟">إلغاء</button></form>
          <?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$subs): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px">لا توجد اشتراكات</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
