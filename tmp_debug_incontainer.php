<?php
/**
 * Debug script to run inside the container.
 * Mocks a verify-email-otp POST request and captures the full error output.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Capture all output and shutdown errors
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n\nFATAL: {$e['message']} in {$e['file']}:{$e['line']}\n";
    }
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/verify-email-otp';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo "\nWARNING[$errno]: $errstr in $errfile:$errline\n";
    return true;
});

// Route execution: replicate the route from index.php
require_once '/var/www/html/config/app.php';
require_once '/var/www/html/controllers/EmailAuthController.php';

$email = 'docktest@example.com';
$otp = '123456';

$service = new EmailOtpService();
$res = $service->verifyCode($email, $otp);
echo "VERIFY: " . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";

if (!($res['verified'] ?? false)) {
    echo "NOT VERIFIED — stopping.\n";
    exit;
}

// Now replicate the rest of verifyEmailOtp
$pdo = \Database::getInstance();
$stmt = $pdo->prepare('SELECT id, uuid, name, is_blocked FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "USER: " . json_encode($user) . "\n";

// Session creation
$body = ['device_uuid' => 'd80', 'fcm_token' => null];
$emailAuth = new EmailLoginController();
$ref = new ReflectionMethod($emailAuth, 'createSession');
$ref->setAccessible(true);
try {
    $token = $ref->invoke($emailAuth, (int)$user['id'], $body['device_uuid'], $body['fcm_token']);
    echo "SESSION OK, token len=" . strlen($token) . "\n";
} catch (Throwable $e) {
    echo "SESSION ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    $e2 = $e->getPrevious();
    if ($e2) echo "  caused by: " . $e2->getMessage() . " at " . $e2->getFile() . ":" . $e2->getLine() . "\n";
    exit;
}

// getUserById
$uc = new UserController();
$ref = new ReflectionMethod($uc, 'getUserById');
$ref->setAccessible(true);
try {
    $ud = $ref->invoke($uc, (int)$user['id']);
    echo "getUserById OK\n";
} catch (Throwable $e) {
    echo "getUserById ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit;
}
