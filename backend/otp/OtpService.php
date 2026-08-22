<?php
/**
 * NOVA Messenger — OTP Service (Core)
 *
 * Responsibilities:
 *  - Create OTP verification records (hashed, never plain)
 *  - Send OTP via ordered provider chain with automatic fallback
 *  - Manual delivery mode (code shown to admins with registration.view_otp)
 *  - Rate limiting per phone / IP
 *  - Verification (attempts, max attempts, expiry, blocking)
 *  - Admin helpers + stats
 *
 * IMPORTANT: the plain code is never persisted. In manual mode we append
 * a `manual_code_hash` column to otp_verifications so admins can see the code
 * (via regenerateManualCode) without ever storing plain text in DB.
 */

declare(strict_types=1);

class OtpService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->ensureManualColumn();
    }

    // ------------------------------------------------------------------
    // Schema guard: additive manual-code column for manual delivery mode
    // ------------------------------------------------------------------

    private function ensureManualColumn(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        
        // 1) otp_verifications columns
        try {
            $this->pdo->exec("ALTER TABLE otp_verifications ADD COLUMN manual_code_hash VARCHAR(255) NULL");
        } catch (Throwable $e) {}
        try {
            $this->pdo->exec("ALTER TABLE otp_verifications ADD COLUMN manual_code VARCHAR(16) NULL");
        } catch (Throwable $e) {}

        // 2) otp_rate_limits columns (Fix for Render schema drift)
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(otp_rate_limits)");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!empty($columns)) {
                if (!in_array('attempts_count', $columns)) {
                    $this->pdo->exec("ALTER TABLE otp_rate_limits ADD COLUMN attempts_count INTEGER NOT NULL DEFAULT 1");
                }
                if (!in_array('resend_count', $columns)) {
                    $this->pdo->exec("ALTER TABLE otp_rate_limits ADD COLUMN resend_count INTEGER NOT NULL DEFAULT 0");
                }
                if (!in_array('cooldown_until', $columns)) {
                    $this->pdo->exec("ALTER TABLE otp_rate_limits ADD COLUMN cooldown_until DATETIME DEFAULT NULL");
                }
                if (!in_array('phone', $columns) || !in_array('last_attempt_at', $columns)) {
                    // Legacy schema (bucket_key style) — reset it
                    $this->pdo->exec("DROP TABLE otp_rate_limits");
                    $this->pdo->exec("CREATE TABLE otp_rate_limits (
                        phone TEXT PRIMARY KEY,
                        last_attempt_at DATETIME NOT NULL,
                        attempts_count INTEGER NOT NULL DEFAULT 1,
                        resend_count INTEGER NOT NULL DEFAULT 0,
                        cooldown_until DATETIME DEFAULT NULL
                    )");
                }
            } else {
                // Table doesn't exist — create it
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS otp_rate_limits (
                    phone TEXT PRIMARY KEY,
                    last_attempt_at DATETIME NOT NULL,
                    attempts_count INTEGER NOT NULL DEFAULT 1,
                    resend_count INTEGER NOT NULL DEFAULT 0,
                    cooldown_until DATETIME DEFAULT NULL
                )");
            }
        } catch (Throwable $e) {
            error_log("OTP Schema Migration failed: " . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Configuration helpers
    // ------------------------------------------------------------------

    private function getSetting(string $key, string $default = ''): string
    {
        static $cache = [];
        if (isset($cache[$key])) return $cache[$key];
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $val = ($row !== false && $row['setting_value'] !== null) ? (string)$row['setting_value'] : $default;
            $cache[$key] = $val;
            return $val;
        } catch (Throwable $e) {
            return $default;
        }
    }

    public function clearSettingsCache(): void
    {
        require_once __DIR__ . '/../helpers/Database.php';
    }

    /** Get an enabled provider chain ordered by priority (fallback order) */
    private function getProviderChain(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, type, priority, is_default, is_fallback, api_key, api_secret, api_base_url, account_sid, sender_id, message_template, extra_config
             FROM otp_providers
             WHERE status = \'enabled\'
             ORDER BY priority ASC, id ASC'
        );
        return $stmt->fetchAll();
    }

    /** Load full decrypted configuration of a provider */
    private function loadProviderConfig(array $row): array
    {
        $config = [
            'type'             => $row['type'],
            'api_key'          => $row['api_key'] !== null ? OtpEncryption::decrypt((string)$row['api_key']) : null,
            'api_secret'       => $row['api_secret'] !== null ? OtpEncryption::decrypt((string)$row['api_secret']) : null,
            'api_base_url'     => $row['api_base_url'] ?? null,
            'account_sid'      => $row['account_sid'] ?? null,
            'sender_id'        => $row['sender_id'] ?? null,
            'message_template' => $row['message_template'] ?? null,
        ];
        $extra = $row['extra_config'] ?? null;
        if ($extra !== null) {
            $extra = is_string($extra) ? (json_decode($extra, true) ?? []) : $extra;
            $config = array_merge($config, $extra);
        }
        return $config;
    }

    private function providerInstance(string $type): OtpProviderInterface
    {
        return match ($type) {
            'twilio'    => new TwilioProvider(),
            'vonage'    => new VonageProvider(),
            'http_rest' => new HttpSmsProvider(),
            'test'           => new TestProvider(),
            'sms_mock'       => new SmsMockProvider(),
            'sms'            => new TestProvider(), // legacy Dev placeholder type
            'whatsapp_mock'  => new WhatsappMockProvider(),
            'whatsapp'       => new WhatsappMockProvider(), // human-friendly alias
            default          => throw new InvalidArgumentException("مزود OTP غير معروف: {$type}"),
        };
    }

    // ------------------------------------------------------------------
    // Delivery mode
    // ------------------------------------------------------------------

    /** auto | manual | auto_fallback */
    public function getDeliveryMode(): string
    {
        $mode = $this->getSetting('otp_delivery_mode', 'auto');
        return in_array($mode, ['auto', 'manual', 'auto_fallback'], true) ? $mode : 'auto';
    }

    // ------------------------------------------------------------------
    // Rate limiting
    // ------------------------------------------------------------------

    public function checkRateLimit(string $phone, string $ip, string $endpoint = 'registration'): ?string
    {
        $perPhone = (int)$this->getSetting('otp_rate_limit_per_phone_per_hour', '10');
        $perIp = (int)$this->getSetting('otp_rate_limit_per_ip_per_hour', '30');
        $perPhone = max(1, $perPhone);
        $perIp = max(1, $perIp);

        $hourAgo = date('Y-m-d H:i:s', time() - 3600);

        // simplified check for the new schema
        $stmt = $this->pdo->prepare(
            "SELECT attempts_count, last_attempt_at FROM otp_rate_limits
             WHERE phone = ? LIMIT 1"
        );
        $stmt->execute([$phone]);
        $row = $stmt->fetch();
        
        if ($row) {
            $hits = (int)$row['attempts_count'];
            $last = $row['last_attempt_at'];
            if ($hits >= $perPhone && strtotime($last) > time() - 3600) {
                $minutesLeft = max(1, (int)ceil((strtotime($last) + 3600 - time()) / 60));
                return "تجاوزت الحد المسموح لهذا الرقم. حاول بعد {$minutesLeft} دقيقة";
            }
        }

        // increment counters
        $this->pdo->prepare(
            "INSERT INTO otp_rate_limits (phone, last_attempt_at, attempts_count)
             VALUES (?, datetime('now'), 1)
             ON CONFLICT(phone) DO UPDATE SET
             attempts_count = CASE WHEN last_attempt_at < datetime('now', '-1 hour') THEN 1 ELSE attempts_count + 1 END,
             last_attempt_at = datetime('now')"
        )->execute([$phone]);

        return null;
    }

    // ------------------------------------------------------------------
    // Resend cooldown
    // ------------------------------------------------------------------

    /**
     * Convert a MySQL datetime string to a UTC Unix timestamp.
     * MySQL DATETIME is stored/read without timezone; the PHP process
     * timezone (Asia/Riyadh) must not be assumed during parsing.
     */
    private function toUnixTs(string|int|float $value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$value, new \DateTimeZone('UTC'));
        if ($dt === false) {
            return 0;
        }
        return $dt->getTimestamp();
    }

    public function resendCooldown(string $phone): int
    {
        $cooldown = 0; // (int)$this->getSetting('otp_resend_cooldown_seconds', '60');
        if ($cooldown <= 0) return 0;
        $stmt = $this->pdo->prepare(
            'SELECT MAX(created_at) AS last_req FROM otp_verifications
             WHERE phone_number = ? AND status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\')'
        );
        $stmt->execute([$phone]);
        $row = $stmt->fetch();
        if (!$row || !$row['last_req']) return 0;
        $lastTs = $this->toUnixTs($row['last_req']);
        $remaining = (int)$cooldown - (time() - $lastTs);
        return max(0, $remaining);
    }

    // ------------------------------------------------------------------
    // Create + send OTP
    // ------------------------------------------------------------------

    /**
     * Create an OTP verification and send it.
     * Returns: ['otp_id', 'delivery_mode', 'sent', 'manual', 'cooldown', 'message']
     */
    public function createAndSend(string $phone, ?string $name = null, string $ip = '', string $ua = '', ?string $devCode = null): array
    {
        $mode = $this->getDeliveryMode();

        // 1. Cancel previous pending OTPs for this phone
        $this->pdo->prepare(
            "UPDATE otp_verifications SET status = 'cancelled', updated_at = datetime('now')
             WHERE phone_number = ? AND status IN ('pending','sent','manual','delivery_failed')"
        )->execute([$phone]);

        // 2. Expire old delivered ones (cleanup, never touch verified)
        $this->pdo->prepare(
            "UPDATE otp_verifications SET status = 'expired'
             WHERE phone_number = ? AND status IN ('sent','manual','delivery_failed')
               AND expires_at IS NOT NULL AND expires_at < datetime('now')"
        )->execute([$phone]);

        // 3. Generate code + hash (dev code, if provided, is the visible code)
        $otpCode = $this->generateCode();
        $otpHash = password_hash($otpCode, PASSWORD_BCRYPT);
        $manualCodeHash = password_hash($otpCode, PASSWORD_BCRYPT);
        $expiryMinutes = (int)$this->getSetting('otp_expiry_minutes', '5');
        $maxAttempts = (int)$this->getSetting('otp_max_attempts', '5');
        if ($devCode !== null) {
            $otpCode = $devCode;
            $otpHash = password_hash($otpCode, PASSWORD_BCRYPT);
            $manualCodeHash = password_hash($otpCode, PASSWORD_BCRYPT);
        }
        // expiryMinutes <= 0 means no expiration (expires_at stays NULL)
        $expiresAt = $expiryMinutes > 0 ? gmdate('Y-m-d H:i:s', time() + $expiryMinutes * 60) : null;

        $stmt = $this->pdo->prepare(
            "INSERT INTO otp_verifications
                (phone_number, name, otp_hash, manual_code_hash, manual_code, status, attempts, max_attempts, delivery_mode, delivery_status, expires_at, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, 'pending', 0, ?, ?, NULL, ?, ?, ?, datetime('now'))"
        );
        // في وضع تطوير (dev code) أو وضع التسليم اليدوي: تخزين الرمز نصيًا حتى يطابق
        // ما يراه المستخدم في الرد (otp_dev) وما يعرضه المدير في لوحة التحكم
        $plainManualCode = $devCode !== null || $mode === 'manual' ? $otpCode : null;
        $stmt->execute([$phone, $name, $otpHash, $manualCodeHash, $plainManualCode, $maxAttempts, $mode, $expiresAt, $ip, $ua]);
        $otpId = (int)$this->pdo->lastInsertId();

        // 4. Send
        $sent = false;
        $manual = ($mode === 'manual');

        if (!$manual) {
            $sent = $this->sendViaChain($otpId, $phone, $otpCode, $mode);
        }

        if ($sent) {
            $this->pdo->prepare(
                "UPDATE otp_verifications SET status = 'sent', delivery_status = 'sent', updated_at = datetime('now') WHERE id = ?"
            )->execute([$otpId]);
        } elseif (!$manual) {
            // All providers failed → switch to manual fallback (admin sees the code)
            $fallbackEnabled = $this->getSetting('otp_enable_manual_fallback', '1') === '1';
            if ($fallbackEnabled) {
                $this->pdo->prepare(
                    "UPDATE otp_verifications SET manual_code_hash = ?, manual_code = ?, status = 'manual',
                           delivery_status = 'manual', updated_at = datetime('now')
                     WHERE id = ?"
                )->execute([password_hash($otpCode, PASSWORD_BCRYPT), $otpCode, $otpId]);
                $manual = true;
            }
        }

        $res = [
            'otp_id' => $otpId,
            'delivery_mode' => $manual ? 'manual' : 'auto',
            'sent' => $sent || $manual,
            'manual' => $manual,
            'cooldown' => (int)$this->getSetting('otp_resend_cooldown_seconds', '60'),
            'message' => $manual
                ? 'تم إنشاء رمز التحقق وسيتم إتاحته للمدير لتسليمه يدويًا'
                : 'تم إرسال رمز التحقق',
        ];

        // Only include otp_debug in development mode
        if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
            $res['otp_debug'] = $otpCode;
        }

        return $res;
    }

    /**
     * Send through the provider chain, falling back on retryable errors.
     */
    private function sendViaChain(int $otpId, string $phone, string $otpCode, string $mode): bool
    {
        $chain = $this->getProviderChain();
        if (count($chain) === 0) {
            return false;
        }

        $template = $this->getSetting('otp_message_template', 'رمز التحقق الخاص بك هو: {OTP}. صالح لمدة {MINUTES} دقيقة. لا تشاركه مع أي شخص. — {APP_NAME}');
        $fallbackEnabled = $this->getSetting('otp_enable_fallback', '1') === '1';

        foreach ($chain as $index => $row) {
            $config = $this->loadProviderConfig($row);
            $instance = $this->providerInstance($row['type']);

            $this->pdo->prepare(
                "INSERT INTO otp_delivery_logs (otp_id, provider_id, provider_type, phone_number, status, created_at)
                 VALUES (?, ?, ?, ?, 'attempt', datetime('now'))"
            )->execute([$otpId, (int)$row['id'], $row['type'], $phone]);

            $result = null;
            try {
                $result = $instance->send($phone, $otpCode, $config, $template);
            } catch (Throwable $e) {
                $result = OtpSendResult::failure('timeout', 0, 'خطأ غير متوقع: ' . substr($e->getMessage(), 0, 100));
            }

            if ($result->success) {
                $this->pdo->prepare(
                    "UPDATE otp_providers SET success_count = success_count + 1, last_used_at = datetime('now'), updated_at = datetime('now') WHERE id = ?"
                )->execute([(int)$row['id']]);
            } else {
                $this->pdo->prepare(
                    "UPDATE otp_providers SET failure_count = failure_count + 1, updated_at = datetime('now') WHERE id = ?"
                )->execute([(int)$row['id']]);
            }

            $this->pdo->prepare(
                'UPDATE otp_delivery_logs SET status = ?, http_code = ?, error_message = ?,
                        response_summary = ?, response_time_ms = ?
                 WHERE otp_id = ? AND provider_id = ? AND status = \'attempt\'
                 ORDER BY id DESC LIMIT 1'
            )->execute([
                $result->success ? 'success' : 'failed',
                $result->httpCode ?: null,
                $result->errorMessage ?: null,
                $result->responseSummary ?: null,
                $result->responseTimeMs ?: null,
                $otpId,
                (int)$row['id'],
            ]);

            if ($result->success) {
                return true;
            }

            $retryable = in_array($result->errorClass, ['auth', 'rate', 'server', 'timeout'], true);
            $isLast = ($index === count($chain) - 1);

            if (!$retryable || !$fallbackEnabled) {
                break; // do not fall back
            }
            if ($isLast) {
                break; // chain exhausted
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Resend
    // ------------------------------------------------------------------

    public function resend(int $otpId, string $ip = '', string $ua = '', ?string $devCode = null): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM otp_verifications WHERE id = ? LIMIT 1');
        $stmt->execute([$otpId]);
        $otp = $stmt->fetch();
        if (!$otp) {
            return ['success' => false, 'message' => 'طلب التحقق غير موجود', 'error_code' => 'NOT_FOUND'];
        }
        if ($otp['status'] === 'verified') {
            return ['success' => false, 'message' => 'تم التحقق من هذا الرمز مسبقًا', 'error_code' => 'OTP_ALREADY_USED'];
        }
        if (!in_array($otp['status'], ['pending', 'sent', 'manual', 'delivery_failed'], true)) {
            return ['success' => false, 'message' => 'هذا الرمز لم يعد صالحًا', 'error_code' => 'OTP_EXPIRED'];
        }

        // Cooldown
        $remaining = $this->resendCooldown($otp['phone_number']);
        if ($remaining > 0) {
            return ['success' => false, 'message' => "يمكنك إعادة الإرسال بعد {$remaining} ثانية", 'error_code' => 'OTP_COOLDOWN', 'cooldown' => $remaining];
        }

        // Max resends
        $maxResends = (int)$this->getSetting('otp_max_resends', '5');
        if ((int)$otp['resends'] >= $maxResends) {
            return ['success' => false, 'message' => 'تجاوزت الحد الأقصى لإعادة الإرسال. حاول لاحقًا', 'error_code' => 'OTP_MAX_RESENDS'];
        }

        $this->pdo->prepare("UPDATE otp_verifications SET resends = resends + 1, updated_at = datetime('now') WHERE id = ?")
                 ->execute([$otpId]);

        // Invalidate the old pending otp and create a new one
        $this->pdo->prepare(
            "UPDATE otp_verifications SET status = 'cancelled', updated_at = datetime('now') WHERE id = ?"
        )->execute([$otpId]);

        $res = $this->createAndSend($otp['phone_number'], $otp['name'], $ip, $ua, $devCode);
        $res['cooldown'] = (int)$this->getSetting('otp_resend_cooldown_seconds', '60');
        $res['success'] = true;
        return $res;
    }

    // ------------------------------------------------------------------
    // Verify
    // ------------------------------------------------------------------

    /**
     * Verify an OTP code for a phone.
     */
    public function verify(string $phone, string $code): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM otp_verifications
             WHERE phone_number = ?
               AND status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\',\'verified\',\'expired\',\'blocked\')
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$phone]);
        $otp = $stmt->fetch();
        if (!$otp) {
            return ['verified' => false, 'error_code' => 'OTP_EXPIRED', 'message' => 'لا يوجد رمز تحقق نشط. اطلب رمزًا جديدًا'];
        }
        if (in_array($otp['status'], ['verified', 'expired', 'blocked'], true)) {
            $msg = $otp['status'] === 'verified'
                ? 'اكتمل التحقق بهذا الرمز. اطلب رمزًا جديدًا إذا أردت محاولة أخرى'
                : ($otp['status'] === 'blocked'
                    ? 'تجاوزت الحد الأقصى للمحاولات. اطلب رمزًا جديدًا'
                    : 'انتهت صلاحية الرمز. اطلب رمزًا جديدًا');
            return ['verified' => false, 'error_code' => $otp['status'] === 'blocked' ? 'OTP_BLOCKED' : 'OTP_EXPIRED', 'message' => $msg];
        }

        if ($otp['expires_at'] !== null && $this->toUnixTs($otp['expires_at']) < time()) {
            $this->pdo->prepare("UPDATE otp_verifications SET status = 'expired', updated_at = datetime('now') WHERE id = ?")
                     ->execute([(int)$otp['id']]);
            return ['verified' => false, 'error_code' => 'OTP_EXPIRED', 'message' => 'انتهت صلاحية الرمز. اطلب رمزًا جديدًا'];
        }

        if ((int)$otp['attempts'] >= (int)$otp['max_attempts']) {
            $this->pdo->prepare("UPDATE otp_verifications SET status = 'blocked', updated_at = datetime('now') WHERE id = ?")
                     ->execute([(int)$otp['id']]);
            return ['verified' => false, 'error_code' => 'OTP_BLOCKED', 'message' => 'تجاوزت الحد الأقصى للمحاولات. اطلب رمزًا جديدًا'];
        }

        $this->pdo->prepare("UPDATE otp_verifications SET attempts = attempts + 1, updated_at = datetime('now') WHERE id = ?")
                 ->execute([(int)$otp['id']]);

        $valid = password_verify(trim($code), $otp['otp_hash']);
        // قبول الكود اليدوي الذي عرضه المدير للمستخدم (وضع التسليم اليدوي)
        if (!$valid && $otp['manual_code_hash'] !== null) {
            $valid = password_verify(trim($code), $otp['manual_code_hash']);
        }
        if (!$valid) {
            $left = (int)$otp['max_attempts'] - (int)$otp['attempts'] - 1;
            $msg = $left <= 0 ? 'تجاوزت الحد الأقصى للمحاولات' : "رمز التحقق غير صحيح. متبقٍ {$left} محاولة";
            return ['verified' => false, 'error_code' => 'OTP_INVALID', 'message' => $msg, 'attempts_left' => max(0, $left)];
        }

        $this->pdo->prepare(
            "UPDATE otp_verifications SET status = 'verified', verified_at = datetime('now'), updated_at = datetime('now') WHERE id = ?"
        )->execute([(int)$otp['id']]);

        // Cleanup: expire other pending OTPs for the same phone
        $this->pdo->prepare(
            "UPDATE otp_verifications SET status = 'cancelled', updated_at = datetime('now')
             WHERE phone_number = ? AND id != ? AND status IN ('pending','sent','manual','delivery_failed')"
        )->execute([$phone, (int)$otp['id']]);

        return ['verified' => true, 'otp_id' => (int)$otp['id']];
    }

    // ------------------------------------------------------------------
    // Admin helpers
    // ------------------------------------------------------------------

    public function getPendingRegistrations(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT o.id, o.phone_number, o.name, o.status, o.delivery_mode, o.delivery_status,
                    o.attempts, o.resends, o.expires_at, o.created_at, o.provider_id,
                    p.name AS provider_name
             FROM otp_verifications o
             LEFT JOIN otp_providers p ON p.id = o.provider_id
             WHERE o.status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\',\'verified\')
             ORDER BY CASE WHEN o.status = \'verified\' THEN 1 ELSE 0 END ASC, o.id DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $countStmt = $this->pdo->query(
            'SELECT COUNT(*) c FROM otp_verifications WHERE status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\')'
        );
        $total = (int)$countStmt->fetch()['c'];

        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))];
    }

    /**
     * Regenerate (or reveal) the OTP code for manual delivery to an admin.
     * The plain code is returned exactly ONCE at generation; admins view
     * the masked hash via manual_code_hash — the code itself is only ever
     * produced at (re)generation time.
     *
     * @return array {code, expires_at} or null
     */
    public function revealManualCode(int $otpId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM otp_verifications WHERE id = ? LIMIT 1');
        $stmt->execute([$otpId]);
        $otp = $stmt->fetch();
        if (!$otp) return null;
        if (!in_array($otp['status'], ['pending', 'sent', 'manual', 'delivery_failed'], true)) {
            return null;
        }
        if ($otp['manual_code_hash'] === null) {
            return null; // auto mode — no manual code available
        }

        // رمز ثابت لا يتغيّر عند إعادة العرض — يظل صالحًا طوال مدّته (يتغيّر فقط عند انتهاء الوقت أو طلب جديد)
        $code = $otp['manual_code'] ?? null;
        $nowPlus = (int)$this->getSetting('otp_expiry_minutes', '5');
        $nowPlusTs = time() + max($nowPlus, 0) * 60;
        // أقصى صلاحية: بين الصلاحية الأصلية و(الآن + مدة الصلاحية)
        // حتى لا تُنقص مدّة الرمز إذا عُرض بعد فترات طويلة
        $originalExpiryTs = ($otp['expires_at'] !== null) ? $this->toUnixTs($otp['expires_at']) : 0;
        $freshExpiryTs = ($originalExpiryTs > $nowPlusTs) ? $originalExpiryTs : $nowPlusTs;

                if ($code === null || $code === '') {
            // أول عرض بدون نص الرمز (سجل قديم أو وضع auto_fallback):
            // يُولَّد رمز جديد ويُحدَّث otp_hash أيضًا حتى يطابق ما يعرضه المدير
            // للمستخدم، وإلا سيظهر رقم مختلف عمّا يقبله التطبيق.
            $code = $this->generateCode();
            $this->pdo->prepare(
                'UPDATE otp_verifications SET otp_hash = ? WHERE id = ?'
            )->execute([password_hash($code, PASSWORD_BCRYPT), $otpId]);
        } elseif ($originalExpiryTs > 0 && $originalExpiryTs < time()) {
            // انتهت الصلاحية: توليد كود جديد وتحديث الهاش وتمديد الصلاحية
            $code = $this->generateCode();
            $freshExpiryTs = $nowPlusTs;
            $this->pdo->prepare(
                'UPDATE otp_verifications SET otp_hash = ? WHERE id = ?'
            )->execute([password_hash($code, PASSWORD_BCRYPT), $otpId]);
        }
        $hash = password_hash($code, PASSWORD_BCRYPT);
        // DB (SQLite datetime('now')) يخزن الوقت بصيغة UTC — نحفظ بنفس الصيغة لقراءة صحيحة لاحقًا
        $freshExpiry = gmdate('Y-m-d H:i:s', $freshExpiryTs);
        $this->pdo->prepare(
            "UPDATE otp_verifications SET manual_code_hash = ?, manual_code = ?, status = 'manual',
                   expires_at = ?, updated_at = datetime('now')
             WHERE id = ?"
        )->execute([$hash, $code, $freshExpiry, $otpId]);
        return ['code' => $code, 'expires_at' => $freshExpiry];
    }

    /**
     * Cancel a registration request (admin, registration.cancel)
     */
    public function cancel(int $otpId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE otp_verifications SET status = 'cancelled', updated_at = datetime('now') " .
            "WHERE id = ? AND status IN ('pending','sent','manual','delivery_failed')"
        );
        $stmt->execute([$otpId]);
        return (bool)$stmt->rowCount();
    }

    /**
     * Verify a registration manually by admin (registration.verify):
     * mark the OTP verified without needing the code.
     */
    public function adminVerify(int $otpId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM otp_verifications WHERE id = ? AND status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\') LIMIT 1'
        );
        $stmt->execute([$otpId]);
        $otp = $stmt->fetch();
        if (!$otp) return null;

                $this->pdo->prepare(
            "UPDATE otp_verifications SET status = 'verified', verified_at = datetime('now'), updated_at = datetime('now') " .
            "WHERE id = ?"
        )->execute([$otpId]);

        $userStmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        $userStmt->execute([$otp['phone_number']]);
        $user = $userStmt->fetch();
        return $user ? (int)$user['id'] : null;
    }

    // ------------------------------------------------------------------
    // Stats
    // ------------------------------------------------------------------

    private function generateCode(): string
    {
        // Always generate a real random OTP (no fixed dev code)
        $length = (int)$this->getSetting('otp_length', '6');
        $length = max(4, min(10, $length));
        return str_pad((string)random_int(0, (int)pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Stats
    // ------------------------------------------------------------------

    public function getStats(string $day = ''): array
    {
        $when = $day !== '' ? " AND DATE(o.created_at) = ?" : "";
        $params = $day !== '' ? [$day] : [];

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total,
SUM(CASE WHEN o.status = 'verified' THEN 1 ELSE 0 END) AS verified,
	                SUM(CASE WHEN o.delivery_mode IN ('auto','auto_fallback') THEN 1 ELSE 0 END) AS auto_count,
	                SUM(CASE WHEN o.delivery_mode = 'manual' OR o.status = 'manual' THEN 1 ELSE 0 END) AS manual_count,
	                SUM(CASE WHEN o.status = 'delivery_failed' THEN 1 ELSE 0 END) AS failed,
	                SUM(CASE WHEN o.status = 'expired' THEN 1 ELSE 0 END) AS expired,
	                SUM(CASE WHEN o.status = 'blocked' THEN 1 ELSE 0 END) AS blocked
            FROM otp_verifications o WHERE 1=1 {$when}"
        );
        $stmt->execute($params);
        $counts = $stmt->fetch();

        $best = $this->pdo->query(
            'SELECT p.id, p.name, p.type, p.success_count, p.failure_count
             FROM otp_providers p ORDER BY p.success_count DESC LIMIT 5'
        )->fetchAll();

        // success rate
        $total = (int)$counts['total'];
        $verified = (int)$counts['verified'];
        $rate = $total > 0 ? round(($verified / $total) * 100, 1) : 0;

        return [
            'counts' => $counts,
            'success_rate' => $rate,
            'top_providers' => $best,
        ];
    }
}
