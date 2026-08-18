<?php
/**
 * NOVA Messenger — OTP Provider Interface
 */

declare(strict_types=1);

/**
 * Result of a single provider delivery attempt.
 */
final class OtpSendResult
{
    public bool $success;
    public int $httpCode = 0;
    public string $errorMessage = '';
    public string $responseSummary = '';
    public int $responseTimeMs = 0;

    /**
     * Error class for fallback logic:
     * - 'auth'      : 401/403 — bad credentials (log + move on)
     * - 'rate'      : 429 — rate limited (move on if fallback available)
     * - 'server'    : 5xx — server error (move on)
     * - 'timeout'   : request timed out (move on)
     * - 'client'    : 4xx (non-auth) — provider rejected input (do NOT fall back; fail)
     * - 'success'   : delivered
     */
    public string $errorClass = 'success';

    public static function success(int $httpCode, string $summary, int $ms): self
    {
        $r = new self();
        $r->success = true;
        $r->httpCode = $httpCode;
        $r->responseSummary = $summary;
        $r->responseTimeMs = $ms;
        return $r;
    }

    public static function failure(string $errorClass, int $httpCode, string $message, string $summary = '', int $ms = 0): self
    {
        $r = new self();
        $r->success = false;
        $r->errorClass = $errorClass;
        $r->httpCode = $httpCode;
        $r->errorMessage = $message;
        $r->responseSummary = $summary;
        $r->responseTimeMs = $ms;
        return $r;
    }
}

/**
 * Every OTP provider must implement this interface.
 * NEVER log or return the decrypted credentials outside this layer.
 */
interface OtpProviderInterface
{
    /**
     * Send an OTP code to a phone number.
     *
     * @param string $phone full E.164 phone (+966...)
     * @param string $otp   the plain 6-digit code
     * @param array  $config provider configuration (decrypted keys + settings)
     * @param string $template message template with {OTP},{PHONE},{MINUTES},{APP_NAME}
     * @return OtpSendResult
     */
    public function send(string $phone, string $otp, array $config, string $template): OtpSendResult;
}
