<?php
/**
 * NOVA Messenger — Admin Auth Settings Controller
 *
 * Endpoints (all JWT + admin role + RBAC protected):
 *  - GET    /admin/auth/settings              auth.settings.view
 *  - POST   /admin/auth/settings              auth.settings.update
 *  - GET    /admin/email-providers            email.providers.view
 *  - POST   /admin/email-providers            email.providers.create
 *  - GET    /admin/email-providers/{id}       email.providers.view
 *  - PUT    /admin/email-providers/{id}       email.providers.update
 *  - DELETE /admin/email-providers/{id}       email.providers.delete
 *  - POST   /admin/email-providers/{id}/toggle email.providers.update
 *  - POST   /admin/email-providers/{id}/test  email.providers.test
 *  - GET    /admin/email-registrations        registration.view (email OTP requests)
 *  - GET    /admin/email-registrations/{id}/code registration.view_otp
 *  - POST   /admin/email-registrations/{id}/verify registration.verify
 *  - POST   /admin/email-registrations/{id}/cancel registration.cancel
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/OtpEncryption.php';
require_once __DIR__ . '/../otp/EmailOtpService.php';
require_once __DIR__ . '/../otp/EmailProviderManager.php';
require_once __DIR__ . '/../helpers/AuthConfigService.php';
require_once __DIR__ . '/AdminOtpController.php'; // for global logAdminAudit()

class AdminAuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ------------------------------------------------------------------
    // Auth helpers (same pattern as AdminOtpController)
    // ------------------------------------------------------------------

    private function authenticateAdmin(string $permission): array
    {
        $authHeader = nova_get_auth_header() ?? '';
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        if ($token === '') Response::unauthorized('يجب تسجيل الدخول أولاً');

        $payload = JwtHelper::verify($token);
        if ($payload === null) Response::unauthorized('الجلسة منتهية أو غير صالحة، يرجى تسجيل الدخول مجدداً');

        $adminId = (int)($payload['user_id'] ?? 0);
        if (!(isset($payload['role']) && $payload['role'] === 'admin')) {
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
        if (!$admin) Response::forbidden('حسابك ليس حساب إدارة');

        $permStmt = $this->pdo->prepare(
            'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.name = ? LIMIT 1'
        );
        $permStmt->execute([(int)$admin['role_id'], $permission]);
        if (!$permStmt->fetch()) Response::forbidden('ليس لديك صلاحية: ' . $permission);

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
    // Unified auth settings
    // GET  /admin/auth/settings
    // POST /admin/auth/settings
    // ------------------------------------------------------------------

    public function settingsGet(): void
    {
        $this->authenticateAdmin('auth.settings.view');

        $keys = [
            'auth_phone_registration', 'auth_email_registration',
            'auth_phone_login', 'auth_email_login', 'auth_username_login',
            'otp_phone_enabled', 'otp_email_enabled',
            'otp_phone_expiry_minutes', 'otp_email_expiry_minutes',
            'otp_phone_max_attempts', 'otp_email_max_attempts',
            'otp_phone_resend_cooldown_seconds', 'otp_email_resend_cooldown_seconds',
            'otp_phone_max_resends', 'otp_email_max_resends',
            'otp_phone_delivery_mode', 'otp_email_delivery_mode',
            'otp_email_template', 'otp_email_from_provider_id', 'app_name',
        ];
        $stmt = $this->pdo->query(
            'SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('
            . implode(',', array_map(static fn ($k) => "'" . addslashes($k) . "'", $keys))
            . ')'
        );
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Conflict detection warning
        $warnings = [];
        $phoneReg = ($settings['auth_phone_registration'] ?? '1') === '1';
        $emailReg = ($settings['auth_email_registration'] ?? '0') === '1';
        $phoneOtp = ($settings['otp_phone_enabled'] ?? '1') === '1';
        $emailOtp = ($settings['otp_email_enabled'] ?? '0') === '1';
        if ($phoneReg && !$phoneOtp) {
            $warnings[] = 'تسجيل الهاتف مفعّل لكن OTP الهاتف معطّل — سيستخدم النظام وضع تجاوز OTP';
        }
        if ($emailReg && !$emailOtp) {
            $warnings[] = 'تسجيل البريد مفعّل لكن OTP البريد معطّل — يجب تفعيل مزود بريد وOTP البريد';
        }
        if (!$phoneReg && !$emailReg) {
            $warnings[] = 'التسجيل متوقف بالكامل (الهاتف والبريد معطّلان)';
        }

        $this->out(['settings' => $settings, 'warnings' => $warnings]);
    }

    public function settingsUpdate(): void
    {
        $admin = $this->authenticateAdmin('auth.settings.update');
        $data = $this->json();

        $allowed = [
            'auth_phone_registration', 'auth_email_registration',
            'auth_phone_login', 'auth_email_login', 'auth_username_login',
            'otp_phone_enabled', 'otp_email_enabled',
            'otp_phone_expiry_minutes', 'otp_email_expiry_minutes',
            'otp_phone_max_attempts', 'otp_email_max_attempts',
            'otp_phone_resend_cooldown_seconds', 'otp_email_resend_cooldown_seconds',
            'otp_phone_max_resends', 'otp_email_max_resends',
            'otp_phone_delivery_mode', 'otp_email_delivery_mode',
            'otp_email_template', 'otp_email_from_provider_id',
        ];
        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : trim((string)$value);
            $this->pdo->prepare(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = ?, updated_at = datetime("now")'
            )->execute([$key, $val, $val]);
        }

        $desc = 'تحديث إعدادات المصادقة: ' . json_encode(array_intersect_key($data, array_flip($allowed)), JSON_UNESCAPED_UNICODE);
        logAdminAudit($admin, 'AUTH_SETTINGS_UPDATED', 'app_settings', 0, $desc);
        $this->out(['updated' => true], 200, 'تم حفظ إعدادات المصادقة والتسجيل');
    }

    // ------------------------------------------------------------------
    // Email providers CRUD
    // ------------------------------------------------------------------

    public function providersIndex(): void
    {
        $this->authenticateAdmin('email.providers.view');
        $this->out(['providers' => (new EmailProviderManager())->list()]);
    }

    public function providersCreate(): void
    {
        $admin = $this->authenticateAdmin('email.providers.create');
        $data = $this->json();
        if (trim((string)($data['name'] ?? '')) === '') {
            $this->out(['success' => false, 'error_code' => 'VALIDATION_ERROR'], 400, 'اسم المزود مطلوب');
        }
        if (!in_array($data['type'] ?? '', ['smtp', 'http_rest'], true)) {
            $this->out(['success' => false, 'error_code' => 'VALIDATION_ERROR'], 400, 'نوع المزود يجب أن يكون smtp أو http_rest');
        }

        $mgr = new EmailProviderManager();
        try {
            $res = $mgr->create($data);
        } catch (Throwable $e) {
            $msg = ($_ENV['APP_ENV'] ?? 'production') === 'development' ? 'خطأ داخلي: ' . $e->getMessage() : 'حدث خطأ داخلي في الخادم';
            $this->out(['success' => false, 'error_code' => 'INTERNAL_ERROR'], 500, $msg);
        }
        logAdminAudit($admin, 'EMAIL_PROVIDER_CREATED', 'email_providers', (int)$res['id'],
            'إضافة مزود بريد: ' . $data['name'] . ' (' . $data['type'] . ')');
        $this->out(['success' => true, 'id' => $res['id']], 201, 'تمت إضافة مزود البريد');
    }

    public function providersShow(int $id): void
    {
        $this->authenticateAdmin('email.providers.view');
        $row = (new EmailProviderManager())->get($id);
        if (!$row) $this->out(['success' => false, 'error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود');
        // Mask secrets
        unset($row['password'], $row['api_key']);
        $this->out(['provider' => $row]);
    }

    public function providersUpdate(int $id): void
    {
        $admin = $this->authenticateAdmin('email.providers.update');
        $data = $this->json();
        $ok = (new EmailProviderManager())->update($id, $data);
        if (!$ok) $this->out(['success' => false, 'error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود');
        logAdminAudit($admin, 'EMAIL_PROVIDER_UPDATED', 'email_providers', $id,
            'تعديل مزود بريد #' . $id . ': ' . ($data['name'] ?? ''));
        $this->out(['success' => true], 200, 'تم تحديث مزود البريد');
    }

    public function providersDelete(int $id): void
    {
        $admin = $this->authenticateAdmin('email.providers.delete');
        $ok = (new EmailProviderManager())->delete($id);
        if (!$ok) $this->out(['success' => false, 'error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود أو لا يمكن حذفه');
        logAdminAudit($admin, 'EMAIL_PROVIDER_DELETED', 'email_providers', $id, 'حذف مزود بريد #' . $id);
        $this->out(['success' => true], 200, 'تم حذف مزود البريد');
    }

    public function providersToggle(int $id): void
    {
        $admin = $this->authenticateAdmin('email.providers.update');
        $data = $this->json();
        $status = (string)($data['status'] ?? 'disabled');
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $this->out(['success' => false, 'error_code' => 'VALIDATION_ERROR'], 400, 'الحالة غير صحيحة');
        }
        $ok = (new EmailProviderManager())->toggle($id, $status);
        if (!$ok) $this->out(['success' => false, 'error_code' => 'NOT_FOUND'], 404, 'المزود غير موجود');
        logAdminAudit($admin, 'EMAIL_PROVIDER_' . strtoupper($status), 'email_providers', $id,
            ($status === 'enabled' ? 'تفعيل' : 'إيقاف') . ' مزود بريد #' . $id);
        $this->out(['success' => true, 'status' => $status], 200, 'تم تحديث حالة المزود');
    }

    public function providersTest(int $id): void
    {
        $admin = $this->authenticateAdmin('email.providers.test');
        $data = $this->json();
        $email = trim((string)($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->out(['success' => false, 'error_code' => 'VALIDATION_ERROR'], 400, 'بريد اختبار صالح مطلوب');
        }
        $res = (new EmailProviderManager())->test($id, $email);
        $this->out($res, $res['success'] ? 200 : 400, $res['message'] ?? ($res['success'] ? 'تم الاختبار' : 'فشل الاختبار'));
    }

    // ------------------------------------------------------------------
    // Email registration requests (email OTP pending codes)
    // ------------------------------------------------------------------

    public function registrationsIndex(): void
    {
        $this->authenticateAdmin('registration.view');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? 20)));
        $res = (new EmailOtpService())->getPendingCodes($page, $perPage);
        $this->out(array_merge($res, ['current_page' => $page, 'per_page' => $perPage]));
    }

    public function registrationsGetCode(int $id): void
    {
        $admin = $this->authenticateAdmin('registration.view_otp');
        $res = (new EmailOtpService())->revealManualCode($id);
        if ($res === null) {
            $this->out(['success' => false, 'error_code' => 'OTP_NOT_VIEWABLE'], 404, 'لا يمكن عرض هذا الرمز');
        }
        
        // Update status to manual when viewed
        $this->pdo->prepare("UPDATE email_verification_codes SET status = 'manual', updated_at = datetime('now') WHERE id = ?")->execute([$id]);
        
        logAdminAudit($admin, 'OTP_VIEWED', 'email_verification_codes', $id, 'المدير شاهد رمز OTP بالبريد للتسليم اليدوي');
        $this->out(['success' => true, 'otp_code' => $res['code'], 'expires_at' => $res['expires_at']], 200, 'انسخ هذا الرمز وأرسله للمستخدم');
    }

    public function registrationsVerify(int $id): void
    {
        $admin = $this->authenticateAdmin('registration.verify');
        $res = (new EmailOtpService())->adminVerify($id);
        if ($res === null) {
            $this->out(['success' => false, 'error_code' => 'NOT_FOUND'], 404, 'طلب التحقق غير موجود أو لم يعد قابلًا للتأكيد');
        }
        logAdminAudit($admin, 'OTP_VERIFIED', 'email_verification_codes', $id,
            'تأكيد تحقق بالبريد يدويًا (' . ($res['email'] ?? '') . ')');
        $this->out(['success' => true, 'email' => $res['email']], 200, 'تم تأكيد التحقق يدويًا');
    }

    public function registrationsCancel(int $id): void
    {
        $admin = $this->authenticateAdmin('registration.cancel');
        if (!(new EmailOtpService())->cancel($id)) {
            $this->out(['success' => false, 'error_code' => 'NOT_FOUND'], 404, 'طلب التحقق غير موجود أو لم يعد قابلًا للإلغاء');
        }
        logAdminAudit($admin, 'OTP_CANCELLED', 'email_verification_codes', $id, 'إلغاء طلب تحقق بالبريد');
        $this->out(['success' => true, 'cancelled' => true], 200, 'تم إلغاء الطلب');
    }
}
