<?php
/**
 * NOVA Messenger - Message Controller
 */

declare(strict_types=1);

class MessageController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // GET /api/v1/conversations/{id}/messages
    public function index(int $convId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->requireMember($convId, $userId);

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(50, max(10, (int)($_GET['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        // Cursor-based: before_id for loading older messages
        $beforeId = (int)($_GET['before_id'] ?? 0);

        $whereExtra = $beforeId ? 'AND m.id < ?' : '';
        $params     = $beforeId ? [$convId, $beforeId, $limit] : [$convId, $limit];

        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.uuid, m.conversation_id, m.sender_id, m.reply_to_message_id,
                    m.type, m.body, m.file_id, m.client_message_id, m.status, m.disappear_after,
                    m.created_at, m.updated_at, m.deleted_at,
                    u.name AS sender_name, u.avatar AS sender_avatar,
                    a.storage_path AS file_path, a.thumbnail_path, a.mime_type, a.file_size,
                    a.width, a.height, a.duration
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             LEFT JOIN attachments a ON a.id = m.file_id
             WHERE m.conversation_id = ? {$whereExtra}
             ORDER BY m.id DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        $messages = array_reverse($stmt->fetchAll());

        // Mark unread non-deleted messages sent to this user as delivered, then mark all as read
        foreach ($messages as $m) {
            if ((int)$m['sender_id'] !== $userId && in_array($m['status'], ['sent', 'delivered'], true) && $m['status'] !== 'deleted') {
                $this->pdo->prepare('UPDATE messages SET status = "delivered", updated_at = datetime("now") WHERE id = ? AND status = "sent"')
                          ->execute([$m['id']]);
            }
        }
        if (!empty($messages)) {
            $ids = array_map(fn ($m) => (int)$m['id'], $messages);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->prepare(
                "INSERT OR IGNORE INTO message_reads (message_id, user_id, read_at)
		                 SELECT id, ?, datetime('now') FROM messages WHERE id IN ($placeholders) AND sender_id != ? AND status NOT IN ('deleted', 'read')"
            )->execute(array_merge([$userId], $ids, [$userId]));
            $this->pdo->prepare(
                "UPDATE messages SET status = 'read', updated_at = datetime("now") WHERE id IN ($placeholders) AND sender_id != ? AND status NOT IN ('deleted', 'read')"
            )->execute(array_merge($ids, [$userId]));
        }

        // Update last_read_message_id
        if (!empty($messages)) {
            $lastId = end($messages)['id'];
            $this->pdo->prepare(
                'UPDATE conversation_members SET last_read_message_id = MAX(COALESCE(last_read_message_id, 0), ?)
                 WHERE conversation_id = ? AND user_id = ?'
            )->execute([$lastId, $convId, $userId]);
        }

        // Disappearing messages: expirations
        // 1) رسائل "بعد القراءة" (-1): تُحذف فورًا بعد أن يقرأها جميع المشاركين
        $this->expireAfterRead($convId);
        // 2) رسائل بوقت محدد (86400...): تُحذف عندما يتجاوز الزمن إعداد كل مُرسل
        $this->expireDisappearingMessages($convId);

        // Enrich: is_edited + deleted_for_me + deleted_for_all
        if (!empty($messages)) {
            $ids = array_map(fn ($m) => (int)$m['id'], $messages);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // التعديلات
            $stmt = $this->pdo->prepare("SELECT message_id, edited_at FROM message_edits WHERE message_id IN ($placeholders)");
            $stmt->execute($ids);
            $edits = [];
            foreach ($stmt->fetchAll() as $e) {
                $edits[(int)$e['message_id']] = true;
            }

            // الحذف الشخصي + الحذف الكلي
            $stmt = $this->pdo->prepare(
                "SELECT message_id, deleted_by, scope_type FROM message_deletions WHERE message_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $deletedForMe = [];
            $deletedForAll = [];
            foreach ($stmt->fetchAll() as $d) {
                if ($d['scope_type'] === 'everyone') {
                    $deletedForAll[(int)$d['message_id']] = true;
                }
                if ((int)$d['deleted_by'] === $userId) {
                    $deletedForMe[(int)$d['message_id']] = true;
                }
            }

            foreach ($messages as &$m) {
                $m['is_edited']       = isset($edits[(int)$m['id']]);
                $m['deleted_for_me']  = isset($deletedForMe[(int)$m['id']]);
                $m['deleted_for_all'] = isset($deletedForAll[(int)$m['id']]);
                if ($m['deleted_for_me'] || $m['deleted_for_all']) {
                    $m['type'] = 'deleted';
                    $m['body'] = '';
                }
            }
            unset($m);
        }

        Response::success($messages);
    }

    // POST /api/v1/conversations/{id}/messages
    public function send(int $convId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->requireMember($convId, $userId);

        // في المجموعات: فحص إعداد «المرسل مسموح للمشرفين فقط»
        try {
            $gStmt = $this->pdo->prepare(
                'SELECT g.id, gs.only_admins_can_message FROM groups g '
                . 'LEFT JOIN group_settings gs ON gs.group_id = g.id '
                . 'WHERE g.conversation_id = ? LIMIT 1'
            );
            $gStmt->execute([$convId]);
            $group = $gStmt->fetch();
            if ($group && (int)($group['only_admins_can_message'] ?? 0) === 1) {
                $rStmt = $this->pdo->prepare(
                    'SELECT role FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1'
                );
                $rStmt->execute([$convId, $userId]);
                $role = (string)($rStmt->fetchColumn() ?: '');
                if ($role !== 'owner' && $role !== 'admin') {
                    Response::forbidden('المرسل مسموح للمشرفين فقط في هذه المجموعة');
                }
            }
        } catch (\Throwable $e) { /* لا نعطل الإرسال بسبب فشل فحص الإعداد */ }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v    = Validator::make($body)->required('client_message_id', 'معرف الرسالة');

        if ($v->fails()) {
            Response::validationError($v->errors());
        }

        $type            = $body['type'] ?? 'text';
        $messageBody     = htmlspecialchars(strip_tags(trim($body['body'] ?? '')), ENT_QUOTES, 'UTF-8');
        $clientMessageId = trim($body['client_message_id']);
        $replyToId       = !empty($body['reply_to_message_id']) ? (int)$body['reply_to_message_id'] : null;
        $fileId          = !empty($body['file_id']) ? (int)$body['file_id'] : null;

        // إعداد الرسائل المختفية الخاص بالطرف المُرسل في هذه المحادثة
        $memberStmt = $this->pdo->prepare(
            'SELECT disappear_after FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1'
        );
        $memberStmt->execute([$convId, $userId]);
        $memberDisappearing = (int)($memberStmt->fetchColumn() ?: 0);
        $disappearAfter   = isset($body['disappear_after']) ? (int)$body['disappear_after'] : $memberDisappearing;
        if (!in_array($disappearAfter, [0, 86400, 3600, 604800, -1], true)) {
            // Fall back to the admin-configured system default for all users (0 = never)
            try {
                $dflt = (int)($this->pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'message_type_default'")->fetchColumn() ?: 0);
                if (in_array($dflt, [0, 86400, 3600, 604800, -1], true)) {
                    $disappearAfter = $dflt;
                } else {
                    $disappearAfter = 0;
                }
            } catch (\Throwable $e) {
                $disappearAfter = 0;
            }
        }

        if ($type === 'text' && empty($messageBody)) {
            Response::error('نص الرسالة لا يمكن أن يكون فارغاً', 'EMPTY_MESSAGE', 400);
        }

        // Idempotency: check client_message_id
        $stmt = $this->pdo->prepare(
            'SELECT id, uuid FROM messages WHERE conversation_id = ? AND client_message_id = ? LIMIT 1'
        );
        $stmt->execute([$convId, $clientMessageId]);
        $existing = $stmt->fetch();
        if ($existing) {
            Response::success($this->getMessageById((int)$existing['id']), 'تم إرسال الرسالة مسبقاً');
        }

        $this->pdo->beginTransaction();
        try {
            $uuid = UuidHelper::generate();
            $this->pdo->prepare(
                'INSERT INTO messages (uuid, conversation_id, sender_id, reply_to_message_id, type, body, file_id, client_message_id, status, disappear_after, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "sent", ?, datetime("now"), datetime("now"))'
            )->execute([$uuid, $convId, $userId, $replyToId, $type, $messageBody, $fileId, $clientMessageId, $disappearAfter]);

            $messageId = (int)$this->pdo->lastInsertId();

            // Update conversation last_message_id and updated_at
            $this->pdo->prepare(
                'UPDATE conversations SET last_message_id = ?, updated_at = datetime("now") WHERE id = ?'
            )->execute([$messageId, $convId]);

            $this->pdo->commit();

            // Send FCM notifications to other members
            $this->sendMessageNotifications($convId, $userId, $messageBody);

            $message = $this->getMessageById($messageId);
            Response::success($message, 'تم إرسال الرسالة', 201);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Send message error: ' . $e->getMessage());
            Response::error('فشل في إرسال الرسالة: ' . substr($e->getMessage(), 0, 120), 'SEND_FAILED', 500);
        }
    }

    // PUT /api/v1/messages/{id}
    public function update(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $msg = $this->getMessageById($id);
        if (!$msg) {
            Response::notFound('الرسالة غير موجودة');
        }

        if ((int)$msg['sender_id'] !== $userId) {
            Response::forbidden('لا يمكنك تعديل رسالة شخص آخر');
        }

        // Enforcement of the admin-configured edit time limit (minutes, 0 = unlimited)
        $this->enforceMessageTimeLimit('edit_time_limit_minutes', (int)$msg['id'], 'انتهت المدة المسموحة لتعديل الرسالة');

        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $newBody = htmlspecialchars(strip_tags(trim($body['body'] ?? '')), ENT_QUOTES, 'UTF-8');

        if (empty($newBody)) {
            Response::error('نص الرسالة لا يمكن أن يكون فارغاً', 'EMPTY_MESSAGE', 400);
        }

        // Save edit history (before/after) for admin tracking
        $this->pdo->prepare(
            'INSERT INTO message_edits (message_id, conversation_id, user_id, old_body, new_body, edited_at)
             VALUES (?, ?, ?, ?, ?, datetime("now"))'
        )->execute([$id, (int)$msg['conversation_id'], $userId, $msg['body'], $newBody]);

        $this->pdo->prepare('UPDATE messages SET body = ?, updated_at = datetime("now") WHERE id = ?')
                  ->execute([$newBody, $id]);

        // Notify other members (for both-parties update sync)
        $this->notifyMessageEvent((int)$msg['conversation_id'], $id, 'edited', (int)$msg['sender_id']);

        Response::success($this->getMessageById($id), 'تم تعديل الرسالة');
    }

    // DELETE /api/v1/messages/{id}
    public function delete(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $msg = $this->getMessageById($id);
        if (!$msg) {
            Response::notFound('الرسالة غير موجودة');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $forAll = filter_var($body['for_all'] ?? $body['everyone'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // الحذف لدى الجميع: للمرسل فقط. الحذف لدي فقط: لأي عضو في المحادثة.
        if ((int)$msg['sender_id'] !== $userId && $forAll) {
            Response::forbidden('لا يمكنك حذف رسالة شخص آخر لدى الجميع');
        }

        // Enforcement of the admin-configured delete time limit (minutes, 0 = unlimited)
        $this->enforceMessageTimeLimit('delete_time_limit_minutes', (int)$msg['id'], 'انتهت المدة المسموحة لحذف الرسالة');
        $scope = $forAll ? 'everyone' : 'self';

        // Save deletion record with original content for admin tracking (before deletion)
        $this->pdo->prepare(
            'INSERT INTO message_deletions (message_id, conversation_id, deleted_by, original_body, original_type, scope_type, deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime("now"))'
        )->execute([$id, (int)$msg['conversation_id'], $userId, $msg['body'], $msg['type'], $scope]);

        if ($forAll) {
            // Delete for everyone: mark deleted server-side for all members
            $this->pdo->prepare(
                'UPDATE messages SET status = "deleted", deleted_at = datetime("now"), body = NULL, updated_at = datetime("now") WHERE id = ?'
            )->execute([$id]);
        } else {
            // Delete for self only: record per-user deletion (keeps the message visible to others)
            $this->pdo->prepare(
                "INSERT OR REPLACE INTO message_deletions (message_id, conversation_id, deleted_by, original_body, original_type, scope_type, deleted_at)
                 VALUES (?, ?, ?, ?, ?, 'self', datetime('now'))"
            )->execute([$id, (int)$msg['conversation_id'], $userId, $msg['body'] ?? '', $msg['type'] ?? 'text']);
        }

        // Notify other members to sync deletion on their devices
        $this->notifyMessageEvent((int)$msg['conversation_id'], $id, $forAll ? 'deleted' : 'deleted_for_me', (int)$msg['sender_id']);

        Response::success(null, $forAll ? 'تم حذف الرسالة لدى الجميع' : 'تم حذف الرسالة لديك');
    }

    /**
     * Enforce the admin-configured time limit (in minutes) for editing/deleting a message.
     * Uses ONLY MySQL clocks (UNIX_TIMESTAMP) to avoid any PHP timezone mismatch.
     * Pass the message id as $messageId.
     */
    private function enforceMessageTimeLimit(string $settingKey, int $messageId, string $expiredMessage): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
            $stmt->execute([$settingKey]);
            $limit = (int)($stmt->fetchColumn() ?: 0);
            if ($limit > 0) {
                // Both timestamps come from MySQL itself, so they are always consistent
                $stmt = $this->pdo->prepare(
                    "SELECT (strftime('%s', 'now') - strftime('%s', created_at)) AS age_seconds FROM messages WHERE id = ?"
                );
                $stmt->execute([$messageId]);
                $ageSeconds = (int)($stmt->fetchColumn() ?: 0);
                $sentMinutesAgo = $ageSeconds / 60;
                if ($sentMinutesAgo > $limit) {
                    Response::forbidden($expiredMessage);
                }
            }
        } catch (\Throwable $e) {
            // Database errors must never block message operations
            error_log('Time-limit check error: ' . $e->getMessage());
        }
    }

    // POST /api/v1/messages/{id}/read
    public function markRead(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $message = $this->getMessageById($id);
        if (!$message) {
            Response::notFound('الرسالة غير موجودة');
        }
        $this->requireMember((int)$message['conversation_id'], $userId);

        $this->pdo->prepare(
            "INSERT OR IGNORE INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, datetime('now'))"
        )->execute([$id, $userId]);

        // Update message status to read if all recipients have read it
        $this->pdo->prepare(
            'UPDATE messages SET status = "read", updated_at = datetime("now") WHERE id = ? AND status NOT IN ("deleted", "read")'
        )->execute([$id]);

        // Disappearing messages: delete immediately after reading when disappear_after = -1
        if ((int)($message['disappear_after'] ?? 0) === -1) {
            $this->pdo->prepare(
                'INSERT INTO message_deletions (message_id, conversation_id, deleted_by, original_body, original_type, scope_type, deleted_at)
                 VALUES (?, ?, ?, ?, ?, "expired", datetime("now"))'
            )->execute([$id, (int)$message['conversation_id'], $userId, $message['body'], $message['type']]);
            $this->pdo->prepare(
                'UPDATE messages SET status = "deleted", deleted_at = datetime("now"), body = NULL, updated_at = datetime("now") WHERE id = ?'
            )->execute([$id]);
            $this->notifyMessageEvent((int)$message['conversation_id'], $id, 'disappeared', $userId);
        }

        // Notify sender that their message was read (update the blue ticks on sender side)
        $this->notifyMessageEvent((int)$message['conversation_id'], $id, 'read', $userId);

        Response::success(null, 'تم تعليم الرسالة كمقروءة');
    }

    // POST /api/v1/messages/{id}/reaction
    public function react(int $id): void
    {
        $auth     = AuthMiddleware::authenticate();
        $userId   = (int)$auth['user_id'];
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $reaction = trim($body['reaction'] ?? '');

        $message = $this->getMessageById($id);
        if (!$message) {
            Response::notFound('الرسالة غير موجودة');
        }
        $this->requireMember((int)$message['conversation_id'], $userId);
        if (mb_strlen($reaction) > 20) {
            Response::error('التفاعل طويل جدًا', 'INVALID_REACTION', 422);
        }

        if (empty($reaction)) {
            // Remove reaction
            $this->pdo->prepare('DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?')
                      ->execute([$id, $userId]);
            Response::success(null, 'تم إزالة التفاعل');
        }

        $this->pdo->prepare(
            "INSERT OR REPLACE INTO message_reactions (message_id, user_id, reaction, created_at)
             VALUES (?, ?, ?, datetime('now'))"
        )->execute([$id, $userId, $reaction]);

        Response::success(null, 'تم إضافة التفاعل');
    }

    // =====================================================
    // Private Helpers
    // =====================================================

    /**
     * Send FCM data notification to other members when a message is edited or deleted (sync for both parties).
     */
    private function notifyMessageEvent(int $convId, int $messageId, string $event, int $exceptUserId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT ud.fcm_token FROM user_devices ud
                 JOIN conversation_members cm ON cm.user_id = ud.user_id
                 WHERE cm.conversation_id = ? AND ud.user_id != ? AND ud.fcm_token IS NOT NULL AND ud.fcm_token != ""'
            );
            $stmt->execute([$convId, $exceptUserId]);
            $devices = $stmt->fetchAll();
            if (empty($devices)) return;
            foreach ($devices as $device) {
                FCMHelper::sendToDevice(
                    $device['fcm_token'],
                    'NOVA Messenger',
                    $event === 'edited' ? 'تم تعديل رسالة' : 'تم حذف رسالة',
                    [
                        'type' => 'message_event',
                        'event' => $event,
                        'message_id' => (string)$messageId,
                        'conversation_id' => (string)$convId,
                    ]
                );
            }
        } catch (\Throwable $e) {
            error_log('Message event notification error: ' . $e->getMessage());
        }
    }

    /**
     * Expire "disappear after read" (-1) messages once every participant has read them.
     */
    private function expireAfterRead(int $convId): void
    {
        try {
            $this->pdo->prepare(
                'UPDATE messages m
                 SET status = "deleted", deleted_at = datetime("now"), body = NULL, updated_at = datetime("now")
                 WHERE m.conversation_id = ? AND m.disappear_after = -1 AND m.deleted_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM conversation_members cm2
                       WHERE cm2.conversation_id = m.conversation_id AND cm2.left_at IS NULL AND cm2.user_id != m.sender_id
                         AND NOT EXISTS (
                             SELECT 1 FROM message_reads mr
                             WHERE mr.message_id = m.id AND mr.user_id = cm2.user_id
                         )
                   )'
            )->execute([$convId]);
        } catch (\Throwable $e) {
            error_log('Expire after-read messages error: ' . $e->getMessage());
        }
    }

    /**
     * Expire time-based disappearing messages in a conversation.
     */
    private function expireDisappearingMessages(int $convId): void
    {
        try {
            $this->pdo->prepare(
                "UPDATE messages SET status = 'deleted', deleted_at = datetime('now'), body = NULL, updated_at = datetime('now')
                 WHERE conversation_id = ? AND disappear_after > 0 AND deleted_at IS NULL
                 AND (strftime('%s', 'now') - strftime('%s', COALESCE(updated_at, created_at))) > disappear_after"
            )->execute([$convId]);
        } catch (\Throwable $e) {
            error_log('Expire disappearing messages error: ' . $e->getMessage());
        }
    }

    private function requireMember(int $convId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1'
        );
        $stmt->execute([$convId, $userId]);
        if (!$stmt->fetch()) {
            Response::forbidden('ليس لديك صلاحية الوصول إلى هذه المحادثة');
        }
    }

    private function getMessageById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.uuid, m.conversation_id, m.sender_id, m.reply_to_message_id,
                    m.type, m.body, m.file_id, m.client_message_id, m.status, m.disappear_after,
                    m.created_at, m.updated_at, m.deleted_at,
                    u.name AS sender_name, u.avatar AS sender_avatar
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function sendMessageNotifications(int $convId, int $senderId, string $messagePreview): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT name, avatar FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();
            if (!$sender) return;

            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT ud.fcm_token FROM user_devices ud
                 JOIN conversation_members cm ON cm.user_id = ud.user_id
                 WHERE cm.conversation_id = ? AND ud.user_id != ? AND ud.fcm_token IS NOT NULL AND ud.fcm_token != ""'
            );
            $stmt->execute([$convId, $senderId]);
            $devices = $stmt->fetchAll();

            $title = $sender['name'];
            $body = mb_substr($messagePreview, 0, 100);
            $data = ['conversation_id' => (string)$convId];
	            $avatar = $sender['avatar'];

            foreach ($devices as $device) {
                FCMHelper::sendMessageNotification(
                    $device['fcm_token'],
                    $title,
                    $body,
                    (string)$convId,
                    $avatar
                );
            }

            // In-app notification (real-time polling fallback): store in notifications table
            // MUST run regardless of FCM devices (no Service Account configured)
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT cm.user_id FROM conversation_members cm WHERE cm.conversation_id = ? AND cm.user_id != ?'
            );
            $stmt->execute([$convId, $senderId]);
            foreach ($stmt->fetchAll() as $member) {
                $this->pdo->prepare(
                    'INSERT INTO notifications (user_id, type, title, body, data_json, created_at)
                     VALUES (?, "message", ?, ?, ?, datetime("now"))'
                )->execute([
                    (int)$member['user_id'],
                    $title,
                    $body,
                    json_encode(['conversation_id' => $convId, 'sender_id' => $senderId, 'avatar' => $sender['avatar'] ?? null], JSON_UNESCAPED_UNICODE),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('FCM notification error: ' . $e->getMessage());
        }
    }

    // POST /api/v1/conversations/{id}/media — رفع فيديو/صورة/صوت وإنشاء رسالة مرفقة تلقائيًا
    public function uploadMedia(int $convId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->requireMember($convId, $userId);

        if (!isset($_FILES['attachment']) || !is_uploaded_file($_FILES['attachment']['tmp_name'])) {
            Response::error('لم يتم رفع أي ملف', 'NO_FILE', 400);
        }
        $file     = $_FILES['attachment'];
        $clientId = trim($_POST['client_message_id'] ?? '');
        if ($clientId === '') {
            Response::error('معرف الرسالة مطلوب', 'MISSING_CLIENT_ID', 400);
        }

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/quicktime',
            'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/wav',
        ];
        $maxSize = 100 * 1024 * 1024; // 100MB

        // فحص نوع الملف الفعلي عبر البصمات (magic bytes) لمنع انتحال النوع
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = (string)finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        // تطبيع الأنواع التي تعيدها بعض أنظمة finfo خارج القائمة القياسية
        // (مثال: audio/x-m4a لملفات M4A، video/x-m4v، video/x-matroska لملفات MKV/WebM)
        $clientMime   = trim($_FILES['attachment']['type'] ?? '');
        $clientHintOk = in_array($clientMime, $allowedMimes, true);
        if (!in_array($realMime, $allowedMimes, true)) {
            $norm = match ($realMime) {
                'audio/x-m4a', 'audio/aac', 'audio/x-aac', 'audio/3gpp', 'audio/3gpp2' => $clientHintOk && str_starts_with($clientMime, 'audio/') ? $clientMime : 'audio/mp4',
                'video/x-m4v' => 'video/mp4',
                'video/x-matroska' => 'video/webm',
                'audio/x-matroska' => 'audio/webm',
                default => $realMime,
            };
            $realMime = $norm;
        }
        if (!in_array($realMime, $allowedMimes, true)) {
            Response::error('نوع الملف غير مسموح به', 'INVALID_FILE_TYPE', 400);
        }
        if ((int)$file['size'] > $maxSize) {
            Response::error('حجم الملف يتجاوز الحد المسموح به (100MB)', 'FILE_TOO_LARGE', 400);
        }

        $extFromMime = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
            'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3', 'audio/wav' => 'wav',
        ];
        $type = in_array($realMime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            ? 'image'
            : (in_array($realMime, ['audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/wav'], true) ? 'audio' : 'video');

        $uuidName = UuidHelper::generate();
        $ext      = $extFromMime[$realMime] ?? 'bin';
        $fileName = $uuidName . '.' . $ext;

        $destDir = ($_ENV['STORAGE_PATH'] ?? __DIR__ . '/../storage') . '/attachments/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $destDir . $fileName)) {
            Response::error('فشل في رفع الملف', 'UPLOAD_FAILED', 500);
        }

        $relPath   = 'attachments/' . $fileName;
        $duration  = null;
        $width     = null;
        $height    = null;
        $thumbRel  = null;

        // استخلاص المدة والأبعاد للفيديو/الصوت + صورة مصغرة للفيديو
        try {
            $probe = json_decode(shell_exec('ffprobe -v quiet -print_format json -show_format -show_streams ' . escapeshellarg($destDir . $fileName) . ' 2>&1') ?? '{}', true);
            if (is_array($probe) && isset($probe['format']['duration'])) {
                $duration = (int)round((float)$probe['format']['duration']);
            }
            if (is_array($probe) && !empty($probe['streams'])) {
                foreach ($probe['streams'] as $stream) {
                    if (isset($stream['width'])) {
                        $width  = (int)$stream['width'];
                        $height = (int)$stream['height'];
                        break;
                    }
                }
            }
            if ($type === 'video') {
                $thumbFile = $uuidName . '_thumb.jpg';
                shell_exec('ffmpeg -y -v quiet -ss 1 -i ' . escapeshellarg($destDir . $fileName) . ' -frames:v 1 -vf scale=320:-1 ' . escapeshellarg($destDir . $thumbFile) . ' 2>&1');
                if (is_file($destDir . $thumbFile)) {
                    $thumbRel = 'attachments/' . $thumbFile;
                }
            }
        } catch (\Throwable $e) {
            error_log('Media probe error: ' . $e->getMessage());
        }

        // منع الإرسال المكرر (idempotency)
        $stmt = $this->pdo->prepare('SELECT id FROM messages WHERE conversation_id = ? AND client_message_id = ? LIMIT 1');
        $stmt->execute([$convId, $clientId]);
        if ($stmt->fetch()) {
            Response::success(null, 'تم إرسال الرسالة مسبقًا');
        }

        // تسجيل المرفق
        $attachmentUuid = UuidHelper::generate();
        $stmt = $this->pdo->prepare(
            'INSERT INTO attachments (uuid, uploader_id, type, original_name, file_name, mime_type, file_size, storage_path, thumbnail_path, duration, width, height, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))'
        );
        $stmt->execute([
            $attachmentUuid, $userId, $type, basename((string)$file['name']), $fileName, $realMime,
            (int)$file['size'], $relPath, $thumbRel, $duration, $width, $height,
        ]);
        $fileId = (int)$this->pdo->lastInsertId();

        // إعداد الرسائل المختفية للمستخدم
        $memberStmt = $this->pdo->prepare('SELECT disappear_after FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1');
        $memberStmt->execute([$convId, $userId]);
        $disappearAfter = (int)($memberStmt->fetchColumn() ?: 0);

        $this->pdo->beginTransaction();
        try {
            $msgUuid = UuidHelper::generate();
            $this->pdo->prepare(
                'INSERT INTO messages (uuid, conversation_id, sender_id, reply_to_message_id, type, body, file_id, client_message_id, status, disappear_after, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, ?, \'\', ?, ?, "sent", ?, datetime("now"), datetime("now"))'
            )->execute([$msgUuid, $convId, $userId, $type, $fileId, $clientId, $disappearAfter]);
            $msgId = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE conversations SET last_message_id = ?, updated_at = datetime("now") WHERE id = ?')
                  ->execute([$msgId, $convId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            file_put_contents('/tmp/send_media_err.log', 'Send media error: ' . $e->getMessage() . "\n", FILE_APPEND);
            Response::error('فشل في إرسال الوسائط', 'SEND_FAILED', 500);
        }

        Response::success(
            [
                'id' => $msgId, 'type' => $type, 'file_id' => $fileId,
                'file_path' => $relPath, 'mime_type' => $realMime,
                'duration' => $duration, 'width' => $width, 'height' => $height,
                'thumbnail_path' => $thumbRel,
            ],
            'تم إرسال الوسائط',
            201
        );
    }

    // POST /api/v1/messages/voice (رفع رسالة صوتية)
    public function uploadVoice(): void
    {
        $auth = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        if (!isset($_FILES['voice']) || !is_uploaded_file($_FILES['voice']['tmp_name'])) {
            Response::error('لم يتم رفع أي ملف صوتي', 'NO_FILE', 400);
        }

        $file     = $_FILES['voice'];
        $convId   = (int)($_POST['conversation_id'] ?? 0);
        $clientId = $_POST['client_message_id'] ?? null;
        $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;

        if (!$convId) {
            Response::error('معرف المحادثة مطلوب', 'MISSING_CONVERSATION', 400);
        }

        $this->requireMember($convId, $userId);

        $allowed  = ['audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/wav'];
        $maxSize  = 10 * 1024 * 1024; // 10MB

        if (!in_array($file['type'], $allowed, true)) {
            Response::error('نوع الملف غير مسموح به', 'INVALID_FILE_TYPE', 400);
        }
        if ($file['size'] > $maxSize) {
            Response::error('حجم الملف يتجاوز الحد المسموح به (10MB)', 'FILE_TOO_LARGE', 400);
        }

        $fileName = UuidHelper::generate() . '.webm';
        $destDir  = ($_ENV['STORAGE_PATH'] ?? __DIR__ . '/../storage') . '/voices/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $destDir . $fileName)) {
            Response::error('فشل في رفع الملف الصوتي', 'UPLOAD_FAILED', 500);
        }

        // تسجيل المرفق في جدول attachments
        $relPath = 'voices/' . $fileName;
        $uuid = UuidHelper::generate();
        $stmt = $this->pdo->prepare(
            'INSERT INTO attachments (uuid, uploader_id, type, original_name, file_name, mime_type, file_size, storage_path, duration, created_at)
             VALUES (?, ?, "audio", "voice_message.webm", ?, ?, ?, ?, ?, datetime("now"))'
        );
        $stmt->execute([$uuid, $userId, $fileName, $file['type'], (int)$file['size'], $relPath, $duration]);
        $fileId = (int)$this->pdo->lastInsertId();

        // حفظ الرسالة (مثل send() لكن صوتية)
        $this->pdo->beginTransaction();
        try {
            $msgUuid = UuidHelper::generate();
            $this->pdo->prepare(
                'INSERT INTO messages (uuid, conversation_id, sender_id, reply_to_message_id, type, body, file_id, client_message_id, status, disappear_after, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, "audio", ?, ?, ?, "sent", 0, datetime("now"), datetime("now"))'
            )->execute([$msgUuid, $convId, $userId, '', $fileId, $clientId ?? $msgUuid]);
            $msgId = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE conversations SET last_message_id = ?, updated_at = datetime("now") WHERE id = ?')
                  ->execute([$msgId, $convId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Response::error('فشل في إرسال الرسالة الصوتية', 'SEND_FAILED', 500);
        }

        // إشعار المستخدمين الآخرين (عبر polling /conversations/{id}/messages)
        $this->pdo->prepare('UPDATE conversations SET updated_at = datetime("now") WHERE id = ?')->execute([$convId]);

        Response::success(['id' => $msgId, 'type' => 'audio', 'file_id' => $fileId], 'تم إرسال الرسالة الصوتية', 201);
    }

    // POST /api/v1/conversations/{id}/typing  {typing: true}
    public function setTyping(int $convId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $this->requireMember($convId, $userId);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $typing = (bool)($body['typing'] ?? true);

        try {
            if ($typing) {
                // صالح 4 ثوانٍ؛ كل حدث كتابة يعيد تعيينه
                $this->pdo->prepare('DELETE FROM typing_status WHERE conversation_id = ? AND user_id = ?')->execute([$convId, $userId]);
                $this->pdo->prepare(
                    'INSERT INTO typing_status (conversation_id, user_id, expires_at, updated_at) VALUES (?, ?, datetime("now", "localtime", "+4 seconds"), datetime("now", "localtime"))'
                )->execute([$convId, $userId]);
                Response::success(['typing' => true, 'expires_at' => '+4s'], 'ok');
            } else {
                $this->pdo->prepare('DELETE FROM typing_status WHERE conversation_id = ? AND user_id = ?')->execute([$convId, $userId]);
                Response::success(['typing' => false], 'ok');
            }
        } catch (\Throwable $e) {
            error_log('Typing status error: ' . $e->getMessage());
            Response::success(['typing' => false], 'ok');
        }
    }

    // GET /api/v1/conversations/{id}/typing
    public function getTyping(int $convId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $this->requireMember($convId, $userId);

        try {
            $stmt = $this->pdo->prepare(
                'SELECT ts.user_id, u.name, u.avatar
                 FROM typing_status ts
                 JOIN users u ON u.id = ts.user_id
                 WHERE ts.conversation_id = ? AND ts.expires_at > datetime("now", "localtime") AND ts.user_id != ?'
            );
            $stmt->execute([$convId, $userId]);
            $typing = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::success(['typing_users' => $typing], 'ok');
        } catch (\Throwable $e) {
            error_log('Get typing error: ' . $e->getMessage());
            Response::success(['typing_users' => []], 'ok');
        }
    }
}
