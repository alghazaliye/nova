<?php
/**
 * NOVA Messenger — Auth Configuration Service
 *
 * Reads the registration/login toggle settings and enforces them server-side.
 * The public config (no secrets) is served via GET /auth/config for Flutter.
 */

declare(strict_types=1);

class AuthConfigService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    private function getSetting(string $key, string $default = '0'): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? (string)$row['setting_value'] : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    /** Public config for the client app (no secrets) */
    public function getConfig(): array
    {
        return [
            'registration' => [
                'phone' => $this->getSetting('auth_phone_registration', '1') === '1',
                'email' => $this->getSetting('auth_email_registration', '0') === '1',
            ],
            'login' => [
                'phone'     => $this->getSetting('auth_phone_login', '1') === '1',
                'email'     => $this->getSetting('auth_email_login', '0') === '1',
                'username'  => $this->getSetting('auth_username_login', '0') === '1',
            ],
            'otp' => [
                'phone' => [
                    'enabled' => $this->getSetting('otp_phone_enabled', '1') === '1',
                    'expiry_minutes' => (int)$this->getSetting('otp_phone_expiry_minutes', '5'),
                    'max_attempts' => (int)$this->getSetting('otp_phone_max_attempts', '5'),
                    'resend_cooldown_seconds' => (int)$this->getSetting('otp_phone_resend_cooldown_seconds', '30'),
                    'max_resends' => (int)$this->getSetting('otp_phone_max_resends', '10'),
                    'delivery_mode' => $this->getSetting('otp_phone_delivery_mode', 'sms'),
                ],
                'email' => [
                    'enabled' => $this->getSetting('otp_email_enabled', '0') === '1',
                    'expiry_minutes' => (int)$this->getSetting('otp_email_expiry_minutes', '5'),
                    'max_attempts' => (int)$this->getSetting('otp_email_max_attempts', '5'),
                    'resend_cooldown_seconds' => (int)$this->getSetting('otp_email_resend_cooldown_seconds', '30'),
                    'max_resends' => (int)$this->getSetting('otp_email_max_resends', '10'),
                    'delivery_mode' => $this->getSetting('otp_email_delivery_mode', 'email'),
                ],
            ],
            'registration_disabled' => $this->isRegistrationFullyDisabled(),
            'app_name' => $this->getSetting('app_name', 'NOVA Messenger'),
        ];
    }

    public function isRegistrationFullyDisabled(): bool
    {
        $phoneOn = $this->getSetting('auth_phone_registration', '1') === '1';
        $emailOn = $this->getSetting('auth_email_registration', '0') === '1';
        return !$phoneOn && !$emailOn;
    }

    /** Assert a requested registration method is allowed, throws Response error if not */
    public function assertRegistrationMethod(string $method): void
    {
        if ($this->isRegistrationFullyDisabled()) {
            Response::error('التسجيل متوقف حاليًا. يرجى التواصل مع إدارة التطبيق', 'REGISTRATION_DISABLED', 503);
        }
        if ($method === 'phone' && $this->getSetting('auth_phone_registration', '1') !== '1') {
            Response::error('التسجيل بالهاتف غير مفعّل حاليًا', 'PHONE_REGISTRATION_DISABLED', 403);
        }
        if ($method === 'email' && $this->getSetting('auth_email_registration', '0') !== '1') {
            Response::error('التسجيل بالبريد الإلكتروني غير مفعّل حاليًا', 'EMAIL_REGISTRATION_DISABLED', 403);
        }
    }

    /** Assert a requested login method is allowed, throws Response error if not */
    public function assertLoginMethod(string $method): void
    {
        if ($method === 'phone' && $this->getSetting('auth_phone_login', '1') !== '1') {
            Response::error('تسجيل الدخول بالهاتف غير مفعّل حاليًا', 'PHONE_LOGIN_DISABLED', 403);
        }
        if ($method === 'email' && $this->getSetting('auth_email_login', '0') !== '1') {
            Response::error('تسجيل الدخول بالبريد الإلكتروني غير مفعّل حاليًا', 'EMAIL_LOGIN_DISABLED', 403);
        }
        if ($method === 'username' && $this->getSetting('auth_username_login', '0') !== '1') {
            Response::error('تسجيل الدخول باسم المستخدم غير مفعّل حاليًا', 'USERNAME_LOGIN_DISABLED', 403);
        }
    }

    public function isOtpEnabled(string $channel): bool
    {
        $key = $channel === 'email' ? 'otp_email_enabled' : 'otp_phone_enabled';
        return $this->getSetting($key, $channel === 'email' ? '0' : '1') === '1';
    }

    public function isEmailVerificationAvailable(): bool
    {
        // Email OTP only works when email registration OR email login is enabled
        // AND otp_email_enabled is on.
        $regOn = $this->getSetting('auth_email_registration', '0') === '1';
        $logOn = $this->getSetting('auth_email_login', '0') === '1';
        $otpOn = $this->isOtpEnabled('email');
        return ($regOn || $logOn) && $otpOn;
    }
}
