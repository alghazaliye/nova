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
        try {
            RateLimitMiddleware::checkByIp();
        // Server-side enforcement of registration methods
        require_once __DIR__ . '/../helpers/AuthConfigService.php';
        $cfg = new AuthConfigService();
        $cfg->assertRegistrationMethod('phone');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($body)
            ->required('phone', 'رقم الهاتف')
            ->phone('phone', 'رقم الهاتف');

        if (isset($body['name']) && trim((string)$body['name']) !== '') {
            $v->minLength('name', 2, 'الاسم')->maxLength('name', 150, 'الاسم');
        }

        if ($v->fails()) {
            Response::validationError($v->errors());
        }

        // WhatsApp-style: phone + optional country_code; name/email optional
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

        // ---- New multi-provider OTP pipeline ----
        require_once __DIR__ . '/../otp/OtpProviderInterface.php';
        require_once __DIR__ . '/../otp/OtpTemplate.php';
        require_once __DIR__ . '/../otp/OtpService.php';
        $otpService = new OtpService();

        // Rate limit check (phone + IP per hour)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateError = $otpService->checkRateLimit($phone, $ip, 'registration');
        if ($rateError !== null) {
            Response::error($rateError, 'OTP_RATE_LIMITED', 429);
        }

        // Resend cooldown
        $cooldown = 0; // Disabled for testing
        if ($cooldown > 0) {
            Response::error("يمكنك إعادة الإرسال بعد {$cooldown} ثانية", 'OTP_COOLDOWN', 429);
        }

        $devCode = $this->isDevelopmentOtp() ? $this->generateOtp() : null;
        $res = $otpService->createAndSend($phone, $name, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $devCode);
        $responseData = [
            'message' => $res['message'],
            'delivery_mode' => $res['delivery_mode'],
            'cooldown' => $res['cooldown'],
            'expires_at' => $this->activeOtpExpiry($phone),
        ];
        if ($this->isDevelopmentOtp()) {
            $responseData['otp_dev'] = $devCode; // Remove in production
        }

        Response::success($responseData, 'تم إرسال رمز التحقق إلى رقم هاتفك');
        } catch (\Throwable $e) {
            error_log("[nova error] AuthController::register: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            throw $e;
        }
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
        if (!str_starts_with($phone, '+')) {
            $phone = '+966' . ltrim($phone, '0');
        }
        $otp   = trim($body['otp']);
        // WhatsApp-style: name is supplied later via profile setup (optional here)
        $name  = isset($body['name']) && trim((string)$body['name']) !== ''
            ? $v->sanitizeString('name')
            : null;

        // ---- Verify via the multi-provider OTP pipeline (new) ----
        // Migration: legacy OTPs stored in app_settings (otp_<md5>) are upgraded on the fly:
        // if no row exists in otp_verifications, fall back to the legacy store once.
        require_once __DIR__ . '/../otp/OtpProviderInterface.php';
        require_once __DIR__ . '/../otp/OtpTemplate.php';
        require_once __DIR__ . '/../otp/OtpService.php';
        $otpService = new OtpService();

        $legacyOtp = null;

        // ---- Verify via the multi-provider OTP pipeline (new) ----
        try {
            $result = $otpService->verify($phone, $otp);
        } catch (\Throwable $e) {
            // Pipeline failure (e.g. table missing in very old schema): fall back to legacy store
            error_log('OTP pipeline error, falling back to legacy: ' . $e->getMessage());
            $result = ['verified' => false, 'message' => 'فشل التحقق من المزود'] + ['error_code' => 'OTP_SERVICE_ERROR'];
        }

        // Fallback: legacy app_settings OTP (dev test mode keeps 123456 alive)
        if (!$result['verified']) {
            $legacyOtp = $this->getStoredOtp($phone);
            if ($legacyOtp) {
                if (!password_verify($otp, $legacyOtp['otp_hash'] ?? '')) {
                    $legacyOtp = null;
                } else {
                    $result = ['verified' => true];
                    $this->clearOtp($phone);
                }
            }
        }

        if (!$result || !$result['verified']) {
            $msg = ($result && isset($result['message'])) ? $result['message'] : 'رمز التحقق غير صحيح';
            $code = ($result && isset($result['error_code'])) ? $result['error_code'] : 'OTP_INVALID';
            $extra = ['attempts_left' => $result['attempts_left'] ?? null];
            $extra = array_filter($extra, static fn ($x) => $x !== null);
            Response::error($msg, $code, 400, $extra);
        }

        // Create or update user
        $stmt = $this->pdo->prepare('SELECT id, uuid, is_blocked FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user) {
            $uuid = UuidHelper::generate();
            // Prefer the name collected during register (stored in otp_verifications) if not sent in body
            $collectedName = null;
            try {
                $vStmt = $this->pdo->prepare('SELECT name FROM otp_verifications WHERE phone_number = ? ORDER BY id DESC LIMIT 1');
                $vStmt->execute([$phone]);
                $vRow = $vStmt->fetch();
                if ($vRow && trim((string)$vRow['name']) !== '') {
                    $collectedName = $vRow['name'];
                }
            } catch (\Throwable $e) {}
            $displayName = $name ?? $collectedName ?? ($legacyOtp['name'] ?? 'مستخدم NOVA');
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (uuid, phone, name, is_verified, created_at, updated_at)" .
                 " VALUES (?, ?, ?, 0, datetime('now'), datetime('now'))"
            );
            $stmt->execute([$uuid, $phone, $displayName]);
            $userId = (int)$this->pdo->lastInsertId();
        } else {
            $userId = (int)$user['id'];
            $uuid   = $user['uuid'];

            // Global ban check BEFORE allowing any login
            if ((int)$user['is_blocked']) {
                $banStmt = $this->pdo->prepare(
                    'SELECT reason, suspend_until FROM user_bans
                     WHERE user_id = ? AND unbanned_at IS NULL ORDER BY id DESC LIMIT 1'
                );
                $banStmt->execute([$userId]);
                $ban = $banStmt->fetch();
                $reason = ($ban && !empty($ban['reason'])) ? ': ' . $ban['reason'] : '';
                // Temporary suspension: if suspend_until is set and not yet passed, show a clear message
                if ($ban && !empty($ban['suspend_until']) && $ban['suspend_until'] > date('Y-m-d H:i:s')) {
                    Response::forbidden(
                        'هذا الحساب معلق مؤقتًا حتى ' . $ban['suspend_until'] . $reason .
                        ' — يمكنك تقديم اعتراض بعد انتهاء التعليق أو التواصل مع الإدارة'
                    );
                }
                Response::forbidden('تم حظر هذا الحساب' . $reason . ' — يمكنك تقديم اعتراض أو التواصل مع إدارة التطبيق');
            }

            // Update name if provided WITHOUT auto-verifying (verification is admin-controlled)
            if ($name !== null) {
                $stmt = $this->pdo->prepare("UPDATE users SET name = ?, updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$name, $userId]);
            }
        }

        // Clear any leftover legacy OTP
        $this->clearOtp($phone);

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
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body)
            ->required('phone', 'رقم الهاتف')
            ->phone('phone', 'رقم الهاتف');
        if ($v->fails()) {
            Response::validationError($v->errors());
        }
        $phone = trim($body['phone']);
        if (!str_starts_with($phone, '+')) {
            $phone = '+966' . ltrim($phone, '0');
        }
        // Server-side enforcement of login methods
        require_once __DIR__ . '/../helpers/AuthConfigService.php';
        $cfg = new AuthConfigService();
        $cfg->assertLoginMethod('phone');

        // Check user exists
        $stmt = $this->pdo->prepare('SELECT id, is_blocked FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $existing = $stmt->fetch();
        if (false) { // Disabled check for testing
            // Don't reveal if phone exists or not
            Response::success(['message' => 'تم إرسال رمز التحقق إذا كان الرقم مسجلاً']);
        }
        // Blocked users cannot start a new login session at all
        if ((int)$existing['is_blocked']) {
            $banStmt = $this->pdo->prepare(
                'SELECT reason, suspend_until FROM user_bans
                 WHERE user_id = ? AND unbanned_at IS NULL ORDER BY id DESC LIMIT 1'
            );
            $banStmt->execute([(int)$existing['id']]);
            $ban = $banStmt->fetch();
            $reason = ($ban && !empty($ban['reason'])) ? ': ' . $ban['reason'] : '';
            if ($ban && !empty($ban['suspend_until']) && $ban['suspend_until'] > date('Y-m-d H:i:s')) {
                Response::error(
                    'هذا الحساب معلق مؤقتًا حتى ' . $ban['suspend_until'] . $reason
                    . ' — يمكنك تقديم اعتراض أو التواصل مع الإدارة',
                    'ACCOUNT_SUSPENDED', 403
                );
            }
            Response::error(
                'تم حظر هذا الحساب' . $reason . ' — يمكنك تقديم اعتراض أو التواصل مع إدارة التطبيق',
                'ACCOUNT_BANNED', 403
            );
        }

        // ---- New multi-provider OTP pipeline ----
        require_once __DIR__ . '/../otp/OtpProviderInterface.php';
        require_once __DIR__ . '/../otp/OtpTemplate.php';
        require_once __DIR__ . '/../otp/OtpService.php';
        $otpService = new OtpService();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateError = $otpService->checkRateLimit($phone, $ip, 'login');
        if ($rateError !== null) {
            Response::success(['message' => 'تم إرسال رمز التحقق إذا كان الرقم مسجلاً']);
        }

        $cooldown = 0; // Disabled for testing
        if ($cooldown > 0) {
            Response::error("يمكنك إعادة الإرسال بعد {$cooldown} ثانية", 'OTP_COOLDOWN', 429);
        }

        $devCode = $this->isDevelopmentOtp() ? $this->generateOtp() : null;
        $res = $otpService->createAndSend($phone, '', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $devCode);
        $responseData = [
            'message' => 'تم إرسال رمز التحقق',
            'delivery_mode' => $res['delivery_mode'],
            'otp_debug' => $res['otp_debug'] ?? null,
            'cooldown' => $res['cooldown'],
            'expires_at' => $this->activeOtpExpiry($phone),
        ];
        if ($this->isDevelopmentOtp()) {
            $responseData['otp_dev'] = $devCode;
        }

        Response::success($responseData, 'تم إرسال رمز التحقق');
    }

    // POST /api/v1/auth/resend-otp
    public function resendOtp(): void
    {
        RateLimitMiddleware::checkByIp();
        $this->assertOtpProviderAvailable();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone = trim((string)($body['phone'] ?? ''));
        $otpId = (int)($body['otp_id'] ?? 0);
        if ($phone === '') {
            Response::validationError(['phone' => 'رقم الهاتف مطلوب']);
        }

        require_once __DIR__ . '/../otp/OtpProviderInterface.php';
        require_once __DIR__ . '/../otp/OtpTemplate.php';
        require_once __DIR__ . '/../otp/OtpService.php';
        $otpService = new OtpService();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateError = $otpService->checkRateLimit($phone, $ip, 'resend');
        if ($rateError !== null) {
            Response::error($rateError, 'OTP_RATE_LIMITED', 429);
        }

        $devCode = $this->isDevelopmentOtp() ? $this->generateOtp() : null;
        $res = $otpService->resend($otpId > 0 ? $otpId : $this->findActiveOtpId($phone), $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $devCode);
        if (!$res['success']) {
            $code = $res['error_code'] ?? 'ERROR';
            $out = ['success' => false, 'message' => $res['message'] ?? 'فشل إعادة الإرسال', 'error_code' => $code];
            if (isset($res['cooldown'])) {
                $out['cooldown'] = (int)$res['cooldown'];
            }
            http_response_code(in_array($code, ['OTP_COOLDOWN', 'OTP_RATE_LIMITED', 'OTP_MAX_RESENDS'], true) ? 429 : 400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $responseData = [
            'message' => 'تم إعادة إرسال رمز التحقق',
            'otp_id' => $res['otp_id'] ?? null,
            'delivery_mode' => $res['delivery_mode'],
            'cooldown' => $res['cooldown'],
        ];
        if ($this->isDevelopmentOtp()) {
            $responseData['otp_dev'] = $devCode;
        }

        Response::success($responseData, 'تم إعادة إرسال رمز التحقق');
    }

    /** صلاحية آخر رمز OTP نشط لنفس الهاتف (للعرض في شاشة التحقق) */
    private function activeOtpExpiry(string $phone): ?string
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT expires_at FROM otp_verifications
                 WHERE phone_number = ? AND status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\')
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$phone]);
            $row = $stmt->fetch();
            return $row ? ($row['expires_at'] ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Find the latest pending otp_verifications id for a phone (legacy-free lookup) */
    private function findActiveOtpId(string $phone): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM otp_verifications
                 WHERE phone_number = ? AND status IN (\'pending\',\'sent\',\'manual\',\'delivery_failed\')
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$phone]);
            $row = $stmt->fetch();
            return $row ? (int)$row['id'] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // POST /api/v1/auth/logout
    public function logout(): void
    {
        $user = AuthMiddleware::authenticate();

        $authHeader = nova_get_auth_header() ?? '';
        $token      = substr($authHeader, 7);
        $tokenHash  = hash('sha256', $token);

        $this->pdo->prepare('UPDATE sessions SET revoked_at = datetime('now') WHERE token_hash = ?')
                  ->execute([$tokenHash]);

        $this->pdo->prepare('UPDATE users SET is_online = 0, last_seen = datetime('now') WHERE id = ?')
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

    /** Read a global app setting from app_settings (admin panel controlled) */
    private function getAppSetting(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_value'] : null;
    }

    /** Register flow without OTP (admin set otp_required = '0') */
    private function registerWithoutOtp(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone = trim((string)($body['phone'] ?? ''));
        if ($phone === '') {
            Response::error('رقم الهاتف مطلوب', 'VALIDATION_ERROR', 400);
        }
        // Normalize phone like the normal register flow
        $phone = ltrim($phone, '+0');
        $countryCode = isset($body['country_code']) && trim((string)$body['country_code']) !== ''
            ? trim(trim((string)$body['country_code']), '+')
            : null;
        if ($countryCode !== null && str_starts_with($phone, $countryCode)) {
            $phone = '+' . $phone;
        } else {
            $phone = ($countryCode !== null ? '+' . $countryCode : '+') . $phone;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            Response::error('رقم الهاتف مسجل مسبقاً', 'PHONE_EXISTS', 409);
        }
        $this->loginOrCreateWithoutOtp($phone, $body['name'] ?? null);
    }

    /** Login or create user without OTP, returning a session token */
    private function loginOrCreateWithoutOtp(string $phone, ?string $name): void
    {
        $stmt = $this->pdo->prepare('SELECT id, uuid, is_blocked FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        if (!$user) {
            $uuid = UuidHelper::generate();
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (uuid, phone, name, is_verified, created_at, updated_at)" .
                 " VALUES (?, ?, ?, 0, datetime('now'), datetime('now'))"
            );
            $name = isset($name) && trim($name) !== '' ? trim($name) : 'مستخدم NOVA';
            $stmt->execute([$uuid, $phone, $name]);
            $userId = (int)$this->pdo->lastInsertId();
        } else {
            $userId = (int)$user['id'];
            if ((int)$user['is_blocked']) {
                $banStmt = $this->pdo->prepare(
                    'SELECT reason, suspend_until FROM user_bans
                     WHERE user_id = ? AND unbanned_at IS NULL ORDER BY id DESC LIMIT 1'
                );
                $banStmt->execute([$userId]);
                $ban = $banStmt->fetch();
                $reason = ($ban && !empty($ban['reason'])) ? ': ' . $ban['reason'] : '';
                if ($ban && !empty($ban['suspend_until']) && $ban['suspend_until'] > date('Y-m-d H:i:s')) {
                    Response::forbidden(
                        'هذا الحساب معلق مؤقتًا حتى ' . $ban['suspend_until'] . $reason
                        . ' — يمكنك تقديم اعتراض أو التواصل مع الإدارة'
                    );
                }
                Response::forbidden('تم حظر هذا الحساب' . $reason . ' — يمكنك تقديم اعتراض أو التواصل مع الإدارة');
            }
        }
        $token    = $this->createSession($userId, null, null);
        $userData = $this->getUserById($userId);
        Response::success([
            'token' => $token,
            'user'  => $userData,
            'otp_bypass' => true,
        ], 'تم تسجيل الدخول بنجاح (التحقق معطّل)');
    }

    private function generateOtp(): string
    {
        // Always a real random code — dev visibility is handled via otp_dev field only
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function isDevelopmentOtp(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') !== 'production';
    }

    private function assertOtpProviderAvailable(): void
    {
        if ($this->isDevelopmentOtp()) {
            return;
        }

        // Fail-closed: require at least one enabled provider in the new pipeline
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) c FROM otp_providers WHERE status = 'enabled' LIMIT 1");
            $enabled = (int)($stmt->fetch()['c'] ?? 0);
            if ($enabled > 0) {
                return;
            }
        } catch (\Throwable $e) {
            // table may not exist in old schema — fall through to error
        }

        Response::error('مزود رسائل التحقق غير مهيأ بعد. يجب تفعيل مزود واحد على الأقل من لوحة التحكم', 'OTP_PROVIDER_NOT_CONFIGURED', 503);
    }

    private function storeOtp(string $phone, string $otpHash, string $expiresAt, string $name): void
    {
        $key   = 'otp_' . md5($phone);
        $value = json_encode(['otp_hash' => $otpHash, 'expires_at' => $expiresAt, 'name' => $name]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (?, ?)
ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = datetime('now')'
	        );
	        $stmt->execute([$key, $value]);
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
        $expiryHours = 8760;
        $expiresAt   = time() + ($expiryHours * 3600);

        $makeJti = static fn (): string =>
            substr(md5((string)microtime(true) . random_int(0, PHP_INT_MAX)), 0, 16);

        $payload = [
            'sub' => $userId,
            'iat' => time(),
            'exp' => $expiresAt,
            'jti' => $makeJti(),
        ];

        $token     = JwtHelper::generate($payload);
        $tokenHash = hash('sha256', $token);
        $deviceId  = null;

        // Register device if provided (non-fatal on failure)
        if ($deviceUuid) {
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO user_devices (user_id, device_uuid, fcm_token, last_active_at, created_at, updated_at)
                     VALUES (?, ?, ?, datetime('now'), datetime('now'), datetime('now'))
ON CONFLICT(user_id, device_uuid) DO UPDATE SET fcm_token = excluded.fcm_token, last_active_at = datetime('now'), updated_at = datetime('now')'
	                );
	                $stmt->execute([$userId, $deviceUuid, $fcmToken]);
                $deviceId = (int)($this->pdo->lastInsertId() ?: 0) ?: null;
            } catch (\Throwable $e) {
                error_log('Device registration error (non-fatal): ' . $e->getMessage());
                $deviceId = null;
            }
        }

        // Insert session; on rare hash collision (same-second retries) retry once with a new jti.
        $insertSession = function (int $uid, string $th, ?int $did, string $expStr) use ($expiresAt): bool {
            try {
                $this->pdo->prepare(
                    "INSERT INTO sessions (user_id, token_hash, device_id, ip_address, user_agent, expires_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, datetime('now'))"
                )->execute([
                    $uid,
                    $th,
                    $did,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    $expStr,
                ]);
                return true;
            } catch (\Throwable $e) {
                error_log('Session insert error: ' . $e->getMessage());
                return false;
            }
        };

        if (!$insertSession($userId, $tokenHash, $deviceId, date('Y-m-d H:i:s', $expiresAt))) {
            $payload['jti'] = $makeJti();
            $token     = JwtHelper::generate($payload);
            $tokenHash = hash('sha256', $token);
            $insertSession($userId, $tokenHash, $deviceId, date('Y-m-d H:i:s', $expiresAt));
        }

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
