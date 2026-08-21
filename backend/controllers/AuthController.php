<?php
declare(strict_types=1);

class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function login(): void
    {
        $body = json_decode(file_get_contents("php://input"), true) ?? [];
        $phone = $body["phone"] ?? "";

        if (empty($phone)) {
            Response::error("رقم الهاتف مطلوب", "MISSING_PHONE", 400);
        }

        // في بيئة التطوير، نسمح بتسجيل الدخول مباشرة
        $otpService = new OtpService();
        $res = $otpService->sendOtp($phone, "phone");

        if ($res["success"]) {
            Response::success([
                "message" => "تم إرسال رمز التحقق",
                "otp_debug" => $res["otp"] ?? null,
                "expires_at" => $res["expires_at"] ?? null
            ]);
        } else {
            Response::error($res["message"] ?? "فشل إرسال الرمز", "OTP_FAILED", 400);
        }
    }

    public function verifyOtp(): void
    {
        $body = json_decode(file_get_contents("php://input"), true) ?? [];
        $phone = $body["phone"] ?? "";
        $otp = $body["otp"] ?? "";

        if (empty($phone) || empty($otp)) {
            Response::error("الرقم والرمز مطلوبان", "MISSING_FIELDS", 400);
        }

        $otpService = new OtpService();
        if ($otpService->verifyOtp($phone, $otp, "phone")) {
            // البحث عن المستخدم أو إنشاؤه
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if (!$user) {
                $uuid = UuidHelper::generate();
                $this->pdo->prepare("INSERT INTO users (uuid, phone, created_at) VALUES (?, ?, datetime(\"now\"))")
                          ->execute([$uuid, $phone]);
                $userId = (int)$this->pdo->lastInsertId();
                
                // إعدادات الخصوصية الافتراضية
                $this->pdo->prepare("INSERT INTO privacy_settings (user_id) VALUES (?)")->execute([$userId]);
                
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            }

            $token = JwtHelper::generate(["user_id" => $user["id"]]);
            
            // تسجيل الجلسة
            $this->pdo->prepare("INSERT INTO sessions (user_id, token, created_at) VALUES (?, ?, datetime(\"now\"))")
                      ->execute([$user["id"], $token]);

            Response::success([
                "token" => $token,
                "user" => $user
            ]);
        } else {
            Response::error("رمز التحقق غير صحيح أو منتهي الصلاحية", "INVALID_OTP", 400);
        }
    }
}
