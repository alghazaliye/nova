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
            'SELECT id, uuid, name, username, avatar, is_online, last_seen, is_verified
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
            'SELECT show_last_seen, show_online_status, show_read_receipts
             FROM privacy_settings WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->pdo->prepare(
                'INSERT INTO privacy_settings (user_id) VALUES (?)'
            )->execute([$userId]);
            $row = ['show_last_seen' => 1, 'show_online_status' => 1, 'show_read_receipts' => 1];
        }
        Response::success([
            'last_seen_visibility' => self::_visibilityForInt((int)($row['show_last_seen'] ?? 1)),
            'online_status'        => ((int)($row['show_online_status'] ?? 1)) === 1,
            'photo_visibility'     => 'contacts',
            'status_visibility'    => 'contacts',
            'read_receipts'        => ((int)($row['show_read_receipts'] ?? 1)) === 1,
        ]);
    }

    // PUT /api/v1/privacy
    public function privacyUpdate(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $sets = []; $params = [];
        // last_seen_visibility: everybody|contacts|nobody → show_last_seen (2|1|0)
        if (array_key_exists('last_seen_visibility', $body)) {
            $val = in_array($body['last_seen_visibility'], ['everybody', 'contacts', 'nobody'], true) ? $body['last_seen_visibility'] : null;
            if ($val !== null) {
                $sets[] = 'show_last_seen = ?';
                $params[] = self::_visibilityToInt($val);
            }
        }
        // online_status: bool → show_online_status (1|0)
        if (array_key_exists('online_status', $body)) {
            $sets[] = 'show_online_status = ?';
            $params[] = filter_var($body['online_status'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        // read_receipts: bool/int → show_read_receipts
        if (array_key_exists('read_receipts', $body)) {
            $sets[] = 'show_read_receipts = ?';
            $params[] = filter_var($body['read_receipts'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
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

    // GET /api/v1/contacts/new — جهات الاتصال (المحفوظة في دفتر المستخدم)
    public function newContacts(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.contact_user_id, c.nickname, c.created_at,
                    u.name, u.username, u.phone, u.avatar, u.is_online, u.last_seen, u.is_verified
             FROM contacts c
             JOIN users u ON u.id = c.contact_user_id
             WHERE c.user_id = ? AND c.is_blocked = 0
             ORDER BY u.is_online DESC, u.last_seen DESC, c.created_at DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        Response::success($rows);
    }

    // POST /api/v1/contacts — إضافة جهة اتصال {contact_user_id, nickname?}
    public function addContact(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $target = (int)($body['contact_user_id'] ?? 0);

        if (!$target || $target === $userId) {
            Response::error('يجب تحديد مستخدم صالح للإضافة', 'INVALID_TARGET', 400);
        }

        $check = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND is_blocked = 0 LIMIT 1');
        $check->execute([$target]);
        if (!$check->fetch()) {
            Response::notFound('المستخدم غير موجود');
        }

        $nickname = trim((string)($body['nickname'] ?? ''));
        $nickname = $nickname !== '' ? htmlspecialchars(strip_tags($nickname), ENT_QUOTES, 'UTF-8') : null;

        $this->pdo->prepare(
            'INSERT INTO contacts (user_id, contact_user_id, nickname) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE is_blocked = 0' . ($nickname !== null ? ', nickname = VALUES(nickname)' : '')
        )->execute([$userId, $target, $nickname]);

        Response::success(null, 'تمت إضافة جهة الاتصال');
    }

    // DELETE /api/v1/contacts/{id} — إزالة جهة اتصال
    public function removeContact(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->pdo->prepare('DELETE FROM contacts WHERE id = ? AND user_id = ?')->execute([$id, $userId]);

        Response::success(null, 'تمت إزالة جهة الاتصال');
    }

    // GET /api/v1/settings — إعدادات التطبيق العامة (من جدول app_settings)
    public function appSettings(): void
    {
        AuthMiddleware::authenticate();
        $stmt = $this->pdo->prepare(
            'SELECT setting_key, setting_value FROM app_settings'
        );
        $stmt->execute();
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        Response::success([
            'allow_calls'     => ($settings['allow_calls'] ?? '1') === '1',
            'allow_groups'    => ($settings['allow_groups'] ?? '1') === '1',
            'allow_stories'   => ($settings['allow_stories'] ?? '1') === '1',
            'allow_registration' => ($settings['allow_registration'] ?? '1') === '1',
            'maintenance_mode'   => ($settings['maintenance_mode'] ?? '0') === '1',
            'app_name'        => $settings['app_name'] ?? 'NOVA Messenger',
            'max_file_size_mb'   => (int)($settings['max_file_size_mb'] ?? 50),
            'max_image_size_mb'  => (int)($settings['max_image_size_mb'] ?? 10),
            'max_video_size_mb'  => (int)($settings['max_video_size_mb'] ?? 100),
            'story_duration_hrs' => (int)($settings['story_duration_hrs'] ?? 24),
            'fcm_enabled'     => ($settings['fcm_enabled'] ?? '1') === '1',
            'edit_time_limit_minutes'   => (int)($settings['edit_time_limit_minutes'] ?? 0),
            'delete_time_limit_minutes' => (int)($settings['delete_time_limit_minutes'] ?? 0),
            'message_type_default'      => $settings['message_type_default'] ?? 'chat',
            'disappearing_default_seconds' => (int)($settings['disappearing_default_seconds'] ?? 0),
        ]);
    }

    // =====================================================
    // Private Helpers
    // =====================================================

    // Privacy helpers: هل يُسمح لـ$viewer برؤية last_seen لـ$target؟ (nobody→لا يُعرض حتى «متصل»)
    public function canSeeLastSeen(int $viewerId, int $targetId): bool
    {
        $stmt = $this->pdo->prepare('SELECT show_last_seen FROM privacy_settings WHERE user_id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $row = $stmt->fetch();
        $mode = $row ? (int)($row['show_last_seen'] ?? 1) : 1; // 2: الجميع، 1: جهات الاتصال، 0: لا أحد
        if ($mode === 2) return true;
        if ($mode === 0) return false;
        $chk = $this->pdo->prepare(
            'SELECT id FROM contacts WHERE (user_id = ? AND contact_user_id = ?) OR (user_id = ? AND contact_user_id = ?) LIMIT 1'
        );
        $chk->execute([$viewerId, $targetId, $targetId, $viewerId]);
        return (bool)$chk->fetch();
    }
    // هل يُسمح بعرض is_online لـ$target أمام $viewer؟
    public function canSeeOnline(int $viewerId, int $targetId): bool
    {
        $stmt = $this->pdo->prepare('SELECT show_online_status FROM privacy_settings WHERE user_id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $row = $stmt->fetch();
        return $row ? ((int)($row['show_online_status'] ?? 1)) !== 0 : true;
    }
    // هل يُسمح بعرض read receipt؟ (show_read_receipts لـأحد الطرفين)
    public function canSeeReadReceipt(int $viewerId, int $otherId): bool
    {
        $stmt = $this->pdo->prepare('SELECT show_read_receipts FROM privacy_settings WHERE user_id = ? LIMIT 1');
        $stmt->execute([$otherId]);
        $row = $stmt->fetch();
        return $row ? ((int)($row['show_read_receipts'] ?? 1)) !== 0 : true;
    }

    // last_seen_visibility value → int: everybody=2 contacts=1 nobody=0
    private static function _visibilityToInt(string $v): int
    {
        return match ($v) {
            'everybody' => 2,
            'nobody'    => 0,
            default     => 1,
        };
    }
    private static function _visibilityForInt(int $v): string
    {
        return match ($v) {
            2 => 'everybody',
            0 => 'nobody',
            default => 'contacts',
        };
    }

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
            'SELECT id, uuid, name, username, bio, avatar, status_text, is_online, last_seen, is_verified
             FROM users WHERE id = ? AND is_blocked = 0 LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
