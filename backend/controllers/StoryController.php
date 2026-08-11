<?php
/**
 * NOVA Messenger - Story Controller
 */

declare(strict_types=1);

class StoryController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // GET /api/v1/stories
    public function index(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.uuid, s.user_id, s.type, s.text, s.file_id, s.privacy,
                    s.created_at, s.expires_at,
                    u.name AS user_name, u.avatar AS user_avatar,
                    (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id) AS view_count,
                    (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id AND sv.viewer_id = ?) AS viewed_by_me
             FROM stories s
             JOIN users u ON u.id = s.user_id
             WHERE s.expires_at > NOW() AND s.deleted_at IS NULL
               AND (s.user_id = ? OR s.privacy = "all"
                    OR (s.privacy = "contacts" AND EXISTS (
                          SELECT 1 FROM contacts c WHERE c.user_id = ? AND c.contact_user_id = s.user_id
                        )))
             ORDER BY s.created_at DESC'
        );
        $stmt->execute([$userId, $userId, $userId]);
        Response::success($stmt->fetchAll());
    }

    // POST /api/v1/stories
    public function create(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $type    = $body['type'] ?? 'text';
        $text    = htmlspecialchars(strip_tags(trim($body['text'] ?? '')), ENT_QUOTES, 'UTF-8');
        $fileId  = !empty($body['file_id']) ? (int)$body['file_id'] : null;
        $privacy = in_array($body['privacy'] ?? '', ['all', 'contacts', 'close_friends']) ? $body['privacy'] : 'contacts';

        if ($type === 'text' && empty($text)) {
            Response::error('نص الحالة لا يمكن أن يكون فارغاً', 'EMPTY_STORY', 400);
        }

        $durationHrs = (int)($_ENV['STORY_DURATION_HRS'] ?? 24);
        $expiresAt   = date('Y-m-d H:i:s', strtotime("+{$durationHrs} hours"));
        $uuid        = UuidHelper::generate();

        $this->pdo->prepare(
            'INSERT INTO stories (uuid, user_id, type, text, file_id, privacy, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)'
        )->execute([$uuid, $userId, $type, $text, $fileId, $privacy, $expiresAt]);

        $storyId = (int)$this->pdo->lastInsertId();
        
        // Send FCM notifications to followers
        $this->sendStoryNotifications($userId, $storyId, $privacy);
        
        Response::success($this->getStoryById($storyId), 'تم نشر الحالة', 201);
    }

    // GET /api/v1/stories/{id}
    public function show(int $id): void
    {
        AuthMiddleware::authenticate();
        $story = $this->getStoryById($id);
        if (!$story) {
            Response::notFound('الحالة غير موجودة أو انتهت صلاحيتها');
        }
        Response::success($story);
    }

    // POST /api/v1/stories/{id}/view
    public function view(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->pdo->prepare(
            'INSERT IGNORE INTO story_views (story_id, viewer_id, viewed_at) VALUES (?, ?, NOW())'
        )->execute([$id, $userId]);

        Response::success(null, 'تم تسجيل المشاهدة');
    }

    // DELETE /api/v1/stories/{id}
    public function delete(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare('SELECT user_id FROM stories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $story = $stmt->fetch();

        if (!$story) {
            Response::notFound('الحالة غير موجودة');
        }

        if ((int)$story['user_id'] !== $userId) {
            Response::forbidden('لا يمكنك حذف حالة شخص آخر');
        }

        $this->pdo->prepare('UPDATE stories SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
        Response::success(null, 'تم حذف الحالة');
    }

    private function getStoryById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.uuid, s.user_id, s.type, s.text, s.file_id, s.privacy, s.created_at, s.expires_at,
                    u.name AS user_name, u.avatar AS user_avatar
             FROM stories s JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND s.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
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
}
