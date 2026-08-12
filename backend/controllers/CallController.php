<?php
/**
 * NOVA Messenger - Call Controller (Signaling)
 * Handles call signaling. Actual media uses WebRTC (client-side).
 */

declare(strict_types=1);

class CallController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // POST /api/v1/calls
    public function initiate(): void
    {
        $auth     = AuthMiddleware::authenticate();
        $callerId = (int)$auth['user_id'];
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

        $calleeId = (int)($body['callee_id'] ?? 0);
        $callType = in_array($body['call_type'] ?? '', ['voice', 'video']) ? $body['call_type'] : 'voice';

        if (!$calleeId) {
            Response::error('يجب تحديد المستخدم المراد الاتصال به', 'MISSING_CALLEE', 400);
        }

        $uuid = UuidHelper::generate();
        $this->pdo->prepare(
            'INSERT INTO calls (uuid, caller_id, call_type, status, created_at) VALUES (?, ?, ?, "calling", NOW())'
        )->execute([$uuid, $callerId, $callType]);
        $callId = (int)$this->pdo->lastInsertId();

        // Add participants
        $this->pdo->prepare('INSERT INTO call_participants (call_id, user_id) VALUES (?, ?)')
                  ->execute([$callId, $callerId]);
        $this->pdo->prepare('INSERT INTO call_participants (call_id, user_id) VALUES (?, ?)')
                  ->execute([$callId, $calleeId]);

        // Send FCM notification to callee
        $this->sendCallNotification($callerId, $calleeId, $uuid, $callType);

        Response::success([
            'call_id'   => $callId,
            'call_uuid' => $uuid,
            'call_type' => $callType,
            'status'    => 'calling',
        ], 'تم بدء الاتصال', 201);
    }

    // GET /api/v1/calls
    public function index(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(50, (int)($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.uuid, c.caller_id, c.call_type, c.status,
                    c.started_at, c.ended_at, c.duration, c.created_at,
                    u.name AS caller_name, u.avatar AS caller_avatar
             FROM calls c
             JOIN call_participants cp ON cp.call_id = c.id AND cp.user_id = ?
             JOIN users u ON u.id = c.caller_id
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $limit, $offset]);
        Response::success($stmt->fetchAll());
    }

    // GET /api/v1/calls/{id}
    public function show(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT c.* FROM calls c
             JOIN call_participants cp ON cp.call_id = c.id AND cp.user_id = ?
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $id]);
        $call = $stmt->fetch();

        if (!$call) {
            Response::notFound('المكالمة غير موجودة');
        }
        Response::success($call);
    }

    // POST /api/v1/calls/{id}/answer
    public function answer(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->updateCallStatus($id, $userId, 'answered');
        $this->pdo->prepare('UPDATE calls SET started_at = NOW() WHERE id = ?')->execute([$id]);
        $this->pdo->prepare('UPDATE call_participants SET joined_at = NOW() WHERE call_id = ? AND user_id = ?')
                  ->execute([$id, $userId]);

        Response::success(null, 'تم قبول المكالمة');
    }

    // POST /api/v1/calls/{id}/reject
    public function reject(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->updateCallStatus($id, $userId, 'rejected');
        Response::success(null, 'تم رفض المكالمة');
    }

    // POST /api/v1/calls/{id}/end
    public function end(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare('SELECT started_at FROM calls WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $call = $stmt->fetch();

        $duration = null;
        if ($call && $call['started_at']) {
            $duration = time() - strtotime($call['started_at']);
        }

        $this->pdo->prepare(
            'UPDATE calls SET status = "ended", ended_at = NOW(), duration = ? WHERE id = ?'
        )->execute([$duration, $id]);

        $this->pdo->prepare('UPDATE call_participants SET left_at = NOW() WHERE call_id = ? AND user_id = ?')
                  ->execute([$id, $userId]);

        Response::success(null, 'تم إنهاء المكالمة');
    }

    private function updateCallStatus(int $callId, int $userId, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM call_participants WHERE call_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$callId, $userId]);
        if (!$stmt->fetch()) {
            Response::forbidden('لا يمكنك تعديل هذه المكالمة');
        }
        $this->pdo->prepare('UPDATE calls SET status = ? WHERE id = ?')->execute([$status, $callId]);
    }

    private function sendCallNotification(int $callerId, int $calleeId, string $callUuid, string $callType): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT name, avatar FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$callerId]);
            $caller = $stmt->fetch();
            if (!$caller) return;

            $stmt = $this->pdo->prepare(
                'SELECT fcm_token FROM user_devices WHERE user_id = ? AND fcm_token IS NOT NULL AND fcm_token != ""'
            );
            $stmt->execute([$calleeId]);
            $devices = $stmt->fetchAll();

            if (empty($devices)) return;

            foreach ($devices as $device) {
                FCMHelper::sendCallNotification(
                    $device['fcm_token'],
                    $caller['name'],
                    $callUuid,
                    $caller['avatar']
                );
            }
        } catch (\Throwable $e) {
            error_log('Call FCM notification error: ' . $e->getMessage());
        }
    }
}
