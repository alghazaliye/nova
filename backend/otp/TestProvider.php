<?php
/**
 * NOVA Messenger — Test OTP Provider
 *
 * Used ONLY in development environments.
 * The OTP code is controlled by OTP_TEST_CODE env var and returned in API response (otp_dev).
 * Never enable this provider in production.
 */

declare(strict_types=1);

class TestProvider implements OtpProviderInterface
{
    public function send(string $phone, string $otp, array $config, string $template): OtpSendResult
    {
        // In production APP_ENV, refuse to operate as test provider
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            return OtpSendResult::failure('client', 0, 'مزود الاختبار غير مسموح في بيئة الإنتاج');
        }
        return OtpSendResult::success(200, 'test-mode: otp returned in response (otp_dev)', 0);
    }
}
