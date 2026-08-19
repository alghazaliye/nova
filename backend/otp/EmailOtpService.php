<?php
/**
 * NOVA Messenger — Email OTP Service
 *
 * Email-only verification codes (independent of the phone OTP pipeline):
 *  - getSetting() reads otp_email_* settings (fallback to otp_phone_*)
 *  - createAndSend(): email row in email_delivery_logs + manual mode support
 *  - verifyCode(): attempts / expiry / block like phone OTP
 *  - sendViaProviders(): SMTP (php-native with OpenSSL) + HTTP REST chain
 *
 * NOTE: otp_verifications (phone) is NOT used for email — email OTPs live in
 * `email_verification_codes` (created by ensureTable) to keep the two
 * channels fully independent as required.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/OtpEncryption.php';

class EmailOtpService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS `email_verification_codes` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `email` VARCHAR(190) NOT NULL,
                  `name` VARCHAR(150) NULL DEFAULT NULL,
                  `code_hash` VARCHAR(255) NOT NULL,
                  `manual_code_hash` VARCHAR(255) NULL DEFAULT NULL,
                  `purpose` ENUM(\'registration\',\'login\',\'phone_verification\') NOT NULL DEFAULT \'registration\',
                  `status` ENUM(\'pending\',\'sent\',\'manual\',\'verified\',\'expired\',\'blocked\',\'cancelled\') NOT NULL DEFAULT \'pending\',
                  `attempts` INT NOT NULL DEFAULT 0,
                  `max_attempts` INT NOT NULL DEFAULT 5,
                  `resends` INT NOT NULL DEFAULT 0,
                  `delivery_mode` ENUM(\'auto\',\'manual\') NOT NULL DEFAULT \'auto\',
                  `expires_at` DATETIME NULL DEFAULT NULL,
                  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
                  `user_agent` TEXT NULL DEFAULT NULL,
                  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  INDEX `idx_evc_email` (`email`),
                  INDEX `idx_evc_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // table already exists
        }
    }

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
        // static cache is per-request anyway
    }

    private function isEnabled(): bool
    {
        return $this->getSetting('otp_email_enabled', '0') === '1';
    }

    /** expiry minutes for email OTP */
    private function expiryMinutes(): int
    {
        $v = (int)$this->getSetting('otp_email_expiry_minutes', '5');
        return $v > 0 ? $v : 5;
    }

    private function maxAttempts(): int
    {
        $v = (int)$this->getSetting('otp_email_max_attempts', '5');
        return $v > 0 ? $v : 5;
    }

    private function cooldownSeconds(): int
    {
        return max(0, (int)$this->getSetting('otp_email_resend_cooldown_seconds', '30'));
    }

    private function maxResends(): int
    {
        $v = (int)$this->getSetting('otp_email_max_resends', '10');
        return $v > 0 ? $v : 10;
    }

    /** Convert MySQL datetime to UTC unix ts (timezone-safe) */
    private function toUnixTs(string|int|float $value): int
    {
        if (is_numeric($value)) return (int)$value;
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$value, new \DateTimeZone('UTC'));
        return $dt !== false ? $dt->getTimestamp() : 0;
    }

    // ------------------------------------------------------------------
    // Code generation (honors dev test envs like phone OTP)
    // ------------------------------------------------------------------

    private function generateCode(): string
    {
        $provider = $_ENV['OTP_PROVIDER'] ?? null;
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Provider chain (email_providers)
    // ------------------------------------------------------------------

    private function getProviderChain(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT * FROM email_providers WHERE status = \'enabled\' ORDER BY priority ASC, id ASC'
            );
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Build an SMTP config array from an email_providers row */
    private function loadSmtpConfig(array $row): array
    {
        return [
            'type' => 'smtp',
            'host' => $row['host'] ?? '',
            'port' => (int)($row['port'] ?? 465),
            'encryption' => $row['encryption'] ?? 'tls',
            'username' => $row['username'] ?? '',
            'password' => $row['password'] !== null ? OtpEncryption::decrypt((string)$row['password']) : '',
            'from_email' => $row['from_email'] ?? '',
            'from_name' => $row['from_name'] ?? 'NOVA Messenger',
        ];
    }

    /** Build an HTTP REST config array from extra_config JSON */
    private function loadRestConfig(array $row): array
    {
        $config = [
            'type' => 'http_rest',
            'api_key' => $row['api_key'] !== null ? OtpEncryption::decrypt((string)$row['api_key']) : '',
            'api_base_url' => $row['api_base_url'] ?? '',
        ];
        $extra = $row['extra_config'] ?? null;
        if ($extra !== null) {
            $extra = is_string($extra) ? (json_decode($extra, true) ?? []) : $extra;
            $config = array_merge($config, $extra);
        }
        return $config;
    }

    // ------------------------------------------------------------------
    // Sending
    // ------------------------------------------------------------------

    /**
     * Send raw email via SMTP using built-in OpenSSL streams (no external lib).
     * Returns success status.
     */
    public function sendSmtp(array $config, string $to, string $subject, string $body): array
    {
        $host = trim((string)($config['host'] ?? ''));
        $port = (int)($config['port'] ?? 465);
        $enc = strtolower((string)($config['encryption'] ?? 'tls'));
        $user = (string)($config['username'] ?? '');
        $pass = (string)($config['password'] ?? '');
        $from = trim((string)($config['from_email'] ?? $user));
        $fromName = trim((string)($config['from_name'] ?? 'NOVA Messenger'));

        if ($host === '' || $from === '') {
            return ['success' => false, 'message' => 'إعدادات SMTP غير مكتملة (host/from_email)'];
        }

        $start = microtime(true);
        try {
            $tls = $enc !== 'none';
            // Use plain tcp:// and upgrade via STARTTLS explicitly (tls:// wrapper
            // fails on hosts without accessible local CA trust such as Gmail).
            $proto = ($tls && $enc === 'ssl') ? 'ssl://' : 'tcp://';
            $errno = 0; $errstr = '';
            $fp = @stream_socket_client($proto . $host . ':' . $port, $errno, $errstr, 15);
            if (!$fp) {
                return ['success' => false, 'message' => 'فشل الاتصال بالخادم: ' . ($errstr ?: 'connection refused'), 'http_code' => 0,
                        'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
            }
            stream_set_timeout($fp, 15);
            $lastLineOk = static function (string $buf): bool {
                // Multi-line SMTP replies (e.g. EHLO 250-...) end with a line whose
                // 4th character is a space: '250 OK', '354 Go ahead', '535 ...'.
                $pos = strrpos($buf, "\r\n");
                $lastLine = ($pos !== false) ? substr($buf, $pos + 2) : $buf;
                return (strlen($lastLine) >= 4 && $lastLine[3] === ' ');
            };
            // We keep the 15s stream timeout; fgets() inside the select loop returns
            // data is available, so we combine it with stream_select for waits.
            $getLine = static function () use ($fp, $lastLineOk): string {
                $line = '';
                $deadline = microtime(true) + 15;
                while (!feof($fp) && microtime(true) < $deadline) {
                    $readArr = [$fp];
                    $writeArr = null;
                    $errArr = null;
                    $read = stream_select($readArr, $writeArr, $errArr, 1);
                    if ($read === 0) continue;            // waiting for data
                    if ($read === false) break;
                    $chunk = fgets($fp, 515);
                    if ($chunk === '' || $chunk === false) {
                        // Nothing available right now and the reply is already
                        // complete (last line starts with '2xx ') — stop waiting.
                        if ($lastLineOk($line)) break;
                        continue;
                    }
                    $line .= $chunk;
                    if ($lastLineOk($line)) break;
                }
                return trim($line);
            };
            $banner = $getLine();
            if (!str_starts_with($banner, '220')) {
                return ['success' => false, 'message' => 'رد غير متوقع من الخادم: ' . substr($banner, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
            }

            $say = static function (string $cmd) use ($fp, $getLine): string {
                fwrite($fp, $cmd . "\r\n");
                return $getLine();
            };

            $say('EHLO nova-messenger.local');
            if ($tls && $enc === 'tls') {
                $r = $say('STARTTLS');
                if (!str_starts_with($r, '220')) {
                    return ['success' => false, 'message' => 'STARTTLS مرفوض: ' . substr($r, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
                }
                stream_set_timeout($fp, 30);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                    return ['success' => false, 'message' => 'فشل تفعيل TLS', 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
                }
                $say('EHLO nova-messenger.local');
            }

            if ($user !== '' && $pass !== '') {
                $r = $say('AUTH LOGIN');
                if (!str_starts_with($r, '334')) {
                    return ['success' => false, 'message' => 'AUTH LOGIN مرفوض: ' . substr($r, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
                }
                $r = $say(base64_encode($user));
                if (!str_starts_with($r, '334')) {
                    return ['success' => false, 'message' => 'اسم المستخدم مرفوض', 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
                }
                $r = $say(base64_encode($pass));
                if (!str_starts_with($r, '235')) {
                    return ['success' => false, 'message' => 'كلمة المرور غير صحيحة: ' . substr($r, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
                }
            }

            $r = $say('MAIL FROM:<' . $from . '>');
            if (!str_starts_with($r, '250')) {
                return ['success' => false, 'message' => 'MAIL FROM مرفوض: ' . substr($r, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
            }
            $r = $say('RCPT TO:<' . $to . '>');
            if (!str_starts_with($r, '250')) {
                return ['success' => false, 'message' => 'المستلم مرفوض: ' . substr($r, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
            }
            $r = $say('DATA');
            if (!str_starts_with($r, '354')) {
                return ['success' => false, 'message' => 'DATA مرفوض: ' . substr($r, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
            }

            $headers = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
            $r = $getLine();
            fclose($fp);

            if (!str_starts_with($r, '250')) {
                return ['success' => false, 'message' => 'الرسالة رُفضت: ' . substr($r, 0, 80), 'http_code' => 0, 'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
            }
            return ['success' => true, 'message' => 'تم الإرسال', 'http_code' => 250,
                    'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'خطأ SMTP: ' . substr($e->getMessage(), 0, 120), 'http_code' => 0,
                    'response_time_ms' => (int)((microtime(true) - $start) * 1000)];
        }
    }

    /**
     * Send via HTTP REST provider (reuses the same convention as HttpSmsProvider
     * for extra_config: method/content_type/auth_type/to_field/otp_field/template_mode/success_expr).
     */
    public function sendRest(array $config, string $to, string $code, string $template): array
    {
        $baseUrl = rtrim(trim((string)($config['api_base_url'] ?? '')), '/');
        if ($baseUrl === '') {
            return ['success' => false, 'message' => 'رابط API غير مهيأ'];
        }
        $method = strtoupper((string)($config['method'] ?? 'POST'));
        $authType = (string)($config['auth_type'] ?? 'bearer');
        $toField = (string)($config['to_field'] ?? 'to');
        $codeField = (string)($config['otp_field'] ?? 'otp');
        $contentType = (string)($config['content_type'] ?? 'application/json');
        $apiKey = (string)($config['api_key'] ?? '');

        $body = trim($template);
        if ($body === '') {
            $body = $this->getSetting('otp_email_template', 'رمز التحقق الخاص بك هو: {OTP}. صالح لمدة {MINUTES} دقيقة. لا تشاركه مع أي شخص. — {APP_NAME}');
        }
        $templateMode = (string)($config['template_mode'] ?? 'inline');

        if ($templateMode === 'inline') {
            $body = str_replace(['{OTP}', '{MINUTES}', '{APP_NAME}'], [$code, (string)$this->expiryMinutes(), 'NOVA Messenger'], $body);
        }

        // Full JSON payload mode: supports providers like Brevo whose body schema
        // cannot be expressed with a flat to/otp field (e.g. "to" must be an array).
        $jsonTpl = (string)($config['payload_template_json'] ?? '');
        if ($jsonTpl !== '') {
            $filled = str_replace(
                ['{TO}', '{OTP}', '{MINUTES}', '{APP_NAME}', '{SUBJECT}'],
                [$to, $code, (string)$this->expiryMinutes(), 'NOVA Messenger', $template !== '' ? $template : 'رمز التحقق'],
                $jsonTpl
            );
            $decoded = json_decode($filled, true);
            if (!is_array($decoded)) {
                return ['success' => false, 'message' => 'قالب JSON غير صالح', 'http_code' => 0, 'response_time_ms' => 0];
            }
            $payload = $decoded;
            $method = strtoupper((string)($config['method'] ?? 'POST'));
        } else {
            $payload = is_array($config['payload_template'] ?? null) ? $config['payload_template'] : [];
            $payload[$toField] = $to;
            $payload[$codeField] = $code;
            if ($templateMode === 'inline') {
                $payload['message'] = $body;
            }
        }

        $headers = ['Content-Type: ' . $contentType, 'Accept: application/json'];
        if ($authType === 'bearer' && $apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;
        if ($authType === 'basic' && $apiKey !== '') $headers[] = 'Authorization: Basic ' . base64_encode($apiKey);
        if ($authType === 'header' && $apiKey !== '') {
            $hk = (string)($config['auth_header_key'] ?? 'X-API-Key');
            $headers[] = $hk . ': ' . $apiKey;
        }

        $url = $baseUrl;
        if ($method === 'GET' || $method === 'DELETE') {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
            $bodyStr = null;
        } else {
            $bodyStr = str_starts_with($contentType, 'application/json')
                ? json_encode($payload, JSON_UNESCAPED_UNICODE)
                : http_build_query($payload);
        }

        $start = microtime(true);
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $bodyStr,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => (bool)($_ENV['EMAIL_REST_SSL_VERIFY'] ?? true),
            ]);
            $raw = (string)curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $time = (int)(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === '' && $err !== '') {
                return ['success' => false, 'message' => 'خطأ cURL: ' . $err, 'http_code' => $httpCode, 'response_time_ms' => $time];
            }
            // success detection via success_expr if provided
            $successExpr = (string)($config['success_expr'] ?? '');
            if ($successExpr !== '') {
                $data = json_decode($raw, true) ?? [];
                $match = (string)($config['success_match'] ?? '1');
                $val = $data[$successExpr] ?? null;
                if ($val === null || (string)$val !== $match) {
                    return ['success' => false, 'message' => 'الاستجابة لم تطابق التوقع: ' . substr($raw, 0, 120), 'http_code' => $httpCode, 'response_time_ms' => $time];
                }
                return ['success' => true, 'message' => 'تم الإرسال', 'http_code' => $httpCode, 'response_time_ms' => $time];
            }
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'message' => 'تم الإرسال', 'http_code' => $httpCode, 'response_time_ms' => $time];
            }
            return ['success' => false, 'message' => 'HTTP ' . $httpCode . ': ' . substr($raw, 0, 120), 'http_code' => $httpCode, 'response_time_ms' => $time];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'خطأ غير متوقع: ' . substr($e->getMessage(), 0, 120), 'http_code' => 0, 'response_time_ms' => 0];
        }
    }

    /** Send via the enabled email provider chain */
    private function sendViaProviders(string $to, string $code, string $subject): array
    {
        $chain = $this->getProviderChain();
        if (count($chain) === 0) {
            return ['success' => false, 'message' => 'لا يوجد مزود بريد مفعّل', 'http_code' => 0, 'response_time_ms' => 0];
        }

        $template = $this->getSetting('otp_email_template', 'رمز التحقق الخاص بك هو: {OTP}. صالح لمدة {MINUTES} دقيقة. لا تشاركه مع أي شخص. — {APP_NAME}');

        foreach ($chain as $index => $row) {
            $result = null;
            try {
                if ($row['type'] === 'smtp') {
                    $body = str_replace(['{OTP}', '{MINUTES}', '{APP_NAME}'], [$code, (string)$this->expiryMinutes(), 'NOVA Messenger'], $template);
                    $result = $this->sendSmtp($this->loadSmtpConfig($row), $to, $subject, $body);
                } else {
                    $result = $this->sendRest($this->loadRestConfig($row), $to, $code, $template);
                }
            } catch (Throwable $e) {
                $result = ['success' => false, 'message' => 'خطأ غير متوقع: ' . substr($e->getMessage(), 0, 100), 'http_code' => 0, 'response_time_ms' => 0];
            }

            $this->logDelivery($to, $row['purpose'] ?? 'registration', (int)$row['id'], $subject, $result);

            $this->pdo->prepare(
                'UPDATE email_providers SET ' . ($result['success'] ? 'success_count = success_count + 1' : 'failure_count = failure_count + 1')
                . ', last_used_at = NOW(), updated_at = NOW() WHERE id = ?'
            )->execute([(int)$row['id']]);

            if ($result['success']) {
                return $result;
            }

            $isLast = ($index === count($chain) - 1);
            if ($isLast) break;
        }

        return ['success' => false, 'message' => 'فشل جميع مزودي البريد', 'http_code' => 0, 'response_time_ms' => 0];
    }

    private function logDelivery(string $to, string $purpose, int $providerId, ?string $subject, array $result): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO email_delivery_logs (email_type, to_email, provider_id, subject, status, http_code, response_time_ms, response_summary, error_message, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $purpose, $to, $providerId ?: null, $subject,
                $result['success'] ? 'sent' : 'failed',
                $result['http_code'] ?: null,
                $result['response_time_ms'] ?: null,
                null,
                ($result['success'] ? null : ($result['message'] ?? null)),
            ]);
        } catch (Throwable $e) {}
    }

    // ------------------------------------------------------------------
    // Create + verify
    // ------------------------------------------------------------------

    public function createAndSend(string $email, ?string $name, string $purpose, string $ip, string $ua, ?string $devCode = null): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'التحقق بالبريد غير مفعّل', 'delivery_mode' => 'none', 'cooldown' => 0];
        }

        // Cancel previous pending codes for this email
        $this->pdo->prepare(
            'UPDATE email_verification_codes SET status = \'cancelled\', updated_at = NOW()
             WHERE email = ? AND status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\')'
        )->execute([$email]);

        $code = ($devCode !== null && trim($devCode) !== '') ? trim($devCode) : $this->generateCode();
        $hash = password_hash($code, PASSWORD_BCRYPT);
        $maxAttempts = $this->maxAttempts();
        $expiry = date('Y-m-d H:i:s', time() + $this->expiryMinutes() * 60);

        // Manual mode: if the only enabled provider is "اختبار (manual)" or no real provider, switch to manual
        $manual = false;
        $chain = $this->getProviderChain();
        $manualOnly = count($chain) === 1 && strtolower($chain[0]['name']) === 'اختبار (manual)';
        if (count($chain) === 0 || $manualOnly) {
            $manual = true;
        }

        $subject = 'رمز التحقق الخاص بك — NOVA Messenger';
        $deliveryMode = $manual ? 'manual' : 'auto';

        $stmt = $this->pdo->prepare(
            'INSERT INTO email_verification_codes
                (email, name, code_hash, manual_code_hash, purpose, status, attempts, max_attempts, resends, delivery_mode, expires_at, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $email, $name, $hash,
            password_hash($code, PASSWORD_BCRYPT),
            $purpose, $manual ? 'manual' : 'pending',
            $maxAttempts, $deliveryMode, $expiry, $ip, $ua,
        ]);
        $codeId = (int)$this->pdo->lastInsertId();

        if (!$manual) {
            $result = $this->sendViaProviders($email, $code, $subject);
            if ($result['success']) {
                $this->pdo->prepare('UPDATE email_verification_codes SET status = \'sent\', updated_at = NOW() WHERE id = ?')->execute([$codeId]);
                return ['success' => true, 'delivery_mode' => 'auto', 'cooldown' => $this->cooldownSeconds()];
            }
            // All providers failed → manual fallback
            $this->pdo->prepare(
                'UPDATE email_verification_codes SET manual_code_hash = ?, status = \'manual\', updated_at = NOW()
                 WHERE id = ? AND status IN (\'pending\')'
            )->execute([password_hash($code, PASSWORD_BCRYPT), $codeId]);
            return ['success' => true, 'delivery_mode' => 'manual', 'cooldown' => $this->cooldownSeconds(),
                    'provider_error' => $result['message'] ?? null];
        }

        return ['success' => true, 'delivery_mode' => 'manual', 'cooldown' => $this->cooldownSeconds()];
    }

    public function verifyCode(string $email, string $code): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM email_verification_codes
             WHERE email = ? AND status IN (\'pending\',\'sent\',\'manual\')
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$email]);
        $otp = $stmt->fetch();

        if (!$otp) {
            return ['verified' => false, 'error_code' => 'OTP_EXPIRED', 'message' => 'لا يوجد رمز تحقق نشط. اطلب رمزًا جديدًا'];
        }
        if ($otp['expires_at'] !== null && $this->toUnixTs($otp['expires_at']) < time()) {
            $this->pdo->prepare('UPDATE email_verification_codes SET status = \'expired\', updated_at = NOW() WHERE id = ?')->execute([(int)$otp['id']]);
            return ['verified' => false, 'error_code' => 'OTP_EXPIRED', 'message' => 'انتهت صلاحية الرمز. اطلب رمزًا جديدًا'];
        }
        if ((int)$otp['attempts'] >= (int)$otp['max_attempts']) {
            $this->pdo->prepare('UPDATE email_verification_codes SET status = \'blocked\', updated_at = NOW() WHERE id = ?')->execute([(int)$otp['id']]);
            return ['verified' => false, 'error_code' => 'OTP_BLOCKED', 'message' => 'تجاوزت الحد الأقصى للمحاولات. اطلب رمزًا جديدًا'];
        }

        $this->pdo->prepare('UPDATE email_verification_codes SET attempts = attempts + 1, updated_at = NOW() WHERE id = ?')->execute([(int)$otp['id']]);

        if (!password_verify(trim($code), $otp['code_hash'])) {
            $left = max(0, (int)$otp['max_attempts'] - (int)$otp['attempts'] - 1);
            $msg = $left <= 0 ? 'تجاوزت الحد الأقصى للمحاولات' : "رمز التحقق غير صحيح. متبقٍ {$left} محاولة";
            return ['verified' => false, 'error_code' => 'OTP_INVALID', 'message' => $msg, 'attempts_left' => $left];
        }

        $this->pdo->prepare('UPDATE email_verification_codes SET status = \'verified\', updated_at = NOW() WHERE id = ?')->execute([(int)$otp['id']]);
        return ['verified' => true, 'email_code_id' => (int)$otp['id']];
    }

    public function resendCooldown(string $email): int
    {
        $cooldown = $this->cooldownSeconds();
        if ($cooldown <= 0) return 0;
        $stmt = $this->pdo->prepare(
            'SELECT MAX(created_at) AS last_req FROM email_verification_codes
             WHERE email = ? AND status IN (\'pending\',\'sent\',\'manual\')'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !$row['last_req']) return 0;
        return max(0, (int)$cooldown - (time() - $this->toUnixTs($row['last_req'])));
    }

    public function resend(int $codeId, string $ip = '', string $ua = '', ?string $devCode = null): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_verification_codes WHERE id = ? LIMIT 1');
        $stmt->execute([$codeId]);
        $otp = $stmt->fetch();
        if (!$otp) {
            return ['success' => false, 'message' => 'طلب التحقق غير موجود', 'error_code' => 'NOT_FOUND'];
        }
        if ($otp['status'] === 'verified') {
            return ['success' => false, 'message' => 'تم التحقق مسبقًا', 'error_code' => 'OTP_ALREADY_USED'];
        }
        if (!in_array($otp['status'], ['pending', 'sent', 'manual'], true)) {
            return ['success' => false, 'message' => 'هذا الرمز لم يعد صالحًا', 'error_code' => 'OTP_EXPIRED'];
        }
        $remaining = $this->resendCooldown((string)$otp['email']);
        if ($remaining > 0) {
            return ['success' => false, 'message' => "يمكنك إعادة الإرسال بعد {$remaining} ثانية", 'error_code' => 'OTP_COOLDOWN', 'cooldown' => $remaining];
        }
        if ((int)$otp['resends'] >= $this->maxResends()) {
            return ['success' => false, 'message' => 'تجاوزت الحد الأقصى لإعادة الإرسال', 'error_code' => 'OTP_MAX_RESENDS'];
        }

        $this->pdo->prepare('UPDATE email_verification_codes SET resends = resends + 1, updated_at = NOW() WHERE id = ?')->execute([$codeId]);
        $this->pdo->prepare('UPDATE email_verification_codes SET status = \'cancelled\', updated_at = NOW() WHERE id = ?')->execute([$codeId]);

        $res = $this->createAndSend((string)$otp['email'], $otp['name'], (string)$otp['purpose'], $ip, $ua, $devCode);
        $res['success'] = true;
        $res['cooldown'] = $this->cooldownSeconds();
        return $res;
    }

    /** Admin: reveal/regenerate manual code for email verification */
    public function revealManualCode(int $codeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_verification_codes WHERE id = ? LIMIT 1');
        $stmt->execute([$codeId]);
        $otp = $stmt->fetch();
        if (!$otp) return null;
        if (!in_array($otp['status'], ['pending', 'sent', 'manual'], true)) return null;
        if ($otp['manual_code_hash'] === null) return null;

        $code = $this->generateCode();
        $this->pdo->prepare(
            'UPDATE email_verification_codes SET manual_code_hash = ?, status = \'manual\',
                   expires_at = ?, updated_at = NOW() WHERE id = ?'
        )->execute([
            password_hash($code, PASSWORD_BCRYPT),
            date('Y-m-d H:i:s', time() + $this->expiryMinutes() * 60),
            $codeId,
        ]);
        return ['code' => $code, 'expires_at' => date('Y-m-d H:i:s', time() + $this->expiryMinutes() * 60)];
    }

    /** Admin: pending email verification requests */
    public function getPendingCodes(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT id, email, name, purpose, status, delivery_mode, attempts, resends, expires_at, created_at
             FROM email_verification_codes
             WHERE status IN (\'pending\',\'sent\',\'manual\')
             ORDER BY id DESC LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $countStmt = $this->pdo->query('SELECT COUNT(*) c FROM email_verification_codes WHERE status IN (\'pending\',\'sent\',\'manual\')');
        $total = (int)($countStmt->fetch()['c']);
        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /** Admin: verify manually */
    public function adminVerify(int $codeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM email_verification_codes WHERE id = ? AND status IN (\'pending\',\'sent\',\'manual\') LIMIT 1'
        );
        $stmt->execute([$codeId]);
        $otp = $stmt->fetch();
        if (!$otp) return null;
        $this->pdo->prepare('UPDATE email_verification_codes SET status = \'verified\', updated_at = NOW() WHERE id = ?')->execute([$codeId]);
        return ['email' => $otp['email'], 'purpose' => $otp['purpose']];
    }

    public function cancel(int $codeId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE email_verification_codes SET status = \'cancelled\', updated_at = NOW()
             WHERE id = ? AND status IN (\'pending\',\'sent\',\'manual\')'
        );
        $stmt->execute([$codeId]);
        return (bool)$stmt->rowCount();
    }
}
