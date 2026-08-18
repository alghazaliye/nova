<?php
/**
 * NOVA Messenger — Generic HTTP REST SMS Provider
 *
 * Works with any SMS gateway that exposes a JSON REST endpoint.
 * Configuration (extra_config JSON) supports:
 *  - method          : POST|GET (default POST)
 *  - content_type    : application/json (default) | form
 *  - auth_type       : bearer|basic|header|query|none
 *  - phone_field     : default "phone"
 *  - otp_field       : default "code"
 *  - message_field   : default "message"
 *  - to_field        : default "to"
 *  - template_mode   : "code_only" (send OTP only) | "full_message" (render template, placeholders replaced)
 *  - success_expr    : JS-like check on JSON response — simplified:
 *                      "json.status=OK"  → $data['status'] == 'OK'
 *                      "json.messages[0].status=0"
 */

declare(strict_types=1);

class HttpSmsProvider implements OtpProviderInterface
{
    public function send(string $phone, string $otp, array $config, string $template): OtpSendResult
    {
        $baseUrl = rtrim(trim((string)($config['api_base_url'] ?? '')), '/');
        if ($baseUrl === '') {
            return OtpSendResult::failure('auth', 0, 'رابط API لمزود HTTP غير محدد');
        }
        $apiKey = trim((string)($config['api_key'] ?? ''));
        $apiSecret = trim((string)($config['api_secret'] ?? ''));

        $method = strtoupper((string)($config['method'] ?? 'POST'));
        $contentType = (string)($config['content_type'] ?? 'application/json');
        $authType = (string)($config['auth_type'] ?? 'bearer');
        $phoneField = (string)($config['phone_field'] ?: 'phone');
        $toField = (string)($config['to_field'] ?: 'to');
        $messageField = (string)($config['message_field'] ?: 'message');
        $otpField = (string)($config['otp_field'] ?: 'code');
        $templateMode = (string)($config['template_mode'] ?: 'full_message');
        $successExpr = trim((string)($config['success_expr'] ?? ''));

        $message = ($templateMode === 'code_only') ? $otp : OtpTemplate::render($template, $phone, $otp, $config);

        $url = $baseUrl;
        $headers = [];
        $payload = null;

        if (str_contains($url, '?')) {
            // query params embedded in URL
        }

        $params = [];
        $params[$toField] = $phone;
        if ($phoneField !== $toField) {
            $params[$phoneField] = $phone;
        }
        $params[$messageField] = $message;
        $params[$otpField] = $otp;
        if (isset($config['sender_id']) && trim((string)$config['sender_id']) !== '') {
            $params['sender'] = $config['sender_id'];
        }

        $start = microtime(true);
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ($method === 'GET') {
            $opts[CURLOPT_URL] = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            if ($authType === 'basic' && $apiKey !== '') {
                $opts[CURLOPT_USERPWD] = "{$apiKey}:{$apiSecret}";
                $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            }
        } else {
            $opts[CURLOPT_POST] = true;
            if ($contentType === 'form') {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                $opts[CURLOPT_POSTFIELDS] = json_encode($params, JSON_UNESCAPED_UNICODE);
                $headers[] = 'Content-Type: application/json; charset=utf-8';
            }
            $headers[] = 'Accept: application/json';
            switch ($authType) {
                case 'bearer':
                    if ($apiKey !== '') $headers[] = "Authorization: Bearer {$apiKey}";
                    break;
                case 'basic':
                    $opts[CURLOPT_USERPWD] = "{$apiKey}:{$apiSecret}";
                    $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
                    break;
                case 'header':
                    if ($apiKey !== '') $headers[] = "X-API-Key: {$apiKey}";
                    if ($apiSecret !== '') $headers[] = "X-API-Secret: {$apiSecret}";
                    break;
                case 'query':
                    // already embedded in URL by admin (they append ?key=xxx)
                    break;
                case 'none':
                default:
                    break;
            }
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ms = (int)((microtime(true) - $start) * 1000);
        $err = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        if ($err) {
            return OtpSendResult::failure('timeout', $httpCode ?: 0, "فشل الاتصال بالمزود: {$err}", '', $ms);
        }

        $data = json_decode((string)$body, true);
        $summary = ($httpCode >= 200 && $httpCode < 300) ? 'HTTP ' . $httpCode : '';

        if ($httpCode === 401 || $httpCode === 403) {
            return OtpSendResult::failure('auth', $httpCode, "غير مصرح من المزود (HTTP {$httpCode})", '', $ms);
        }
        if ($httpCode === 429) {
            return OtpSendResult::failure('rate', $httpCode, "حد الاستخدام من المزود (HTTP 429)", '', $ms);
        }
        if ($httpCode >= 500) {
            return OtpSendResult::failure('server', $httpCode, "خطأ خادم من المزود (HTTP {$httpCode})", '', $ms);
        }

        // Custom success expression check
        $isSuccess = $this->checkSuccess($data, $successExpr);
        if ($isSuccess) {
            return OtpSendResult::success($httpCode, $summary, $ms);
        }

        return OtpSendResult::failure('client', $httpCode, "رفض المزود الرسالة (HTTP {$httpCode})", $summary, $ms);
    }

    /**
     * Evaluate a simple success expression like "json.status=OK" or
     * "json.messages[0].status=0". Returns true when no expression configured and HTTP 2xx.
     */
    private function checkSuccess(?array $data, string $expr): bool
    {
        if ($expr === '') return true; // trust HTTP 2xx
        if (!is_array($data)) return false;

        // parse path from expr: remove "json." prefix, split on "="
        $path = $expr;
        $expected = null;
        if (($eqPos = strrpos($path, '=')) !== false) {
            $expected = substr($path, $eqPos + 1);
            $path = substr($path, 0, $eqPos);
        }
        $path = preg_replace('/^json\./', '', $path);
        $segments = preg_split('/[\[\]]+/', trim($path, '[]'));
        $segments = array_values(array_filter($segments, static fn ($s) => $s !== ''));

        $current = $data;
        foreach ($segments as $seg) {
            if (!is_array($current)) return false;
            if (!array_key_exists($seg, $current)) return false;
            $current = $current[$seg];
        }

        if ($expected === null) {
            return (bool)$current;
        }
        if (is_numeric($expected)) {
            return (string)$current === $expected;
        }
        return strtolower((string)$current) === strtolower($expected);
    }
}
