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
                $this->pdo->prepare('UPDATE messages SET status = "delivered", updated_at = NOW() WHERE id = ? AND status = "sent"')
                          ->execute([$m['id']]);
            }
        }
        if (!empty($messages)) {
            $ids = array_map(fn ($m) => (int)$m['id'], $messages);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->prepare(
                "INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
                 SELECT id, ?, NOW() FROM messages WHERE id IN ($placeholders) AND sender_id != ? AND status NOT IN ('deleted', 'read')"
            )->execute(array_merge([$userId], $ids, [$userId]));
            $this->pdo->prepare(
                "UPDATE messages SET status = 'read', updated_at = NOW() WHERE id IN ($placeholders) AND sender_id != ? AND status NOT IN ('deleted', 'read')"
            )->execute(array_merge($ids, [$userId]));
        }

        // Update last_read_message_id
        if (!empty($messages)) {
            $lastId = end($messages)['id'];
            $this->pdo->prepare(
                'UPDATE conversation_members SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)
                 WHERE conversation_id = ? AND user_id = ?'
            )->execute([$lastId, $convId, $userId]);
        }

        // Disappearing messages: expirations
        // 1) رسائل "بعد القراءة" (-1): تُحذف فورًا بعد أن يقرأها جميع المشاركين
        $this->expireAfterRead($convId);
        // 2) رسائل بوقت محدد (86400...): تُحذف عندما يتجاوز الزمن إعداد كل مُرسل
        $this->expireDisappearingMessages($convId);

        Response::success($messages);
    }

    // POST /api/v1/conversations/{id}/messages
    public function send(int $convId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->requireMember($convId, $userId);

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
            $disappearAfter = 0;
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
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "sent", ?, NOW(), NOW())'
            )->execute([$uuid, $convId, $userId, $replyToId, $type, $messageBody, $fileId, $clientMessageId, $disappearAfter]);

            $messageId = (int)$this->pdo->lastInsertId();

            // Update conversation last_message_id and updated_at
            $this->pdo->prepare(
                'UPDATE conversations SET last_message_id = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$messageId, $convId]);

            $this->pdo->commit();

            // Send FCM notifications to other members
            $this->sendMessageNotifications($convId, $userId, $messageBody);

            $message = $this->getMessageById($messageId);
            Response::success($message, 'تم إرسال الرسالة', 201);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Send message error: ' . $e->getMessage());
            Response::error('فشل في إرسال الرسالة', 'SEND_FAILED', 500);
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

        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $newBody = htmlspecialchars(strip_tags(trim($body['body'] ?? '')), ENT_QUOTES, 'UTF-8');

        if (empty($newBody)) {
            Response::error('نص الرسالة لا يمكن أن يكون فارغاً', 'EMPTY_MESSAGE', 400);
        }

        // Save edit history (before/after) for admin tracking
        $this->pdo->prepare(
            'INSERT INTO message_edits (message_id, conversation_id, user_id, old_body, new_body, edited_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$id, (int)$msg['conversation_id'], $userId, $msg['body'], $newBody]);

        $this->pdo->prepare('UPDATE messages SET body = ?, updated_at = NOW() WHERE id = ?')
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

        if ((int)$msg['sender_id'] !== $userId) {
            Response::forbidden('لا يمكنك حذف رسالة شخص آخر');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $forAll = filter_var($body['for_all'] ?? $body['everyone'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $scope = $forAll ? 'everyone' : 'self';

        // Save deletion record with original content for admin tracking (before deletion)
        $this->pdo->prepare(
            'INSERT INTO message_deletions (message_id, conversation_id, deleted_by, original_body, original_type, scope_type, deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$id, (int)$msg['conversation_id'], $userId, $msg['body'], $msg['type'], $scope]);

        if ($forAll) {
            // Delete for everyone: mark deleted server-side for all members
            $this->pdo->prepare(
                'UPDATE messages SET status = "deleted", deleted_at = NOW(), body = NULL, updated_at = NOW() WHERE id = ?'
            )->execute([$id]);
        } else {
            // Delete for self only: update per-user read state, keep message visible to others
            $this->pdo->prepare(
                'UPDATE conversation_members SET last_read_message_id = 0, left_at = left_at WHERE conversation_id = ? AND user_id = ?'
            )->execute([(int)$msg['conversation_id'], $userId]);
            $this->pdo->prepare(
                'INSERT INTO message_reads (message_id, user_id, read_at, deleted_for_me) VALUES (?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE deleted_for_me = 1'
            )->execute([$id, $userId]);
        }

        // Notify other members to sync deletion on their devices
        $this->notifyMessageEvent((int)$msg['conversation_id'], $id, $forAll ? 'deleted' : 'deleted_for_me', (int)$msg['sender_id']);

        Response::success(null, $forAll ? 'تم حذف الرسالة لدى الجميع' : 'تم حذف الرسالة لديك');
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
            'INSERT IGNORE INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())'
        )->execute([$id, $userId]);

        // Update message status to read if all recipients have read it
        $this->pdo->prepare(
            'UPDATE messages SET status = "read", updated_at = NOW() WHERE id = ? AND status NOT IN ("deleted", "read")'
        )->execute([$id]);

        // Disappearing messages: delete immediately after reading when disappear_after = -1
        if ((int)($message['disappear_after'] ?? 0) === -1) {
            $this->pdo->prepare(
                'INSERT INTO message_deletions (message_id, conversation_id, deleted_by, original_body, original_type, scope_type, deleted_at)
                 VALUES (?, ?, ?, ?, ?, "expired", NOW())'
            )->execute([$id, (int)$message['conversation_id'], $userId, $message['body'], $message['type']]);
            $this->pdo->prepare(
                'UPDATE messages SET status = "deleted", deleted_at = NOW(), body = NULL, updated_at = NOW() WHERE id = ?'
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
            'INSERT INTO message_reactions (message_id, user_id, reaction, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE reaction = ?, created_at = NOW()'
        )->execute([$id, $userId, $reaction, $reaction]);

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
                 SET status = "deleted", deleted_at = NOW(), body = NULL, updated_at = NOW()
                 WHERE m.conversation_id = ? AND m.disappear_after = -1 AND m.deleted_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM conversation_members cm2
                       LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = cm2.user_id AND mr.deleted_for_me = 0
                       WHERE cm2.conversation_id = m.conversation_id AND cm2.left_at IS NULL AND cm2.user_id != m.sender_id
                         AND mr.message_id IS NULL
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
                'UPDATE messages SET status = "deleted", deleted_at = NOW(), body = NULL, updated_at = NOW()
                 WHERE conversation_id = ? AND disappear_after > 0 AND deleted_at IS NULL
                   AND TIMESTAMPDIFF(SECOND, COALESCE(updated_at, created_at), NOW()) > disappear_after'
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
                     VALUES (?, "message", ?, ?, ?, NOW())'
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
}
