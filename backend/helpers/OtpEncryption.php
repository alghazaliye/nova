<?php
/**
 * NOVA Messenger — OTP Secret Encryption Helper
 *
 * Encrypts provider API keys/secrets so they are NEVER stored as plain text
 * in the database, Git, logs, or Audit Logs.
 *
 * Format stored: base64(iv)::base64(ciphertext)
 * Encryption key comes from `otp_encryption_key` app setting (auto-generated once).
 */

declare(strict_types=1);

class OtpEncryption
{
    private static ?string $key = null;

    private static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        // Prefer explicit env var (both $_ENV and getenv cover Apache/mod_php and CLI),
        // else use the setting from app_settings
        $key = $_ENV['OTP_ENCRYPTION_KEY'] ?? getenv('OTP_ENCRYPTION_KEY') ?: null;
        if ($key === null) {
            try {
                $pdo = Database::getInstance();
                $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
                $stmt->execute(['otp_encryption_key']);
                $row = $stmt->fetch();
                $key = $row ? trim((string)$row['setting_value']) : null;
            } catch (Throwable $e) {
                $key = null;
            }
        }

        if ($key === null || strlen($key) < 16) {
            $key = bin2hex(random_bytes(16));
            try {
                Database::getInstance()->prepare(
                    'INSERT INTO app_settings (setting_key, setting_value)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = \'\', ?, setting_value), updated_at = NOW()'
                )->execute(['otp_encryption_key', $key, $key]);
            } catch (Throwable $e) {
                // best-effort; fall back to the generated key for this request
            }
        }

        self::$key = substr(hash('sha256', $key), 0, 32);
        return self::$key;
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $key = self::getKey();
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Failed to encrypt OTP secret');
        }
        return base64_encode($iv) . '::' . base64_encode($cipher . $tag);
    }

    public static function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }
        $parts = explode('::', $encrypted, 2);
        if (count($parts) !== 2) {
            return $encrypted; // not encrypted (e.g. legacy plain value), return as-is
        }
        $iv = base64_decode($parts[0], true);
        $blob = base64_decode($parts[1], true);
        if ($iv === false || $blob === false || strlen($blob) < 16) {
            return $encrypted;
        }
        $cipher = substr($blob, 0, -16);
        $tag = substr($blob, -16);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::getKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    /** Mask a secret for display: show first 4 and last 4 chars only */
    public static function mask(string $secret, int $keep = 4): string
    {
        if ($secret === '') return '';
        if (strlen($secret) <= $keep * 2 + 3) {
            return str_repeat('•', strlen($secret));
        }
        return substr($secret, 0, $keep) . str_repeat('•', max(3, strlen($secret) - $keep * 2)) . substr($secret, -$keep);
    }
}
