<?php
/**
 * NOVA Messenger — Email Provider Manager (Admin CRUD + test)
 *
 * Secrets (SMTP password / REST api_key) are AES-256-GCM encrypted via
 * OtpEncryption and never returned in plain text.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/OtpEncryption.php';

class EmailProviderManager
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        self::ensureSchema($this->pdo);
    }

    /**
     * Auto-migrate: ensure email_providers has the columns required by this manager
     * (needed when the live SQLite DB was created from an older schema).
     */
    private static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $cols = [];
            foreach ($pdo->query('PRAGMA table_info(email_providers)')->fetchAll() as $r) {
                $cols[$r['name']] = true;
            }
            $missing = [
                'encryption' => "ALTER TABLE email_providers ADD COLUMN encryption text NOT NULL DEFAULT 'tls'",
                'api_base_url' => 'ALTER TABLE email_providers ADD COLUMN api_base_url varchar(300) DEFAULT NULL',
                'api_key' => 'ALTER TABLE email_providers ADD COLUMN api_key text DEFAULT NULL',
                'extra_config' => 'ALTER TABLE email_providers ADD COLUMN extra_config text DEFAULT NULL',
            ];
            foreach ($missing as $col => $sql) {
                if (!isset($cols[$col])) {
                    try {
                        $pdo->exec($sql);
                    } catch (Throwable $e) {
                        // ignore if column now exists
                    }
                }
            }
        } catch (Throwable $e) {
            // non-fatal: schema check best-effort
        }
    }

    private function encryptField(string $value): string
    {
        return $value !== '' ? OtpEncryption::encrypt($value) : '';
    }

    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, type, status, priority, is_default, is_fallback, host, port, encryption,
                    username, from_email, from_name, api_base_url,
                    CASE WHEN password IS NOT NULL AND password <> \'\' THEN 1 ELSE 0 END AS has_password,
                    CASE WHEN api_key IS NOT NULL AND api_key <> \'\' THEN 1 ELSE 0 END AS has_key,
                    success_count, failure_count, last_used_at, created_at, updated_at
             FROM email_providers ORDER BY priority ASC, id ASC'
        );
        return $stmt->fetchAll();
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_providers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a provider. Ensures only one default per type.
     */
    public function create(array $data): array
    {
        $this->clearDefaultIfNeeded($data['type'] ?? 'smtp', (int)($data['is_default'] ?? 0));

        $stmt = $this->pdo->prepare(
            'INSERT INTO email_providers
                (name, type, status, priority, is_default, is_fallback, host, port, encryption,
                 username, password, from_email, from_name, api_base_url, api_key, extra_config, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))'
        );
        $stmt->execute([
            trim((string)$data['name']),
            $data['type'],
            $data['status'] ?? 'disabled',
            (int)($data['priority'] ?? 0),
            (int)($data['is_default'] ?? 0),
            (int)($data['is_fallback'] ?? 0),
            trim((string)($data['host'] ?? '')) ?: null,
            isset($data['port']) && $data['port'] !== '' ? (int)$data['port'] : null,
            (($e = $data['encryption'] ?? null) !== null && in_array((string)$e, ['none', 'ssl', 'tls'], true)) ? $e : (($data['type'] ?? 'smtp') === 'http_rest' ? 'none' : 'tls'),
            trim((string)($data['username'] ?? '')) ?: null,
            $this->encryptField((string)($data['password'] ?? '')),
            trim((string)($data['from_email'] ?? '')) ?: null,
            trim((string)($data['from_name'] ?? '')) ?: null,
            trim((string)($data['api_base_url'] ?? '')) ?: null,
            $this->encryptField((string)($data['api_key'] ?? '')),
            is_array($data['extra_config'] ?? null) ? json_encode($data['extra_config'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return ['id' => (int)$this->pdo->lastInsertId()];
    }

    /**
     * Update a provider. Empty secret fields keep the existing encrypted value.
     */
    public function update(int $id, array $data): bool
    {
        $current = $this->get($id);
        if (!$current) return false;

        $this->clearDefaultIfNeeded($data['type'] ?? (string)$current['type'], (int)($data['is_default'] ?? 0), $id);

        $password = isset($data['password']) && trim((string)$data['password']) !== ''
            ? $this->encryptField(trim((string)$data['password']))
            : $current['password'];
        $apiKey = isset($data['api_key']) && trim((string)$data['api_key']) !== ''
            ? $this->encryptField(trim((string)$data['api_key']))
            : $current['api_key'];

        $stmt = $this->pdo->prepare(
            'UPDATE email_providers SET name = ?, type = ?, status = ?, priority = ?,
                    is_default = ?, is_fallback = ?, host = ?, port = ?, encryption = ?,
                    username = ?, password = ?, from_email = ?, from_name = ?,
                    api_base_url = ?, api_key = ?, extra_config = ?, updated_at = datetime("now")
             WHERE id = ?'
        );
        return (bool)$stmt->execute([
            trim((string)$data['name']),
            $data['type'] ?? $current['type'],
            $data['status'] ?? 'disabled',
            (int)($data['priority'] ?? 0),
            (int)($data['is_default'] ?? 0),
            (int)($data['is_fallback'] ?? 0),
            isset($data['host']) ? (trim((string)$data['host']) ?: null) : $current['host'],
            isset($data['port']) && $data['port'] !== '' ? (int)$data['port'] : ($current['port'] ?? null),
            in_array((string)($data['encryption'] ?? 'tls'), ['none', 'ssl', 'tls'], true) ? $data['encryption'] : ($current['encryption'] ?? 'tls'),
            isset($data['username']) ? (trim((string)$data['username']) ?: null) : $current['username'],
            $password,
            isset($data['from_email']) ? (trim((string)$data['from_email']) ?: null) : $current['from_email'],
            isset($data['from_name']) ? (trim((string)$data['from_name']) ?: null) : $current['from_name'],
            isset($data['api_base_url']) ? (trim((string)$data['api_base_url']) ?: null) : $current['api_base_url'],
            $apiKey,
            is_array($data['extra_config'] ?? null) ? json_encode($data['extra_config'], JSON_UNESCAPED_UNICODE) : $current['extra_config'],
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM email_providers WHERE id = ? AND `name` != \'اختبار (manual)\'');
        $stmt->execute([$id]);
        return (bool)$stmt->rowCount();
    }

    public function toggle(int $id, string $status): bool
    {
        if (!in_array($status, ['enabled', 'disabled'], true)) return false;
        $stmt = $this->pdo->prepare('UPDATE email_providers SET status = ?, updated_at = datetime("now") WHERE id = ?');
        return (bool)$stmt->execute([$status, $id]);
    }

    /**
     * Test a provider by sending a real email to the given address with code 000000.
     */
    public function test(int $id, string $email): array
    {
        $row = $this->get($id);
        if (!$row) return ['success' => false, 'message' => 'المزود غير موجود'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'البريد غير صالح'];
        }

        if ($row['type'] === 'smtp') {
            $svc = new EmailOtpService();
            $result = $svc->sendSmtp($this->decryptSmtpRow($row), $email, 'رسالة اختبار — NOVA Messenger',
                'هذه رسالة اختبار من مزود البريد "' . $row['name'] . '" ضمن تطبيق NOVA Messenger. الرمز التجريبي: 000000');
            if ($result['success']) {
                $this->pdo->prepare('UPDATE email_providers SET success_count = success_count + 1, last_used_at = datetime("now"), updated_at = datetime("now") WHERE id = ?')->execute([$id]);
            } else {
                $this->pdo->prepare('UPDATE email_providers SET failure_count = failure_count + 1, updated_at = datetime("now") WHERE id = ?')->execute([$id]);
            }
            $this->logDelivery($email, $id, $result);
            return $result;
        }

        $svc = new EmailOtpService();
        $restConfig = [
            'api_key' => $row['api_key'] !== null ? OtpEncryption::decrypt((string)$row['api_key']) : '',
            'api_base_url' => $row['api_base_url'],
        ];
        $extra = is_string($row['extra_config'] ?? null) ? json_decode($row['extra_config'], true) : null;
        if (is_array($extra)) $restConfig = array_merge($restConfig, $extra);

        $result = $svc->sendRest($restConfig, $email, '000000', 'رسالة اختبار من مزود البريد "' . $row['name'] . '" — الرمز التجريبي: 000000');
        if ($result['success']) {
            $this->pdo->prepare('UPDATE email_providers SET success_count = success_count + 1, last_used_at = datetime("now"), updated_at = datetime("now") WHERE id = ?')->execute([$id]);
        } else {
            $this->pdo->prepare('UPDATE email_providers SET failure_count = failure_count + 1, updated_at = datetime("now") WHERE id = ?')->execute([$id]);
        }
        $this->logDelivery($email, $id, $result);
        return $result;
    }

    private function decryptSmtpRow(array $row): array
    {
        return [
            'host' => $row['host'] ?? '',
            'port' => (int)($row['port'] ?? 465),
            'encryption' => $row['encryption'] ?? 'tls',
            'username' => $row['username'] ?? '',
            'password' => $row['password'] !== null ? OtpEncryption::decrypt((string)$row['password']) : '',
            'from_email' => $row['from_email'] ?? '',
            'from_name' => $row['from_name'] ?? 'NOVA Messenger',
        ];
    }

    private function logDelivery(string $email, int $providerId, array $result): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO email_delivery_logs (email_type, to_email, provider_id, subject, status, http_code, response_time_ms, error_message, created_at)
                 VALUES (\'registration\', ?, ?, \'رسالة اختبار\', ?, ?, ?, ?, datetime("now"))'
            )->execute([
                $email, $providerId,
                $result['success'] ? 'sent' : 'failed',
                ($result['http_code'] ?? 0) ?: null,
                ($result['response_time_ms'] ?? 0) ?: null,
                ($result['success'] ? null : ($result['message'] ?? null)),
            ]);
        } catch (Throwable $e) {}
    }

    /**
     * When a new default is created, unset the previous default of the same type
     * (except when editing the same row).
     */
    private function clearDefaultIfNeeded(string $type, int $isDefault, ?int $exceptId = null): void
    {
        if ($isDefault !== 1) return;
        $sql = 'UPDATE email_providers SET is_default = 0 WHERE type = ? AND is_default = 1';
        $params = [$type];
        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }
        $this->pdo->prepare($sql)->execute($params);
    }

    public function deliveryLogs(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.*, p.name AS provider_name, p.type AS provider_type
             FROM email_delivery_logs l
             LEFT JOIN email_providers p ON p.id = l.provider_id
             ORDER BY l.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
