<?php

/**
 * NOVA Messenger - Groups Controller
 * نظام المجموعات: معلومات المجموعة، إدارة الأعضاء، إعدادات النشر
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

class GroupsController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /** GET /groups/mine — مجموعاتي مع الأعضاء */
    public function mine(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.conversation_id, g.name, g.description, g.avatar, g.created_by,
                    c.title, c.last_message_id, c.updated_at
             FROM groups g
             JOIN conversations c ON c.id = g.conversation_id
             JOIN conversation_members cm ON cm.conversation_id = g.conversation_id
             WHERE cm.user_id = ? AND cm.left_at IS NULL
             ORDER BY c.updated_at DESC'
        );
        $stmt->execute([$userId]);
        $groups = $stmt->fetchAll();

        // Add member count per group
        foreach ($groups as &$g) {
            $cnt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM conversation_members WHERE conversation_id = ? AND left_at IS NULL'
            );
            $cnt->execute([(int)$g['conversation_id']]);
            $g['member_count'] = (int)$cnt->fetchColumn();
        }
        unset($g);

        Response::success($groups ?: []);
    }

    /** GET /groups/{id} — تفاصيل المجموعة + الأعضاء مع الأدوار + الإعدادات + دوري */
    public function show(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.conversation_id, g.name, g.description, g.avatar, g.created_by,
                    c.title, c.created_at, c.updated_at,
                    u.name AS creator_name, u.avatar AS creator_avatar
             FROM groups g
             JOIN conversations c ON c.id = g.conversation_id
             JOIN conversation_members cm ON cm.conversation_id = g.conversation_id AND cm.user_id = ? AND cm.left_at IS NULL
             JOIN users u ON u.id = g.created_by
             WHERE g.id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $id]);
        $group = $stmt->fetch();
        if (!$group) {
            Response::notFound('المجموعة غير موجودة أو لست عضوًا فيها');
        }

        // Members with roles
        $stmt = $this->pdo->prepare(
            'SELECT cm.user_id, cm.role, cm.joined_at,
                    u.name, u.avatar, u.username, u.phone, u.last_seen, u.is_verified
             FROM conversation_members cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.conversation_id = ? AND cm.left_at IS NULL
             ORDER BY (CASE cm.role WHEN "owner" THEN 1 WHEN "admin" THEN 2 WHEN "member" THEN 3 ELSE 4 END), cm.joined_at ASC'
        );
        $stmt->execute([(int)$group['conversation_id']]);
        $members = $stmt->fetchAll();
        require_once __DIR__ . '/UserController.php';
        $uc = new UserController();
        // تطبيق خصوصية الملف الشخصي (بما في ذلك الحظر وآخر الظهور) لكل عضو في المجموعة
        $group['members'] = array_map(function ($m) use ($uc, $userId) {
            return $uc->filterProfile($m, $userId, (int)$m['user_id']);
        }, $members);

        // Group settings
        $stmt = $this->pdo->prepare(
            'SELECT only_admins_can_message, only_admins_can_edit, approval_required
             FROM group_settings WHERE group_id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $group['settings'] = $stmt->fetch() ?: [
            'only_admins_can_message' => 0,
            'only_admins_can_edit' => 0,
            'approval_required' => 0,
        ];

        // My role
        foreach ($group['members'] as $m) {
            if ((int)$m['user_id'] === $userId) {
                $group['my_role'] = $m['role'];
                break;
            }
        }

        Response::success($group);
    }

    /** Check membership + role, returns ['group' => [...], 'role' => ..., 'is_admin' => bool] or fails */
    private function requireGroupAccess(int $groupId, int $userId, bool $adminOnly = false): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.conversation_id, cm.role, cm.left_at
             FROM groups g
             JOIN conversation_members cm ON cm.conversation_id = g.conversation_id
             WHERE g.id = ? AND cm.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$groupId, $userId]);
        $row = $stmt->fetch();
        if (!$row || $row['left_at'] !== null) {
            Response::notFound('المجموعة غير موجودة أو لست عضوًا فيها');
        }
        $role = $row['role'];
        if ($adminOnly && $role !== 'admin' && $role !== 'owner') {
            Response::forbidden('هذه العملية للمشرفين فقط');
        }
        return ['group' => $row, 'role' => $role, 'is_admin' => $role === 'admin' || $role === 'owner'];
    }

    /** POST /groups/{id}/members — إضافة أعضاء (مشرفون فقط) */
    public function addMembers(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $access = $this->requireGroupAccess($id, $userId, true);
        $memberIds = array_map('intval', (array)($body['member_ids'] ?? []));
        if (empty($memberIds)) {
            Response::error('يجب تحديد أعضاء للإضافة', 'MISSING_MEMBERS', 400);
        }
        $convId = (int)$access['group']['conversation_id'];

        // Remove existing members and current owner
        $stmt = $this->pdo->prepare('SELECT user_id FROM conversation_members WHERE conversation_id = ? AND left_at IS NULL');
        $stmt->execute([$convId]);
        $existing = array_map(fn ($r) => (int)$r['user_id'], $stmt->fetchAll());
        $existing[] = $userId;

        $this->pdo->beginTransaction();
        try {
            $added = 0;
            $userCtrl = new UserController();
            foreach ($memberIds as $mid) {
                if (!in_array($mid, $existing, true)) {
                    // فرض الخصوصية: هل يسمح المستخدم بالإضافة للمجموعات؟
                    $targetPrivacy = $this->pdo->prepare('SELECT groups_from FROM privacy_settings WHERE user_id = ? LIMIT 1');
                    $targetPrivacy->execute([$mid]);
                    $tp = $targetPrivacy->fetch();
                    $groupsFrom = (int)($tp['groups_from'] ?? 2); // 2=الجميع، 1=جهات الاتصال، 0=لا أحد

                    $canAdd = true;
                    if ($groupsFrom === 0) {
                        $canAdd = false;
                    } elseif ($groupsFrom === 1) {
                        $isContact = $this->pdo->prepare('SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ? LIMIT 1');
                        $isContact->execute([$mid, $userId]);
                        if (!$isContact->fetch()) {
                            $canAdd = false;
                        }
                    }

                    // فحص الحظر
                    if ($userCtrl->isBlockedEither($userId, $mid)) {
                        $canAdd = false;
                    }

                    if ($canAdd) {
                        $this->pdo->prepare(
                            'INSERT INTO conversation_members (conversation_id, user_id, role, joined_at) VALUES (?, ?, "member", datetime("now"))'
                        )->execute([$convId, $mid]);
                        $added++;
                    }
                }
            }
            $this->pdo->commit();
            Response::success(['added' => $added], 'تمت إضافة الأعضاء');
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Add group members error: ' . $e->getMessage());
            Response::error('فشل في إضافة الأعضاء', 'ADD_MEMBERS_FAILED', 500);
        }
    }

    /** DELETE /groups/{id}/members/{uid} — حذف عضو (مشرفون فقط) */
    public function removeMember(int $id, int $uid): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $access = $this->requireGroupAccess($id, $userId, true);

        // لا يمكن للمشرفين العاديين طرد مشرفين أو المالك؛ المالك يمكنه طرد الجميع
        if ($access['role'] !== 'owner' && $access['role'] === 'admin') {
            $stmt = $this->pdo->prepare(
                'SELECT role FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1'
            );
            $stmt->execute([(int)$access['group']['conversation_id'], $uid]);
            $target = $stmt->fetch();
            if ($target && ($target['role'] === 'admin' || $target['role'] === 'owner')) {
                Response::forbidden('لا يمكنك إزالة مشرف أو مالك المجموعة');
            }
        }

        $this->pdo->prepare(
            'UPDATE conversation_members SET left_at = datetime("now") WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL'
        )->execute([(int)$access['group']['conversation_id'], $uid]);

        Response::success(null, 'تمت إزالة العضو من المجموعة');
    }

    /** PUT /groups/{id}/members/{uid}/role — تعيين دور (مالك فقط) */
    public function setRole(int $id, int $uid): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $access = $this->requireGroupAccess($id, $userId, false);

        if ($access['role'] !== 'owner') {
            Response::forbidden('تعيين الأدوار للمالك فقط');
        }
        if ((int)$uid === $userId) {
            Response::error('لا يمكنك تغيير دورك الخاص', 'SELF_ROLE_CHANGE', 400);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $role = $body['role'] ?? 'member';
        if (!in_array($role, ['admin', 'member'], true)) {
            Response::error('الدور غير صالح. استخدم admin أو member', 'INVALID_ROLE', 400);
        }

        $this->pdo->prepare(
            'UPDATE conversation_members SET role = ? WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL'
        )->execute([$role, (int)$access['group']['conversation_id'], $uid]);

        Response::success(null, $role === 'admin' ? 'تم تعيين العضو مشرفًا' : 'تم إزالة الصلاحيات الإدارية');
    }

    /** PUT /groups/{id}/settings — إعدادات النشر (مشرفون فقط) */
    public function updateSettings(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $access = $this->requireGroupAccess($id, $userId, true);

        $updates = [];
        $params  = [];
        foreach (['only_admins_can_message', 'only_admins_can_edit', 'approval_required'] as $field) {
            if (array_key_exists($field, $body)) {
                $updates[] = "$field = ?";
                $params[]  = filter_var($body[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }
        if (empty($updates)) {
            Response::error('لم يتم تحديد إعدادات للتحديث', 'NO_FIELDS', 400);
        }

        $params[] = $id;
        $this->pdo->prepare(
            'UPDATE group_settings SET ' . implode(', ', $updates) . ', updated_at = datetime("now") WHERE group_id = ?'
        )->execute($params);

        Response::success(null, 'تم تحديث إعدادات المجموعة');
    }

    /** PUT /groups/{id}/title — تغيير اسم المجموعة (مشرفون فقط) */
    public function updateTitle(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $access = $this->requireGroupAccess($id, $userId, true);

        $title = htmlspecialchars(strip_tags(trim($body['title'] ?? '')), ENT_QUOTES, 'UTF-8');
        if (empty($title)) {
            Response::error('اسم المجموعة لا يمكن أن يكون فارغًا', 'EMPTY_TITLE', 400);
        }

        $convId = (int)$access['group']['conversation_id'];
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE groups SET name = ?, updated_at = datetime("now") WHERE id = ?')->execute([$title, $id]);
            $this->pdo->prepare('UPDATE conversations SET title = ?, updated_at = datetime("now") WHERE id = ?')->execute([$title, $convId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        Response::success(null, 'تم تحديث اسم المجموعة');
    }

    /** POST /groups/{id}/avatar — صورة المجموعة (مشرفون فقط) */
    public function uploadAvatar(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $this->requireGroupAccess($id, $userId, true);

        $file = $_FILES['avatar'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Response::error('يجب رفع صورة', 'MISSING_AVATAR', 400);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
            Response::error('صيغة الصورة غير مدعومة', 'INVALID_IMAGE', 400);
        }
        if ((int)$file['size'] > 5 * 1024 * 1024) {
            Response::error('حجم الصورة يتجاوز 5 ميجابايت', 'TOO_LARGE', 400);
        }

        $dir = '/home/ubuntu/nova_new/backend/storage/avatars/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ext   = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $name  = 'g-' . $id . '-' . bin2hex(random_bytes(8)) . '.' . strtolower(substr($ext, 0, 4));
        $dest  = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::error('فشل في حفظ الصورة', 'UPLOAD_FAILED', 500);
        }

        $url = '/media/avatars/' . $name;
        $this->pdo->prepare('UPDATE groups SET avatar = ?, updated_at = datetime("now") WHERE id = ?')->execute([$url, $id]);

        Response::success(['avatar' => $url], 'تم تحديث صورة المجموعة');
    }

    /** POST /groups/{id}/leave — مغادرة المجموعة */
    public function leave(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $access = $this->requireGroupAccess($id, $userId, false);

        $convId = (int)$access['group']['conversation_id'];

        if ($access['role'] === 'owner') {
            // نقل الملكية لأقدم عضو أو حذف المجموعة
            $stmt = $this->pdo->prepare(
                'SELECT user_id FROM conversation_members WHERE conversation_id = ? AND left_at IS NULL AND user_id != ?
                 ORDER BY joined_at ASC LIMIT 1'
            );
            $stmt->execute([$convId, $userId]);
            $next = $stmt->fetch();
            if ($next) {
                $this->pdo->prepare(
                    'UPDATE conversation_members SET role = "owner" WHERE conversation_id = ? AND user_id = ?'
                )->execute([$convId, (int)$next['user_id']]);
            } else {
                $this->pdo->prepare('DELETE FROM conversations WHERE id = ?')->execute([$convId]);
                $this->pdo->prepare('DELETE FROM groups WHERE id = ?')->execute([$id]);
            }
        }

        $this->pdo->prepare(
            'UPDATE conversation_members SET left_at = datetime("now") WHERE conversation_id = ? AND user_id = ?'
        )->execute([$convId, $userId]);

        Response::success(null, 'تمت مغادرة المجموعة');
    }
}
