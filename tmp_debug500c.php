<?php
// cleanup ثم register ثم verify متتاليًا
$BASE = 'http://localhost:8082/api/v1';
$EMAIL = 'docktest@example.com';

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
    $code = 'N/A';
    foreach (($http_response_header ?? []) as $h) {
        if (str_starts_with($h, 'HTTP')) { $code = trim($h); }
    }
    echo "=== $path | $code ===\n" . substr($out ?? '', 0, 350) . "\n\n";
    return $out;
}

// حذف المستخدم والرموز
sub("DELETE FROM email_verification_codes WHERE email='$EMAIL'; DELETE FROM users WHERE email='$EMAIL';");

// تفعيل الإعدادات
sub("UPDATE app_settings SET setting_value='1' WHERE setting_key IN ('auth_email_registration','otp_email_enabled','auth_email_login','auth_username_login');");

$out = call('/auth/register-email', [
    'email' => $EMAIL, 'name' => 'اختبار دوكر', 'phone' => '',
    'device_uuid' => 'd99', 'app_version' => '3.6.0', 'platform' => 'web',
    'os_name' => 'Web', 'os_version' => 'browser']);

$v = call('/auth/verify-email-otp', ['email' => $EMAIL, 'otp' => '123456']);
$jd = json_decode($v, true);
$token = $jd['data']['token'] ?? null;
echo "TOKEN=" . ($token ? substr($token,0,30)."..." : "NONE") . "\n";
if ($token) {
    call('/auth/set-password', ['new_password' => 'password123'], $token);
    call('/auth/login-email', ['email' => $EMAIL, 'password' => 'password123',
        'device_uuid' => 'd100', 'app_version' => '3.6.0', 'platform' => 'web', 'os_name' => 'Web', 'os_version' => 'browser']);
    $pw = trim(shell_exec('php -r \'echo password_hash("password123", PASSWORD_BCRYPT);\''));
    $uid = $jd['data']['user']['id'] ?? null;
    if ($uid) {
        sub("UPDATE users SET username='dockuser', password_hash='$pw' WHERE id=$uid;");
        sleep(1);
        call('/auth/login-username', ['username' => 'dockuser', 'password' => 'password123',
            'device_uuid' => 'd101', 'app_version' => '3.6.0', 'platform' => 'web', 'os_name' => 'Web', 'os_version' => 'browser']);
    }
    call('/auth/logout', [], $token);
}
echo "DONE\n";

function sub(string $sql) {
    $out = shell_exec("sudo docker exec nova513 mysql -h127.0.0.1 -unova_user -pnova2026 nova -e " . escapeshellarg($sql) . " 2>&1");
    echo "DB: " . trim($out) . "\n";
}
