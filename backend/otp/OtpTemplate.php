<?php
/**
 * NOVA Messenger — OTP Message Template Renderer
 *
 * Placeholders: {OTP} {PHONE} {MINUTES} {APP_NAME}
 */

declare(strict_types=1);

class OtpTemplate
{
    private static function getSetting(string $key, string $default): string
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row !== false && $row['setting_value'] !== null ? (string)$row['setting_value'] : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    public static function render(string $template, string $phone, string $otp, array $config = []): string
    {
        $minutes = self::getSetting('otp_expiry_minutes', '5');
        $appName = self::getSetting('app_name', 'NOVA Messenger');

        $replacements = [
            '{OTP}'       => $otp,
            '{PHONE}'     => $phone,
            '{MINUTES}'   => $minutes,
            '{APP_NAME}'  => $appName,
        ];
        $result = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Legacy per-provider template field if it exists
        if (isset($config['message_template']) && trim((string)$config['message_template']) !== '') {
            $result = str_replace(array_keys($replacements), array_values($replacements), (string)$config['message_template']);
        }
        return $result;
    }
}
