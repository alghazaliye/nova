<?php
/**
 * NOVA Messenger - Reports Controller
 * Users report other users/messages; admins review them.
 */

declare(strict_types=1);

class ReportsController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // POST /api/v1/reports  {reported_user_id, conversation_id?, message_id?, reason, description?}
    public function create(): void
    {
        $auth      = AuthMiddleware::authenticate();
        $reporter  = (int)$auth['user_id'];
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];

        $reportedId = (int)($body['reported_user_id'] ?? 0);
        if (!$reportedId) {
            Response::error('يجب تحديد المستخدم المُبلَّغ عنه', 'MISSING_REPORTED_USER', 400);
        }
        if ($reportedId === $reporter) {
            Response::error('لا يمكنك الإبلاغ عن نفسك', 'SELF_REPORT', 400);
        }

        // Verify the user exists
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$reportedId]);
        if (!$stmt->fetch()) {
            Response::notFound('المستخدم المُبلَّغ عنه غير موجود');
        }

        $reason = htmlspecialchars(strip_tags(trim($body['reason'] ?? 'إساءة')), ENT_QUOTES, 'UTF-8');
        $desc   = !empty($body['description']) ? htmlspecialchars(strip_tags(trim($body['description'])), ENT_QUOTES, 'UTF-8') : null;
        $convId = !empty($body['conversation_id']) ? (int)$body['conversation_id'] : null;
        $msgId  = !empty($body['message_id']) ? (int)$body['message_id'] : null;

        // If a message was reported, make sure it belongs to the reported conversation/user
        if ($msgId !== null) {
            $ms = $this->pdo->prepare('SELECT conversation_id, sender_id FROM messages WHERE id = ? LIMIT 1');
            $ms->execute([$msgId]);
            $m  = $ms->fetch();
            if (!$m) {
                Response::notFound('الرسالة غير موجودة');
            }
            if ($convId === null) {
                $convId = (int)$m['conversation_id'];
            }
        }

        // Prevent duplicate pending reports
        $stmt = $this->pdo->prepare(
            'SELECT id FROM reports WHERE reporter_id = ? AND reported_user_id = ? AND (message_id = ? OR (message_id IS NULL AND ? IS NULL)) AND status = "pending" LIMIT 1'
        );
        $stmt->execute([$reporter, $reportedId, $msgId, $msgId]);
        if ($stmt->fetch()) {
            Response::error('سبق الإبلاغ عن هذا المستخدم وسيتم مراجعته قريبًا', 'DUPLICATE_REPORT', 409);
        }

        $this->pdo->prepare(
            'INSERT INTO reports (reporter_id, reported_user_id, message_id, conversation_id, reason, description, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())'
        )->execute([$reporter, $reportedId, $msgId, $convId, $reason, $desc]);

        Response::success(null, 'تم تسجيل البلاغ وسيتم مراجعته من قبل الإدارة', 201);
    }

    // GET /api/v1/reports — user's own report history
    public function index(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $reporter = (int)$auth['user_id'];
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.reported_user_id, r.reason, r.description, r.status, r.created_at,
                    u.name AS reported_name
             FROM reports r
             JOIN users u ON u.id = r.reported_user_id
             WHERE r.reporter_id = ?
             ORDER BY r.created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$reporter]);
        Response::success($stmt->fetchAll());
    }
}
