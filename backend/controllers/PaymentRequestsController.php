<?php
/**
 * NOVA Messenger - Payment Requests Controller
 * User-facing subscription requests (with receipt upload) and
 * admin review endpoints (approve / reject).
 */

declare(strict_types=1);

class PaymentRequestsController
{
    private PDO $pdo;
    private int $userId = 0;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $session = AuthMiddleware::authenticate();
        $this->userId = (int)$session['user_id'];
    }

    /** Require the caller to be an active admin (admin pages/routes) */
    private function requireAdmin(): int
    {
        $authHeader = nova_get_auth_header() ?? '';
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        $payload = $token !== '' ? JwtHelper::verify($token) : null;
        $isAdmin = $payload !== null
            && isset($payload['role']) && $payload['role'] === 'admin'
            && isset($payload['exp']) && (int)$payload['exp'] > time();
        if (!$isAdmin) {
            Response::forbidden('هذه العمليات متاحة للمشرفين فقط');
        }
        return (int)($payload['user_id'] ?? $payload['sub'] ?? 0);
    }

    // ============ User-facing ============

    // POST /api/v1/subscriptions/request
    public function createRequest(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $planId = (int)($body['plan_id'] ?? 0);

        $stmt = $this->pdo->prepare('SELECT id FROM plans WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$planId]);
        if (!$stmt->fetch()) {
            Response::notFound('الباقة غير موجودة أو غير مفعلة');
        }

        // Prevent flooding: one pending request per user per plan
        $dup = $this->pdo->prepare(
            'SELECT id FROM payment_requests WHERE user_id = ? AND plan_id = ? AND status = "pending" LIMIT 1'
        );
        $dup->execute([$this->userId, $planId]);
        if ($dup->fetch()) {
            Response::success(null, 'لديك طلب معلق على هذه الباقة، انتظر مراجعته');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO payment_requests (user_id, plan_id) VALUES (?, ?)'
        );
        $stmt->execute([$this->userId, $planId]);

        Response::success(['id' => (int)$this->pdo->lastInsertId()], 'تم إرسال طلب الاشتراك وسيراجعه المشرف');
    }

    // POST /api/v1/subscriptions/request/upload (receipt file, multipart)
    public function uploadReceipt(int $id): void
    {
        $files = $_FILES['receipt'] ?? $_FILES['file'] ?? null;
        if (!$files || ($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::validationError(['receipt' => 'يرجى إرفاق إيصال الدفع']);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, status, user_id FROM payment_requests WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::notFound('الطلب غير موجود');
        }
        if ((int)$row['user_id'] !== $this->userId) {
            Response::forbidden('هذا الطلب ليس لك');
        }
        if ($row['status'] !== 'pending') {
            Response::success(null, 'لا يمكن تعديل طلب تمت مراجعته');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $files['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed, true)) {
            Response::validationError(['receipt' => 'صيغة الملف غير مدعومة (صور أو PDF فقط)']);
        }
        if (($files['size'] ?? 0) > 5 * 1024 * 1024) {
            Response::validationError(['receipt' => 'حجم الملف يتجاوز 5 ميجابايت']);
        }

        $dir = __DIR__ . '/../storage/receipts';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ext = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg'));
        $fileName = 'receipt-' . $id . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($files['tmp_name'], $dir . '/' . $fileName)) {
            Response::serverError('فشل حفظ الإيصال');
        }

        $this->pdo->prepare('UPDATE payment_requests SET receipt_path = ? WHERE id = ?')
             ->execute([$fileName, $id]);

        Response::success(null, 'تم إرفاق إيصال الدفع');
    }

    // GET /api/v1/subscriptions/my
    public function mySubscriptions(): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT us.id, us.plan_id, us.status, us.starts_at, us.expires_at,
                    p.name plan_name, p.price, p.currency, p.period, p.max_devices,
                    p.plan_type, p.enable_verification, p.verification_duration_days,
                    p.features
             FROM user_subscriptions us
             LEFT JOIN plans p ON p.id = us.plan_id
             WHERE us.user_id = ? ORDER BY us.id DESC'
        );
        $stmt->execute([$this->userId]);
        $subs = $stmt->fetchAll();

        // Independent verification badge state
        $user = $this->pdo->prepare('SELECT verified_until, is_verified FROM users WHERE id = ? LIMIT 1');
        $user->execute([$this->userId]);
        $uRow = $user->fetch();
        $now = date('Y-m-d H:i:s');
        $stillVerified = !empty($uRow['is_verified']) && (!empty($uRow['verified_until']) && $uRow['verified_until'] >= $now);

        // Pending payment requests of this user
        $reqStmt = $this->pdo->prepare(
            'SELECT id, plan_id, status, receipt_path, admin_note, created_at
             FROM payment_requests WHERE user_id = ? ORDER BY id DESC LIMIT 50'
        );
        $reqStmt->execute([$this->userId]);

        Response::success([
            'subscriptions' => $subs,
            'is_verified'   => (int)$stillVerified,
            'verified_until' => $uRow['verified_until'],
            'payment_requests' => $reqStmt->fetchAll(),
        ]);
    }

    // ============ Admin-facing (routes under /admin/payment-requests) ============

    public function __call(string $name, array $args): mixed
    {
        // Admin endpoints may be routed directly from index.php
        return null;
    }

    // GET /api/v1/admin/payment-requests
    public function adminIndex(): void
    {
        $this->requireAdmin();
        $status = (string)($_GET['status'] ?? '');
        $where  = '1=1';
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where  .= ' AND pr.status = ?';
            $params[] = $status;
        }
        $stmt = $this->pdo->prepare(
            "SELECT pr.id, pr.user_id, pr.plan_id, pr.status, pr.receipt_path, pr.admin_note,
                    pr.reviewed_by, pr.reviewed_at, pr.created_at,
                    u.name user_name, u.phone user_phone, u.email user_email, u.is_verified,
                    p.name plan_name, p.price, p.period
             FROM payment_requests pr
             JOIN users u ON u.id = pr.user_id
             JOIN plans p ON p.id = pr.plan_id
             WHERE {$where} ORDER BY pr.id DESC LIMIT 200"
        );
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    // POST /api/v1/admin/payment-requests/{id}/approve
    public function approveRequest(int $id): void
    {
        $this->requireAdmin();
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, plan_id, status FROM payment_requests WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::notFound('الطلب غير موجود');
        }
        if ($row['status'] !== 'pending') {
            Response::success(null, 'تمت مراجعة هذا الطلب سابقًا');
        }

        // Fetch plan details to apply the same logic as subscribeUser
        $planStmt = $this->pdo->prepare(
            'SELECT id, period, enable_verification, verification_duration_days, plan_type
             FROM plans WHERE id = ? LIMIT 1'
        );
        $planStmt->execute([(int)$row['plan_id']]);
        $plan = $planStmt->fetch();
        if (!$plan) {
            Response::notFound('الباقة محذوفة أو غير مفعلة');
        }

        $durationDays = match ($plan['period'] ?? 'monthly') {
            'yearly'   => 365,
            'lifetime' => null,
            default     => 30,
        };
        $expiresAt = $durationDays !== null ? date('Y-m-d H:i:s', strtotime("+{$durationDays} days")) : null;

        $this->pdo->prepare(
            'INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, expires_at)
             VALUES (?, ?, "active", datetime("now","localtime"), ?)'
        )->execute([(int)$row['user_id'], (int)$row['plan_id'], $expiresAt]);

        $this->pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([(int)$row['user_id']]);

        $verifiedUntil = null;
        if (!empty($plan['enable_verification']) && !empty($plan['verification_duration_days'])) {
            $verifiedUntil = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['verification_duration_days'] . ' days'));
        } else {
            $verifiedUntil = $expiresAt;
        }
        $this->pdo->prepare('UPDATE users SET verified_until = ? WHERE id = ?')
             ->execute([$verifiedUntil, (int)$row['user_id']]);

        $this->pdo->prepare(
            'UPDATE payment_requests SET status = "approved", reviewed_by = ?, reviewed_at = datetime("now") WHERE id = ?'
        )->execute([$this->adminId(), $id]);

        // Notify the user
        $this->notifyUser((int)$row['user_id'], 'subscription_approved', 'تم تفعيل اشتراكك', 'تمت الموافقة على طلب الاشتراك وتفعيل الباقة');

        Response::success(['verified_until' => $verifiedUntil], 'تمت الموافقة وتفعيل الاشتراك');
    }

    // POST /api/v1/admin/payment-requests/{id}/reject
    public function rejectRequest(int $id): void
    {
        $this->requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, plan_id, status FROM payment_requests WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::notFound('الطلب غير موجود');
        }
        if ($row['status'] !== 'pending') {
            Response::success(null, 'تمت مراجعة هذا الطلب سابقًا');
        }

        $adminNote = isset($body['admin_note']) ? trim((string)$body['admin_note']) : '';
        $this->pdo->prepare(
            'UPDATE payment_requests SET status = "rejected", admin_note = ?, reviewed_by = ?, reviewed_at = datetime("now")
             WHERE id = ?'
        )->execute([$adminNote ?: null, $this->adminId(), $id]);

        $this->notifyUser((int)$row['user_id'], 'subscription_rejected', 'تم رفض طلب الاشتراك', $adminNote ?: 'راجع المشرف طلبك ولم تتم الموافقة عليه');

        Response::success(null, 'تم رفض الطلب وإشعار المستخدم');
    }

    private function notifyUser(int $userId, string $type, string $title, string $body): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO notifications (user_id, type, title, body) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $type, $title, $body]);
        } catch (\Throwable $e) {
            error_log('Notification failed: ' . $e->getMessage());
        }
    }

    private function adminId(): int
    {
        $session = AuthMiddleware::authenticate();
        return (int)$session['user_id'];
    }
}
