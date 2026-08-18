<?php
// register ثم verify متتاليًا في نفس العملية — طباعة كل الاستجابات
$BASE = 'http://localhost:8082/api/v1';

function call(string $path, array $json, ?string $token = null) {
    global $BASE;
    $hdr = "Content-Type: application/json\r\n";
    if ($token) $hdr .= "Authorization: Bearer $token\r\n";
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => $hdr,
        'content' => json_encode($json),
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents("$BASE$path", false, $ctx);
    echo "=== $path ===\n";
    foreach (($http_response_header ?? []) as $h) {
        if (str_starts_with($h, 'HTTP')) { echo "  $h\n"; }
    }
    echo "  BODY: " . substr($out ?? '', 0, 300) . "\n\n";
    return $out;
}

$out = call('/auth/register-email', [
    'email' => 'docktest@example.com', 'name' => 'اختبار دوكر', 'phone' => '',
    'device_uuid' => 'd99', 'app_version' => '3.6.0', 'platform' => 'web',
    'os_name' => 'Web', 'os_version' => 'browser']);

call('/auth/verify-email-otp', ['email' => 'docktest@example.com', 'otp' => '123456']);
