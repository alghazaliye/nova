<?php
/**
 * NOVA Messenger - Admin Controller
 * Handles admin-only endpoints: plans management, verification toggling,
 * global bans, and device registrations overview.
 */

declare(strict_types=1);

class AdminController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();

        // Only admins can call these endpoints.
        $adminSession = AuthMiddleware::authenticate();

        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.role_id FROM admins a WHERE a.id = ? AND a.is_active = 1 LIMIT 1'
        );
        $stmt->execute([(int)$adminSession['user_id']]);
        if (!$stmt->fetch()) {
            Response::forbidden('هذه العمليات متاحة للمشرفين فقط');
        }
    }

    // ============ Plans ============

    // GET /api/v1/admin/plans
    public function plansIndex(): void
    {
        $rows = $this->pdo->query(
            'SELECT id, name, description, price, currency, period, max_devices, features,
                    badge_color, is_active, created_at FROM plans ORDER BY id ASC'
        )->fetchAll();
        Response::success($rows);
    }

    // POST /api/v1/admin/plans
    public function plansCreate(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body)
            ->required('name', 'اسم الباقة')
            ->maxLength('name', 150, 'اسم الباقة')
            ->required('price', 'السعر');
        $period = (string)($body['period'] ?? 'monthly');
        if (!in_array($period, ['monthly', 'yearly', 'lifetime'], true)) {
            $v->setError('period', 'الفترة يجب أن تكون monthly أو yearly أو lifetime');
        }
        if ($v->fails()) {
            Response::validationError($v->errors());
        }
        $price = is_numeric($body['price'] ?? null) ? (float)$body['price'] : null;
        if ($price === null) {
            Response::validationError(['price' => 'قيمة السعر غير صحيحة']);
        }
        $maxDevices = isset($body['max_devices']) && is_numeric($body['max_devices']) && (int)$body['max_devices'] >= 1
            ? (int)$body['max_devices'] : 1;

        $features = !empty($body['features']) ? json_encode((array)$body['features'], JSON_UNESCAPED_UNICODE) : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO plans (name, description, price, currency, period, max_devices, features, badge_color, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($v->sanitizeString('name')),
            isset($body['description']) ? trim($v->sanitizeString('description')) : null,
            $price,
            strtoupper((string)($body['currency'] ?? 'SAR')),
            $period,
            $maxDevices,
            $features,
            (string)($body['badge_color'] ?? 'blue'),
            (int)($body['is_active'] ?? 1),
        ]);

        Response::success(['id' => (int)$this->pdo->lastInsertId()], 'تم إنشاء الباقة بنجاح');
    }

    // PUT /api/v1/admin/plans/{id}
    public function plansUpdate(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $stmt = $this->pdo->prepare('SELECT id FROM plans WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::notFound('الباقة غير موجودة');
        }

        $set = [];
        $params = [];
        foreach (['name' => 'name', 'description' => 'description', 'price' => 'price',
                  'currency' => 'currency', 'period' => 'period', 'max_devices' => 'max_devices',
                  'features' => 'features', 'badge_color' => 'badge_color'] as $field => $key) {
            if (array_key_exists($key, $body)) {
                $val = $body[$key];
                if ($key === 'features' && is_array($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                $set[] = "{$field} = ?";
                $params[] = $val;
            }
        }
        if (array_key_exists('is_active', $body)) {
            $set[] = 'is_active = ?';
            $params[] = (int)$body['is_active'];
        }

        if ($set) {
            $params[] = $id;
            $this->pdo->prepare('UPDATE plans SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
        }

        Response::success(null, 'تم تحديث الباقة بنجاح');
    }

    // DELETE /api/v1/admin/plans/{id}
    public function plansDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM plans WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::notFound('الباقة غير موجودة');
        }
        $this->pdo->prepare('DELETE FROM plans WHERE id = ?')->execute([$id]);
        // Detach subscriptions so FK does not block deletion
        $this->pdo->prepare("UPDATE user_subscriptions SET plan_id = NULL WHERE plan_id = ?")->execute([$id]);
        Response::success(null, 'تم حذف الباقة بنجاح');
    }

    // ============ Verification ============

    // POST /api/v1/admin/users/{id}/verify
    public function verifyUser(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $isVerified = isset($body['is_verified']) ? (int)(bool)$body['is_verified'] : 1;

        $stmt = $this->pdo->prepare('SELECT id, name, is_verified FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }
        if ((int)$user['is_verified'] === $isVerified) {
            Response::success(null, 'لا تغيير مطلوب');
        }

        $this->pdo->prepare('UPDATE users SET is_verified = ?, updated_at = NOW() WHERE id = ?')
             ->execute([$isVerified, $id]);

        Response::success(
            ['is_verified' => $isVerified],
            $isVerified ? 'تم توثيق الحساب وإظهار العلامة الزرقاء' : 'تم إلغاء توثيق الحساب'
        );
    }

    // ============ Global Ban ============

    // POST /api/v1/admin/users/{id}/ban
    public function banUser(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = isset($body['reason']) ? trim((string)$body['reason']) : '';

        $stmt = $this->pdo->prepare('SELECT id, name, is_blocked FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }
        if ((int)$user['is_blocked']) {
            Response::success(null, 'المستخدم محظور بالفعل');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_bans (user_id, reason, banned_by) VALUES (?, ?, ?)'
        );
        $stmt->execute([$id, $reason ?: null, $this->adminId()]);

        // Soft-delete every live session so the blocked user is kicked out immediately
        $this->pdo->prepare(
            'UPDATE sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL'
        )->execute([$id]);

        // Revoke all device registrations so the quota must be re-validated on unban
        $this->pdo->prepare('UPDATE device_registrations SET is_active = 0 WHERE user_id = ?')->execute([$id]);

        $this->pdo->prepare('UPDATE users SET is_blocked = 1, blocked_at = NOW() WHERE id = ?')->execute([$id]);

        Response::success(null, 'تم حظر المستخدم ومنع الدخول للتطبيق');
    }

    // POST /api/v1/admin/users/{id}/unban
    public function unbanUser(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id, name, is_blocked FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }
        if (!(int)$user['is_blocked']) {
            Response::success(null, 'المستخدم غير محظور');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE user_bans SET unbanned_at = NOW(), unbanned_by = ?
             WHERE user_id = ? AND unbanned_at IS NULL'
        );
        $stmt->execute([$this->adminId(), $id]);

        $this->pdo->prepare('UPDATE users SET is_blocked = 0, blocked_at = NULL WHERE id = ?')->execute([$id]);

        Response::success(null, 'تم فك الحظر عن المستخدم');
    }

    // ============ Subscriptions ============

    // POST /api/v1/admin/users/{id}/subscribe
    public function subscribeUser(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $planId = (int)($body['plan_id'] ?? 0);
        $durationDays = isset($body['duration_days']) ? (int)$body['duration_days'] : null;

        $stmt = $this->pdo->prepare('SELECT id FROM plans WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$planId]);
        if (!$stmt->fetch()) {
            Response::notFound('الباقة غير موجودة أو غير مفعلة');
        }

        $stmt = $this->pdo->prepare('SELECT id, name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::notFound('المستخدم غير موجود');
        }

        $planStmt = $this->pdo->prepare('SELECT period FROM plans WHERE id = ? LIMIT 1');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();

        if ($durationDays === null) {
            $durationDays = match ($plan['period'] ?? 'monthly') {
                'yearly'   => 365,
                'lifetime' => null,
                default     => 30,
            };
        }

        $this->pdo->prepare(
            'INSERT INTO user_subscriptions (user_id, plan_id, status, activated_by, ends_at)
             VALUES (?, ?, "active", ?, ?)
             ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), status = "active",
             activated_by = VALUES(activated_by), ends_at = VALUES(ends_at)'
        )->execute([$id, $planId, $this->adminId(), $durationDays !== null ? date('Y-m-d H:i:s', strtotime("+{$durationDays} days")) : null]);

        // Verification badge follows the plan
        $this->pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$id]);

        Response::success(null, 'تم تفعيل الاشتراك وعلامة التحقق الزرقاء');
    }

    // POST /api/v1/admin/subscriptions/{id}/cancel
    public function cancelSubscription(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, status FROM user_subscriptions WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $sub = $stmt->fetch();
        if (!$sub) {
            Response::notFound('الاشتراك غير موجود');
        }

        $this->pdo->prepare('UPDATE user_subscriptions SET status = "cancelled" WHERE id = ?')->execute([$id]);

        // If no other active subscription remains, remove the verification badge
        $activeCount = (int)$this->pdo->prepare(
            'SELECT COUNT(*) FROM user_subscriptions WHERE user_id = ? AND status = "active"'
        )->executeQuery([$sub['user_id']])->fetchColumn();

        if ($activeCount === 0) {
            $this->pdo->prepare('UPDATE users SET is_verified = 0 WHERE id = ?')->execute([$sub['user_id']]);
        }

        Response::success(null, 'تم إلغاء الاشتراك');
    }

    // ============ Devices ============

    // GET /api/v1/admin/devices
    public function devicesIndex(): void
    {
        $search = (string)($_GET['q'] ?? '');
        $where  = '1=1';
        $params = [];
        if ($search !== '') {
            $where  .= ' AND (u.name LIKE ? OR u.phone LIKE ? OR dr.device_model LIKE ? OR dr.device_fingerprint LIKE ?)';
            $s       = "%{$search}%";
            $params  = array_fill(0, 4, $s);
        }
        $stmt = $this->pdo->prepare(
            "SELECT dr.id, dr.user_id, dr.device_fingerprint, dr.device_model, dr.os_name, dr.os_version,
                    dr.app_version, dr.platform, dr.barcode_hash, dr.is_active, dr.first_seen, dr.last_seen,
                    u.name user_name, u.phone user_phone
             FROM device_registrations dr
             JOIN users u ON u.id = dr.user_id
             WHERE {$where} ORDER BY dr.id DESC LIMIT 300"
        );
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    // DELETE /api/v1/admin/devices/{id}
    public function deviceDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM device_registrations WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::notFound('الجهاز غير موجود');
        }
        $this->pdo->prepare('DELETE FROM device_registrations WHERE id = ?')->execute([$id]);
        Response::success(null, 'تم حذف الجهاز');
    }

    // POST /api/v1/admin/users/{id}/deactivate-device/{deviceId}
    public function deactivateDevice(int $id, int $deviceId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE device_registrations SET is_active = 0 WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$deviceId, $id]);
        if ($stmt->rowCount() === 0) {
            Response::notFound('الجهاز غير موجود لهذا المستخدم');
        }
        Response::success(null, 'تم إيقاف الجهاز');
    }

    // ============ User detail (admin view) ============

    // GET /api/v1/admin/users/{id}/admin
    public function userAdmin(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.uuid, u.phone, u.email, u.name, u.username, u.bio, u.avatar, u.status_text,
                    u.is_online, u.last_seen, u.is_verified, u.is_blocked, u.blocked_at, u.created_at
             FROM users u WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }

        // Active ban info
        $banStmt = $this->pdo->prepare(
            'SELECT id, reason, banned_at, unbanned_at FROM user_bans WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $banStmt->execute([$id]);
        $user['latest_ban'] = $banStmt->fetch() ?: null;

        // Active subscriptions
        $subStmt = $this->pdo->prepare(
            'SELECT us.id, us.plan_id, us.status, us.started_at, us.ends_at,
                    p.name plan_name, p.price, p.currency, p.period, p.max_devices, p.features
             FROM user_subscriptions us
             LEFT JOIN plans p ON p.id = us.plan_id
             WHERE us.user_id = ? ORDER BY us.id DESC'
        );
        $subStmt->execute([$id]);
        $user['subscriptions'] = $subStmt->fetchAll();

        // Devices
        $devStmt = $this->pdo->prepare(
            'SELECT id, device_fingerprint, device_model, os_name, os_version, app_version,
                    platform, barcode_hash, is_active, first_seen, last_seen
             FROM device_registrations WHERE user_id = ? ORDER BY id DESC'
        );
        $devStmt->execute([$id]);
        $user['devices'] = $devStmt->fetchAll();

        $maxDevices = 1;
        foreach ($user['subscriptions'] as $s) {
            if ($s['status'] === 'active' && (int)$s['max_devices'] > $maxDevices) {
                $maxDevices = (int)$s['max_devices'];
            }
        }
        $user['max_devices_allowed'] = $maxDevices;

        Response::success($user);
    }

    // Private helpers
    private function adminId(): int
    {
        $session = AuthMiddleware::authenticate();
        $stmt = $this->pdo->prepare('SELECT id FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([(int)$session['user_id']]);
        $admin = $stmt->fetch();
        return (int)($admin['id'] ?? 0);
    }
}
