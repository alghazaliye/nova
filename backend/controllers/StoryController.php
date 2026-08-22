<?php
/**
 * NOVA Messenger - Story Controller
 * نظام الحالات الشامل: نشر / مشاهدة / تفاعلات / ردود / خصوصية / حظر / إدارة
 */

declare(strict_types=1);

class StoryController
{
    private PDO $pdo;

    /** التفاعلات المسموحة على الحالات */
    private const ALLOWED_REACTIONS = ['❤️', '😂', '😮', '😢', '👍', '🔥'];

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ============================================================
    // GET /api/v1/stories  — قائمة الحالات مع فلترة الخصوصية والحظر
    // ============================================================
    public function index(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        // قائمة المستخدمين المحظورين متبادلًا مع $userId
        $blockedStmt = $this->pdo->prepare(
            'SELECT blocked_user_id FROM blocks WHERE user_id = ? UNION SELECT user_id FROM blocks WHERE blocked_user_id = ?'
        );
        $blockedStmt->execute([$userId, $userId]);
        $blockedIds = array_column($blockedStmt->fetchAll(), 'blocked_user_id');
        $blockedIds[] = $userId; // لا يرى المستخدم قصته ضمن قائمة "الآخرين" هنا (حالته لها شاشة منفصلة)
        $blockedIn = implode(',', array_map('intval', $blockedIds)) ?: '0';

        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.uuid, s.user_id, s.type, s.text, s.file_id, s.privacy,
                    s.created_at, s.expires_at, s.views_count,
                    u.id AS u_id, u.name, u.username, u.phone, u.email, u.avatar, u.is_verified,
                    (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id) AS view_count,
                    (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id AND sv.viewer_id = ?) AS viewed_by_me
             FROM stories s
             JOIN users u ON u.id = s.user_id
             WHERE s.expires_at > datetime("now") AND s.deleted_at IS NULL AND s.deleted_by IS NULL
               AND s.user_id NOT IN (' . $blockedIn . ')
             ORDER BY s.created_at DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        require_once __DIR__ . '/UserController.php';
        $userCtrl = new UserController();
        foreach ($rows as &$r) {
            $ownerId = (int)$r['user_id'];
            $user = [
                'id' => $ownerId,
                'name' => $r['name'],
                'username' => $r['username'],
                'phone' => $r['phone'],
                'email' => $r['email'],
                'avatar' => $r['avatar'],
                'is_verified' => $r['is_verified']
            ];
            $filtered = $userCtrl->filterProfile($user, $userId, $ownerId);
            $r['user_name'] = $filtered['display_name'] ?? $filtered['name'];
            $r['user_avatar'] = $filtered['avatar'];
            $r['user_username'] = $filtered['username'];
            $r['user_is_verified'] = (bool)$filtered['is_verified'];
        }

        // فلترة الخصوصية بعد الجلب (story_privacy: 0=لا أحد، 1=جهات اتصال، 2=الجميع)
        $filtered = [];
        $contactOwnerIds = [];
        $userCtrl = new UserController();
        foreach ($rows as $r) {
            $ownerId = (int)$r['user_id'];
            if ($ownerId === $userId) {
                $filtered[] = $r;
                continue;
            }
            $sp = (string)($r['privacy'] ?? 'contacts');
            if ($sp === 'none') continue;
            
            if ($sp === 'contacts') {
                $contactOwnerIds[$ownerId] = true;
                $r['_pending_contact'] = true;
                $filtered[] = $r;
                continue;
            }
            
            $pl = $this->storyPrivacyLevel($ownerId);
            if ($pl['level'] === 0) continue;
            if ($pl['level'] === 2) {
                $filtered[] = $r;
                continue;
            }
            
            $contactOwnerIds[$ownerId] = true;
            $r['_pending_contact'] = true;
            $filtered[] = $r;
        }

        if ($contactOwnerIds !== []) {
            $ids = implode(',', array_map('intval', array_keys($contactOwnerIds)));
            $chk = $this->pdo->query(
                'SELECT user_id, contact_user_id FROM contacts WHERE (user_id IN (' . $ids . ') AND contact_user_id = ' . (int)$userId . ') OR (user_id = ' . (int)$userId . ' AND contact_user_id IN (' . $ids . '))'
            );
            $contactPairs = [];
            foreach ($chk->fetchAll() as $cp) {
                $contactPairs[(int)$cp['user_id']] = true;
                $contactPairs[(int)$cp['contact_user_id']] = true;
            }
            $stillFiltered = [];
            foreach ($filtered as $fr) {
                if (!empty($fr['_pending_contact'])) {
                    unset($fr['_pending_contact']);
                    $ownerId = (int)$fr['user_id'];
                    if (!isset($contactPairs[$ownerId])) continue;
                }
                $stillFiltered[] = $fr;
            }
            $filtered = $stillFiltered;
        }

        $grouped = [];
        foreach ($filtered as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($grouped[$uid])) {
                $uProfile = $userCtrl->filterProfile([
                    'id' => $uid,
                    'name' => $row['user_name'],
                    'avatar' => $row['user_avatar'],
                    'is_verified' => $row['user_is_verified']
                ], $userId, $uid);

                $grouped[$uid] = [
                    'user_id' => $uid,
                    'user_name' => $uProfile['display_name'] ?? 'مستخدم نوفا',
                    'user_avatar' => $uProfile['avatar'],
                    'user_is_verified' => (bool)$uProfile['is_verified'],
                    'stories' => []
                ];
            }
            
            $s = [
                'id' => (int)$row['id'],
                'uuid' => $row['uuid'],
                'user_id' => $uid,
                'type' => $row['type'],
                'text' => $row['text'],
                'created_at' => $row['created_at'],
                'expires_at' => $row['expires_at'],
                'views_count' => (int)$row['view_count'],
                'is_viewed' => (bool)$row['viewed_by_me'],
                'is_mine' => ($uid === $userId)
            ];

            if (!empty($row['file_id'])) {
                $att = $this->pdo->prepare('SELECT file_name, mime_type FROM attachments WHERE id = ? LIMIT 1');
                $att->execute([(int)$row['file_id']]);
                $a = $att->fetch();
                if ($a) {
                    $s['file_url']  = '/media/attachments/' . $a['file_name'];
                    $s['file_mime'] = $a['mime_type'];
                }
            }
            
            $grouped[$uid]['stories'][] = $s;
        }

        Response::success(array_values($grouped));
    }

    // ============================================================
    // POST /api/v1/stories/upload (multipart: file + text + privacy)
    // ============================================================
    public function upload(): void
    {
        SettingsHelper::enforceFeature($this->pdo, 'allow_stories', 'الحالات');
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            Response::error('لم يتم رفع أي ملف', 'NO_FILE', 400);
        }
        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm'];
        if (!in_array($ext, $allowed, true)) {
            Response::error('نوع الملف غير مدعوم', 'UNSUPPORTED_FILE', 400);
        }
        $maxBytes = ((int)SettingsHelper::getSetting($this->pdo, 'max_video_size_mb', '100')) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            Response::error('حجم الملف كبير جداً', 'FILE_TOO_LARGE', 400);
        }
        $fileName = $userId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $isVideo = str_starts_with($mime, 'video/');
        $destDir = '/home/ubuntu/nova_new/backend/storage/attachments/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }
        $dest = $destDir . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::error('فشل حفظ الملف', 'UPLOAD_FAILED', 500);
        }
        $type = $isVideo ? 'video' : 'image';

        $text    = htmlspecialchars(strip_tags(trim($_POST['text'] ?? '')), ENT_QUOTES, 'UTF-8');
        $privacy = $this->normalizePrivacy($_POST['privacy'] ?? '');

        $uuid = UuidHelper::generate();
        $attachmentId = $this->attachmentInsert($uuid, $userId, $type, $file, $fileName, $mime);
        $storyId = $this->insertStory($userId, $type, $text, $privacy, $attachmentId);

        if ($storyId) {
            $this->sendStoryNotifications($userId, $storyId, $privacy);
            Response::success([
                'story_id' => $storyId,
                'type'     => $type,
                'file_url' => '/media/attachments/' . $fileName,
                'file_mime' => $mime,
            ], 'تم نشر الحالة', 201);
        }
        @unlink($dest);
        Response::error('فشل نشر الحالة', 'UPLOAD_FAILED', 500);
    }

    // ============================================================
    // POST /api/v1/stories  — حالة نصية
    // ============================================================
    public function create(): void
    {
        SettingsHelper::enforceFeature($this->pdo, 'allow_stories', 'الحالات');
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $type    = $body['type'] ?? 'text';
        $text    = htmlspecialchars(strip_tags(trim($body['text'] ?? '')), ENT_QUOTES, 'UTF-8');
        $fileId  = !empty($body['file_id']) ? (int)$body['file_id'] : null;
        $privacy = $this->normalizePrivacy($body['privacy'] ?? '');

        if ($type === 'text' && empty($text)) {
            Response::error('نص الحالة لا يمكن أن يكون فارغاً', 'EMPTY_STORY', 400);
        }

        $storyId = $this->insertStory($userId, $type, $text, $privacy, $fileId);
        if (!$storyId) {
            Response::error('فشل نشر الحالة', 'STORY_FAILED', 500);
        }

        $this->sendStoryNotifications($userId, $storyId, $privacy);
        Response::success($this->getStoryById($storyId, $userId), 'تم نشر الحالة', 201);
    }

    // ============================================================
    // GET /api/v1/stories/{id}
    // ============================================================
    public function show(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $story  = $this->getStoryById($id, $userId);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        Response::success($story);
    }

    // ============================================================
    // POST /api/v1/stories/{id}/view  — تسجيل المشاهدة (فريد لكل مستخدم)
    // ============================================================
    public function view(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $story = $this->getStoryById($id, $userId, true); // استخدام الفحص الصارم (strict) للخصوصية
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        if ((int)$story['user_id'] === $userId) {
            Response::success(null, 'لا يمكن مشاهدة حالتك الخاصة');
        }

        // احترام إيصالات القراءة: إذا عطّل صاحب الحالة read receipts
        // لا تُسجل مشاهدة ولا تُكشف في قائمة المشاهدين (سياسة واتساب).
        $userCtrl = new UserController();
        if (!$userCtrl->canSeeReadReceipt($userId, (int)$story['user_id'])) {
            Response::success(null, 'تمت المشاهدة دون تسجيلها');
        }

        $blockedStmt = $this->pdo->prepare(
            'SELECT id FROM blocks WHERE (user_id = ? AND blocked_user_id = ?) OR (user_id = ? AND blocked_user_id = ?) LIMIT 1'
        );
        $blockedStmt->execute([$userId, (int)$story['user_id'], (int)$story['user_id'], $userId]);
        if ($blockedStmt->fetch()) {
            Response::success(null, 'تمت المشاهدة');
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO story_views (story_id, viewer_id, viewed_at) VALUES (?, ?, datetime("now"))'
        );
        $ins->execute([$id, $userId]);
        // atomic counter
        $this->pdo->prepare('UPDATE stories SET views_count = views_count + 1 WHERE id = ?')->execute([$id]);

        Response::success(null, 'تم تسجيل المشاهدة');
    }

    // ============================================================
    // GET /api/v1/stories/{id}/views  — قائمة المشاهدين (للصاحب فقط)
    // ============================================================
    public function views(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $story = $this->getStoryById($id, $userId, true);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        if ((int)$story['user_id'] !== $userId) {
            Response::forbidden('قائمة المشاهدين متاحة لصاحب الحالة فقط');
        }

        // المشاهدين الفعليين: لا يشمل من عطّل إيصالات القراءة لديهم أو المحظورين
        $userCtrl = new UserController();
        $stmt = $this->pdo->prepare(
            'SELECT sv.viewer_id, sv.viewed_at, u.name AS viewer_name, u.avatar AS viewer_avatar
             FROM story_views sv
             JOIN users u ON u.id = sv.viewer_id
             WHERE sv.story_id = ?
             ORDER BY sv.viewed_at DESC'
        );
        $stmt->execute([$id]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            // أخفِ المشاهدين الذين عطّلوا read receipts أو محظورين متبادلًا
            if (!$userCtrl->canSeeReadReceipt((int)$story['user_id'], (int)$r['viewer_id'])) {
                continue;
            }
            $blockedStmt = $this->pdo->prepare(
                'SELECT id FROM blocks WHERE (user_id = ? AND blocked_user_id = ?) OR (user_id = ? AND blocked_user_id = ?) LIMIT 1'
            );
            $blockedStmt->execute([$userId, (int)$r['viewer_id'], (int)$r['viewer_id'], $userId]);
            if ($blockedStmt->fetch()) {
                continue;
            }
            $r['viewer_id'] = (int)$r['viewer_id'];
            $rows[] = $r;
        }
        Response::success(['views' => $rows]);
    }

    // ============================================================
    // POST /api/v1/stories/{id}/reaction  — تفاعل (فريد per user)
    // ============================================================
    public function react(int $id): void
    {
        SettingsHelper::enforceFeature($this->pdo, 'allow_stories', 'الحالات');
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $reaction = mb_substr(trim((string)($body['reaction'] ?? '')), 0, 10);

        if (!in_array($reaction, self::ALLOWED_REACTIONS, true)) {
            Response::error('تفاعل غير مدعوم', 'INVALID_REACTION', 400);
        }

        $story = $this->getStoryById($id, $userId, false);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        $authorId = (int)$story['user_id'];
        if ($authorId === $userId) {
            Response::error('لا يمكن التفاعل مع حالتك الخاصة', 'SELF_REACTION', 400);
        }

        // فحص الحظر
        $userCtrl = new UserController();
        if ($userCtrl->isBlockedEither($userId, $authorId)) {
            Response::forbidden('لا يمكنك التفاعل مع حالة هذا المستخدم');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO story_reactions (story_id, user_id, reaction, created_at)
                 VALUES (?, ?, ?, datetime("now"))
ON CONFLICT(story_id, user_id) DO UPDATE SET reaction = excluded.reaction, created_at = datetime("now")'
	            )->execute([$id, $userId, $reaction]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Response::error('فشل تسجيل التفاعل', 'REACTION_FAILED', 500);
        }

        $this->notifyAuthor((int)$story['user_id'], 'reaction', $userId, $reaction, (string)$id);
        Response::success(['reaction' => $reaction], 'تم تسجيل التفاعل', 201);
    }

    // ============================================================
    // DELETE /api/v1/stories/{id}/reaction
    // ============================================================
    public function unreact(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->pdo->prepare('DELETE FROM story_reactions WHERE story_id = ? AND user_id = ?')->execute([$id, $userId]);
        Response::success(null, 'تم إزالة التفاعل');
    }

    // ============================================================
    // POST /api/v1/stories/{id}/reply  — الرد يصل كمحادثة مع صاحب الحالة
    // ============================================================
    public function reply(int $id): void
    {
        SettingsHelper::enforceFeature($this->pdo, 'allow_stories', 'الحالات');
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $messageBody = mb_substr(trim((string)($body['body'] ?? '')), 0, 4000);
        if ($messageBody === '') {
            Response::error('نص الرد لا يمكن أن يكون فارغاً', 'EMPTY_REPLY', 400);
        }

        $story = $this->getStoryById($id, $userId, false);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        $authorId = (int)$story['user_id'];
        if ($authorId === $userId) {
            Response::error('لا يمكنك الرد على حالتك الخاصة', 'SELF_REPLY', 400);
        }

        // فحص الحظر
        $userCtrl = new UserController();
        if ($userCtrl->isBlockedEither($userId, $authorId)) {
            Response::forbidden('لا يمكنك الرد على حالة هذا المستخدم');
        }

        // البحث عن محادثة خاصة موجودة أو إنشاء واحدة
        $convStmt = $this->pdo->prepare(
            'SELECT c.id, c.uuid FROM conversations c
             JOIN conversation_members cm1 ON cm1.conversation_id = c.id AND cm1.user_id = ?
             JOIN conversation_members cm2 ON cm2.conversation_id = c.id AND cm2.user_id = ?
             WHERE c.type = "private" AND cm1.left_at IS NULL AND cm2.left_at IS NULL
             LIMIT 1'
        );
        $convStmt->execute([$userId, $authorId]);
        $existing = $convStmt->fetch();
        if ($existing) {
            $convId = (int)$existing['id'];
        } else {
            $uuid = UuidHelper::generate();
            $this->pdo->prepare(
                'INSERT INTO conversations (uuid, type, created_by, created_at, updated_at) VALUES (?, "private", ?, datetime("now"), datetime("now"))'
            )->execute([$uuid, $userId]);
            $convId = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO conversation_members (conversation_id, user_id, role, joined_at) VALUES (?, ?, "owner", datetime("now"))')->execute([$convId, $userId]);
            $this->pdo->prepare('INSERT INTO conversation_members (conversation_id, user_id, role, joined_at) VALUES (?, ?, "member", datetime("now"))')->execute([$convId, $authorId]);
        }

        $msgUuid = UuidHelper::generate();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO messages (uuid, conversation_id, sender_id, reply_to_message_id, reply_to_status_id, type, body, client_message_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, ?, "text", ?, ?, "sent", datetime("now"), datetime("now"))'
            )->execute([$msgUuid, $convId, $userId, $id, $messageBody, 'reply_' . $msgUuid]);
            $msgId = (int)$this->pdo->lastInsertId();

            $this->pdo->prepare(
                'INSERT INTO story_replies (story_id, sender_id, message_id, created_at)
                 VALUES (?, ?, ?, datetime("now"))'
            )->execute([$id, $userId, $msgId]);

            $this->pdo->prepare(
                'UPDATE conversations SET last_message_id = ?, updated_at = datetime("now") WHERE id = ?'
            )->execute([$msgId, $convId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Story reply error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            Response::error('فشل إرسال الرد: ' . $e->getMessage(), 'REPLY_FAILED', 500);
        }

        $this->notifyAuthor($authorId, 'reply', $userId, $messageBody, (string)$id);
        Response::success([
            'conversation_id' => $convId,
            'message_id'      => $msgId,
        ], 'تم إرسال الرد', 201);
    }

    // ============================================================
    // PUT /api/v1/stories/{id}  — تعديل نص الحالة (لصاحبها فقط)
    // ============================================================
    public function update(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $story = $this->getStoryById($id, $userId, false);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        if ((int)$story['user_id'] !== $userId) {
            Response::forbidden('لا يمكنك تعديل حالة شخص آخر');
        }

        // النص فقط قابل للتعديل؛ الوسائط تتطلب إعادة إنشاء
        $text = isset($body['text']) ? htmlspecialchars(strip_tags(trim((string)$body['text'])), ENT_QUOTES, 'UTF-8') : null;
        $privacy = isset($body['privacy']) ? $this->normalizePrivacy((string)$body['privacy']) : null;

        if ($text === null && $privacy === null) {
            Response::error('لا يوجد ما يعدَّل. النص قابل للتعديل فقط؛ الوسائط تتطلب إعادة إنشاء الحالة', 'NOTHING_TO_UPDATE', 400);
        }

        if ($text !== null) {
            if ((string)$story['type'] !== 'text' || $text === '') {
                Response::error('تعديل النص متاح للحالات النصية فقط', 'TEXT_EDIT_ONLY', 400);
            }
        }

        if ($text !== null) {
            $this->pdo->prepare('UPDATE stories SET text = ? WHERE id = ?')->execute([$text, $id]);
        }
        if ($privacy !== null) {
            $this->pdo->prepare('UPDATE stories SET privacy = ? WHERE id = ?')->execute([$privacy, $id]);
        }

        Response::success($this->getStoryById($id, $userId), 'تم تعديل الحالة');
    }

    // ============================================================
    // DELETE /api/v1/stories/{id}  — حذف لصاحب الحالة
    // ============================================================
    public function delete(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare('SELECT user_id, deleted_at FROM stories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $story = $stmt->fetch();

        if (!$story) {
            Response::notFound('الحالة غير موجودة');
        }
        if ((int)$story['user_id'] !== $userId) {
            Response::forbidden('لا يمكنك حذف حالة شخص آخر');
        }

        $this->pdo->prepare('UPDATE stories SET deleted_at = datetime("now"), deleted_by = ? WHERE id = ?')->execute([$userId, $id]);
        Response::success(null, 'تم حذف الحالة');
    }

    // ============================================================
    // GET /stories/{id}/reactions  — قائمة التفاعلات لصاحب الحالة
    // ============================================================
    public function reactions(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $story = $this->getStoryById($id, $userId, false);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        if ((int)$story['user_id'] !== $userId) {
            Response::forbidden('التفاعلات متاحة لصاحب الحالة فقط');
        }

        $stmt = $this->pdo->prepare(
            'SELECT sr.user_id, sr.reaction, sr.created_at,
                    u.name AS user_name, u.avatar AS user_avatar
             FROM story_reactions sr
             JOIN users u ON u.id = sr.user_id
             WHERE sr.story_id = ?
             ORDER BY sr.created_at DESC'
        );
        $stmt->execute([$id]);
        $rows = array_map(fn($r) => array_merge($r, ['user_id' => (int)$r['user_id']]), $stmt->fetchAll());
        Response::success(['reactions' => $rows]);
    }

    // ============================================================
    // GET /stories/{id}/replies  — قائمة الردود لصاحب الحالة
    // ============================================================
    public function replies(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $story = $this->getStoryById($id, $userId, false);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        if ((int)$story['user_id'] !== $userId) {
            Response::forbidden('الردود متاحة لصاحب الحالة فقط');
        }

        $stmt = $this->pdo->prepare(
            'SELECT sr.id, sr.sender_id, sr.message_id, sr.created_at,
                    u.name AS sender_name, u.avatar AS sender_avatar,
                    m.body AS message_body
             FROM story_replies sr
             JOIN users u ON u.id = sr.sender_id
             JOIN messages m ON m.id = sr.message_id
             WHERE sr.story_id = ?
             ORDER BY sr.created_at DESC'
        );
        $stmt->execute([$id]);
        $rows = array_map(fn($r) => array_merge($r, ['sender_id' => (int)$r['sender_id'], 'message_id' => (int)$r['message_id']]), $stmt->fetchAll());
        Response::success(['replies' => $rows]);
    }

    // ============================================================
    // POST /stories/{id}/report  — الإبلاغ عن حالة
    // ============================================================
    public function report(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $reporter = (int)$auth['user_id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $stmt = $this->pdo->prepare('SELECT id, user_id FROM stories WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $story = $stmt->fetch();
        if (!$story) {
            Response::notFound('الحالة غير موجودة');
        }
        $ownerId = (int)$story['user_id'];
        if ($ownerId === $reporter) {
            Response::error('لا يمكنك الإبلاغ عن حالتك', 'SELF_REPORT', 400);
        }

        $reason = htmlspecialchars(strip_tags(trim((string)($body['reason'] ?? 'إساءة'))), ENT_QUOTES, 'UTF-8');

        $dup = $this->pdo->prepare(
            "SELECT id FROM reports WHERE reporter_id = ? AND story_id = ? AND status = 'pending' LIMIT 1"
        );
        $dup->execute([$reporter, $id]);
        if ($dup->fetch()) {
            Response::error('سبق الإبلاغ عن هذه الحالة', 'DUPLICATE_REPORT', 409);
        }

        $this->pdo->prepare(
            "INSERT INTO reports (reporter_id, reported_user_id, story_id, reason, description, status, priority, created_at)
             VALUES (?, ?, ?, ?, NULL, 'pending', 'medium', datetime('now','localtime'))"
        )->execute([$reporter, $ownerId, $id, $reason]);

        Response::success(null, 'تم الإبلاغ عن الحالة وسيتم مراجعتها', 201);
    }

    // ============================================================
    // إداري: POST /admin/api/v1/stories/{id}/delete  — حذف إداري
    // ============================================================
    public function adminDelete(int $id): void
    {
        $admin = $this->requireAdmin('statuses.delete');

        $stmt = $this->pdo->prepare('SELECT id, user_id, deleted_at, deleted_by FROM stories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $story = $stmt->fetch();
        if (!$story) {
            Response::notFound('الحالة غير موجودة');
        }

        $reason = htmlspecialchars(strip_tags(trim((string)($_POST['reason'] ?? $_SERVER['HTTP_X_REASON'] ?? 'حذف إداري وفق السياسة'))), ENT_QUOTES, 'UTF-8');

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE stories SET deleted_at = datetime("now"), deleted_by = ? WHERE id = ?'
            )->execute([0 - (int)$admin['id'], $id]); // deleted_by سالب = حذف إداري (ids موجبة للمستخدمين)

            $this->pdo->prepare(
                'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, datetime("now"))'
            )->execute([
                (int)$admin['id'], 'statuses.admin_deleted', 'story', $id,
                'حذف إداري للحالة #' . $id . ' - صاحبها: ' . (int)$story['user_id'] . ' - السبب: ' . $reason,
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Response::error('فشل الحذف الإداري', 'ADMIN_DELETE_FAILED', 500);
        }

        Response::success(null, 'تم حذف الحالة إداريًا');
    }

    // ============================================================
    // إداري: GET /admin/api/v1/stories/stats  — إحصائيات الحالات
    // ============================================================
    public function adminStats(): void
    {
        $this->requireAdmin('statuses.stats');

        $sql = fn($where): int => (int)($this->pdo->query("SELECT COUNT(*) FROM stories AS s {$where}")->fetchColumn() ?: 0);

        $stats = [
            'total'           => $sql(''),
'active'          => $sql("WHERE s.deleted_at IS NULL AND s.deleted_by IS NULL AND s.expires_at > datetime('now')"),
            'today'           => $sql("WHERE DATE(s.created_at) = DATE('now','localtime')"),
            'expired'         => $sql("WHERE s.expires_at <= datetime('now')"),
            'deleted'         => $sql('WHERE s.deleted_at IS NOT NULL AND s.deleted_by IS NULL'),
            'admin_deleted'   => $sql('WHERE s.deleted_at IS NOT NULL AND s.deleted_by IS NOT NULL'),
            'type_image'      => $sql("WHERE s.type = 'image' AND s.expires_at > datetime('now') AND s.deleted_at IS NULL AND s.deleted_by IS NULL"),
            'type_video'      => $sql("WHERE s.type = 'video' AND s.expires_at > datetime('now') AND s.deleted_at IS NULL AND s.deleted_by IS NULL"),
            'type_text'       => $sql("WHERE s.type = 'text' AND s.expires_at > datetime('now') AND s.deleted_at IS NULL AND s.deleted_by IS NULL"),
            'total_views'     => (int)($this->pdo->query('SELECT COUNT(*) FROM story_views')->fetchColumn() ?: 0),
            'total_reactions' => (int)($this->pdo->query('SELECT COUNT(*) FROM story_reactions')->fetchColumn() ?: 0),
            'total_replies'   => (int)($this->pdo->query('SELECT COUNT(*) FROM story_replies')->fetchColumn() ?: 0),
        ];

        // أكثر الحالات مشاهدة (Active)
        $top = $this->pdo->query(
            'SELECT s.id, s.user_id, u.name AS owner_name, (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id) AS vc
             FROM stories s JOIN users u ON u.id = s.user_id
             WHERE s.expires_at > datetime("now") AND s.deleted_at IS NULL AND s.deleted_by IS NULL
             ORDER BY vc DESC LIMIT 5'
        )->fetchAll();

        Response::success(['stats' => $stats, 'top_viewed' => $top]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * مستوى خصوصية الحالة لصاحبها: 0 = لا أحد، 1 = جهات الاتصال، 2 = الجميع.
     * يعتمد على privacy_settings (story_privacy, allow_by_phone).
     */
    private function storyPrivacyLevel(int $ownerId): array
    {
        $stmt = $this->pdo->prepare('SELECT story_privacy, allow_by_phone FROM privacy_settings WHERE user_id = ? LIMIT 1');
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch();
        // DB: 1=الجميع 2=جهات الاتصال 3=مشاركة مع 4=لا أحد ← تحويل إلى مستوى 0..2
        $raw = $row ? (int)($row['story_privacy'] ?? 1) : 1;
        $level = match ($raw) {
            1 => 2, // الجميع
            4 => 0, // لا أحد
            default => 1, // جهات الاتصال / مشاركة مع
        };
        return ['level' => $level, 'allow_by_phone' => $row ? ((int)($row['allow_by_phone'] ?? 1)) !== 0 : true];
    }

    /** تحويل privacy المدخل إلى قيمة آمنة */
    private function normalizePrivacy(string $value): string
    {
        return match ($value) {
            'all', 'everyone' => 'all',
            'none'            => 'none',
            default           => 'contacts',
        };
    }

    /**
     * جلب حالة بالتفصيل مع فحوصات: انتهاء، حذف، حظر متبادل، خصوصية.
     * $strict: true => يطبق الخصوصية والحظر (للـshow)، false => يطبق الوجود فقط (الوصول التفصيلي يتولاه المستدعي)
     */
    private function getStoryById(int $id, int $viewerId, bool $strict = true): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.uuid, s.user_id, s.type, s.text, s.file_id, s.privacy,
                    s.created_at, s.expires_at, s.views_count,
                    u.name AS user_name, u.avatar AS user_avatar,
                    (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id) AS view_count
             FROM stories s JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND s.deleted_at IS NULL AND s.deleted_by IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $story = $stmt->fetch();
        if (!$story) {
            return null;
        }
        if ((bool)$story['expires_at'] && new \DateTime((string)$story['expires_at']) <= new \DateTime()) {
            return null; // منتهية
        }
        $isOwner = ((int)$story['user_id'] === $viewerId);
        if ($isOwner) {
            // صاحب الحالة يرى حالته دائمًا حتى لو ضبط خصوصيته العامة "لا أحد"
            $story['user_id']   = (int)$story['user_id'];
            $story['is_owner']  = true;
            return $this->attachFileData($story);
        }
        if (!$strict) {
            return $story;
        }
        // الحظر المتبادل
        $blockedStmt = $this->pdo->prepare(
            'SELECT id FROM blocks WHERE (user_id = ? AND blocked_user_id = ?) OR (user_id = ? AND blocked_user_id = ?) LIMIT 1'
        );
        $blockedStmt->execute([$viewerId, (int)$story['user_id'], (int)$story['user_id'], $viewerId]);
        if ($blockedStmt->fetch()) {
            return null;
        }

        // خصوصية الحالة: خصوصية الحالة نفسها (all/contacts/none) تتقدم على الإعداد العام
        $perStory = $story['privacy'] ?? 'contacts';
        $pl = $this->storyPrivacyLevel((int)$story['user_id']);
        if ($perStory === 'none' || $pl['level'] === 0) {
            return null;
        }
        $visible = match ($perStory) {
            'all' => $pl['level'] === 2, // إعداد عام "الجميع" مطلوب للحالة العامة
            'none' => false,
            default => $this->isContactOf($viewerId, (int)$story['user_id']),
        };
        if (!$visible) {
            return null;
        }

        $story['user_id']   = (int)$story['user_id'];
        $story['is_owner']  = false;
        return $this->attachFileData($story);
    }

    /** ربط بيانات المرفق إن وجد */
    private function attachFileData(array $story): array
    {
        if (!empty($story['file_id'])) {
            $att = $this->pdo->prepare('SELECT file_name, mime_type FROM attachments WHERE id = ? LIMIT 1');
            $att->execute([(int)$story['file_id']]);
            $a = $att->fetch();
            if ($a) {
                $story['file_url']  = '/media/attachments/' . $a['file_name'];
                $story['file_mime'] = $a['mime_type'];
            }
        }
        return $story;
    }

    /** هل $a جهة اتصال لـ$b أو العكس؟ */
    private function isContactOf(int $a, int $b): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM contacts WHERE (user_id = ? AND contact_user_id = ?) OR (user_id = ? AND contact_user_id = ?) LIMIT 1'
        );
        $stmt->execute([$a, $b, $b, $a]);
        return (bool)$stmt->fetch();
    }

    private function insertStory(int $userId, string $type, string $text, string $privacy, ?int $fileId): ?int
    {
        $durationHrs = (int)SettingsHelper::getSetting($this->pdo, 'story_duration_hrs', '24');
        if ($durationHrs <= 0) {
            $durationHrs = 24;
        }
        $expiresAt = date('Y-m-d H:i:s', time() + (int)($durationHrs * 3600));
        $uuid      = UuidHelper::generate();

        $this->pdo->prepare(
            'INSERT INTO stories (uuid, user_id, type, text, file_id, privacy, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime("now"), ?)'
        )->execute([$uuid, $userId, $type, $text, $fileId, $privacy, $expiresAt]);

        return (int)$this->pdo->lastInsertId() ?: null;
    }

    /** إدراج attachment للرفع وتفعيل uuid */
    private function attachmentInsert(string $uuid, int $userId, string $type, array $file, string $fileName, string $mime): int
    {
        $this->pdo->prepare(
            'INSERT INTO attachments (uuid, uploader_id, type, original_name, file_name, mime_type, file_size, storage_path, duration, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, datetime("now"))'
        )->execute([$uuid, $userId, $type, $file['name'], $fileName, $mime, (int)$file['size'], 'attachments/' . $fileName]);
        return (int)$this->pdo->lastInsertId();
    }

    private function sendStoryNotifications(int $authorId, int $storyId, string $privacy): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT name, avatar FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$authorId]);
            $author = $stmt->fetch();
            if (!$author) return;

            // Get contacts or all users based on privacy
            if ($privacy === 'all') {
                $stmt = $this->pdo->prepare(
                    'SELECT DISTINCT ud.fcm_token FROM user_devices ud WHERE ud.fcm_token IS NOT NULL AND ud.fcm_token != ""'
                );
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare(
                    'SELECT DISTINCT ud.fcm_token FROM user_devices ud
                     JOIN contacts c ON c.contact_user_id = ud.user_id
                     WHERE c.user_id = ? AND ud.fcm_token IS NOT NULL AND ud.fcm_token != ""'
                );
                $stmt->execute([$authorId]);
            }

            $devices = $stmt->fetchAll();
            if (empty($devices)) return;

            foreach ($devices as $device) {
                FCMHelper::sendStoryNotification(
                    $device['fcm_token'],
                    $author['name'],
                    (string)$storyId,
                    $author['avatar']
                );
            }
        } catch (\Throwable $e) {
            error_log('Story FCM notification error: ' . $e->getMessage());
        }
    }

    /** إشعار لصاحب الحالة عند رد أو تفاعل */
    private function notifyAuthor(int $authorId, string $kind, int $actorId, string $content, string $storyId): void
    {
        try {
            if (!FCMHelper::isEnabled()) {
                return;
            }
            $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$actorId]);
            $actor = $stmt->fetch();
            if (!$actor) return;

            $tokenStmt = $this->pdo->prepare(
                'SELECT DISTINCT fcm_token FROM user_devices WHERE user_id = ? AND fcm_token IS NOT NULL AND fcm_token != ""'
            );
            $tokenStmt->execute([$authorId]);
            foreach ($tokenStmt->fetchAll() as $row) {
                if ($kind === 'reply') {
                    FCMHelper::sendToDevice(
                        $row['fcm_token'],
                        'رد على حالتك',
                        mb_substr((string)$actor['name'], 0, 40) . ': ' . mb_substr((string)$content, 0, 80),
                        ['type' => 'story_reply', 'story_id' => $storyId, 'action' => 'open_story_reply']
                    );
                } else {
                    FCMHelper::sendToDevice(
                        $row['fcm_token'],
                        'تفاعل جديد',
                        mb_substr((string)$actor['name'], 0, 40) . ' تفاعل مع حالتك ' . mb_substr((string)$content, 0, 4),
                        ['type' => 'story_reaction', 'story_id' => $storyId, 'action' => 'open_story_reactions']
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('Story notification error: ' . $e->getMessage());
        }
    }

    /**
     * التحقق من هوية الأدمن وصلاحية معينة (نفس نمط AdminOtpController::authenticateAdmin).
     * يدعم JWT الأدمن المستقل (role=admin) والـJWT المرتبط بالجلسة.
     */
    private function requireAdmin(string $permission): array
    {
        $authHeader = nova_get_auth_header() ?? '';
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        if ($token === '') {
            Response::unauthorized('يجب تسجيل الدخول أولاً');
        }
        $payload = JwtHelper::verify($token);
        if ($payload === null) {
            Response::unauthorized('الجلسة منتهية أو غير صالحة، يرجى تسجيل الدخول مجدداً');
        }
        $isStandaloneAdminJwt = isset($payload['role']) && $payload['role'] === 'admin';
        if (!$isStandaloneAdminJwt) {
            AuthMiddleware::authenticate(); // session-bound JWT
        }
        $adminId = (int)($payload['user_id'] ?? 0);

        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.name, a.role_id, r.name AS role_name
             FROM admins a JOIN roles r ON r.id = a.role_id
             WHERE a.id = ? AND a.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            Response::forbidden('هذه العمليات متاحة للمشرفين فقط');
        }
        $permStmt = $this->pdo->prepare(
            'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.name = ? LIMIT 1'
        );
        $permStmt->execute([(int)$admin['role_id'], $permission]);
        if (!$permStmt->fetch()) {
            Response::forbidden('ليس لديك صلاحية: ' . $permission);
        }
        $admin['permission'] = $permission;
        return $admin;
    }
}
