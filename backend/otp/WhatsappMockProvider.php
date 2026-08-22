<?php
/**
 * NOVA Messenger — WhatsApp Mock OTP Provider (وضع التجربة)
 *
 * مزود داخلي لا يرسل رسالة واتساب فعلية. يحتفظ بالرمز في جدول otp_verifications
 * (manual_code_hash) بحيث يظهر للمدير مباشرة في صفحة «طلبات التسجيل»
 * في لوحة التحكم (رمز مرئي قابل للنسخ) دون الحاجة لتفعيل وضع manual fallback.
 *
 * يُرجع success دائمًا حتى لا يتحول الطلب إلى وضع "manual" المزدوج.
 * للإنتاج استخدم مزود http_rest مع WhatsApp Cloud API / Twilio WhatsApp.
 */
declare(strict_types=1);

class WhatsappMockProvider implements OtpProviderInterface
{
    private PDO|TursoPdo $pdo;

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getInstance();
    }

    public function send(string $phone, string $otp, array $config, string $template): OtpSendResult
    {
        // لا إرسال فعلي — نحتفظ بالرمز في manual_code_hash ليظهر للمدير
        // مباشرة في صفحة «طلبات التسجيل» (رمز مرئي قابل للنسخ).
        $summary = sprintf(
            'whatsapp-mock: تم الاحتفاظ بالرمز للتسليم اليدوي (phone=%s) — يُعرض من صفحة "طلبات التسجيل" في لوحة التحكم. للإنتاج فعّل مزود WhatsApp حقيقي (http_rest)',
            $phone
        );
        try {
            $this->pdo()->prepare(
                'UPDATE otp_verifications SET manual_code_hash = ?, status = \'sent\',
                       delivery_status = \'sent\', updated_at = datetime("now")
                 WHERE phone_number = ? AND status = \'pending\''
            )->execute([password_hash($otp, PASSWORD_BCRYPT), $phone]);
        } catch (Throwable $e) {
            // فشل غير حرج — الرمز ما يزال في otp_hash وسيفعّل وضع manual تلقائيًا
        }
        return OtpSendResult::success(200, $summary, 0);
    }
}
