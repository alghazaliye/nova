<?php
/**
 * NOVA Messenger - Appeals Controller (user-facing)
 * Allows blocked users to submit an appeal and view its status.
 */

declare(strict_types=1);

class AppealsController
{
    private PDO $pdo;
    private int $userId = 0;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $authHeader = nova_get_auth_header() ?? '';
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        if ($token === '') {
            Response::unauthorized('يجب تسجيل الدخول أولاً');
        }
        $payload = JwtHelper::verify($token);
        if ($payload === null) {
            Response::unauthorized('الجلسة منتهية أو غير صالحة، يرجى تسجيل الدخول مجدداً');
        }
        $this->userId = (int)($payload['user_id'] ?? $payload['sub'] ?? 0);
        if ($this->userId === 0) {
            Response::unauthorized('بيانات الجلسة غير صالحة');
        }
    }

    /** Verify the user still exists (their sessions may be revoked by a ban) */
    private function assertUserActive(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, phone FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::unauthorized('المستخدم غير موجود');
        }
        return $user;
    }

    // POST /api/v1/appeals
    public function create(): void
    {
        $user = $this->assertUserActive();
        $userId = $this->userId;
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = isset($body['reason']) ? trim((string)$body['reason']) : '';
        if ($reason === '' || mb_strlen($reason) < 5) {
            Response::validationError(['reason' => 'سبب الاعتراض مطلوب (5 أحرف على الأقل)']);
        }
        if (mb_strlen($reason) > 1000) {
            Response::validationError(['reason' => 'سبب الاعتراض طويل جدًا']);
        }

        // A non-blocked user cannot appeal (nothing to appeal against)
        $banStmt = $this->pdo->prepare('SELECT is_blocked FROM users WHERE id = ? LIMIT 1');
        $banStmt->execute([$userId]);
        $banRow = $banStmt->fetch();
        if (!$banRow || !(int)$banRow['is_blocked']) {
            Response::success(null, 'حسابك يعمل بشكل طبيعي، لا حاجة لاعتراض حاليًا');
        }

        // Prevent duplicate pending appeals
        $dup = $this->pdo->prepare('SELECT id FROM user_appeals WHERE user_id = ? AND status = "pending" LIMIT 1');
        $dup->execute([$userId]);
        if ($dup->fetch()) {
            Response::success(['error_code' => 'DUPLICATE_APPEAL'], 'لديك اعتراض قيد المراجعة بالفعل');
        }

        $contactValue = (string)($user['phone'] ?? '') ?: (string)($user['email'] ?? '');

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_appeals (user_id, contact_value, reason, status) VALUES (?, ?, ?, "pending")'
        );
        $stmt->execute([$userId, $contactValue ?: null, $reason]);

        Response::success(['id' => (int)$this->pdo->lastInsertId()], 'تم إرسال الاعتراض، سيتم مراجعته من قبل الإدارة');
    }

    // GET /api/v1/appeals
    public function index(): void
    {
        $this->assertUserActive();
        $userId = $this->userId;
        $stmt = $this->pdo->prepare(
            'SELECT id, contact_value, reason, status, admin_note, reviewed_at, created_at
             FROM user_appeals WHERE user_id = ? ORDER BY id DESC LIMIT 20'
        );
        $stmt->execute([$userId]);
        Response::success($stmt->fetchAll());
    }
}
