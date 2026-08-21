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

    /**
     * التحقق مما إذا كان المستخدم يملك صلاحية الوصول لملف وسائط معين.
     * يدعم التحقق من الصور الشخصية (العامة) والمرفقات (للمشاركين في المحادثة).
     */
    public function canAccessMedia(int $userId, string $relPath): bool
    {
        // 1. الصور الشخصية: عامة حالياً (يتم فلترتها في API الملف الشخصي)
        if (strpos($relPath, 'avatars/') === 0) {
            return true;
        }

        $fileName = basename($relPath);
        
        // جلب بيانات المرفق
        $stmt = $this->pdo->prepare('SELECT id, uploader_id, type FROM attachments WHERE file_name = ? LIMIT 1');
        $stmt->execute([$fileName]);
        $attachment = $stmt->fetch();
        
        if (!$attachment) return false;
        if ((int)$attachment['uploader_id'] === $userId) return true;

        $uploaderId = (int)$attachment['uploader_id'];

        // 2. الحالات (Stories)
        if (strpos($relPath, 'stories/') === 0 || $attachment['type'] === 'story') {
            $sStmt = $this->pdo->prepare('SELECT privacy FROM stories WHERE file_id = ? LIMIT 1');
            $sStmt->execute([$attachment['id']]);
            $story = $sStmt->fetch();
            if ($story) {
                $privacy = $story['privacy'] ?? 'everyone';
                if ($privacy === 'everyone') return !$this->isBlockedEither($userId, $uploaderId);
                if ($privacy === 'contacts') return $this->isContactOf($userId, $uploaderId) && !$this->isBlockedEither($userId, $uploaderId);
                return false;
            }
        }

        // 3. المرفقات والرسائل الصوتية
        $mStmt = $this->pdo->prepare('SELECT conversation_id FROM messages WHERE file_id = ? LIMIT 1');
        $mStmt->execute([$attachment['id']]);
        $msg = $mStmt->fetch();
        if ($msg) {
            $cStmt = $this->pdo->prepare('SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1');
            $cStmt->execute([(int)$msg['conversation_id'], $userId]);
            return (bool)$cStmt->fetch();
        }

        return false;
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

        $user = $this->getUserById((int)$auth['user_id']);
        Response::success($user, 'تم تحديث الصورة الشخصية بنجاح');
    }

    // GET /api/v1/users/{id}
    public function getUser(int $id): void
    {
        $auth = AuthMiddleware::authenticate();
        $viewerId = (int)$auth['user_id'];
        $user = $this->getPublicProfile($id, $viewerId);
        if (!$user) {
            Response::notFound('المستخدم غير موجود');
        }
        // صاحبه يرى دائمًا آخر ظهور/الوضع المتصل كاملين من قاعدة البيانات
        if ($viewerId === $id) {
            $raw = $this->getUserById($id);
            $user['last_seen'] = $raw['last_seen'] ?? null;
            $user['is_online'] = (bool)($raw['is_online'] ?? false);
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

        // فلترة البحث بحسب إعدادات find_by_* لصاحب الحساب، واستبعاد المستخدمين الذين حظرهم viewer أو حظروا viewer
        $isNumeric = preg_match('/^[0-9+\s\-()]+$/', $query) === 1;
        $isEmail   = mb_strpos($query, '@') !== false;
        $nameCols  = ['name LIKE ?', 'username LIKE ?'];
        if ($isNumeric) {
            $nameCols = ['name LIKE ?', 'phone LIKE ?'];
        }
        if ($isEmail) {
            $nameCols = ['name LIKE ?', 'email LIKE ?'];
        }
        $cols  = implode(' OR ', $nameCols);
        $stmt  = $this->pdo->prepare(
            'SELECT id, uuid, name, username, avatar, is_online, last_seen, is_verified
             FROM users
             WHERE (' . $cols . ')
               AND id != ? AND is_blocked = 0
               AND id NOT IN (
                   SELECT user_id FROM blocks WHERE blocked_user_id = ?
                   UNION ALL
                   SELECT blocked_user_id FROM blocks WHERE user_id = ?
               )
             LIMIT 20'
        );
        $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $query) . '%';
        $stmt->execute([$like, $like, $auth['user_id'], $auth['user_id'], $auth['user_id']]);
        $rows = $stmt->fetchAll();

        // فلترة find_by_* لكل مستخدم في النتائج وفرض الخصوصية
        $viewerId = (int)$auth['user_id'];
        $filtered = [];
        foreach ($rows as $r) {
            $ownerId = (int)$r['id'];
            $p = $this->pdo->prepare('SELECT find_by_phone, find_by_email, find_by_username FROM privacy_settings WHERE user_id = ? LIMIT 1');
            $p->execute([$ownerId]);
            $ps = $p->fetch() ?: ['find_by_phone' => 1, 'find_by_email' => 1, 'find_by_username' => 1];
            
            if ($isNumeric && ((int)($ps['find_by_phone'] ?? 1)) === 0) continue;
            if ($isEmail && ((int)($ps['find_by_email'] ?? 1)) === 0) continue;
            if (!$isNumeric && !$isEmail && ((int)($ps['find_by_username'] ?? 1)) === 0) continue;

            $r = $this->applyPresencePrivacy($r, $viewerId);
            $r = $this->filterProfile($r, $viewerId, $ownerId);
            $filtered[] = $r;
        }
        Response::success($filtered);
    }

    // POST /api/v1/heartbeat — تحديث آخر الظهور وحالة الاتصال (يُستدعى مع كل نبضة من التطبيق)
    public function heartbeat(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        // تنظيف جماعي: أي مستخدم لم يُحدَّث آخر ظهور خلال 5 دقائق يُعتبر offline فعليًا
        try {
            $this->pdo->prepare(
                'UPDATE users SET is_online = 0 WHERE is_online = 1 AND last_seen < NOW() - INTERVAL 5 MINUTE'
            )->execute();
        } catch (\Throwable $e) {
            // تجاهل — غير حرج
        }
        $this->pdo->prepare(
            'UPDATE users SET is_online = 1, last_seen = NOW(), updated_at = NOW() WHERE id = ?'
        )->execute([$userId]);
        Response::success(null, 'ok');
    }

    // POST /api/v1/heartbeat/offline — آخر ظهور فعلي عند الخلفية/فقدان الاتصال/إغلاق التطبيق
    public function setOffline(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $this->pdo->prepare(
            'UPDATE users SET is_online = 0, last_seen = NOW(), updated_at = NOW() WHERE id = ?'
        )->execute([$userId]);
        Response::success(null, 'ok');
    }

    // GET /api/v1/privacy — جميع إعدادات الخصوصية والهوية
    public function privacyGet(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT show_last_seen, show_online_status, show_read_receipts,
                    show_phone, show_email, show_avatar, show_status_text,
                    messages_from, calls_from, groups_from,
                    find_by_phone, find_by_email, find_by_username,
                    display_identity, story_privacy, allow_by_phone
             FROM privacy_settings WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->pdo->prepare(
                'INSERT INTO privacy_settings (user_id) VALUES (?)'
            )->execute([$userId]);
            $row = self::_defaultPrivacyRow();
        }
        Response::success([
            'last_seen_visibility' => self::_visibilityForInt((int)($row['show_last_seen'] ?? 1)),
            'online_status'        => ((int)($row['show_online_status'] ?? 1)) === 1,
            'photo_visibility'     => self::_visibilityForInt((int)($row['show_avatar'] ?? 1)),
            'status_visibility'    => self::_visibilityForInt((int)($row['show_status_text'] ?? 1)),
            'phone_visibility'     => self::_visibilityForInt((int)($row['show_phone'] ?? 2)),
            'email_visibility'     => self::_visibilityForInt((int)($row['show_email'] ?? 2)),
            'read_receipts'        => ((int)($row['show_read_receipts'] ?? 1)) === 1,
            'messages_from'        => self::_visibilityForInt((int)($row['messages_from'] ?? 1)),
            'calls_from'           => self::_visibilityForInt((int)($row['calls_from'] ?? 1)),
            'groups_from'          => self::_visibilityForInt((int)($row['groups_from'] ?? 1)),
            'find_by_phone'        => ((int)($row['find_by_phone'] ?? 1)) === 1,
            'find_by_email'        => ((int)($row['find_by_email'] ?? 1)) === 1,
            'find_by_username'     => ((int)($row['find_by_username'] ?? 1)) === 1,
            'display_identity'     => (string)($row['display_identity'] ?? 'name_username'),
            'story_privacy'        => (int)($row['story_privacy'] ?? 1),
            'allow_by_phone'       => ((int)($row['allow_by_phone'] ?? 1)) === 1,
        ]);
    }

    // PUT /api/v1/privacy — جميع إعدادات الخصوصية والهوية
    public function privacyUpdate(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $sets = []; $params = [];

        $map = [
            'last_seen_visibility' => ['show_last_seen', 'visibility'],
            'photo_visibility'     => ['show_avatar', 'visibility'],
            'status_visibility'    => ['show_status_text', 'visibility'],
            'phone_visibility'     => ['show_phone', 'visibility'],
            'email_visibility'     => ['show_email', 'visibility'],
            'messages_from'        => ['messages_from', 'visibility'],
            'calls_from'           => ['calls_from', 'visibility'],
            'groups_from'          => ['groups_from', 'visibility'],
        ];
        foreach ($map as $input => [$col, $type]) {
            if (array_key_exists($input, $body)) {
                $val = in_array($body[$input], ['everybody', 'contacts', 'nobody'], true) ? $body[$input] : null;
                if ($val !== null) {
                    $sets[] = "{$col} = ?";
                    $params[] = self::_visibilityToInt($val);
                }
            }
        }
        // online_status / read_receipts: bool
        if (array_key_exists('online_status', $body)) {
            $sets[] = 'show_online_status = ?';
            $params[] = filter_var($body['online_status'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if (array_key_exists('read_receipts', $body)) {
            $sets[] = 'show_read_receipts = ?';
            $params[] = filter_var($body['read_receipts'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        // find_by_*: bool
        foreach (['find_by_phone', 'find_by_email', 'find_by_username'] as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "{$f} = ?";
                $params[] = filter_var($body[$f], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }
        // display_identity: name_username|username|phone|email|name_phone|name_email
        if (array_key_exists('display_identity', $body)) {
            $valid = ['name_username', 'username', 'phone', 'email', 'name_phone', 'name_email'];
            $val   = in_array((string)$body['display_identity'], $valid, true) ? (string)$body['display_identity'] : null;
            if ($val !== null) {
                $sets[] = 'display_identity = ?';
                $params[] = $val;
            }
        }
        // story_privacy: 1=all 2=contacts 3=share_with 4=nobody (numbers)
        if (array_key_exists('story_privacy', $body)) {
            $v = (int)$body['story_privacy'];
            if (in_array($v, [1, 2, 3, 4], true)) {
                $sets[] = 'story_privacy = ?';
                $params[] = $v;
            }
        }
        // allow_by_phone: bool
        if (array_key_exists('allow_by_phone', $body)) {
            $sets[] = 'allow_by_phone = ?';
            $params[] = filter_var($body['allow_by_phone'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
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
                    u.name, u.username, u.avatar, u.is_online, u.last_seen, u.is_verified
             FROM contacts c
             JOIN users u ON u.id = c.contact_user_id
             WHERE c.user_id = ? AND c.is_blocked = 0
             ORDER BY u.is_online DESC, u.last_seen DESC, c.created_at DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        // contact_name = nickname إن وُجد ELSE display_name وفق إعدادات صاحب الحساب؛ لا كشف phone في القائمة
        foreach ($rows as &$r) {
            $ownerId = (int)$r['contact_user_id'];
            unset($r['name']);
            $r['phone'] = null;
            $r['email'] = null;
            $q = $this->pdo->prepare('SELECT display_identity FROM privacy_settings WHERE user_id = ? LIMIT 1');
            $q->execute([$ownerId]);
            $r['display_identity'] = $q->fetchColumn() ?: 'name_username';
            $nick = trim((string)($r['nickname'] ?? ''));
            $r['display_name'] = self::_displayNameForIdentity($r);
            $r['contact_name'] = $nick !== '' ? $nick : $r['display_name'];
            // احترام show_last_seen/show_online_status لصاحب الحساب
            if (!$this->canSeeOnline($userId, $ownerId)) {
                $r['is_online'] = false;
            }
            if (!$this->canSeeLastSeen($userId, $ownerId)) {
                $r['last_seen'] = null;
            }
        }
        unset($r);

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

    /**
     * تطبيق خصوصية آخر الظهور والحالة المتصلة على صف مستخدم (مصفوفة) أمام $viewerId.
     * is_online يصبح false إذا لم يُسمح برؤيته أو انقضت مهلة الحضور (5 دقائق بدون نبضات).
     * last_seen يُخفى (null) إذا لم يُسمح برؤيته.
     */
    public function applyPresencePrivacy(array $row, int $viewerId): array
    {
        $ownerId = (int)($row['id'] ?? 0);
        if ($viewerId === $ownerId || $ownerId <= 0) {
            return $row; // صاحبه يرى كل شيء
        }
        // مهلة الحضور: إذا لم يُحدَّث آخر ظهور خلال 5 دقائق → غير متصل فعليًا
        $rawOnline = (bool)($row['is_online'] ?? false);
        $lastSeen  = $row['last_seen'] ?? null;
        if ($rawOnline && $lastSeen) {
            try {
                // DB يخزن last_seen بـUTC (SQLite datetime('now'))، لذا تُفسَّر القيم بـUTC دائمًا
                $ls = new \DateTime((string)$lastSeen . ' UTC');
                $now = new \DateTime('now UTC');
                if ($now->getTimestamp() - $ls->getTimestamp() > 300) {
                    $rawOnline = false;
                }
            } catch (\Throwable $e) {
                $rawOnline = false;
            }
        }
        if (!$this->canSeeOnline($viewerId, $ownerId)) {
            $row['is_online'] = false;
            if (!$this->canSeeLastSeen($viewerId, $ownerId)) {
                $row['last_seen'] = null;
            }
        } elseif (!$this->canSeeLastSeen($viewerId, $ownerId)) {
            $row['is_online'] = false; // خيار «لا أحد» يخفي «متصل» أيضًا
            $row['last_seen']   = null;
        } else {
            $row['is_online'] = $rawOnline;
        }
        return $row;
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

    private static function _defaultPrivacyRow(): array
    {
        return [
            'show_last_seen' => 1, 'show_online_status' => 1, 'show_read_receipts' => 1,
            'show_phone' => 2, 'show_email' => 2, 'show_avatar' => 1, 'show_status_text' => 1,
            'messages_from' => 1, 'calls_from' => 1, 'groups_from' => 1,
            'find_by_phone' => 1, 'find_by_email' => 1, 'find_by_username' => 1,
            'display_identity' => 'name_username', 'story_privacy' => 1, 'allow_by_phone' => 1,
        ];
    }

    /** هل الحظر متبادل بين $a و$b؟ (blocks اتجاه واحد يكفي لأن الحظر يمنع الطرف الآخر أيضًا) */
    public function isBlockedEither(int $a, int $b): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM blocks WHERE (user_id = ? AND blocked_user_id = ?) OR (user_id = ? AND blocked_user_id = ?) LIMIT 1'
        );
        $stmt->execute([$a, $b, $b, $a]);
        return (bool)$stmt->fetch();
    }

    /** هل $a جهة اتصال لـ$b أو العكس؟ (علاقة جهة اتصال واحدة الاتجاه تكفي) */
    public function isContactOf(int $a, int $b): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM contacts WHERE (user_id = ? AND contact_user_id = ?) OR (user_id = ? AND contact_user_id = ?) LIMIT 1'
        );
        $stmt->execute([$a, $b, $b, $a]);
        return (bool)$stmt->fetch();
    }

    /** اسم جهة الاتصال المحفوظ (nickname > display identity) لـ$target أمام $viewer */
    private function contactNameDisplay(int $viewerId, int $targetId, array $profile): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT nickname FROM contacts WHERE user_id = ? AND contact_user_id = ? LIMIT 1'
        );
        $stmt->execute([$viewerId, $targetId]);
        $row = $stmt->fetch();
        $nick = $row ? trim((string)($row['nickname'] ?? '')) : null;
        if ($nick !== '' && $nick !== null) {
            return $nick;
        }
        return self::_displayNameForIdentity($profile);
    }

    /** بناء display_name من profile وفق display_identity */
    private static function _displayNameForIdentity(array $p): ?string
    {
        $mode = (string)($p['display_identity'] ?? 'name_username');
        $name = trim((string)($p['name'] ?? ''));
        $username = trim((string)($p['username'] ?? ''));
        $phone = trim((string)($p['phone'] ?? ''));
        $email = trim((string)($p['email'] ?? ''));
        switch ($mode) {
            case 'username':
                return $username !== '' ? $username : ($name !== '' ? $name : null);
            case 'phone':
                return $phone !== '' ? $phone : ($name !== '' ? $name : null);
            case 'email':
                return $email !== '' ? $email : ($name !== '' ? $name : null);
            case 'name_phone':
                return $name !== '' ? ($phone !== '' ? $name . ' (' . $phone . ')' : $name) : ($phone !== '' ? $phone : null);
            case 'name_email':
                return $name !== '' ? ($email !== '' ? $name . ' (' . $email . ')' : $name) : ($email !== '' ? $email : null);
            case 'name_username':
            default:
                return $name !== '' ? $name : ($username !== '' ? $username : null);
        }
    }

    /** تطبيق قواعد خصوصية على profile لـ$ownerId أمام $viewerId (null = لا عرض) */
    public function filterProfile(array $profile, int $viewerId, int $ownerId): ?array
    {
        if ($viewerId === $ownerId) {
            return $profile; // صاحبه يرى كل شيء
        }
        if ($this->isBlockedEither($viewerId, $ownerId)) {
            // طرف محظور: أقصى حد معلومات — فقط display name من الاسم الأول
            $name = trim((string)($profile['name'] ?? ''));
            $first = mb_strpos($name, ' ') !== false ? mb_substr($name, 0, (int)mb_strpos($name, ' ')) : $name;
            return [
                'id' => $profile['id'],
                'display_name' => $first !== '' ? $first : null,
                'avatar' => null, 'status_text' => null,
                'phone' => null, 'email' => null,
                'is_online' => false, 'last_seen' => null,
                'is_verified' => false,
            ];
        }

        // إعدادات صاحب الحساب
        $stmt = $this->pdo->prepare(
            'SELECT show_last_seen, show_online_status, show_read_receipts,
                    show_phone, show_email, show_avatar, show_status_text,
                    display_identity
             FROM privacy_settings WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch() ?: self::_defaultPrivacyRow();

        $isContact = $this->isContactOf($viewerId, $ownerId);
        $show = function (int $mode) use ($isContact): bool {
            if ($mode === 2) return true;
            if ($mode === 0) return false;
            return $isContact; // 1 = جهات الاتصال
        };

        $profile['phone'] = $show((int)($row['show_phone'] ?? 2)) ? $profile['phone'] : null;
        $profile['email'] = $show((int)($row['show_email'] ?? 2)) ? $profile['email'] : null;
        if (!$show((int)($row['show_avatar'] ?? 1))) {
            $profile['avatar'] = null;
        }
        $profile['status_text'] = $show((int)($row['show_status_text'] ?? 1)) ? $profile['status_text'] : null;

        if (!$show((int)($row['show_online_status'] ?? 1))) {
            $profile['is_online'] = false;
        }
        if (!$show((int)($row['show_last_seen'] ?? 1))) {
            $profile['last_seen'] = null;
            $profile['is_online'] = false;
        }

        $profile['display_name'] = self::_displayNameForIdentity($profile);
        $profile['contact_name'] = $this->contactNameDisplay($viewerId, $ownerId, $profile);
        return $profile;
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

    private function getPublicProfile(int $id, ?int $viewerId = null): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, uuid, name, username, phone, email, bio, avatar, status_text,
                    is_online, last_seen, is_verified
             FROM users WHERE id = ? AND is_blocked = 0 LIMIT 1'
        );
        $stmt->execute([$id]);
        $profile = $stmt->fetch() ?: null;
        if (!$profile || $viewerId === null) {
            return $profile;
        }
        // خصوصية آخر الظهور والحالة المتصلة (تشمل مهلة الحضور 5 دقائق)
        $profile = $this->applyPresencePrivacy($profile, $viewerId);
        return $this->filterProfile($profile, $viewerId, $id);
    }
}
