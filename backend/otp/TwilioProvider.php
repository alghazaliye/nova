<?php
/**
 * NOVA Messenger — Twilio OTP Provider
 */

declare(strict_types=1);

class TwilioProvider implements OtpProviderInterface
{
    public function send(string $phone, string $otp, array $config, string $template): OtpSendResult
    {
        $accountSid = trim((string)($config['account_sid'] ?? ''));
        $authToken = trim((string)($config['api_secret'] ?? '')); // API secret holds Twilio Auth Token
        $from = trim((string)($config['sender_id'] ?? ''));
        if ($accountSid === '' || $authToken === '') {
            return OtpSendResult::failure('auth', 0, 'بيانات Twilio غير مكتملة (Account SID / Auth Token)');
        }
        if ($from === '') {
            return OtpSendResult::failure('client', 0, 'المُرسل (From) غير محدد لمزود Twilio');
        }

        $message = OtpTemplate::render($template, $phone, $otp, $config);
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['To' => $phone, 'From' => $from, 'Body' => $message]),
            CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ms = (int)((microtime(true) - $start) * 1000);
        $err = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        if ($err) {
            return OtpSendResult::failure('timeout', $httpCode ?: 0, "فشل الاتصال بـ Twilio: {$err}", '', $ms);
        }

        $data = json_decode((string)$body, true) ?? [];
        if ($httpCode >= 200 && $httpCode < 300) {
            $sid = (string)($data['sid'] ?? '');
            return OtpSendResult::success($httpCode, $sid ? "SID: {$sid}" : 'Accepted', $ms);
        }

        $messageErr = (string)($data['message'] ?? '');
        $code = (string)($data['code'] ?? '');
        if ($httpCode === 401 || $httpCode === 403) {
            return OtpSendResult::failure('auth', $httpCode, "Twilio {$code}: {$messageErr}", $code ? "Code {$code}" : '', $ms);
        }
        if ($httpCode === 429) {
            return OtpSendResult::failure('rate', $httpCode, "Twilio {$code}: {$messageErr}", '', $ms);
        }
        if ($httpCode >= 500) {
            return OtpSendResult::failure('server', $httpCode, "Twilio {$code}: {$messageErr}", '', $ms);
        }
        return OtpSendResult::failure('client', $httpCode, "Twilio {$code}: {$messageErr}", $code ? "Code {$code}" : '', $ms);
    }
}
