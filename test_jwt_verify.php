<?php
// اختبار: هل JwtHelper::verify يقبل توكن admin المولّد من adminApiLogin؟
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/backend/config/app.php';

$tok = $argv[1] ?? '';
if ($tok === '') {
    // نحصل على توكن من الخادم
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode(['email' => 'admin@nova-messenger.com', 'password' => '738155861']),
            'timeout' => 15,
        ],
    ]);
    $resp = file_get_contents('http://localhost:8080/api/v1/admin/otp/login', false, $ctx);
    $tok = json_decode($resp, true)['data']['token'];
}
echo "TOKEN: " . substr($tok, 0, 30) . "...\n";
$p = JwtHelper::verify($tok);
var_dump($p);
