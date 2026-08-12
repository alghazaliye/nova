<?php
/**
 * NOVA Messenger - Authentication Controller
 * Handles: register, login (OTP), verify OTP, logout, refresh, me
 */

declare(strict_types=1);

class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // POST /api/v1/auth/register
    public function register(): void
    {
        RateLimitMiddleware::checkByIp();
        $this->assertOtpProviderAvailable();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($body)
            ->required('phone', 'رقم الهاتف')
            ->phone('phone', 'رقم الهاتف');

        $countryCode = ($body['country_code'] ?? '') !== '' ? trim((string)$body['country_code'], '+') : null;
        if (isset($body['name']) && trim((string)$body['name']) !== '') {
            $v->minLength('name', 2, 'الاسم')->maxLength('name', 150, 'الاسم');
        }

        if ($v->fails()) {
            Response::validationError($v->errors());
        }

        // WhatsApp-style flow: phone + optional country_code; name/email optional
        $countryCode = $v->sanitizeString('country_code');
        $countryCode = ($countryCode !== '') ? trim($countryCode, '+') : null;
        $phone = $v->sanitizeString('phone');
        if ($countryCode !== null && $phone !== '') {
            $phone = ltrim($phone, '+0');
            // Avoid duplicating the country code if the phone already starts with it
            if (str_starts_with($phone, $countryCode)) {
                $phone = '+' . $phone;
            } else {
                $phone = '+' . $countryCode . $phone;
            }
        }
        $name = isset($body['name']) && trim((string)$body['name']) !== ''
            ? $v->sanitizeString('name')
            : 'مستخدم NOVA';

        // Check if phone already exists
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            Response::error('رقم الهاتف مسجل مسبقاً', 'PHONE_EXISTS', 409);
        }

                // Generate and store OTP (no expiry: remains valid until used/replaced)
        $otp       = $this->generateOtp();
        $otpHash   = password_hash($otp, PASSWORD_BCRYPT);
        $neverExpires = '2099-12-31 23:59:59';
        $this->storeOtp($phone, $otpHash, $neverExpires, $name);

        // Send OTP (in dev mode, return it directly)
        $responseData = ['message' => 'تم إرسال رمز التحقق'];
        if ($this->isDevelopmentOtp()) {
            $responseData['otp_dev'] = $otp; // Remove in production
        }

        Response::success($responseData, 'تم إرسال رمز التحقق إلى رقم هاتفك');
    }

    // POST /api/v1/auth/verify-otp
    public function verifyOtp(): void
    {
        RateLimitMiddleware::checkByIp();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body)
            ->required('phone', 'رقم الهاتف')
            ->required('otp', 'رمز التحقق');

        if ($v->fails()) {
            Response::validationError($v->errors());
        }

        $phone = trim($body['phone']);
        $otp   = trim($body['otp']);
        // WhatsApp-style: name is supplied later via profile setup (optional here)
        $name  = isset($body['name']) && trim((string)$body['name']) !== ''
            ? $v->sanitizeString('name')
            : null;

        // Verify OTP
        $otpData = $this->getStoredOtp($phone);
        if (!$otpData) {
            Response::error('رمز التحقق غير موجود. اطلب رمزًا جديدًا', 'OTP_EXPIRED', 400);
        }


        if (!password_verify($otp, $otpData['otp_hash'])) {
            Response::error('رمز التحقق غير صحيح', 'OTP_INVALID', 400);
        }

        // Create or update user
        $stmt = $this->pdo->prepare('SELECT id, uuid, is_blocked FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user) {
            $uuid = UuidHelper::generate();
            $displayName = $name ?? $otpData['name'];
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (uuid, phone, name, is_verified, created_at, updated_at)
                 VALUES (?, ?, ?, 0, NOW(), NOW())'
            );
            $stmt->execute([$uuid, $phone, $displayName]);
            $userId = (int)$this->pdo->lastInsertId();
        } else {
            $userId = (int)$user['id'];
            $uuid   = $user['uuid'];

            // Global ban check BEFORE allowing any login
            if ((int)$user['is_blocked']) {
                $banStmt = $this->pdo->prepare(
                    'SELECT reason FROM user_bans WHERE user_id = ? AND unbanned_at IS NULL ORDER BY id DESC LIMIT 1'
                );
                $banStmt->execute([$userId]);
                $ban = $banStmt->fetch();
                $reason = ($ban && !empty($ban['reason'])) ? ': ' . $ban['reason'] : '';
                Response::forbidden('تم حظر هذا الحساب' . $reason . ' — يرجى التواصل مع إدارة التطبيق');
            }

            // Update name if provided WITHOUT auto-verifying (verification is admin-controlled)
            if ($name !== null) {
                $stmt = $this->pdo->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$name, $userId]);
            }
        }

        // Clear OTP (in dev mode keep it alive so test users can re-login with the same code)
        if (!($this->isDevelopmentOtp() && ($_ENV['APP_ENV'] ?? 'production') === 'development')) {
            $this->clearOtp($phone);
        }

        // Create session token
        $token    = $this->createSession($userId, $body['device_uuid'] ?? null, $body['fcm_token'] ?? null);
        $userData = $this->getUserById($userId);

        Response::success([
            'token' => $token,
            'user'  => $userData,
        ], 'تم تسجيل الدخول بنجاح');
    }

    // POST /api/v1/auth/login
    public function login(): void
    {
        RateLimitMiddleware::checkByIp();
        $this->assertOtpProviderAvailable();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body)
            ->required('phone', 'رقم الهاتف')
            ->phone('phone', 'رقم الهاتف');

        if ($v->fails()) {
            Response::validationError($v->errors());
        }

        $phone = trim($body['phone']);

        // Check user exists
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = ? AND is_blocked = 0 LIMIT 1');
        $stmt->execute([$phone]);
        if (!$stmt->fetch()) {
            // Don't reveal if phone exists or not
            Response::success(['message' => 'تم إرسال رمز التحقق إذا كان الرقم مسجلاً']);
        }

        // Generate OTP
        $otp       = $this->generateOtp();
        $otpHash   = password_hash($otp, PASSWORD_BCRYPT);
        $neverExpires = '2099-12-31 23:59:59';
        $this->storeOtp($phone, $otpHash, $neverExpires, '');

        $responseData = ['message' => 'تم إرسال رمز التحقق'];
        if ($this->isDevelopmentOtp()) {
            $responseData['otp_dev'] = $otp;
        }

        Response::success($responseData, 'تم إرسال رمز التحقق');
    }

    // POST /api/v1/auth/logout
    public function logout(): void
    {
        $user = AuthMiddleware::authenticate();

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token      = substr($authHeader, 7);
        $tokenHash  = hash('sha256', $token);

        $this->pdo->prepare('UPDATE sessions SET revoked_at = NOW() WHERE token_hash = ?')
                  ->execute([$tokenHash]);

        $this->pdo->prepare('UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = ?')
                  ->execute([(int)$user['user_id']]);

        Response::success(null, 'تم تسجيل الخروج بنجاح');
    }

    // GET /api/v1/auth/me
    public function me(): void
    {
        $user     = AuthMiddleware::authenticate();
        $userData = $this->getUserById((int)$user['user_id']);
        Response::success($userData);
    }

    // POST /api/v1/auth/refresh
    public function refresh(): void
    {
        $user     = AuthMiddleware::authenticate();
        $newToken = $this->createSession((int)$user['user_id']);
        Response::success(['token' => $newToken], 'تم تجديد الجلسة بنجاح');
    }

    // =====================================================
    // Private Helpers
    // =====================================================

    private function generateOtp(): string
    {
        if (($_ENV['OTP_PROVIDER'] ?? 'test') === 'test') {
            return $_ENV['OTP_TEST_CODE'] ?? '123456';
        }
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function isDevelopmentOtp(): bool
    {
        return ($_ENV['OTP_PROVIDER'] ?? 'sms') === 'test'
            && ($_ENV['APP_ENV'] ?? 'production') !== 'production';
    }

    private function assertOtpProviderAvailable(): void
    {
        if ($this->isDevelopmentOtp()) {
            return;
        }

        // SMS delivery is intentionally fail-closed until a real provider is wired.
        Response::error('مزود رسائل التحقق غير مهيأ بعد', 'OTP_PROVIDER_NOT_CONFIGURED', 503);
    }

    private function storeOtp(string $phone, string $otpHash, string $expiresAt, string $name): void
    {
        $key   = 'otp_' . md5($phone);
        $value = json_encode(['otp_hash' => $otpHash, 'expires_at' => $expiresAt, 'name' => $name]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()'
        );
        $stmt->execute([$key, $value, $value]);
    }

    private function getStoredOtp(string $phone): ?array
    {
        $key  = 'otp_' . md5($phone);
        $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return json_decode($row['setting_value'], true);
    }

    private function clearOtp(string $phone): void
    {
        $key = 'otp_' . md5($phone);
        $this->pdo->prepare('DELETE FROM app_settings WHERE setting_key = ?')->execute([$key]);
    }

    private function createSession(int $userId, ?string $deviceUuid = null, ?string $fcmToken = null): string
    {
        $expiryHours = (int)($_ENV['JWT_EXPIRY_HOURS'] ?? 720);
        $expiresAt   = time() + ($expiryHours * 3600);

        $payload = [
            'sub' => $userId,
            'iat' => time(),
            'exp' => $expiresAt,
        ];

        $token     = JwtHelper::generate($payload);
        $tokenHash = hash('sha256', $token);
        $deviceId  = null;

        // Register device if provided
        if ($deviceUuid) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_devices (user_id, device_uuid, fcm_token, last_active_at, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW(), NOW())
                 ON DUPLICATE KEY UPDATE fcm_token = ?, last_active_at = NOW(), updated_at = NOW()'
            );
            $stmt->execute([$userId, $deviceUuid, $fcmToken, $fcmToken]);
            $deviceId = $this->pdo->lastInsertId() ?: null;
        }

        $this->pdo->prepare(
            'INSERT INTO sessions (user_id, token_hash, device_id, ip_address, user_agent, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $userId,
            $tokenHash,
            $deviceId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            date('Y-m-d H:i:s', $expiresAt),
        ]);

        return $token;
    }

    private function getUserById(int $id): ?array
    {
        // Reuse the full profile builder from UserController (plan, device quota,
        // blocked flag, updated_at) so /auth/me, verify-otp and me return complete data.
        $uc  = new UserController();
        $ref = new \ReflectionMethod($uc, 'getUserById');
        $ref->setAccessible(true);
        return $ref->invoke($uc, $id);
    }
}
