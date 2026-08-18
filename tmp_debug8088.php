<?php
// يُشغَّل داخل container nova513 — register ثم verify عبر PHP dev server على 8088
$BASE = 'http://127.0.0.1:8088/api/v1';
$EMAIL = 'docktest@example.com';

function call(string $path, array $json) {
    global $BASE;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($json),
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents("$BASE$path", false, $ctx);
    $code = 'N/A';
    foreach (($http_response_header ?? []) as $h) {
        if (str_starts_with($h, 'HTTP')) { $code = $h; }
    }
    echo "=== $path | $code ===\n" . ($out ?? '') . "\n\n";
    return $out;
}

call('/auth/register-email', [
    'email' => $EMAIL, 'name' => 'اختبار دوكر', 'phone' => '',
    'device_uuid' => 'd50', 'app_version' => '3.6.0', 'platform' => 'web',
    'os_name' => 'Web', 'os_version' => 'browser']);

call('/auth/verify-email-otp', ['email' => $EMAIL, 'otp' => '123456']);
