<?php
/**
 * NOVA Messenger - User Controller
 */

declare(strict_types=1);

class UserController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // GET /api/v1/users/me
    public function me(): void
    {
        $auth = AuthMiddleware::authenticate();
        $user = $this->getUserById((int)$auth['user_id']);
        Response::success($user);
    }

    // PUT /api/v1/users/me
    public function updateMe(): void
    {
        $auth = AuthMiddleware::authenticate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed = ['name', 'username', 'bio', 'status_text', 'email'];
        $updates = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $updates[] = "{$field} = ?";
                $params[]  = htmlspecialchars(strip_tags(trim((string)$body[$field])), ENT_QUOTES, 'UTF-8');
            }
        }

        if (empty($updates)) {
            Response::error('لا توجد بيانات للتحديث', 'NO_DATA', 400);
        }

        // Validate username uniqueness
        if (isset($body['username']) && !empty($body['username'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
            $stmt->execute([$body['username'], $auth['user_id']]);
            if ($stmt->fetch()) {
                Response::error('اسم المستخدم مستخدم من قبل شخص آخر', 'USERNAME_TAKEN', 409);
            }
        }

        $params[] = $auth['user_id'];
        $sql      = 'UPDATE users SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?';
        $this->pdo->prepare($sql)->execute($params);

        Response::success($this->getUserById((int)$auth['user_id']), 'تم تحديث الملف الشخصي بنجاح');
    }

    // POST /api/v1/users/avatar
    public function uploadAvatar(): void
    {
        $auth = AuthMiddleware::authenticate();

        if (!isset($_FILES['avatar'])) {
            Response::error('لم يتم رفع أي صورة', 'NO_FILE', 400);
        }

        $file     = $_FILES['avatar'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize  = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed, true)) {
            Response::error('نوع الملف غير مسموح به. يُسمح فقط بـ JPEG, PNG, WebP', 'INVALID_FILE_TYPE', 400);
        }

        if ($file['size'] > $maxSize) {
            Response::error('حجم الصورة يتجاوز الحد المسموح به (5MB)', 'FILE_TOO_LARGE', 400);
        }

        // Verify it's actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            Response::error('الملف المرفوع ليس صورة صالحة', 'INVALID_IMAGE', 400);
        }

        $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$file['type']];
        $fileName = UuidHelper::generate() . '.' . $ext;
        $destDir  = ($_ENV['STORAGE_PATH'] ?? __DIR__ . '/../storage') . '/avatars/';

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $destDir . $fileName)) {
            Response::error('فشل في رفع الصورة', 'UPLOAD_FAILED', 500);
        }

        $avatarUrl = rtrim($_ENV['STORAGE_URL'] ?? '', '/') . '/avatars/' . $fileName;

        $this->pdo->prepare('UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?')
                  ->execute([$avatarUrl, $auth['user_id']]);

        Response::success(['avatar' => $avatarUrl], 'تم تحديث الصورة الشخصية بنجاح');
    }

    // GET /api/v1/users/{id}
    public function getUser(int $id): void
    {
        AuthMiddleware::authenticate();
        $user = $this->getPublicProfile($id);
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }
        Response::success($user);
    }

    // GET /api/v1/users/search?q=...
    public function search(): void
    {
        $auth  = AuthMiddleware::authenticate();
        $query = trim($_GET['q'] ?? '');

        if (mb_strlen($query) < 2) {
            Response::error('يجب إدخال حرفين على الأقل للبحث', 'QUERY_TOO_SHORT', 400);
        }

        $search = '%' . $query . '%';
        $stmt   = $this->pdo->prepare(
            'SELECT id, uuid, name, username, avatar, is_online, last_seen
             FROM users
             WHERE (name LIKE ? OR username LIKE ? OR phone LIKE ?)
               AND id != ? AND is_blocked = 0
             LIMIT 20'
        );
        $stmt->execute([$search, $search, $search, $auth['user_id']]);
        Response::success($stmt->fetchAll());
    }

    // POST /api/v1/heartbeat — تحديث آخر الظهور وحالة الاتصال (يُستدعى مع كل نبضة من التطبيق)
    public function heartbeat(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->pdo->prepare(
            'UPDATE users SET is_online = 1, last_seen = NOW(), updated_at = NOW() WHERE id = ?'
        )->execute([$userId]);

        Response::success(null, 'ok');
    }

    // GET /api/v1/privacy
    public function privacyGet(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT last_seen_visibility, photo_visibility, status_visibility, read_receipts
             FROM privacy_settings WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->pdo->prepare(
                'INSERT INTO privacy_settings (user_id) VALUES (?)'
            )->execute([$userId]);
            $row = ['last_seen_visibility' => 'contacts', 'photo_visibility' => 'contacts', 'status_visibility' => 'contacts', 'read_receipts' => 1];
        }
        Response::success($row);
    }

    // PUT /api/v1/privacy
    public function privacyUpdate(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed = ['last_seen_visibility', 'photo_visibility', 'status_visibility', 'read_receipts'];
        $sets = []; $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                if ($field === 'read_receipts') {
                    $val = filter_var($body[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                } else {
                    $val = in_array($body[$field], ['everybody', 'contacts', 'nobody'], true) ? $body[$field] : null;
                    if ($val === null) continue;
                }
                $sets[] = "{$field} = ?";
                $params[] = $val;
            }
        }
        if (empty($sets)) {
            Response::error('لا توجد بيانات للتحديث', 'NO_DATA', 400);
        }
        $params[] = $userId;
        $this->pdo->prepare('INSERT INTO privacy_settings (user_id) SELECT ? WHERE NOT EXISTS (SELECT 1 FROM privacy_settings WHERE user_id = ?)')->execute([$userId, $userId]);
        $this->pdo->prepare('UPDATE privacy_settings SET ' . implode(', ', $sets) . ' WHERE user_id = ?')->execute($params);

        Response::success(null, 'تم تحديث إعدادات الخصوصية');
    }

    // POST /api/v1/users/{id}/block
    public function blockUser(int $targetId): void
    {
        $auth = AuthMiddleware::authenticate();
        $myId = (int)$auth['user_id'];

        if ($myId === $targetId) {
            Response::error('لا يمكنك حظر نفسك', 'SELF_BLOCK', 400);
        }

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO blocks (user_id, blocked_user_id, created_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$myId, $targetId]);

        Response::success(null, 'تم حظر المستخدم');
    }

    // DELETE /api/v1/users/{id}/block
    public function unblockUser(int $targetId): void
    {
        $auth = AuthMiddleware::authenticate();
        $this->pdo->prepare('DELETE FROM blocks WHERE user_id = ? AND blocked_user_id = ?')
                  ->execute([$auth['user_id'], $targetId]);
        Response::success(null, 'تم فك الحظر عن المستخدم');
    }

    // GET /api/v1/users/settings
    public function getSettings(): void
    {
        $auth = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        Response::success([
            'disappear_default' => (int)($settings['disappear_default'] ?? 0),
        ]);
    }

    // PUT /api/v1/users/settings
    public function updateSettings(): void
    {
        $auth = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed = [0, 86400, 3600, 604800, -1];
        $disappearDefault = (int)($body['disappear_default'] ?? 0);
        if (!in_array($disappearDefault, $allowed, true)) {
            Response::error('القيمة غير صالحة: 0 (دائم)، 3600 (بعد ساعة)، 86400 (بعد 24 ساعة)، 604800 (بعد أسبوع)، -1 (بعد القراءة)', 'INVALID_VALUE', 400);
        }

        $this->pdo->prepare(
            'INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()'
        )->execute([$userId, 'disappear_default', $disappearDefault, $disappearDefault]);

        // Update the disappear_after for all current conversations of this user
        $this->pdo->prepare(
            'UPDATE conversation_members SET disappear_after = ? WHERE user_id = ? AND left_at IS NULL'
        )->execute([$disappearDefault, $userId]);

        Response::success(['disappear_default' => $disappearDefault], 'تم حفظ الإعدادات');
    }

    // =====================================================
    // Private Helpers
    // =====================================================

    private function getUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, uuid, phone, email, name, username, bio, avatar, status_text,
                    is_online, last_seen, is_verified, is_blocked, created_at, updated_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch() ?: null;
        if ($user) {
            // Active subscription plan (if any)
            $subStmt = $this->pdo->prepare(
                'SELECT p.id, p.name, p.price, p.currency, p.period, p.max_devices, p.badge_color
                 FROM user_subscriptions us
                 JOIN plans p ON p.id = us.plan_id
                 WHERE us.user_id = ? AND us.status = "active"
                 ORDER BY us.id DESC LIMIT 1'
            );
            $subStmt->execute([$id]);
            $user['plan'] = $subStmt->fetch() ?: null;

            // Device quota info
            $devStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM device_registrations WHERE user_id = ? AND is_active = 1'
            );
            $devStmt->execute([$id]);
            $user['active_devices_count'] = (int)$devStmt->fetchColumn();
            $user['max_devices_allowed'] = $user['plan']['max_devices'] ?? 1;
        }
        return $user;
    }

    private function getPublicProfile(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, uuid, name, username, bio, avatar, status_text, is_online, last_seen
             FROM users WHERE id = ? AND is_blocked = 0 LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
