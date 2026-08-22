<?php
/**
 * NOVA Messenger - Call Controller (Signaling)
 * Handles call signaling. Actual media uses WebRTC (client-side).
 */

declare(strict_types=1);

class CallController
{
    private PDO|TursoPdo $pdo;

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
        $contactPhone = $body['contact_phone'] ?? '';
        $callType = in_array($body['call_type'] ?? '', ['voice', 'video']) ? $body['call_type'] : 'voice';

        $userCtrl = new UserController($this->pdo);
        if (!$calleeId && !empty($contactPhone)) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = ? OR phone = ? OR phone = ? LIMIT 1');
            $clean = preg_replace('/[^0-9]/', '', $contactPhone);
            $stmt->execute([$contactPhone, '+' . $clean, $clean]);
            $u = $stmt->fetch();
            if ($u) $calleeId = (int)$u['id'];
        }

        if (!$calleeId || $calleeId === $callerId) {
            Response::error('يجب تحديد مستخدم صحيح للاتصال به', 'MISSING_CALLEE', 400);
        }

        // فرض الخصوصية: هل يسمح المستخدم المستهدف باستقبال مكالمات من هذا المستخدم؟
        $targetPrivacy = $this->pdo->prepare('SELECT calls_from FROM privacy_settings WHERE user_id = ? LIMIT 1');
        $targetPrivacy->execute([$calleeId]);
        $tp = $targetPrivacy->fetch();
        $callsFrom = (int)($tp['calls_from'] ?? 2); // 2=الجميع، 1=جهات الاتصال، 0=لا أحد

        if ($callsFrom === 0) {
            Response::forbidden('هذا المستخدم لا يسمح باستقبال مكالمات جديدة');
        }
        if ($callsFrom === 1) {
            $isContact = $this->pdo->prepare('SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ? LIMIT 1');
            $isContact->execute([$calleeId, $callerId]);
            if (!$isContact->fetch()) {
                Response::forbidden('هذا المستخدم يسمح باستقبال مكالمات من جهات اتصاله فقط');
            }
        }

        // فحص الحظر
        if ($userCtrl->isBlockedEither($callerId, $calleeId)) {
            Response::forbidden('لا يمكنك الاتصال بهذا المستخدم');
        }

        $uuid = UuidHelper::generate();
        $this->pdo->prepare(
            "INSERT INTO calls (uuid, caller_id, call_type, status, created_at) VALUES (?, ?, ?, 'ringing', datetime('now'))"
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
                    cu.id AS caller_u_id, cu.name AS caller_n, cu.username AS caller_u, cu.phone AS caller_p, cu.email AS caller_e, cu.avatar AS caller_a, cu.is_verified AS caller_v,
                    pe.id AS callee_u_id, pe.name AS callee_n, pe.username AS callee_u, pe.phone AS callee_p, pe.email AS callee_e, pe.avatar AS callee_a, pe.is_verified AS callee_v
             FROM calls c
             JOIN users cu ON cu.id = c.caller_id
             JOIN users pe ON pe.id = ?
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$calleeId, $callId]);
        $call = $stmt->fetch();

        $callerFiltered = $userCtrl->filterProfile([
            'id' => $call['caller_u_id'], 'name' => $call['caller_n'], 'username' => $call['caller_u'],
            'phone' => $call['caller_p'], 'email' => $call['caller_e'], 'avatar' => $call['caller_a'], 'is_verified' => $call['caller_v']
        ], $calleeId, (int)$call['caller_u_id']);

        $calleeFiltered = $userCtrl->filterProfile([
            'id' => $call['callee_u_id'], 'name' => $call['callee_n'], 'username' => $call['callee_u'],
            'phone' => $call['callee_p'], 'email' => $call['callee_e'], 'avatar' => $call['callee_a'], 'is_verified' => $call['callee_v']
        ], $callerId, (int)$call['callee_u_id']);

        Response::success([
            'id'            => (int)$call['id'],
            'call_id'       => (int)$call['id'],
            'call_uuid'     => $call['uuid'],
            'caller_id'     => (int)$call['caller_id'],
            'callee_id'     => (int)$calleeId,
            'call_type'     => $call['call_type'],
            'status'        => 'ringing',
            'caller_name'   => $callerFiltered['display_name'] ?? $callerFiltered['name'],
            'caller_avatar' => $callerFiltered['avatar'],
            'peer_name'     => $calleeFiltered['display_name'] ?? $calleeFiltered['name'],
            'peer_avatar'   => $calleeFiltered['avatar'],
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
            "INSERT INTO call_signals (call_id, sender_id, signal_type, payload, created_at) VALUES (?, ?, ?, ?, datetime('now'))"
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

            $userCtrl = new UserController($this->pdo);
            $stmt = $this->pdo->prepare('SELECT id, name, username, phone, email, avatar, is_verified FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();
            if (!$sender) return;

            $stmt = $this->pdo->prepare(
                'SELECT fcm_token FROM user_devices WHERE user_id = ? AND fcm_token IS NOT NULL AND fcm_token != ""'
            );

            foreach ($peers as $peerId) {
                // تطبيق الخصوصية على اسم المرسل لكل مستقبل
                $filteredSender = $userCtrl->filterProfile($sender, $peerId, (int)$senderId);
                $senderDisplayName = $filteredSender['display_name'] ?? $filteredSender['name'];

                $stmt->execute([$peerId]);
                foreach ($stmt->fetchAll() as $device) {
                    FCMHelper::sendCallSignalNotification(
                        $device['fcm_token'],
                        (string)$callId,
                        $signalType,
                        $payloadJson,
                        $senderDisplayName
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
"UPDATE calls SET status = 'ended', ended_at = datetime('now'),
                            duration = (strftime('%s', 'now') - strftime('%s', created_at)) * 1000
                     WHERE status IN ('calling', 'ringing')
                       AND created_at < datetime('now', '-60 seconds')"
            )->execute();
            // إنهاء المكالمات "answered" المعلقة التي لا تملك ended_at
            // (سقطت دون إنهاء صريح — أكثر من 5 دقائق) حتى لا تفتح شاشة المكالمة
            // لدى المستخدمين عند كل دخول.
            $this->pdo->prepare(
"UPDATE calls SET status = 'ended', ended_at = COALESCE(ended_at, datetime('now')),
                            duration = COALESCE(duration, (strftime('%s', 'now') - strftime('%s', started_at)) * 1000)
                     WHERE status IN ('answered', 'accepted')
                       AND ended_at IS NULL
                       AND created_at < datetime('now', '-300 seconds')"
            )->execute();
        } catch (\Throwable $e) {
            error_log('Incoming call cleanup error: ' . $e->getMessage());
        }

        $stmt = $this->pdo->prepare(
"SELECT c.id, c.uuid, c.caller_id, c.call_type, c.status, c.created_at,
                        u.id AS u_id, u.name AS u_n, u.username AS u_u, u.phone AS u_p, u.email AS u_e, u.avatar AS u_a, u.is_verified AS u_v
                 FROM calls c
                 JOIN call_participants cp ON cp.call_id = c.id AND cp.user_id = ?
                 JOIN users u ON u.id = c.caller_id
                 WHERE c.status IN ('calling', 'ringing') AND c.caller_id != ?
                   AND c.created_at > datetime('now', '-60 seconds')
                 ORDER BY c.created_at DESC
                 LIMIT 1"
        );
        $stmt->execute([$userId, $userId]);
        $rows = $stmt->fetchAll();
        
        $userCtrl = new UserController($this->pdo);
        foreach ($rows as &$row) {
            $filtered = $userCtrl->filterProfile([
                'id' => $row['u_id'], 'name' => $row['u_n'], 'username' => $row['u_u'],
                'phone' => $row['u_p'], 'email' => $row['u_e'], 'avatar' => $row['u_a'], 'is_verified' => $row['u_v']
            ], $userId, (int)$row['caller_id']);
            $row['caller_name'] = $filtered['display_name'] ?? $filtered['name'];
            $row['caller_avatar'] = $filtered['avatar'];
            $row['is_verified'] = $filtered['is_verified'];
        }
        
        Response::success($rows);
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
"UPDATE calls SET status = 'ended', ended_at = datetime('now'),
                            duration = COALESCE(duration, (strftime('%s', 'now') - strftime('%s', started_at)) * 1000)
                     WHERE status IN ('answered', 'accepted')
                       AND ended_at IS NULL
                       AND created_at < datetime('now', '-300 seconds')"
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
                    u.id AS u_id, u.name AS u_n, u.username AS u_u, u.phone AS u_p, u.email AS u_e, u.avatar AS u_a, u.is_verified AS u_v,
                    p.id AS p_id, p.name AS p_n, p.username AS p_u, p.phone AS p_p, p.email AS p_e, p.avatar AS p_a, p.is_verified AS p_v
             FROM calls c
             JOIN call_participants cp ON cp.call_id = c.id AND cp.user_id = ?
             JOIN users u ON u.id = c.caller_id
             LEFT JOIN users p ON p.id = (SELECT cp2.user_id FROM call_participants cp2 WHERE cp2.call_id = c.id AND cp2.user_id != c.caller_id LIMIT 1)
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $limit, $offset]);
        $rows = $stmt->fetchAll();
        
        $userCtrl = new UserController($this->pdo);
        foreach ($rows as &$row) {
            $callerF = $userCtrl->filterProfile([
                'id' => $row['u_id'], 'name' => $row['u_n'], 'username' => $row['u_u'],
                'phone' => $row['u_p'], 'email' => $row['u_e'], 'avatar' => $row['u_a'], 'is_verified' => $row['u_v']
            ], $userId, (int)$row['caller_id']);
            
            $row['caller_name'] = $callerF['display_name'] ?? $callerF['name'];
            $row['caller_avatar'] = $callerF['avatar'];
            $row['caller_is_verified'] = $callerF['is_verified'];

            if ($row['p_id']) {
                $peerF = $userCtrl->filterProfile([
                    'id' => $row['p_id'], 'name' => $row['p_n'], 'username' => $row['p_u'],
                    'phone' => $row['p_p'], 'email' => $row['p_e'], 'avatar' => $row['p_a'], 'is_verified' => $row['p_v']
                ], $userId, (int)$row['p_id']);
                $row['peer_name'] = $peerF['display_name'] ?? $peerF['name'];
                $row['peer_avatar'] = $peerF['avatar'];
                $row['peer_is_verified'] = $peerF['is_verified'];
            }
        }
        
        Response::success($rows);
    }

    // GET /api/v1/calls/{id}
    public function show(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $stmt = $this->pdo->prepare(
            'SELECT c.*, 
                    u.id AS u_id, u.name AS u_n, u.username AS u_u, u.phone AS u_p, u.email AS u_e, u.avatar AS u_a, u.is_verified AS u_v,
                    p.id AS p_id, p.name AS p_n, p.username AS p_u, p.phone AS p_p, p.email AS p_e, p.avatar AS p_a, p.is_verified AS p_v
             FROM calls c
             JOIN call_participants cp ON cp.call_id = c.id AND cp.user_id = ?
             JOIN users u ON u.id = c.caller_id
             LEFT JOIN users p ON p.id = (SELECT cp2.user_id FROM call_participants cp2 WHERE cp2.call_id = c.id AND cp2.user_id != c.caller_id LIMIT 1)
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $id]);
        $call = $stmt->fetch();

        if (!$call) {
            Response::notFound('المكالمة غير موجودة');
        }

        $userCtrl = new UserController($this->pdo);
        $callerF = $userCtrl->filterProfile([
            'id' => $call['u_id'], 'name' => $call['u_n'], 'username' => $call['u_u'],
            'phone' => $call['u_p'], 'email' => $call['u_e'], 'avatar' => $call['u_a'], 'is_verified' => $call['u_v']
        ], $userId, (int)$call['caller_id']);
        
        $call['caller_name'] = $callerF['display_name'] ?? $callerF['name'];
        $call['caller_avatar'] = $callerF['avatar'];
        $call['caller_is_verified'] = $callerF['is_verified'];

        if ($call['p_id']) {
            $peerF = $userCtrl->filterProfile([
                'id' => $call['p_id'], 'name' => $call['p_n'], 'username' => $call['p_u'],
                'phone' => $call['p_p'], 'email' => $call['p_e'], 'avatar' => $call['p_a'], 'is_verified' => $call['p_v']
            ], $userId, (int)$call['p_id']);
            $call['peer_name'] = $peerF['display_name'] ?? $peerF['name'];
            $call['peer_avatar'] = $peerF['avatar'];
            $call['peer_is_verified'] = $peerF['is_verified'];
        }

        Response::success($call);
    }

    // POST /api/v1/calls/{id}/answer
    public function answer(int $id): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];

        $this->updateCallStatus($id, $userId, 'answered');
        $this->pdo->prepare("UPDATE calls SET started_at = datetime('now') WHERE id = ?")->execute([$id]);
        $this->pdo->prepare("UPDATE call_participants SET joined_at = datetime('now') WHERE call_id = ? AND user_id = ?")
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
            "UPDATE calls SET status = 'ended', ended_at = datetime('now'), duration = ? WHERE id = ?"
        )->execute([$duration, $id]);

        $this->pdo->prepare("UPDATE call_participants SET left_at = datetime('now') WHERE call_id = ? AND user_id = ?")
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
"INSERT INTO notifications (user_id, type, title, body, data_json, created_at)
                     VALUES (?, 'incoming_call', ?, ?, ?, datetime('now'))"
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

    // GET /api/v1/calls/ice-servers
    public function iceServers(): void
    {
        AuthMiddleware::authenticate();
        // إعدادات افتراضية (يمكن مستقبلاً جلبها من لوحة التحكم أو Xirsys/Twilio)
        $servers = [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
            ['urls' => 'stun:stun2.l.google.com:19302'],
            ['urls' => 'stun:stun3.l.google.com:19302'],
            ['urls' => 'stun:stun4.l.google.com:19302'],
        ];
        
        // إذا كان هناك خادم TURN مخصص في الإعدادات
        $turnUrl = SettingsHelper::getSetting($this->pdo, 'turn_server_url');
        if ($turnUrl) {
            $servers[] = [
                'urls'     => $turnUrl,
                'username' => SettingsHelper::getSetting($this->pdo, 'turn_server_user', ''),
                'credential' => SettingsHelper::getSetting($this->pdo, 'turn_server_pass', ''),
            ];
        }

        Response::success($servers);
    }

    /**
     * POST /api/v1/calls/{id}/log
     * Custom logging for WebRTC errors and events.
     */
    public function logEvent(int $callId): void
    {
        $auth   = AuthMiddleware::authenticate();
        $userId = (int)$auth['user_id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $eventType = $body['event_type'] ?? 'unknown';
        $logLevel  = $body['log_level'] ?? 'info';
        $message   = $body['message'] ?? null;
        $details   = isset($body['details']) ? json_encode($body['details']) : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $this->pdo->prepare(
                "INSERT INTO webrtc_logs (call_id, user_id, event_type, log_level, message, details, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))"
            )->execute([
                $callId,
                $userId,
                $eventType,
                $logLevel,
                $message,
                $details,
                $ipAddress,
                $userAgent
            ]);

            Response::success(null, 'Log recorded', 201);
        } catch (\Throwable $e) {
            error_log('WebRTC logging error: ' . $e->getMessage());
            Response::error('Failed to record log', 'LOG_ERROR', 500);
        }
    }
}
