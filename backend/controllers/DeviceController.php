<?php
/**
 * NOVA Messenger - Device Registration Controller
 * Captures device details (model, OS, fingerprint) on every login/setup and
 * enforces the per-user device limit defined by the active subscription plan.
 * Also handles QR-based linked device flow.
 */

declare(strict_types=1);

class DeviceController
{
    private PDO|TursoPdo $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // POST /api/v1/devices/register
    public function register(): void
    {
        $user  = AuthMiddleware::authenticate();
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $v     = Validator::make($body)->required('device_fingerprint', 'معرف الجهاز');
        if ($v->fails()) {
            Response::validationError($v->errors());
        }

        $userId    = (int)$user['user_id'];
        $fingerprint = trim((string)$v->sanitizeString('device_fingerprint'));
        $model     = isset($body['device_model']) ? trim((string)$v->sanitizeString('device_model')) : null;
        $osName    = isset($body['os_name']) ? trim((string)$v->sanitizeString('os_name')) : null;
        $osVersion = isset($body['os_version']) ? trim((string)$v->sanitizeString('os_version')) : null;
        $appVersion= isset($body['app_version']) ? trim((string)$v->sanitizeString('app_version')) : null;
        $platform  = isset($body['platform']) ? trim((string)$v->sanitizeString('platform')) : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO device_registrations
                (user_id, device_uuid, device_name, os, app_version, is_active, last_seen)
             VALUES (?, ?, ?, ?, ?, 1, datetime("now"))
             ON CONFLICT(user_id, device_uuid) DO UPDATE SET
                device_name = excluded.device_name, os = excluded.os, app_version = excluded.app_version,
                is_active = 1, last_seen = datetime("now")'
        );
        $stmt->execute([$userId, $fingerprint, ($model ?? '') . ' (' . ($osName ?? 'unknown') . ')', $osName, $appVersion]);

        $deviceId = (int)$this->pdo->lastInsertId() ?: $this->getDeviceId($userId, $fingerprint);

        // Sync with user_devices table for FCM compatibility
        $this->pdo->prepare(
            'INSERT INTO user_devices (user_id, device_uuid, platform, last_active_at, updated_at)
             VALUES (?, ?, ?, datetime("now"), datetime("now"))
             ON CONFLICT(user_id, device_uuid) DO UPDATE SET
                last_active_at = datetime("now"), updated_at = datetime("now")'
        )->execute([$userId, $fingerprint, $platform ?? $osName ?? 'web']);

        $maxAllowed = $this->getMaxDevicesAllowed($userId);
        $activeCount = $this->getActiveDeviceCount($userId);

        if ($activeCount > $maxAllowed) {
            $stmt = $this->pdo->prepare('UPDATE device_registrations SET is_active = 0 WHERE user_id = ? AND id = ?');
            $stmt->execute([$userId, $deviceId]);
            Response::forbidden("تجاوزت حد الأجهزة المسموح به للباقة ({$maxAllowed} جهاز).");
        }

        Response::success([
            'device_id' => $deviceId,
            'active_devices' => $activeCount,
            'max_devices_allowed' => $maxAllowed,
        ], 'تم تسجيل الجهاز بنجاح');
    }

    public function saveFcmToken(): void
    {
        $user = AuthMiddleware::authenticate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v    = Validator::make($body)
            ->required('fcm_token', 'رمز الإشعارات')
            ->required('device_fingerprint', 'معرف الجهاز');

        if ($v->fails()) {
            Response::validationError($v->errors());
        }

        $userId      = (int)$user['user_id'];
        $fcmToken    = trim((string)$body['fcm_token']);
        $fingerprint = trim((string)$body['device_fingerprint']);

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_devices (user_id, device_uuid, fcm_token, last_active_at, updated_at)
             VALUES (?, ?, ?, datetime("now"), datetime("now"))
             ON CONFLICT(user_id, device_uuid) DO UPDATE SET
                fcm_token = excluded.fcm_token, last_active_at = datetime("now"), updated_at = datetime("now")'
        );
        $stmt->execute([$userId, $fingerprint, $fcmToken]);

        Response::success(null, 'تم حفظ رمز الإشعارات بنجاح');
    }

    // GET /api/v1/devices
    public function index(): void
    {
        $user = AuthMiddleware::authenticate();
        $userId = (int)$user['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT id, device_uuid, device_name, os, app_version, is_active, last_seen, created_at
             FROM device_registrations WHERE user_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$userId]);
        $devices = $stmt->fetchAll();

        $currentId = $this->getDeviceIdByFingerprint($userId);
        $devices = array_map(
            fn (array $d) => array_merge($d, ['is_current' => (int)$d['id'] === $currentId ? 1 : 0]),
            $devices
        );

        $maxAllowed = $this->getMaxDevicesAllowed($userId);
        $activeCount = $this->getActiveDeviceCount($userId);

        Response::success([
            'devices' => $devices,
            'active_count' => $activeCount,
            'max_devices' => $maxAllowed,
            'max_devices_allowed' => $maxAllowed,
            'can_add_device' => $activeCount < $maxAllowed,
        ]);
    }

    // POST /api/v1/devices/{id}/toggle
    public function toggleDevice(int $deviceId): void
    {
        $user = AuthMiddleware::authenticate();
        $userId = (int)$user['user_id'];

        $stmt = $this->pdo->prepare('SELECT id, is_active FROM device_registrations WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$deviceId, $userId]);
        $row = $stmt->fetch();
        if (!$row) Response::notFound('الجهاز غير موجود');

        $currentlyActive = (int)$row['is_active'] === 1;
        if ($currentlyActive) {
            if ((int)$row['id'] === $this->getDeviceIdByFingerprint($userId)) {
                Response::error('لا يمكن إلغاء ارتباط الجهاز الحالي', 'CANNOT_TOGGLE_CURRENT', 422);
            }
            $this->pdo->prepare('UPDATE device_registrations SET is_active = 0 WHERE id = ?')->execute([$deviceId]);
            Response::success(['device_id' => $deviceId, 'is_active' => 0], 'تم إلغاء ارتباط الجهاز');
        }

        if ($this->getActiveDeviceCount($userId) >= $this->getMaxDevicesAllowed($userId)) {
            Response::forbidden("تجاوزت حد الأجهزة المسموح به للباقة.");
        }

        $this->pdo->prepare('UPDATE device_registrations SET is_active = 1 WHERE id = ?')->execute([$deviceId]);
        Response::success(['device_id' => $deviceId, 'is_active' => 1], 'تم تفعيل الجهاز');
    }

    // --- QR LINKING SYSTEM ---

    // POST /api/v1/devices/link/init (New Device)
    public function createLinkSession(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $uuid = bin2hex(random_bytes(16));
        $stmt = $this->pdo->prepare(
            'INSERT INTO link_sessions (session_uuid, device_name, platform, status, expires_at)
             VALUES (?, ?, ?, "pending", datetime("now", "+5 minutes"))'
        );
        $stmt->execute([
            $uuid,
            $body['device_name'] ?? 'Unknown Device',
            $body['platform'] ?? 'web'
        ]);
        Response::success(['session_uuid' => $uuid, 'expires_in' => 300]);
    }

    // GET /api/v1/devices/link/{uuid} (New Device Polling)
    public function getLinkSessionStatus(string $uuid): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM link_sessions WHERE session_uuid = ? AND expires_at > datetime("now") LIMIT 1'
        );
        $stmt->execute([$uuid]);
        $session = $stmt->fetch();

        if (!$session) Response::error('الرمز منتهي الصلاحية أو غير صالح', 'EXPIRED', 404);

        if ($session['status'] === 'authorized' && $session['user_id']) {
            // Generate real session for this device
            $userStmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $userStmt->execute([$session['user_id']]);
            $user = $userStmt->fetch();
            
            $token = JwtHelper::generate(['sub' => $user['id'], 'iat' => time(), 'exp' => time() + (30 * 86400), 'jti' => bin2hex(random_bytes(8))]);
            $this->pdo->prepare(
                'INSERT INTO sessions (user_id, token_hash, device_name, platform, expires_at)
                 VALUES (?, ?, ?, ?, datetime("now", "+30 days"))'
            )->execute([
                $user['id'],
                hash('sha256', $token),
                $session['device_name'],
                $session['platform']
            ]);

            $this->pdo->prepare('UPDATE link_sessions SET status = "completed" WHERE id = ?')->execute([$session['id']]);
            
            Response::success([
                'status' => 'completed',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'phone' => $user['phone'],
                    'avatar' => $user['avatar']
                ]
            ]);
        }

        Response::success(['status' => $session['status']]);
    }

    // POST /api/v1/devices/link/authorize (Primary Device)
    public function authorizeLinkSession(): void
    {
        $auth = AuthMiddleware::authenticate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $uuid = $body['session_uuid'] ?? '';

        $stmt = $this->pdo->prepare(
            'SELECT * FROM link_sessions WHERE session_uuid = ? AND status = "pending" AND expires_at > datetime("now") LIMIT 1'
        );
        $stmt->execute([$uuid]);
        $session = $stmt->fetch();

        if (!$session) Response::error('الرمز غير صالح أو منتهي', 'INVALID', 400);

        // Check device limit
        if ($this->getActiveDeviceCount((int)$auth['user_id']) >= $this->getMaxDevicesAllowed((int)$auth['user_id'])) {
            Response::forbidden("تجاوزت حد الأجهزة المسموح به للباقة.");
        }

        $this->pdo->prepare(
            'UPDATE link_sessions SET user_id = ?, status = "authorized" WHERE id = ?'
        )->execute([$auth['user_id'], $session['id']]);

        Response::success(null, 'تمت الموافقة على ربط الجهاز');
    }

    private function getDeviceId(int $userId, string $fingerprint): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM device_registrations WHERE user_id = ? AND device_uuid = ? LIMIT 1');
        $stmt->execute([$userId, $fingerprint]);
        return (int)($stmt->fetch()['id'] ?? 0);
    }

    private function getDeviceIdByFingerprint(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM device_registrations WHERE user_id = ? AND is_active = 1 ORDER BY last_seen DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        return (int)($stmt->fetch()['id'] ?? 0);
    }

    private function getActiveDeviceCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM device_registrations WHERE user_id = ? AND is_active = 1');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function getMaxDevicesAllowed(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.max_devices FROM user_subscriptions us
             JOIN plans p ON p.id = us.plan_id
             WHERE us.user_id = ? AND us.status = "active"
             ORDER BY us.id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? max(1, (int)$row['max_devices']) : 1;
    }
}
