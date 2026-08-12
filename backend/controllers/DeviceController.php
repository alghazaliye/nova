<?php
/**
 * NOVA Messenger - Device Registration Controller
 * Captures device details (model, OS, fingerprint) on every login/setup and
 * enforces the per-user device limit defined by the active subscription plan.
 */

declare(strict_types=1);

class DeviceController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        AuthMiddleware::authenticate();
    }

    // POST /api/v1/devices/register
    // Body: device_model, os_name, os_version, app_version, platform, device_fingerprint
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

        // Upsert the device
        $stmt = $this->pdo->prepare(
            'INSERT INTO device_registrations
                (user_id, device_fingerprint, device_model, os_name, os_version, app_version, platform, is_active, first_seen, last_seen)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                device_model = VALUES(device_model), os_name = VALUES(os_name), os_version = VALUES(os_version),
                app_version = VALUES(app_version), platform = VALUES(platform),
                is_active = 1, last_seen = NOW()'
        );
        $stmt->execute([$userId, $fingerprint, $model, $osName, $osVersion, $appVersion, $platform]);

        $deviceId = (int)$this->pdo->lastInsertId() ?: $this->getDeviceId($userId, $fingerprint);

        // Enforce device limit based on active subscription plan
        $maxAllowed = $this->getMaxDevicesAllowed($userId);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM device_registrations WHERE user_id = ? AND is_active = 1'
        );
        $stmt->execute([$userId]);
        $activeCount = (int)$stmt->fetchColumn();

        $overLimit = false;
        if ($activeCount > $maxAllowed) {
            $overLimit = true;
            // Strict enforcement: REJECT the new device registration (user must deactivate an existing one first)
            $stmt = $this->pdo->prepare(
                'UPDATE device_registrations SET is_active = 0 WHERE user_id = ? AND id = ?'
            );
            $stmt->execute([$userId, $deviceId]);
            $oldest = $this->pdo->prepare(
                'SELECT id, device_model, platform FROM device_registrations WHERE user_id = ? AND is_active = 1 ORDER BY last_seen ASC LIMIT 1'
            );
            $oldest->execute([$userId]);
            $old = $oldest->fetch();
            $oldInfo = $old ? (string)$old['device_model'] . ' (' . (string)$old['platform'] . ')' : '';
            Response::forbidden(
                "تجاوزت حد الأجهزة المسموح به للباقة ({$maxAllowed} جهاز)" .
                ($oldInfo ? " — آخر جهاز نشط: {$oldInfo}." : '') .
                ' يرجى ترقية الباقة أو إلغاء جهاز من لوحة الإعدادات ثم إعادة المحاولة'
            );
        }

        Response::success([
            'device_id' => $deviceId,
            'active_devices' => $this->getActiveDeviceCount($userId),
            'max_devices_allowed' => $maxAllowed,
        ], 'تم تسجيل تفاصيل الجهاز بنجاح');
    }

    // GET /api/v1/devices
    public function index(): void
    {
        $user = AuthMiddleware::authenticate();
        $userId = (int)$user['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT id, device_fingerprint, device_model, os_name, os_version, app_version,
                    platform, barcode_hash, is_active, first_seen, last_seen
             FROM device_registrations WHERE user_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$userId]);
        $devices = $stmt->fetchAll();

        // The device matching the current session's fingerprint is the current device
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

    // POST /api/v1/devices/{id}/toggle — تفعيل/إلغاء ارتباط جهاز owned by this user
    public function toggleDevice(int $deviceId): void
    {
        $user = AuthMiddleware::authenticate();
        $userId = (int)$user['user_id'];

        $stmt = $this->pdo->prepare('SELECT id, is_active FROM device_registrations WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$deviceId, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('الجهاز غير موجود', 'NOT_FOUND', 404);
        }

        $currentlyActive = (int)$row['is_active'] === 1;
        if ($currentlyActive) {
            // Protect the current device from being deactivated via toggle (must use logout instead)
            $currentId = $this->getDeviceIdByFingerprint($userId);
            if ((int)$row['id'] === $currentId) {
                Response::error('لا يمكن إلغاء ارتباط الجهاز الحالي — استخدم تسجيل الخروج', 'CANNOT_TOGGLE_CURRENT', 422);
            }
            $this->pdo->prepare('UPDATE device_registrations SET is_active = 0 WHERE id = ?')->execute([$deviceId]);
            Response::success(['device_id' => $deviceId, 'is_active' => 0], 'تم إلغاء ارتباط الجهاز');
        }

        // Re-activating a device is subject to the plan limit
        $maxAllowed = $this->getMaxDevicesAllowed($userId);
        $activeCount = $this->getActiveDeviceCount($userId);
        if ($activeCount >= $maxAllowed) {
            Response::forbidden(
                "تجاوزت حد الأجهزة المسموح به للباقة ({$maxAllowed} جهاز) — ألغِ ارتباط جهاز آخر أولًا"
            );
        }

        $this->pdo->prepare('UPDATE device_registrations SET is_active = 1 WHERE id = ?')->execute([$deviceId]);
        Response::success(['device_id' => $deviceId, 'is_active' => 1], 'تم تفعيل الجهاز');
    }

    // Private helpers

    private function getDeviceId(int $userId, string $fingerprint): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM device_registrations WHERE user_id = ? AND device_fingerprint = ? LIMIT 1');
        $stmt->execute([$userId, $fingerprint]);
        return (int)($stmt->fetch()['id'] ?? 0);
    }

    private function getDeviceIdByFingerprint(int $userId): int
    {
        // Match current session device: sessions store ip/user_agent; here we use the most recent active device
        $stmt = $this->pdo->prepare(
            'SELECT id FROM device_registrations WHERE user_id = ? AND is_active = 1 ORDER BY last_seen DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        return (int)($stmt->fetch()['id'] ?? 0);
    }

    private function getActiveDeviceCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM device_registrations WHERE user_id = ? AND is_active = 1'
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function getMaxDevicesAllowed(int $userId): int
    {
        // Latest active subscription plan defines the device cap
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
