<?php
/**
 * NOVA Messenger - Notification Controller
 */

declare(strict_types=1);

class NotificationController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // GET /api/v1/notifications
    public function index(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(50, (int)($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;

        $stmt = $this->pdo->prepare(
            'SELECT id, type, title, body, data_json, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $limit, $offset]);
        $notifications = $stmt->fetchAll();

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $countStmt->execute([$userId]);
        $unreadCount = (int)$countStmt->fetchColumn();

        Response::success([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    // POST /api/v1/notifications/{id}/read
    public function markRead(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        )->execute([$id, $userId]);

        Response::success(null, 'تم تعليم الإشعار كمقروء');
    }

    // POST /api/v1/notifications/read-all
    public function markAllRead(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
                  ->execute([$userId]);

        Response::success(null, 'تم تعليم جميع الإشعارات كمقروءة');
    }
}
