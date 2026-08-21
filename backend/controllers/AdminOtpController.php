<?php
/**
 * NOVA Messenger — Admin OTP API Controller
 *
 * Endpoints (all JWT + admin role + RBAC protected):
 *  - GET    /admin/otp/providers              otp.providers.view
 *  - POST   /admin/otp/providers              otp.providers.create
 *  - GET    /admin/otp/providers/{id}         otp.providers.view
 *  - PUT    /admin/otp/providers/{id}         otp.providers.update
 *  - DELETE /admin/otp/providers/{id}         otp.providers.delete
 *  - POST   /admin/otp/providers/{id}/toggle  otp.providers.enable
 *  - POST   /admin/otp/providers/{id}/test    otp.providers.test
 *  - GET    /admin/otp/registrations          registration.view
 *  - GET    /admin/otp/registrations/{id}/code registration.view_otp
 *  - POST   /admin/otp/registrations/{id}/verify registration.verify
 *  - POST   /admin/otp/registrations/{id}/cancel registration.cancel
 *  - GET    /admin/otp/stats                  otp.stats
 *  - GET    /admin/otp/settings               otp.settings
 *  - POST   /admin/otp/settings               otp.settings
 */

declare(strict_types=1);

require_once __DIR__ . '/../otp/OtpProviderInterface.php';
require_once __DIR__ . '/../otp/OtpTemplate.php';
require_once __DIR__ . '/../otp/TwilioProvider.php';
require_once __DIR__ . '/../otp/VonageProvider.php';
require_once __DIR__ . '/../otp/HttpSmsProvider.php';
require_once __DIR__ . '/../otp/TestProvider.php';
require_once __DIR__ . '/../otp/SmsMockProvider.php';
require_once __DIR__ . '/../otp/WhatsappMockProvider.php';
require_once __DIR__ . '/../otp/OtpService.php';
require_once __DIR__ . '/../otp/ProviderManager.php';
require_once __DIR__ . '/../helpers/OtpEncryption.php';

class AdminOtpController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ------------------------------------------------------------------
    // Auth helpers: JWT + admin role + permission
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // Admin API login (JWT for admin panel AJAX)
    // POST /admin/otp/login {email, password}
    // ------------------------------------------------------------------

    public function adminApiLogin(): never
    {
        $body = $this->json();
        $email    = trim((string)($body['email'] ?? ''));
        $password = trim((string)($body['password'] ?? ''));
        if ($email === '' || $password === '') {
            $this->out(['success' => false, 'message' => 'البيانات غير صحيحة', 'error_code' => 'VALIDATION_ERROR'], 400, 'البيانات غير صحيحة');
        }
        if (strlen($email) > 190 || strlen($password) > 200) {
            $this->out(['success' => false, 'message' => 'البيانات غير صحيحة', 'error_code' => 'VALIDATION_ERROR'], 400, 'البيانات غير صحيحة');
        }
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.name, a.role_id, r.name AS role_name, a.is_active, a.password_hash
             FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $ok = is_array($admin) && ($admin['is_active'] == 1) && !empty($admin['password_hash']) && password_verify($password, $admin['password_hash']);
        if (!$ok) {
            $this->out(['success' => false, 'message' => 'بيانات الدخول غير صحيحة', 'error_code' => 'AUTH_FAILED'], 401, 'بيانات الدخول غير صحيحة');
        }
        $this->pdo->prepare('UPDATE admins SET last_login_at = datetime("now") WHERE id = ?')->execute([(int)$admin['id']]);
        $token = JwtHelper::generate([
            'user_id' => (int)$admin['id'],
            'role'    => 'admin',
            'admin_role' => $admin['role_name'] ?? 'admin',
            'iat' => time(),
            'exp' => time() + 72 * 3600,
        ]);
        $this->out([
            'success' => true,
            'message' => 'تم تسجيل الدخول',
            'data' => [
                'token' => $token,
                'admin' => [
                    'id' => (int)$admin['id'],
                    'name' => $admin['name'],
                    'email' => $email,
                    'role_name' => $admin['role_name'],
                ],
            ],
        ], 200, 'تم تسجيل الدخول');
    }

    private function authenticateAdmin(string $permission): array
    {
        // Try session-bound JWT first (user JWT), then fall back to a
        // standalone admin JWT issued by /admin/otp/login (not session-bound).
        $authHeader = nova_get_auth_header() ?? '';
        $token = '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }
        if ($token === '') {
            Response::unauthorized('يجب تسجيل الدخول أولاً');
        }
        $payload = JwtHelper::verify($token);
        if ($payload === null) {
            Response::unauthorized('الجلسة منتهية أو غير صالحة، يرجى تسجيل الدخول مجدداً');
        }
        $adminId = (int)($payload['user_id'] ?? 0);
        // Standalone admin JWTs carry the 'role' => 'admin' claim
        $isStandaloneAdminJwt = isset($payload['role']) && $payload['role'] === 'admin';
        if (!$isStandaloneAdminJwt) {
            // Session-bound JWT: verify the session record exists (user JWT)
            $user = AuthMiddleware::authenticate();
            $adminId = (int)$user['user_id'];
        }
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.name, a.email, a.role_id, r.name AS role_name
             FROM admins a JOIN roles r ON r.id = a.role_id
             WHERE a.id = ? AND a.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            Response::forbidden('حسابك ليس حساب إدارة');
        }
        $permStmt = $this->pdo->prepare(
            'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.name = ? LIMIT 1'
        );
        $permStmt->execute([(int)$admin['role_id'], $permission]);
        if (!$permStmt->fetch()) {
            Response::forbidden('ليس لديك صلاحية: ' . $permission);
        }
        $admin['permission'] = $permission;
        return $admin;
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function out(array $data, int $code = 200, string $message = 'تم'): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge($data, ['message' => $message]), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ------------------------------------------------------------------
    // Providers CRUD
    // ------------------------------------------------------------------

    public function providersIndex(): void
    {
        $this->authenticateAdmin('otp.providers.view');
        $this->out(['providers' => (new ProviderManager())->list()]);
    }

    public function providersCreate(): void
    {
        $admin = $this->authenticateAdmin('otp.providers.create');
        $data = $this->json();

        $types = ['twilio', 'vonage', 'http_rest', 'sms_mock', 'whatsapp_mock', 'test'];
        $type = trim((string)($data['type'] ?? ''));
        if (!in_array($type, $types, true)) {
            $this->out(['error_code' => 'INVALID_TYPE'], 400, 'نوع المزود غير صالح: ' . htmlspecialchars($type));
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            $this->out(['error_code' => 'VALIDATION_ERROR'], 400, 'اسم المزود مطلوب');
        }

        // Basic config validation by type
        $valid = $this->validateProviderConfig($type, $data);
        if ($valid !== true) {
            $this->out(['error_code' => 'VALIDATION_ERROR'], 400, $valid);
        }

        // Ensure unique default
        if (!empty($data['is_default'])) {
            $this->pdo->prepare('UPDATE otp_providers SET is_default = 0, updated_at = datetime("now") WHERE type = ?')->execute([$type]);
        }

        $id = (new ProviderManager())->create($data);
        logAdminAudit($admin, 'OTP_PROVIDER_CREATED', 'otp_providers', $id, "إنشاء مزود OTP: {$name} ({$type})");
        $this->out(['provider_id' => $id], 201, 'تم إنشاء المزود بنجاح');
    }

    public function providersUpdate(int $id): void
    {
        $admin = $this->authenticateAdmin('otp.providers.update');
        $data = $this->json();
        $manager = new ProviderManager();
        $current = $manager->get($id);
        if (!$current) {
            $this->out(['error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود');
        }

        $name = trim((string)($data['name'] ?? $current['name']));
        $type = trim((string)($data['type'] ?? $current['type']));

        if (!empty($data['is_default'])) {
            $this->pdo->prepare('UPDATE otp_providers SET is_default = 0, updated_at = datetime("now") WHERE type = ? AND id != ?')->execute([$type, $id]);
        }

        $ok = $manager->update($id, array_merge($current, $data, ['name' => $name, 'type' => $type]));
        if (!$ok) {
            $this->out(['error_code' => 'UPDATE_FAILED'], 500, 'فشل تحديث المزود');
        }
        logAdminAudit($admin, 'OTP_PROVIDER_UPDATED', 'otp_providers', $id, "تعديل مزود OTP: {$name}");
        $this->out(['updated' => true], 200, 'تم تحديث المزود بنجاح');
    }

    public function providersDelete(int $id): void
    {
        $admin = $this->authenticateAdmin('otp.providers.delete');
        $manager = new ProviderManager();
        if (!$manager->get($id)) {
            $this->out(['error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود');
        }
        $manager->delete($id);
        logAdminAudit($admin, 'OTP_PROVIDER_DELETED', 'otp_providers', $id, 'حذف مزود OTP');
        $this->out(['deleted' => true], 200, 'تم حذف المزود');
    }

    public function providersToggle(int $id): void
    {
        $admin = $this->authenticateAdmin('otp.providers.enable');
        $data = $this->json();
        $status = trim((string)($data['status'] ?? ''));
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $this->out(['error_code' => 'INVALID_STATUS'], 400, 'الحالة يجب أن تكون enabled أو disabled');
        }
        $manager = new ProviderManager();
        $row = $manager->get($id);
        if (!$row) {
            $this->out(['error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود');
        }
        $manager->toggle($id, $status);
        logAdminAudit($admin, $status === 'enabled' ? 'OTP_PROVIDER_ENABLED' : 'OTP_PROVIDER_DISABLED', 'otp_providers', $id, ($status === 'enabled' ? 'تفعيل' : 'تعطيل') . ' مزود OTP: ' . $row['name']);
        $this->out(['status' => $status], 200, $status === 'enabled' ? 'تم تفعيل المزود' : 'تم تعطيل المزود');
    }

    public function providersTest(int $id): void
    {
        $admin = $this->authenticateAdmin('otp.providers.test');
        $data = $this->json();
        $phone = trim((string)($data['phone'] ?? ''));
        if ($phone === '') {
            $this->out(['error_code' => 'VALIDATION_ERROR'], 400, 'رقم الهاتف للاختبار مطلوب');
        }
        $manager = new ProviderManager();
        $row = $manager->get($id);
        if (!$row) {
            $this->out(['error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود');
        }
        $result = $manager->test($id, $phone);
        logAdminAudit($admin, 'OTP_PROVIDER_TESTED', 'otp_providers', $id,
            'اختبار مزود OTP: ' . $row['name'] . ' — ' . ($result['success'] ? 'نجح' : 'فشل') . ($result['http_code'] ? " (HTTP {$result['http_code']})" : ''));
        $this->out($result, $result['success'] ? 200 : 424, $result['message']);
    }

    private function validateProviderConfig(string $type, array $data): true|string
    {
        switch ($type) {
            case 'twilio':
                $sid = trim((string)($data['account_sid'] ?? ''));
                $secret = trim((string)($data['api_secret'] ?? ''));
                $from = trim((string)($data['sender_id'] ?? ''));
                if ($sid === '' || $secret === '' || $from === '') {
                    return 'مزود Twilio يتطلب: Account SID و Auth Token والمُرسل (From)';
                }
                return true;
            case 'vonage':
                $key = trim((string)($data['api_key'] ?? ''));
                $secret = trim((string)($data['api_secret'] ?? ''));
                if ($key === '' || $secret === '') {
                    return 'مزود Vonage يتطلب: API Key و API Secret';
                }
                return true;
            case 'http_rest':
                $url = trim((string)($data['api_base_url'] ?? ''));
                if ($url === '') {
                    return 'مزود HTTP REST يتطلب: رابط API (api_base_url)';
                }
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return 'صيغة رابط API غير صحيحة';
                }
                return true;
            case 'test':
            case 'sms_mock':
            case 'whatsapp_mock':
            case 'whatsapp':
                return true; // no credentials required (internal trial channel)
            default:
                return 'نوع المزود غير معروف';
        }
    }

    // ------------------------------------------------------------------
    // Registrations (OTP verifications)
    // ------------------------------------------------------------------

    public function registrationsIndex(): void
    {
        $this->authenticateAdmin('registration.view');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? 20)));
        $service = new OtpService();
        $res = $service->getPendingRegistrations($page, $perPage);
        $this->out(array_merge($res, ['current_page' => $page, 'per_page' => $perPage]));
    }

    public function registrationsGetCode(int $id): void
    {
        $admin = $this->authenticateAdmin('registration.view_otp');
        $service = new OtpService();
        $res = $service->revealManualCode($id);
        if ($res === null) {
            $this->out(['error_code' => 'OTP_NOT_VIEWABLE'], 404, 'لا يمكن عرض هذا الرمز (غير موجود أو تم التحقق منه أو وضعه تلقائي)');
        }
        logAdminAudit($admin, 'OTP_VIEWED', 'otp_verifications', $id, 'المدير شاهد رمز OTP للتسليم اليدوي');
        $this->out(['otp_code' => $res['code'], 'expires_at' => $res['expires_at']], 200, 'انسخ هذا الرمز وأرسله للمستخدم');
    }

    public function registrationsVerify(int $id): void
    {
        $admin = $this->authenticateAdmin('registration.verify');
        $service = new OtpService();
        $userId = $service->adminVerify($id);
        if ($userId === null) {
            $this->out(['error_code' => 'NOT_FOUND'], 404, 'طلب التسجيل غير موجود أو لم يعد قابلًا للتأكيد');
        }
        logAdminAudit($admin, 'OTP_VERIFIED', 'otp_verifications', $id, 'تأكيد تسجيل يدويًا من قبل المدير (user_id=' . $userId . ')');
        $this->out(['user_id' => $userId], 200, 'تم تأكيد التسجيل يدويًا');
    }

    public function registrationsCancel(int $id): void
    {
        $admin = $this->authenticateAdmin('registration.cancel');
        $service = new OtpService();
        if (!$service->cancel($id)) {
            $this->out(['error_code' => 'NOT_FOUND'], 404, 'طلب التسجيل غير موجود أو لم يعد قابلًا للإلغاء');
        }
        logAdminAudit($admin, 'OTP_CANCELLED', 'otp_verifications', $id, 'إلغاء طلب تسجيل من قبل المدير');
        $this->out(['cancelled' => true], 200, 'تم إلغاء طلب التسجيل');
    }

    // ------------------------------------------------------------------
    // Stats & settings
    // ------------------------------------------------------------------

    public function stats(): void
    {
        $this->authenticateAdmin('otp.stats');
        $day = trim((string)($_GET['day'] ?? ''));
        $this->out((new OtpService())->getStats($day));
    }

    public function settingsGet(): void
    {
        $this->authenticateAdmin('otp.settings');
        $stmt = $this->pdo->query(
            "SELECT setting_key, setting_value FROM app_settings
             WHERE setting_key IN (
               'otp_length','otp_expiry_minutes','otp_max_attempts','otp_resend_cooldown_seconds',
               'otp_max_resends','otp_delivery_mode','otp_default_provider_id','otp_enable_fallback',
               'otp_enable_manual_fallback','otp_message_template','otp_rate_limit_per_phone_per_hour',
               'otp_rate_limit_per_ip_per_hour','otp_required','app_name')"
        );
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $this->out(['settings' => $settings]);
    }

    public function settingsUpdate(): void
    {
        $admin = $this->authenticateAdmin('otp.settings');
        $data = $this->json();
        $allowed = [
            'otp_length', 'otp_expiry_minutes', 'otp_max_attempts', 'otp_resend_cooldown_seconds',
            'otp_max_resends', 'otp_delivery_mode', 'otp_default_provider_id', 'otp_enable_fallback',
            'otp_enable_manual_fallback', 'otp_message_template', 'otp_rate_limit_per_phone_per_hour',
            'otp_rate_limit_per_ip_per_hour', 'otp_required',
        ];
        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : trim((string)$value);
            $this->pdo->prepare(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = datetime("now")'
	            )->execute([$key, $val]);
        }
        logAdminAudit($admin, 'OTP_SETTINGS_UPDATED', 'app_settings', 0, 'تحديث إعدادات OTP');
        $this->out(['updated' => true], 200, 'تم حفظ إعدادات OTP');
    }
}

/**
 * Global audit helper for admin OTP actions.
 * NOTE: NEVER logs OTP codes, API keys, or secrets.
 */
function logAdminAudit(array $admin, string $action, string $entityType, int $entityId, string $description): void
{
    try {
        $pdo = Database::getInstance();
        $pdo->prepare(
            'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, datetime("now"))'
        )->execute([
            $admin['id'], $action, $entityType, $entityId ?: null, $description,
            $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $e) {
        // audit failure must not break the request
    }
}
