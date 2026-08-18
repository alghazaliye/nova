<?php
// تشغيل verify-email-otp محليًا على الخادم 8080 مع عرض الأخطاء الكاملة
ini_set('display_errors', '1');
error_reporting(E_ALL);

$body = json_encode(['email' => 'qt@example.com', 'otp' => '123456']);
$ctx = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\n",
    'content' => $body,
    'ignore_errors' => true,
]]);
$out = @file_get_contents('http://localhost:8080/api/v1/auth/verify-email-otp', false, $ctx);
$http = $http_response_header[0] ?? 'N/A';
echo "HTTP: $http\n";
echo "BODY: $out\n";
