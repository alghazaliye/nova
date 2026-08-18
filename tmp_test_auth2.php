<?php
// دورة كاملة register-email -> verify-email-otp عبر الخادم المحلي 8080
ini_set('display_errors', '1');
error_reporting(E_ALL);
$BASE = 'http://localhost:8080/api/v1';

global $BASE;
function call(string $path, array $json) {
    global $BASE;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($json),
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents("$BASE$path", false, $ctx);
    return [$GLOBALS['hdr'][0] ?? 'N/A', $out];
}
$GLOBALS['hdr'] = $http_response_header ?? [];
// إعادة التقاط header بعد كل طلب:
function call2(string $path, array $json) {
    global $BASE;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($json),
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents("$BASE$path", false, $ctx);
    global $lastHdr;
    $lastHdr = $http_response_header ?? [];
    return [$lastHdr[0] ?? 'N/A', $out];
}
function j(?string $s) {
    try { return json_decode($s, true); } catch (Throwable $e) { return ['raw' => $s]; }
}

// 1. register
[$h, $body] = call2('/auth/register-email', [
    'email' => 'qt2@example.com', 'name' => 'اختبار كيو تي 2', 'phone' => '',
    'device_uuid' => 'uqt2', 'app_version' => '3.6.0', 'platform' => 'web',
    'os_name' => 'Web', 'os_version' => 'browser']);
echo "REG: $h -> " . substr($body, 0, 150) . "\n";

// 2. verify
[$h, $body] = call2('/auth/verify-email-otp', ['email' => 'qt2@example.com', 'otp' => '123456']);
echo "VERIFY: $h -> " . substr($body, 0, 300) . "\n";
$data = j($body);
$token = $data['data']['token'] ?? null;
if ($token) {
    // 3. set-password
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer $token\r\n",
        'content' => json_encode(['new_password' => 'password123']),
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents("$BASE/auth/set-password", false, $ctx);
    echo "SET-PW: " . substr($out, 0, 150) . "\n";

    // 4. login-email
    [$h, $body] = call2('/auth/login-email', [
        'email' => 'qt2@example.com', 'password' => 'password123', 'device_uuid' => 'uqt3',
        'app_version' => '3.6.0', 'platform' => 'web', 'os_name' => 'Web', 'os_version' => 'browser']);
    echo "LOGIN-EMAIL: $h -> " . substr($body, 0, 200) . "\n";

    // 5. login-username
    [$h, $body] = call2('/auth/login-username', [
        'username' => 'qt2user', 'password' => 'password123', 'device_uuid' => 'uqt4',
        'app_version' => '3.6.0', 'platform' => 'web', 'os_name' => 'Web', 'os_version' => 'browser']);
    echo "LOGIN-USERNAME: $h -> " . substr($body, 0, 200) . "\n";

    // 6. تسجيل الخروج
    call2('/auth/logout', []);

    // 7. اختبار منع التسجيل عند إيقافه
    // (ننفذه بعد ضبط الإعدادات في shell خارجي)
}
