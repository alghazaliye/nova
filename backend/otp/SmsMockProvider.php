<?php
/**
 * NOVA Messenger — SMS Mock OTP Provider (وضع التجربة)
 *
 * مزود داخلي لا يرسل رسالة نصية فعلية. يُسجِّل الرمز في جدول otp_verifications
 * (manual_code_hash) ويُتِيح للمدير عرضه من لوحة التحكم (صفحة طلبات التسجيل
 * → زر "عرض الرمز") أو عبر زر النسخ في التطبيق لاحقًا.
 *
 * يعمل في جميع البيئات لأن غرضه الأساسي هو التجربة اليدوية.
 * يُرجع success دائمًا حتى لا يتحول الطلب إلى وضع "manual" المزدوج.
 */
declare(strict_types=1);

class SmsMockProvider implements OtpProviderInterface
{
    public function send(string $phone, string $otp, array $config, string $template): OtpSendResult
    {
        // الرمز يُتاح للمدير من لوحة التحكم (registration.view_otp) مع تسجيل تدقيق.
        $summary = sprintf(
            'sms-mock: تم الاحتفاظ بالرمز للتسليم اليدوي (phone=%s) — يُعرض من صفحة "طلبات التسجيل" في لوحة التحكم',
            $phone
        );
        return OtpSendResult::success(200, $summary, 0);
    }
}
