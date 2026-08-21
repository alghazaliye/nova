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
    private int $standaloneAdminId = 0;

    public function __construct()
    {
        $this->pdo = Database::getInstance();

        // Only admins can call these endpoints.
        // Support both session-bound user JWTs (via AuthMiddleware) and
        // standalone admin JWTs (role=admin in payload, used by the admin panel).
        $authHeader = nova_get_auth_header() ?? '';
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        $payload = $token !== '' ? JwtHelper::verify($token) : null;
        $isStandaloneAdminJwt = $payload !== null
            && isset($payload['role']) && $payload['role'] === 'admin'
            && isset($payload['exp']) && (int)$payload['exp'] > time();
        if ($isStandaloneAdminJwt) {
            $adminId = (int)($payload['user_id'] ?? 0);
            $this->standaloneAdminId = $adminId;
        } else {
            $adminSession = AuthMiddleware::authenticate();
            $adminId = (int)$adminSession['user_id'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.role_id FROM admins a WHERE a.id = ? AND a.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$adminId]);
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
                    badge_color, is_active, plan_type, enable_verification,
                    verification_duration_days, created_at FROM plans ORDER BY id ASC'
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
        $planType = in_array((string)($body['plan_type'] ?? 'premium'), ['free', 'verification', 'premium', 'pro', 'custom'], true)
            ? (string)$body['plan_type'] : 'premium';
        $enableVerification = isset($body['enable_verification']) && (int)$body['enable_verification'] ? 1 : 0;
        $verificationDays = isset($body['verification_duration_days']) && is_numeric($body['verification_duration_days']) && (int)$body['verification_duration_days'] >= 1
            ? (int)$body['verification_duration_days'] : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO plans (name, description, price, currency, period, max_devices, features, badge_color, is_active,
             plan_type, enable_verification, verification_duration_days)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            $planType,
            $enableVerification,
            $verificationDays,
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
                  'features' => 'features', 'badge_color' => 'badge_color',
                  'plan_type' => 'plan_type', 'enable_verification' => 'enable_verification',
                  'verification_duration_days' => 'verification_duration_days'] as $field => $key) {
            if ($key === 'plan_type' && array_key_exists($key, $body) && !in_array((string)$body[$key], ['free', 'verification', 'premium', 'pro', 'custom'], true)) {
                continue; // reject invalid plan_type silently
            }
            if (array_key_exists($key, $body)) {
                $val = $body[$key];
                if ($key === 'features' && is_array($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                if ($key === 'enable_verification') {
                    $val = $val ? 1 : 0;
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

        $this->pdo->prepare('UPDATE users SET is_verified = ?, updated_at = datetime("now") WHERE id = ?')
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
            'UPDATE sessions SET revoked_at = datetime("now") WHERE user_id = ? AND revoked_at IS NULL'
        )->execute([$id]);

        // Revoke all device registrations so the quota must be re-validated on unban
        $this->pdo->prepare('UPDATE device_registrations SET is_active = 0 WHERE user_id = ?')->execute([$id]);

        $this->pdo->prepare('UPDATE users SET is_blocked = 1, blocked_at = datetime("now") WHERE id = ?')->execute([$id]);

        Response::success(null, 'تم حظر المستخدم ومنع الدخول للتطبيق');
    }

    // ============ Temporary Suspend ============

    // POST /api/v1/admin/users/{id}/suspend
    public function suspendUser(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $hours = (int)($body['hours'] ?? 24);
        if (!in_array($hours, [1, 6, 12, 24, 72, 168, 720], true)) {
            $hours = max(1, min(720, $hours));
        }
        $reason = isset($body['reason']) ? trim((string)$body['reason']) : '';

        $stmt = $this->pdo->prepare('SELECT id, name, is_blocked FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }
        if ((int)$user['is_blocked']) {
            Response::success(null, 'المستخدم محظور دائمًا بالفعل');
        }

        $suspendUntil = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_bans (user_id, reason, banned_by, suspend_until) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$id, $reason ?: null, $this->adminId(), $suspendUntil]);
        // is_blocked must be 1 so login/OTP entry points reject the account
        $this->pdo->prepare('UPDATE users SET is_blocked = 1 WHERE id = ?')->execute([$id]);
        // Kick live sessions
        $this->pdo->prepare(
            'UPDATE sessions SET revoked_at = datetime("now") WHERE user_id = ? AND revoked_at IS NULL'
        )->execute([$id]);

        Response::success(
            ['suspend_until' => $suspendUntil],
            "تم تعليق الحساب مؤقتًا حتى {$suspendUntil} (بالتوقيت المحلي)"
        );
    }

    // ============ Appeals ============

    // GET /api/v1/admin/appeals
    public function listAppeals(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $where  = '1=1';
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where  .= ' AND a.status = ?';
            $params[] = $status;
        }
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.user_id, a.contact_value, a.reason, a.status, a.admin_note,
                    a.reviewed_by, a.reviewed_at, a.created_at,
                    u.name user_name, u.phone user_phone, u.is_blocked
             FROM user_appeals a
             JOIN users u ON u.id = a.user_id
             WHERE {$where} ORDER BY a.id DESC LIMIT 200"
        );
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    // POST /api/v1/admin/appeals/{id}/review
    public function reviewAppeal(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $newStatus = (string)($body['status'] ?? '');
        if (!in_array($newStatus, ['approved', 'rejected'], true)) {
            Response::validationError(['status' => 'يجب تحديد حالة الموافقة أو الرفض']);
        }
        $adminNote = isset($body['admin_note']) ? trim((string)$body['admin_note']) : '';

        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, status FROM user_appeals WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $appeal = $stmt->fetch();
        if (!$appeal) {
            Response::notFound('الاعتراض غير موجود');
        }
        if ($appeal['status'] !== 'pending') {
            Response::success(null, 'تمت مراجعة هذا الاعتراض سابقًا');
        }

        $this->pdo->prepare(
            'UPDATE user_appeals SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = datetime("now") WHERE id = ?'
        )->execute([$newStatus, $adminNote ?: null, $this->adminId(), $id]);

        // Approved appeal → unban the user automatically
        if ($newStatus === 'approved') {
            $this->pdo->prepare(
                'UPDATE user_bans SET unbanned_at = datetime("now"), unbanned_by = ?
                 WHERE user_id = ? AND unbanned_at IS NULL'
            )->execute([$this->adminId(), (int)$appeal['user_id']]);
            $this->pdo->prepare(
                'UPDATE users SET is_blocked = 0, blocked_at = NULL WHERE id = ?'
            )->execute([(int)$appeal['user_id']]);
        }

        Response::success(null, $newStatus === 'approved' ? 'تم قبول الاعتراض وفك الحظر' : 'تم رفض الاعتراض');
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
            'UPDATE user_bans SET unbanned_at = datetime("now"), unbanned_by = ?
             WHERE user_id = ? AND unbanned_at IS NULL'
        );
        $stmt->execute([$this->adminId(), $id]);

        $this->pdo->prepare('UPDATE users SET is_blocked = 0, blocked_at = NULL WHERE id = ?')->execute([$id]);

        // Also clear any active temporary suspension for this user
        $this->pdo->prepare(
            'UPDATE user_bans SET unbanned_at = datetime("now"), unbanned_by = ?
             WHERE user_id = ? AND unbanned_at IS NULL AND suspend_until IS NOT NULL'
        )->execute([$this->adminId(), $id]);

        Response::success(null, 'تم فك الحظر عن المستخدم');
    }

    // ============ Appeals (user-side endpoint exposed via UserController route) ============

    // POST /api/v1/admin/users/{id}/appeals  — create an appeal ON BEHALF of a blocked user (admin creates)
    public function createAppeal(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = isset($body['reason']) ? trim((string)$body['reason']) : '';
        if ($reason === '') {
            Response::validationError(['reason' => 'سبب الاعتراض مطلوب']);
        }
        $stmt = $this->pdo->prepare('SELECT id, name, is_blocked FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }
        $contactValue = ($user['phone'] ?? '') ?: ($user['email'] ?? '');
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_appeals (user_id, contact_value, reason) VALUES (?, ?, ?)'
        );
        $stmt->execute([$id, $contactValue ?: null, $reason]);
        Response::success(['id' => (int)$this->pdo->lastInsertId()], 'تم تسجيل الاعتراض');
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

        $planStmt = $this->pdo->prepare('SELECT period, enable_verification, verification_duration_days, plan_type FROM plans WHERE id = ? LIMIT 1');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();

        if ($durationDays === null) {
            $durationDays = match ($plan['period'] ?? 'monthly') {
                'yearly'   => 365,
                'lifetime' => null,
                default     => 30,
            };
        }

        $expiresAt = $durationDays !== null ? date('Y-m-d H:i:s', strtotime("+{$durationDays} days")) : null;
        $this->pdo->prepare(
            'INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, expires_at)
             VALUES (?, ?, "active", datetime("now","localtime"), ?)'
        )->execute([$id, $planId, $expiresAt]);

        // Verification badge follows the plan
        $this->pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$id]);

        // Independent verification: if the plan enables verification, the badge
        // survives until verification_duration_days ends even after the
        // subscription expires. Otherwise it follows the subscription itself.
        $verifiedUntil = null;
        if (!empty($plan['enable_verification']) && !empty($plan['verification_duration_days'])) {
            $verifiedUntil = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['verification_duration_days'] . ' days'));
        } else {
            $verifiedUntil = $expiresAt;
        }
        $this->pdo->prepare('UPDATE users SET verified_until = ? WHERE id = ?')->execute([$verifiedUntil, $id]);

        Response::success(['verified_until' => $verifiedUntil], 'تم تفعيل الاشتراك وعلامة التحقق الزرقاء');
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
            $this->pdo->prepare('UPDATE users SET is_verified = 0, verified_until = NULL WHERE id = ?')->execute([$sub['user_id']]);
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
                    u.is_online, u.last_seen, u.is_verified, u.verified_until, u.is_blocked, u.blocked_at, u.created_at
             FROM users u WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }

        // Independent verification deadline
        $user['verified_until'] = $user['verified_until'] ?? null;

        // Active ban info
        $banStmt = $this->pdo->prepare(
            'SELECT id, reason, banned_at, unbanned_at FROM user_bans WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $banStmt->execute([$id]);
        $user['latest_ban'] = $banStmt->fetch() ?: null;

        // Active subscriptions
        $subStmt = $this->pdo->prepare(
            'SELECT us.id, us.plan_id, us.status, us.starts_at, us.expires_at,
                    p.name plan_name, p.price, p.currency, p.period, p.max_devices, p.features
             FROM user_subscriptions us
             LEFT JOIN plans p ON p.id = us.plan_id
             WHERE us.user_id = ? ORDER BY us.id DESC'
        );
        $subStmt->execute([$id]);
        $user['subscriptions'] = $subStmt->fetchAll();

        // Devices
        $devStmt = $this->pdo->prepare(
            'SELECT id, device_fingerprint, device_model, os, os_version, app_version,
                    platform, device_name, is_active, last_seen
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

    // DELETE /api/v1/admin/users/{id}
    public function userDelete(int $id): void
    {
        // Support both session-bound user JWTs and standalone admin JWTs
        $authHeader = nova_get_auth_header() ?? '';
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        if ($token === '') {
            Response::unauthorized('يجب تسجيل الدخول أولاً');
        }
        $payload = JwtHelper::verify($token);
        if ($payload === null) {
            Response::unauthorized('الجلسة منتهية أو غير صالحة، يرجى تسجيل الدخول مجدداً');
        }
        $adminId = (int)($payload['user_id'] ?? 0);
        $isStandaloneAdminJwt = isset($payload['role']) && $payload['role'] === 'admin';
        if (!$isStandaloneAdminJwt) {
            // Session-bound JWT: verify the session record exists (user JWT)
            $user = AuthMiddleware::authenticate();
            $adminId = (int)$user['user_id'];
        }
        $stmt = $this->pdo->prepare(
            'SELECT a.id, r.name AS role_name
             FROM admins a JOIN roles r ON r.id = a.role_id
             WHERE a.id = ? AND a.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$adminId]);
        $adminRow = $stmt->fetch();
        if (!$adminRow || stripos($adminRow['role_name'] ?? '', 'admin') === false) {
            Response::forbidden('غير مصرح لك بهذا الإجراء');
        }

        $stmt = $this->pdo->prepare('SELECT id, phone, name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }

        // Hard-delete rows not covered by ON DELETE CASCADE
        $this->pdo->prepare('DELETE FROM conversations WHERE created_by = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM calls WHERE caller_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM messages WHERE sender_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM attachments WHERE uploader_id = ?')->execute([$id]);
        // Cascaded tables (blocks, call_participants, contacts, conv_members,
        // message_reactions, message_reads, notifications, reports, sessions,
        // stories, story_views, devices, call_signals, user_bans, user_subscriptions)
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

        Response::success(['id' => $id, 'phone' => $user['phone'], 'name' => $user['name']], 'تم حذف المستخدم');
    }

    // Private helpers
    private function adminId(): int
    {
        // Standalone admin JWT (role=admin) is already resolved in the constructor
        if ($this->standaloneAdminId > 0) {
            return $this->standaloneAdminId;
        }
        $session = AuthMiddleware::authenticate();
        $stmt = $this->pdo->prepare('SELECT id FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([(int)$session['user_id']]);
        $admin = $stmt->fetch();
        return (int)($admin['id'] ?? 0);
    }
}
