<?php
/**
 * NOVA Messenger — Vonage (Nexmo) OTP Provider
 */

declare(strict_types=1);

class VonageProvider implements OtpProviderInterface
{
    public function send(string $phone, string $otp, array $config, string $template): OtpSendResult
    {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        $apiSecret = trim((string)($config['api_secret'] ?? ''));
        $from = trim((string)($config['sender_id'] ?? ''));
        if ($apiKey === '' || $apiSecret === '') {
            return OtpSendResult::failure('auth', 0, 'بيانات Vonage غير مكتملة (API Key / API Secret)');
        }
        if ($from === '') {
            $from = 'NOVA';
        }

        $message = OtpTemplate::render($template, $phone, $otp, $config);
        $url = 'https://rest.nexmo.com/sms/json';

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'api_key' => $apiKey, 'api_secret' => $apiSecret,
                'to' => $phone, 'from' => $from, 'text' => $message, 'type' => 'unicode',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ms = (int)((microtime(true) - $start) * 1000);
        $err = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        if ($err) {
            return OtpSendResult::failure('timeout', $httpCode ?: 0, "فشل الاتصال بـ Vonage: {$err}", '', $ms);
        }

        $data = json_decode((string)$body, true) ?? [];
        $msg = (array)($data['messages'][0] ?? ['status' => '-1', 'error-text' => '']);
        $status = (string)($msg['status'] ?? '-1');

        if ($httpCode >= 200 && $httpCode < 300 && $status === '0') {
            $mId = (string)($msg['message-id'] ?? '');
            return OtpSendResult::success($httpCode, $mId ? "Message-ID: {$mId}" : 'Accepted', $ms);
        }

        $errText = (string)($msg['error-text'] ?? '');
        if ($httpCode === 401 || $httpCode === 403 || $status === '9') {
            return OtpSendResult::failure('auth', $httpCode ?: 401, "Vonage: {$errText}", "status {$status}", $ms);
        }
        if ($httpCode === 429 || $status === '6') {
            return OtpSendResult::failure('rate', $httpCode ?: 429, "Vonage: {$errText}", '', $ms);
        }
        if ($httpCode >= 500 || in_array($status, ['3', '5'], true)) {
            return OtpSendResult::failure('server', $httpCode ?: 500, "Vonage: {$errText}", '', $ms);
        }
        return OtpSendResult::failure('client', $httpCode ?: 400, "Vonage: {$errText}", "status {$status}", $ms);
    }
}
