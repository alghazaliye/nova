<?php
/**
 * NOVA Messenger — OTP Provider Manager (Admin CRUD + test)
 */

declare(strict_types=1);

class ProviderManager
{
    private PDO|TursoPdo $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, type, status, priority, is_default, is_fallback,
                    sender_id, success_count, failure_count, last_used_at,
                    CASE WHEN api_key IS NOT NULL AND api_key <> \'\' THEN 1 ELSE 0 END AS has_key,
                    CASE WHEN api_secret IS NOT NULL AND api_secret <> \'\' THEN 1 ELSE 0 END AS has_secret,
                    created_at, updated_at
             FROM otp_providers ORDER BY priority ASC, id ASC'
        );
        return $stmt->fetchAll();
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM otp_providers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a provider. Secrets are encrypted before storage.
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO otp_providers
                (name, type, status, priority, is_default, is_fallback, api_base_url, api_key, api_secret,
                 account_sid, message_template, sender_id, extra_config, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))'
        );
        $stmt->execute([
            trim($data['name']),
            $data['type'],
            $data['status'] ?? 'disabled',
            (int)($data['priority'] ?? 0),
            (int)($data['is_default'] ?? 0),
            (int)($data['is_fallback'] ?? 0),
            trim((string)($data['api_base_url'] ?? '')) ?: null,
            $this->encryptField($data['api_key'] ?? ''),
            $this->encryptField($data['api_secret'] ?? ''),
            trim((string)($data['account_sid'] ?? '')) ?: null,
            trim((string)($data['message_template'] ?? '')) ?: null,
            trim((string)($data['sender_id'] ?? '')) ?: null,
            is_array($data['extra_config'] ?? null) ? json_encode($data['extra_config'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update a provider. Only non-empty secrets replace the old ones (never blank-out accidentally).
     */
    public function update(int $id, array $data): bool
    {
        $current = $this->get($id);
        if (!$current) return false;

        $apiKey = isset($data['api_key']) && trim($data['api_key']) !== ''
            ? $this->encryptField($data['api_key'])
            : $current['api_key'];
        $apiSecret = isset($data['api_secret']) && trim($data['api_secret']) !== ''
            ? $this->encryptField($data['api_secret'])
            : $current['api_secret'];

        $stmt = $this->pdo->prepare(
            'UPDATE otp_providers SET name = ?, type = ?, status = ?, priority = ?,
                    is_default = ?, is_fallback = ?, api_base_url = ?, api_key = ?, api_secret = ?,
                    account_sid = ?, message_template = ?, sender_id = ?,
                    extra_config = ?, updated_at = datetime("now")
             WHERE id = ?'
        );
        return (bool)$stmt->execute([
            trim($data['name']),
            $data['type'],
            $data['status'] ?? 'disabled',
            (int)($data['priority'] ?? 0),
            (int)($data['is_default'] ?? 0),
            (int)($data['is_fallback'] ?? 0),
            trim((string)($data['api_base_url'] ?? '')) ?: null,
            $apiKey,
            $apiSecret,
            trim((string)($data['account_sid'] ?? '')) ?: null,
            trim((string)($data['message_template'] ?? '')) ?: null,
            trim((string)($data['sender_id'] ?? '')) ?: null,
            is_array($data['extra_config'] ?? null) ? json_encode($data['extra_config'], JSON_UNESCAPED_UNICODE) : null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM otp_providers WHERE id = ?');
        $stmt->execute([$id]);
        return (bool)$stmt->rowCount();
    }

    /** Toggle enabled/disabled */
    public function toggle(int $id, string $status): bool
    {
        if (!in_array($status, ['enabled', 'disabled'], true)) return false;
        $stmt = $this->pdo->prepare('UPDATE otp_providers SET status = ?, updated_at = datetime("now") WHERE id = ?');
        return (bool)$stmt->execute([$status, $id]);
    }

    /**
     * Test a provider by sending a real SMS with code 000000 (test code).
     * Result never contains decrypted secrets.
     */
    public function test(int $id, string $phone): array
    {
        $row = $this->get($id);
        if (!$row) {
            return ['success' => false, 'message' => 'المزود غير موجود'];
        }
        if (!preg_match('/^\+\d{7,15}$/', $phone)) {
            return ['success' => false, 'message' => 'صيغة الرقم غير صحيحة (مثال: +966501234567)'];
        }

        $config = [
            'type'             => $row['type'],
            'api_key'          => OtpEncryption::decrypt((string)($row['api_key'] ?? '')),
            'api_secret'       => OtpEncryption::decrypt((string)($row['api_secret'] ?? '')),
            'api_base_url'     => $row['api_base_url'],
            'account_sid'      => $row['account_sid'],
            'sender_id'        => $row['sender_id'],
            'message_template' => $row['message_template'],
        ];
        $extra = is_string($row['extra_config'] ?? null) ? json_decode($row['extra_config'], true) : ($row['extra_config'] ?? []);
        if (is_array($extra)) {
            $config = array_merge($config, $extra);
        }

        $template = trim((string)$config['message_template']);
        if ($template === '') {
            $template = 'رمز التحقق الخاص بك هو: {OTP}. صالح لمدة {MINUTES} دقيقة. لا تشاركه مع أي شخص. — {APP_NAME}';
        }

        if ($row['type'] === 'sms_mock' && !class_exists(SmsMockProvider::class, false)) {
            require_once __DIR__ . '/SmsMockProvider.php';
        }
        if ($row['type'] === 'whatsapp_mock' && !class_exists(WhatsappMockProvider::class, false)) {
            require_once __DIR__ . '/WhatsappMockProvider.php';
        }
        $instance = match ($row['type']) {
            'twilio'          => new TwilioProvider(),
            'vonage'          => new VonageProvider(),
            'http_rest'       => new HttpSmsProvider(),
            'test'            => new TestProvider(),
            'sms_mock'        => new SmsMockProvider(),
            'whatsapp_mock'   => new WhatsappMockProvider(),
            'whatsapp'        => new WhatsappMockProvider(),
            default     => throw new InvalidArgumentException("مزود غير معروف: {$row['type']}"),
        };

        // The test code: real code but documented as test (users know it's a test)
        $testCode = '000000';

        $result = null;
        try {
            $result = $instance->send($phone, $testCode, $config, $template);
        } catch (Throwable $e) {
            $result = OtpSendResult::failure('timeout', 0, 'خطأ غير متوقع: ' . substr($e->getMessage(), 0, 100));
        }

        if ($result->success) {
            $this->pdo->prepare(
                'UPDATE otp_providers SET success_count = success_count + 1, last_used_at = datetime("now"), updated_at = datetime("now")
                 WHERE id = ?'
            )->execute([$id]);
            return [
                'success' => true,
                'message' => 'تم إرسال رسالة الاختبار بنجاح',
                'http_code' => $result->httpCode,
                'response_time_ms' => $result->responseTimeMs,
                'summary' => $result->responseSummary,
            ];
        }

        $this->pdo->prepare(
            'UPDATE otp_providers SET failure_count = failure_count + 1, updated_at = datetime("now") WHERE id = ?'
        )->execute([$id]);
        return [
            'success' => false,
            'message' => $result->errorMessage ?: 'فشل إرسال رسالة الاختبار',
            'http_code' => $result->httpCode,
            'error_class' => $result->errorClass,
            'response_time_ms' => $result->responseTimeMs,
        ];
    }

    private function encryptField(string $value): string
    {
        return $value !== '' ? OtpEncryption::encrypt($value) : '';
    }

    /** Delivery logs for a given otp or provider */
    public function deliveryLogs(?int $otpId = null, ?int $providerId = null, int $limit = 50): array
    {
        $where = [];
        $params = [];
        if ($otpId !== null) { $where[] = 'l.otp_id = ?'; $params[] = $otpId; }
        if ($providerId !== null) { $where[] = 'l.provider_id = ?'; $params[] = $providerId; }
        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $params[] = $limit;
        $stmt = $this->pdo->prepare(
            "SELECT l.*, p.name AS provider_name, p.type AS provider_type
             FROM otp_delivery_logs l
             LEFT JOIN otp_providers p ON p.id = l.provider_id
             {$whereSql}
             ORDER BY l.id DESC LIMIT ?"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Get the full provider row (encrypted secrets) for test() path */
    public function getFull(int $id): ?array
    {
        return $this->get($id);
    }
}
