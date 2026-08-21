<?php
/**
 * NOVA Messenger - Conversation Controller
 */

declare(strict_types=1);

class ConversationController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // GET /api/v1/conversations
    public function index(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.uuid, c.type, c.title, c.avatar, c.updated_at,
                    cm.is_muted, cm.is_pinned, cm.last_read_message_id,
                    cm.disappear_after,
                    m.body AS last_message_body, m.type AS last_message_type,
                    m.created_at AS last_message_at, m.sender_id AS last_message_sender_id,
                    (SELECT COUNT(*) FROM messages msg
                     WHERE msg.conversation_id = c.id
                       AND msg.id > COALESCE(cm.last_read_message_id, 0)
                       AND msg.sender_id != ? AND msg.deleted_at IS NULL) AS unread_count
             FROM conversations c
             JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = ?
             LEFT JOIN messages m ON m.id = c.last_message_id
             WHERE cm.left_at IS NULL
             ORDER BY cm.is_pinned DESC, c.updated_at DESC
             LIMIT 100'
        );
        $stmt->execute([$userId, $userId]);
        $conversations = $stmt->fetchAll();

        // Enrich: is_group, group_id, member_count
        foreach ($conversations as &$conv) {
            $conv['is_group'] = $conv['type'] === 'group';
            if ($conv['type'] === 'group') {
                $gs = $this->pdo->prepare('SELECT id FROM groups WHERE conversation_id = ? LIMIT 1');
                $gs->execute([(int)$conv['id']]);
                $row = $gs->fetch();
                $conv['group_id'] = $row ? (int)$row['id'] : null;
            }
            $mc = $this->pdo->prepare(
                'SELECT COUNT(*) FROM conversation_members WHERE conversation_id = ? AND left_at IS NULL'
            );
            $mc->execute([(int)$conv['id']]);
            $conv['member_count'] = (int)$mc->fetchColumn();
            // Enrich: typing_users — المستخدمون الذين يكتبون حاليًا في هذه المحادثة
            try {
                $tp = $this->pdo->prepare(
                    'SELECT t.user_id, u.name, u.avatar
                     FROM typing_status t
                     JOIN users u ON u.id = t.user_id
                     WHERE t.conversation_id = ? AND t.expires_at > datetime("now")'
                );
                $tp->execute([(int)$conv['id']]);
                $conv['typing_users'] = array_map(function ($r) {
                    $r['user_id'] = (int)$r['user_id'];
                    return $r;
                }, $tp->fetchAll());
            } catch (\Throwable $e) {
                $conv['typing_users'] = [];
            }
            if ($conv['type'] === 'private') {
                $other = $this->getOtherParticipant((int)$conv['id'], $userId);
                if ($other) {
                    $conv['title']  = $other['name'];
                    $conv['avatar'] = $other['avatar'];
                    $conv['other_user'] = $other;
                }
            }
        }

        Response::success($conversations);
    }

    // POST /api/v1/conversations
    public function create(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $type = $body['type'] ?? 'private';

        if ($type === 'private') {
            $targetId = (int)($body['user_id'] ?? 0);
            if (!$targetId || $targetId === $userId) {
                Response::error('يجب تحديد مستخدم صحيح', 'MISSING_USER_ID', 400);
            }

            // فرض الخصوصية: هل يسمح المستخدم المستهدف باستقبال رسائل من هذا المستخدم؟
            $userCtrl = new UserController();
            $targetPrivacy = $this->pdo->prepare('SELECT messages_from FROM privacy_settings WHERE user_id = ? LIMIT 1');
            $targetPrivacy->execute([$targetId]);
            $tp = $targetPrivacy->fetch();
            $messagesFrom = (int)($tp['messages_from'] ?? 2); // 2=الجميع، 1=جهات الاتصال، 0=لا أحد

            if ($messagesFrom === 0) {
                Response::forbidden('هذا المستخدم لا يسمح باستقبال رسائل جديدة');
            }
            if ($messagesFrom === 1) {
                $isContact = $this->pdo->prepare('SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ? LIMIT 1');
                $isContact->execute([$targetId, $userId]);
                if (!$isContact->fetch()) {
                    Response::forbidden('هذا المستخدم يسمح باستقبال رسائل من جهات اتصاله فقط');
                }
            }

            // فحص الحظر
            if ($userCtrl->isBlockedEither($userId, $targetId)) {
                Response::forbidden('لا يمكنك بدء محادثة مع هذا المستخدم');
            }

            // Check if private conversation already exists
            $stmt = $this->pdo->prepare(
                'SELECT c.id, c.uuid FROM conversations c
                 JOIN conversation_members cm1 ON cm1.conversation_id = c.id AND cm1.user_id = ?
                 JOIN conversation_members cm2 ON cm2.conversation_id = c.id AND cm2.user_id = ?
                 WHERE c.type = "private" AND cm1.left_at IS NULL AND cm2.left_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$userId, $targetId]);
            $existing = $stmt->fetch();

            if ($existing) {
                Response::success($this->getConversationById((int)$existing['id'], $userId));
            }

            // Create new private conversation
            $this->pdo->beginTransaction();
            try {
                $uuid = UuidHelper::generate();
                $this->pdo->prepare(
                    'INSERT INTO conversations (uuid, type, created_by, created_at, updated_at) VALUES (?, "private", ?, datetime("now"), datetime("now"))'
                )->execute([$uuid, $userId]);
                $convId = (int)$this->pdo->lastInsertId();

                $this->addMember($convId, $userId, 'owner');
                $this->addMember($convId, $targetId, 'member');

                $this->pdo->commit();
                Response::success($this->getConversationById($convId, $userId), 'تم إنشاء المحادثة', 201);
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                error_log('Create conversation error: ' . $e->getMessage());
                Response::error('فشل في إنشاء المحادثة', 'CREATE_FAILED', 500);
            }
        } elseif ($type === 'group') {
            SettingsHelper::enforceFeature($this->pdo, 'allow_groups', 'المجموعات');
            $title   = htmlspecialchars(strip_tags(trim($body['title'] ?? '')), ENT_QUOTES, 'UTF-8');
            $members = $body['members'] ?? [];

            if (empty($title)) {
                Response::error('يجب تحديد اسم المجموعة', 'MISSING_TITLE', 400);
            }

            $this->pdo->beginTransaction();
            try {
                $uuid = UuidHelper::generate();
                $this->pdo->prepare(
                    'INSERT INTO conversations (uuid, type, title, created_by, created_at, updated_at) VALUES (?, "group", ?, ?, datetime("now"), datetime("now"))'
                )->execute([$uuid, $title, $userId]);
                $convId = (int)$this->pdo->lastInsertId();

                // Create group record
                $groupUuid = UuidHelper::generate();
                $this->pdo->prepare(
                    'INSERT INTO groups (conversation_id, name, created_by, created_at, updated_at) VALUES (?, ?, ?, datetime("now"), datetime("now"))'
                )->execute([$convId, $title, $userId]);
                $groupId = (int)$this->pdo->lastInsertId();

                // Default group settings
                $this->pdo->prepare(
                    'INSERT INTO group_settings (group_id, created_at, updated_at) VALUES (?, datetime("now"), datetime("now"))'
                )->execute([$groupId]);

                // Add creator as owner
                $this->addMember($convId, $userId, 'owner');

                // Add other members
                foreach ($members as $memberId) {
                    if ((int)$memberId !== $userId) {
                        $this->addMember($convId, (int)$memberId, 'member');
                    }
                }

                $this->pdo->commit();
                Response::success($this->getConversationById($convId, $userId), 'تم إنشاء المجموعة', 201);
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                error_log('Create group error: ' . $e->getMessage());
                Response::error('فشل في إنشاء المجموعة', 'CREATE_FAILED', 500);
            }
        } else {
            Response::error('نوع المحادثة غير صحيح', 'INVALID_TYPE', 400);
        }
    }

    // GET /api/v1/conversations/{id}
    public function show(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->requireMember($id, $userId);
        Response::success($this->getConversationById($id, $userId));
    }

    // DELETE /api/v1/conversations/{id}
    public function delete(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->requireMember($id, $userId);

        // Mark as left (soft delete for the user)
        $this->pdo->prepare(
            "UPDATE conversation_members SET left_at = datetime('now') WHERE conversation_id = ? AND user_id = ?"
        )->execute([$id, $userId]);

        Response::success(null, 'تم حذف المحادثة');
    }

    // POST /api/v1/conversations/{id}/mute
    public function mute(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $this->requireMember($id, $userId);
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $isMuted = $body['muted'] ?? true;
        $duration = (int)($body['duration'] ?? 0); // minutes, 0 = forever

        $until = null;
        if ($isMuted && $duration > 0) {
            $until = date('Y-m-d H:i:s', time() + ($duration * 60));
        } elseif ($isMuted) {
            $until = '2099-12-31 23:59:59'; // effectively forever
        }

        $this->pdo->prepare(
            'UPDATE conversation_members SET is_muted = ?, muted_until = ? WHERE conversation_id = ? AND user_id = ?'
        )->execute([$isMuted ? 1 : 0, $until, $id, $userId]);

        Response::success(['muted' => $isMuted, 'until' => $until], 'تم تحديث حالة الإشعارات');
    }

    // POST /api/v1/conversations/{id}/pin
    public function pin(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $this->requireMember($id, $userId);

        $this->pdo->prepare(
            'UPDATE conversation_members SET is_pinned = NOT is_pinned WHERE conversation_id = ? AND user_id = ?'
        )->execute([$id, $userId]);

        Response::success(null, 'تم تغيير حالة التثبيت');
    }

    // =====================================================
    // Private Helpers
    // =====================================================

    private function addMember(int $convId, int $userId, string $role = 'member'): void
    {
        // SQLite doesn't support INSERT IGNORE, use INSERT OR IGNORE
        $this->pdo->prepare(
            "INSERT OR IGNORE INTO conversation_members (conversation_id, user_id, role, joined_at, created_at, updated_at)
             VALUES (?, ?, ?, datetime('now'), datetime('now'), datetime('now'))"
        )->execute([$convId, $userId, $role]);
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

    private function getConversationById(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.uuid, c.type, c.title, c.avatar, c.created_by, c.updated_at,
                    cm.role, cm.is_muted, cm.is_pinned
             FROM conversations c
             JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = ?
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $id]);
        $conv = $stmt->fetch();

        if ($conv) {
            $conv['is_group'] = $conv['type'] === 'group';
            if ($conv['type'] === 'group') {
                $gs = $this->pdo->prepare('SELECT id FROM groups WHERE conversation_id = ? LIMIT 1');
                $gs->execute([(int)$conv['id']]);
                $row = $gs->fetch();
                $conv['group_id'] = $row ? (int)$row['id'] : null;
            }
            $mc = $this->pdo->prepare(
                'SELECT COUNT(*) FROM conversation_members WHERE conversation_id = ? AND left_at IS NULL'
            );
            $mc->execute([(int)$conv['id']]);
            $conv['member_count'] = (int)$mc->fetchColumn();
        }

        if ($conv && $conv['type'] === 'private') {
            $other = $this->getOtherParticipant($id, $userId);
            if ($other) {
                $conv['other_user'] = $other;
                $conv['title']      = $other['name'];
                $conv['avatar']     = $other['avatar'];
            }
        }

        return $conv ?: null;
    }

    // PUT /api/v1/conversations/{id} — ضبط الرسائل المختفية للمستخدم الحالي فقط
    public function updateDisappearing(int $convId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $value = $body['disappear_after'] ?? null;
        if (!in_array($value, [0, 3600, 86400, 604800, 2592000, -1], true)) {
            Response::error('القيمة غير صالحة', 'INVALID_VALUE', 400);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE conversation_members SET disappear_after = ?
             WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL'
        );
        $stmt->execute([(int)$value, $convId, $userId]);

        if ($stmt->rowCount() === 0) {
            Response::error('المحادثة غير موجودة', 'NOT_FOUND', 404);
        }

        // عند "بعد القراءة": نعلّم فورًا أن هذا الطرف يريد الإخفاء الفوري
        Response::success(['disappear_after' => (int)$value]);
    }

    private function getOtherParticipant(int $convId, int $myId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.uuid, u.name, u.username, u.avatar, u.is_online, u.last_seen, u.is_verified
             FROM conversation_members cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.conversation_id = ? AND cm.user_id != ? AND cm.left_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$convId, $myId]);
        $row = $stmt->fetch() ?: null;
        if ($row) {
            require_once __DIR__ . '/UserController.php';
            $userCtrl = new UserController();
            $row = $userCtrl->applyPresencePrivacy($row, $myId);
            $row = $userCtrl->filterProfile($row, $myId, (int)$row['id']);
        }
        return $row;
    }
}
