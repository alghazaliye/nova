<?php
// إعادة إنتاج 500 من verify-email-otp مع طباعة الخطأ الكامل
$body = json_encode(['email' => 'docktest@example.com', 'otp' => '123456']);
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $body,
        'ignore_errors' => true,
    ],
];
$ctx = stream_context_create($opts);
$out = @file_get_contents('http://localhost:8082/api/v1/auth/verify-email-otp', false, $ctx);
echo "HEADERS:\n";
foreach (($http_response_header ?? []) as $h) echo "  $h\n";
echo "BODY ($out):\n$out\n";
