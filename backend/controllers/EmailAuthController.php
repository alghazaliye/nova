<?php
/**
 * NOVA Messenger — Email / Username Authentication Controller
 *
 * Endpoints:
 *  - POST /auth/register-email   {email, name}            → email OTP (registration)
 *  - POST /auth/login-email      {email, password}        → session JWT (password login)
 *  - POST /auth/login-username   {username, password}     → session JWT (password login)
 *  - POST /auth/set-password     {new_password}  (auth)   → set password for email login
 *  - POST /auth/verify-email-otp {email, otp, name}       → verify email OTP → create/verify user
 *  - POST /auth/resend-email-otp {email, code_id}         → resend
 *  - GET  /auth/config           → public registration/login config (no secrets)
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/AuthConfigService.php';
require_once __DIR__ . '/../otp/EmailOtpService.php';

class EmailAuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ---------------------------------------------------------------
    // Public config (no auth required, no secrets)
    // GET /auth/config
    // ---------------------------------------------------------------

    public function config(): void
    {
        $cfg = new AuthConfigService();
        Response::success($cfg->getConfig());
    }

    // ---------------------------------------------------------------
    // Email registration (send OTP)
    // POST /auth/register-email {email, name?}
    // ---------------------------------------------------------------

    public function registerEmail(): void
    {
        RateLimitMiddleware::checkByIp();
        $cfg = new AuthConfigService();
        $cfg->assertRegistrationMethod('email');

        if (!$cfg->isEmailVerificationAvailable()) {
            Response::error('التحقق بالبريد غير مهيأ حاليًا. يرجى التسجيل بالهاتف', 'EMAIL_OTP_UNAVAILABLE', 503);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body)
            ->required('email', 'البريد الإلكتروني')
            ->email('email', 'البريد الإلكتروني');
        if (isset($body['name']) && trim((string)$body['name']) !== '') {
            $v->minLength('name', 2, 'الاسم')->maxLength('name', 150, 'الاسم');
        }
        if ($v->fails()) Response::validationError($v->errors());

        $email = strtolower(trim((string)$v->sanitizeString('email')));
        if (strlen($email) > 190) Response::validationError(['email' => 'البريد طويل جدًا']);

        // Check email already exists and is verified
        $stmt = $this->pdo->prepare('SELECT id, email_verified FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($row = $stmt->fetch()) {
            if ((int)$row['email_verified']) {
                Response::error('هذا البريد مسجل مسبقًا', 'EMAIL_EXISTS', 409);
            }
        }

        $service = new EmailOtpService();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $cooldown = $service->resendCooldown($email);
        if ($cooldown > 0) {
            Response::error("يمكنك إعادة الإرسال بعد {$cooldown} ثانية", 'OTP_COOLDOWN', 429);
        }

        $name = isset($body['name']) && trim((string)$body['name']) !== ''
            ? trim($v->sanitizeString('name')) : null;

        $res = $service->createAndSend($email, $name, 'registration', $ip, $ua);
        if (!$res['success']) {
            Response::error($res['message'] ?? 'فشل إرسال رمز التحقق', 'EMAIL_OTP_SEND_FAILED', 503);
        }

        $out = ['delivery_mode' => $res['delivery_mode'], 'cooldown' => $res['cooldown'] ?? 0];
        if ($this->isDevTest()) $out['otp_dev'] = ($_ENV['OTP_TEST_CODE'] ?? '123456');

        Response::success($out, 'تم إرسال رمز التحقق إلى بريدك الإلكتروني');
    }

    // ---------------------------------------------------------------
    // Verify email OTP → create user / mark email verified
    // POST /auth/verify-email-otp {email, otp, name?}
    // ---------------------------------------------------------------

    public function verifyEmailOtp(): void
    {
        RateLimitMiddleware::checkByIp();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body)
            ->required('email', 'البريد الإلكتروني')
            ->required('otp', 'رمز التحقق')
            ->email('email', 'البريد الإلكتروني');
        if ($v->fails()) Response::validationError($v->errors());

        $email = strtolower(trim((string)$body['email']));
        $otp = trim((string)$body['otp']);
        $name = isset($body['name']) && trim((string)$body['name']) !== ''
            ? trim($v->sanitizeString('name')) : null;

        $service = new EmailOtpService();
        $result = $service->verifyCode($email, $otp);
        if (!$result['verified']) {
            Response::error(
                $result['message'] ?? 'رمز التحقق غير صحيح',
                $result['error_code'] ?? 'OTP_INVALID',
                400,
                array_filter(['attempts_left' => $result['attempts_left'] ?? null], static fn ($x) => $x !== null)
            );
        }

        // Create user (email-only) or link email to existing user
        $stmt = $this->pdo->prepare('SELECT id, uuid, name, is_blocked FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $uuid = UuidHelper::generate();
            $displayName = $name ?? 'مستخدم NOVA';
            // phone NOT NULL + UNIQUE (uq_users_phone): استخدام بصمة بريد فريدة
            // لتجنب تكرار '' بين الحسابات التي لا تملك هاتفًا
            $emailPhone = 'e_' . substr(hash('sha256', $email), 0, 26);
            $this->pdo->prepare(
                'INSERT INTO users (uuid, email, name, phone, username, email_verified, is_verified, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 1, 1, NOW(), NOW())'
            )->execute([$uuid, $email, $displayName, $emailPhone, $emailPhone]);
            $userId = (int)$this->pdo->lastInsertId();
        } else {
            $userId = (int)$user['id'];
            if ((int)$user['is_blocked']) {
                Response::forbidden('تم حظر هذا الحساب — يرجى التواصل مع إدارة التطبيق');
            }
            $this->pdo->prepare(
                'UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = ?'
            )->execute([$userId]);
            if ($name !== null && ($user['name'] ?? '') === 'مستخدم NOVA') {
                $this->pdo->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $userId]);
            }
        }

        $token = $this->createSession($userId, $body['device_uuid'] ?? null, $body['fcm_token'] ?? null);
        $uc = new UserController();
        $ref = new \ReflectionMethod($uc, 'getUserById');
        $ref->setAccessible(true);
        $userData = $ref->invoke($uc, $userId);

        Response::success(['token' => $token, 'user' => $userData], 'تم تسجيل الدخول بنجاح');
    }

    // ---------------------------------------------------------------
    // Resend email OTP
    // POST /auth/resend-email-otp {email, code_id?}
    // ---------------------------------------------------------------

    public function resendEmailOtp(): void
    {
        RateLimitMiddleware::checkByIp();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = strtolower(trim((string)($body['email'] ?? '')));
        if ($email === '') Response::validationError(['email' => 'البريد الإلكتروني مطلوب']);

        $service = new EmailOtpService();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $codeId = (int)($body['code_id'] ?? 0);
        $res = $service->resend($codeId > 0 ? $codeId : $this->findActiveCodeId($email), $ip, $ua);
        if (!$res['success']) {
            $code = $res['error_code'] ?? 'ERROR';
            $out = ['success' => false, 'message' => $res['message'] ?? 'فشل إعادة الإرسال', 'error_code' => $code];
            if (isset($res['cooldown'])) $out['cooldown'] = (int)$res['cooldown'];
            http_response_code(in_array($code, ['OTP_COOLDOWN', 'OTP_MAX_RESENDS'], true) ? 429 : 400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $out = ['delivery_mode' => $res['delivery_mode'], 'cooldown' => $res['cooldown'] ?? 0];
        if ($this->isDevTest()) $out['otp_dev'] = ($_ENV['OTP_TEST_CODE'] ?? '123456');
        Response::success($out, 'تم إعادة إرسال رمز التحقق إلى بريدك');
    }

    private function findActiveCodeId(string $email): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM email_verification_codes
                 WHERE email = ? AND status IN (\'pending\',\'sent\',\'manual\')
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            return $row ? (int)$row['id'] : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    // ---------------------------------------------------------------
    // Password-based login (email / username)
    // POST /auth/login-email {email, password}
    // POST /auth/login-username {username, password}
    // ---------------------------------------------------------------

    public function loginEmail(): void
    {
        RateLimitMiddleware::checkByIp();
        $cfg = new AuthConfigService();
        $cfg->assertLoginMethod('email');
        $this->loginByField('email', $cfg);
    }

    public function loginUsername(): void
    {
        RateLimitMiddleware::checkByIp();
        $cfg = new AuthConfigService();
        $cfg->assertLoginMethod('username');
        $this->loginByField('username', $cfg);
    }

    private function loginByField(string $field, AuthConfigService $cfg): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $value = trim((string)($body[$field] ?? ''));
        $password = trim((string)($body['password'] ?? ''));
        if ($value === '' || $password === '') {
            Response::validationError([$field === 'email' ? 'email' : 'username' => 'مطلوب', 'password' => 'مطلوب']);
        }
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => 'صيغة البريد غير صحيحة']);
        }
        if (strlen($password) < 6 || strlen($password) > 200) {
            Response::validationError(['password' => 'كلمة المرور يجب أن تكون بين 6 و200 حرف']);
        }

        $sql = match ($field) {
            'email' => 'SELECT id, uuid, name, password_hash, is_blocked FROM users WHERE email = ? LIMIT 1',
            default => 'SELECT id, uuid, name, password_hash, is_blocked FROM users WHERE username = ? LIMIT 1',
        };
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([strtolower($value)]);
        $user = $stmt->fetch();

        if (!$user || $user['password_hash'] === null) {
            // Don't reveal account existence
            sleep(1);
            Response::error('بيانات الدخول غير صحيحة', 'AUTH_FAILED', 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            sleep(1);
            Response::error('بيانات الدخول غير صحيحة', 'AUTH_FAILED', 401);
        }

        if ((int)$user['is_blocked']) {
            Response::forbidden('تم حظر هذا الحساب — يرجى التواصل مع إدارة التطبيق');
        }

        $token = $this->createSession((int)$user['id'], $body['device_uuid'] ?? null, $body['fcm_token'] ?? null);
        $uc = new UserController();
        $ref = new \ReflectionMethod($uc, 'getUserById');
        $ref->setAccessible(true);
        $userData = $ref->invoke($uc, (int)$user['id']);

        Response::success(['token' => $token, 'user' => $userData], 'تم تسجيل الدخول بنجاح');
    }

    // ---------------------------------------------------------------
    // Set / change password (authenticated)
    // POST /auth/set-password {new_password, current_password?}
    // ---------------------------------------------------------------

    public function setPassword(): void
    {
        $user = AuthMiddleware::authenticate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $newPassword = trim((string)($body['new_password'] ?? ''));
        if (strlen($newPassword) < 6 || strlen($newPassword) > 200) {
            Response::validationError(['new_password' => 'كلمة المرور يجب أن تكون بين 6 و200 حرف']);
        }

        // Require current password if the account already has one
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$user['user_id']]);
        $row = $stmt->fetch();
        if ($row && $row['password_hash'] !== null) {
            $current = trim((string)($body['current_password'] ?? ''));
            if ($current === '' || !password_verify($current, $row['password_hash'])) {
                Response::error('كلمة المرور الحالية غير صحيحة', 'AUTH_FAILED', 401);
            }
        }

        $this->pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
                  ->execute([password_hash($newPassword, PASSWORD_BCRYPT), (int)$user['user_id']]);

        Response::success(null, 'تم حفظ كلمة المرور بنجاح');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createSession(int $userId, ?string $deviceUuid = null, ?string $fcmToken = null): string
    {
        $expiryHours = (int)($_ENV['JWT_EXPIRY_HOURS'] ?? 720);
        $expiresAt = time() + ($expiryHours * 3600);
        $makeJti = static fn (): string =>
            substr(md5((string)microtime(true) . random_int(0, PHP_INT_MAX)), 0, 16);

        $payload = [
            'sub' => $userId,
            'iat' => time(),
            'exp' => $expiresAt,
            'jti' => $makeJti(),
        ];
        $token = JwtHelper::generate($payload);
        $tokenHash = hash('sha256', $token);

        $deviceId = null;
        if ($deviceUuid) {
            try {
                $this->pdo->prepare(
                    'INSERT INTO device_registrations (user_id, device_uuid, is_active, created_at)
                     VALUES (?, ?, 1, NOW())
                     ON DUPLICATE KEY UPDATE is_active = 1, last_seen = NOW()'
                )->execute([$userId, $deviceUuid]);
                $deviceId = (int)($this->pdo->lastInsertId() ?: 0) ?: null;
            } catch (Throwable $e) {
                error_log('Device registration error (non-fatal): ' . $e->getMessage());
            }
            // FCM token update on user_devices if the column exists
            if ($fcmToken) {
                try {
                    $this->pdo->prepare(
                        'UPDATE device_registrations SET device_name = ? WHERE user_id = ? AND device_uuid = ?'
                    )->execute([substr($fcmToken, 0, 128), $userId, $deviceUuid]);
                } catch (Throwable $e) {}
            }
        }

        $insert = function () use ($userId, $tokenHash, $deviceId, $expiresAt): bool {
            try {
                $this->pdo->prepare(
                    'INSERT INTO sessions (user_id, token_hash, device_id, ip_address, user_agent, expires_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $userId, $tokenHash, $deviceId,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    date('Y-m-d H:i:s', $expiresAt),
                ]);
                return true;
            } catch (Throwable $e) {
                return false;
            }
        };
        if (!$insert()) {
            $payload['jti'] = $makeJti();
            $token = JwtHelper::generate($payload);
            $tokenHash = hash('sha256', $token);
            $insert();
        }
        return $token;
    }

    private function isDevTest(): bool
    {
        return ($_ENV['OTP_PROVIDER'] ?? 'sms') === 'test'
            && ($_ENV['APP_ENV'] ?? 'production') !== 'production';
    }
}
