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
        SettingsHelper::enforceFeature($this->pdo, 'allow_calls', 'المكالمات');
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
            'INSERT INTO calls (uuid, caller_id, call_type, status, created_at) VALUES (?, ?, ?, "ringing", NOW())'
        )->execute([$uuid, $callerId, $callType]);
        $callId = (int)$this->pdo->lastInsertId();

        // Add participants
        $this->pdo->prepare('INSERT INTO call_participants (call_id, user_id) VALUES (?, ?)')
                  ->execute([$callId, $callerId]);
        $this->pdo->prepare('INSERT INTO call_participants (call_id, user_id) VALUES (?, ?)')
                  ->execute([$callId, $calleeId]);

        // Send FCM notification to callee
        $this->sendCallNotification($callerId, $calleeId, $uuid, $callType);

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.uuid, c.caller_id, c.call_type, c.status,
                    cu.name AS caller_name, cu.avatar AS caller_avatar,
                    pe.name AS callee_name, pe.avatar AS callee_avatar
             FROM calls c
             JOIN users cu ON cu.id = c.caller_id
             JOIN users pe ON pe.id = ?
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$calleeId, $callId]);
        $call = $stmt->fetch();

        Response::success([
            'id'            => (int)$call['id'],
            'call_id'       => (int)$call['id'],
            'call_uuid'     => $call['uuid'],
            'caller_id'     => (int)$call['caller_id'],
            'callee_id'     => (int)$calleeId,
            'call_type'     => $call['call_type'],
            'status'        => 'ringing',
            'caller_name'   => $call['caller_name'],
            'caller_avatar' => $call['caller_avatar'],
            'peer_name'     => $call['callee_name'],
            'peer_avatar'   => $call['callee_avatar'],
        ], 'تم بدء الاتصال', 201);
    }

    // POST /api/v1/calls/{id}/signal (WebRTC: offer / answer / candidate)
    public function signal(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $stmt = $this->pdo->prepare(
            'SELECT id FROM call_participants WHERE call_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            Response::forbidden('لست مشاركًا في هذه المكالمة');
        }

        $signalType = in_array($body['signal_type'] ?? '', ['offer', 'answer', 'candidate'], true) ? $body['signal_type'] : 'candidate';
        $payload    = json_encode($body['payload'] ?? $body);

        $this->pdo->prepare(
            'INSERT INTO call_signals (call_id, sender_id, signal_type, payload, created_at) VALUES (?, ?, ?, ?, NOW())'
        )->execute([$id, $userId, $signalType, $payload]);

        // Push the signal to the peer devices via FCM (high-priority data message)
        $this->notifyPeerSignal($id, $userId, $signalType, $payload);

        Response::success(null, 'تم إرسال الإشارة', 201);
    }

    // GET /api/v1/calls/{id}/signals?since=2026-01-01 00:00:00
    public function signals(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT id FROM call_participants WHERE call_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            Response::forbidden('لست مشاركًا في هذه المكالمة');
        }

        $since  = $_GET['since'] ?? null;
        $sql    = 'SELECT cs.id, cs.sender_id, cs.signal_type, cs.payload, cs.created_at
                   FROM call_signals cs
                   WHERE cs.call_id = ? AND cs.sender_id != ?';
        $params = [$id, $userId];
        if ($since) {
            $sql .= ' AND cs.created_at > ?';
            $params[] = $since;
        }
        $sql .= ' ORDER BY cs.created_at ASC LIMIT 100';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['payload'] = json_decode($row['payload'], true);
        }
        Response::success($rows);
    }

    private function notifyPeerSignal(int $callId, int $senderId, string $signalType, string $payloadJson): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT user_id FROM call_participants WHERE call_id = ? AND user_id != ?'
            );
            $stmt->execute([$callId, $senderId]);
            $peers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($peers)) return;

            $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();
            if (!$sender) return;

            $stmt = $this->pdo->prepare(
                'SELECT fcm_token FROM user_devices WHERE user_id = ? AND fcm_token IS NOT NULL AND fcm_token != ""'
            );

            foreach ($peers as $peerId) {
                $stmt->execute([$peerId]);
                foreach ($stmt->fetchAll() as $device) {
                    FCMHelper::sendCallSignalNotification(
                        $device['fcm_token'],
                        (string)$callId,
                        $signalType,
                        $payloadJson,
                        $sender['name']
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('Call signal notification error: ' . $e->getMessage());
        }
    }

    // GET /api/v1/calls/incoming — المكالمات الواردة النشطة (ringing/calling) للمستخدم الحالي
    public function incoming(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        // إنهاء تلقائي للمكالمات القديمة التي لم يرد عليها أحد (أكثر من 60 ثانية)
        // حتى لا تظهر مكالمات "ميتة" في واجهة الطرف الآخر.
        try {
            $this->pdo->prepare(
                'UPDATE calls SET status = "ended", ended_at = NOW(),
                        duration = TIMESTAMPDIFF(SECOND, created_at, NOW()) * 1000
                 WHERE status IN ("calling", "ringing")
                   AND created_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)'
            )->execute();
            // إنهاء المكالمات "answered" المعلقة التي لا تملك ended_at
            // (سقطت دون إنهاء صريح — أكثر من 5 دقائق) حتى لا تفتح شاشة المكالمة
            // لدى المستخدمين عند كل دخول.
            $this->pdo->prepare(
                'UPDATE calls SET status = "ended", ended_at = COALESCE(ended_at, NOW()),
                        duration = COALESCE(duration, TIMESTAMPDIFF(SECOND, started_at, NOW()) * 1000)
                 WHERE status IN ("answered", "accepted")
                   AND ended_at IS NULL
                   AND created_at < DATE_SUB(NOW(), INTERVAL 300 SECOND)'
            )->execute();
        } catch (\Throwable $e) {
            error_log('Incoming call cleanup error: ' . $e->getMessage());
        }

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.uuid, c.caller_id, c.call_type, c.status, c.created_at,
                    u.name AS caller_name, u.avatar AS caller_avatar
             FROM calls c
             JOIN call_participants cp ON cp.call_id = c.id AND cp.user_id = ?
             JOIN users u ON u.id = c.caller_id
             WHERE c.status IN ("calling", "ringing") AND c.caller_id != ?
               AND c.created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
             ORDER BY c.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$userId, $userId]);
        Response::success($stmt->fetchAll());
    }

    // GET /api/v1/calls
    public function index(): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(50, (int)($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;

        // إنهاء المكالمات المعلقة (answered بدون ended_at) أكثر من 5 دقائق
        try {
            $this->pdo->prepare(
                'UPDATE calls SET status = "ended", ended_at = NOW(),
                        duration = COALESCE(duration, TIMESTAMPDIFF(SECOND, started_at, NOW()) * 1000)
                 WHERE status IN ("answered", "accepted")
                   AND ended_at IS NULL
                   AND created_at < DATE_SUB(NOW(), INTERVAL 300 SECOND)'
            )->execute();
        } catch (\Throwable $e) {
            error_log('Stale call cleanup error: ' . $e->getMessage());
        }

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.uuid, c.caller_id,
                    (SELECT cp2.user_id FROM call_participants cp2
                     WHERE cp2.call_id = c.id AND cp2.user_id != c.caller_id
                     LIMIT 1) AS callee_id,
                    c.call_type, c.status,
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
            'SELECT c.*, u.name AS caller_name, u.avatar AS caller_avatar
             FROM calls c
             JOIN call_participants cp ON cp.call_id = c.id AND cp.user_id = ?
             JOIN users u ON u.id = c.caller_id
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

            // In-app call notification (real-time polling fallback)
            $this->pdo->prepare(
                'INSERT INTO notifications (user_id, type, title, body, data_json, created_at)
                 VALUES (?, "incoming_call", ?, ?, ?, NOW())'
            )->execute([
                $calleeId,
                $caller['name'],
                mb_substr($caller['name'], 0, 60) . ' يُجري اتصالًا... ',
                json_encode(['call_uuid' => $callUuid, 'caller_id' => $callerId, 'avatar' => $caller['avatar'] ?? null], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log('Call FCM notification error: ' . $e->getMessage());
        }
    }
}
