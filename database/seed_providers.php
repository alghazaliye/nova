<?php
/**
 * NOVA Messenger — Production provider seed (Supports Turso & SQLite)
 *
 * Idempotent. Inserts/updates:
 *   - email_providers id=1 (Gmail SMTP) with ENV-crypted password
 *   - otp_providers id=1 placeholder row.
 */
declare(strict_types=1);

// Load database class and config
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/config/app.php';

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "[seed_providers] Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// ---------- 1) otp_providers placeholder ----------
$db->prepare(
    'INSERT OR IGNORE INTO otp_providers (id, name, type, status, priority, is_default)
     VALUES (1, :name, :type, :status, 0, 1)'
)->execute([':name' => 'Dev SMS (placeholder)', ':type' => 'sms', ':status' => 'enabled']);

// ---------- 2) email_providers (Gmail SMTP) ----------
$username = getenv('GMAIL_SMTP_USERNAME') ?: '';
$password = getenv('GMAIL_SMTP_PASSWORD') ?: '';

function encryptPassword($db, string $plain): string
{
    if ($plain === '') return '';
    
    $key = getenv('OTP_ENCRYPTION_KEY') ?: null;
    if ($key === null || strlen($key) < 16) {
        try {
            $tmpStmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'otp_encryption_key' LIMIT 1");
            $tmpStmt->execute();
            $row = $tmpStmt->fetch(PDO::FETCH_ASSOC);
            $key = $row !== false ? trim((string)$row['setting_value']) : null;
        } catch (Throwable $e) {
            $key = null;
        }
    }
    
    if ($key === null || strlen($key) < 16) {
        $key = bin2hex(random_bytes(16));
        try {
            $db->prepare("INSERT INTO app_settings (setting_key, setting_value)
                VALUES ('otp_encryption_key', ?)
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = CASE WHEN setting_value = '' THEN excluded.setting_value ELSE setting_value END")
                ->execute([$key]);
        } catch (Throwable $e) { }
    }
    
    $aesKey = substr(hash('sha256', (string)$key), 0, 32);
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('openssl_encrypt failed');
    return base64_encode($iv) . '::' . base64_encode($cipher . $tag);
}

$encPassword = encryptPassword($db, $password);

$stmt = $db->prepare(
    'INSERT INTO email_providers
       (id, name, type, status, priority, is_default, is_fallback, host, port, encryption,
        username, password, from_email, from_name, success_count, failure_count)
     VALUES (1, :name, :type, :status, 1, 1, 0, :host, :port, :encryption,
             :username, :password, :from_email, :from_name, 0, 0)
     ON CONFLICT(id) DO UPDATE SET
       status = excluded.status,
       host = excluded.host,
       port = excluded.port,
       encryption = excluded.encryption,
       username = excluded.username,
       password = excluded.password,
       from_email = excluded.from_email,
       from_name = excluded.from_name,
       updated_at = datetime("now")'
);
$stmt->execute([
    ':name'       => 'Gmail SMTP',
    ':type'       => 'smtp',
    ':status'     => ($username !== '' && $password !== '') ? 'enabled' : 'disabled',
    ':host'       => 'smtp.gmail.com',
    ':port'       => 465,
    ':encryption' => 'ssl',
    ':username'   => $username,
    ':password'   => $encPassword,
    ':from_email' => $username,
    ':from_name'  => 'NOVA Messenger',
]);

echo "[seed_providers] Done. Turso/SQLite compatible.\n";
