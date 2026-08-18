<?php
/**
 * تشغيل verify-email-otp مباشرة داخل container عبر طلب HTTP داخلي مع التقاط الأخطاء.
 * يُشغَّل على الخادم المحلي ضد container 8082 باستخدام تسجيل كامل.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

function req(string $url, array $json, string &$codeOut): string {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($json),
        'ignore_errors' => true,
        'timeout' => 15,
    ]]);
    $out = @file_get_contents($url, false, $ctx);
    $codeOut = 'N/A';
    foreach (($http_response_header ?? []) as $h) {
        if (str_starts_with($h, 'HTTP')) { $codeOut = trim($h); }
    }
    return $out ?? '';
}

$BASE = 'http://localhost:8082/api/v1';
$code = '';
echo "=== register-email ===\n";
$r = req("$BASE/auth/register-email", [
    'email' => 'docktest@example.com', 'name' => 'اختبار', 'phone' => '',
    'device_uuid' => 'd80', 'app_version' => '3.6.0', 'platform' => 'web',
    'os_name' => 'Web', 'os_version' => 'browser'], $code);
echo "$code\n$r\n\n";

sleep(1);

echo "=== verify-email-otp ===\n";
$r = req("$BASE/auth/verify-email-otp", [
    'email' => 'docktest@example.com', 'otp' => '123456'], $code);
echo "$code\n$r\n\n";

if ($code === 'N/A' || $code === 'HTTP/1.0 500 Internal Server Error') {
    echo "ERROR_INFO: " . print_r(error_get_last(), true) . "\n";
}
