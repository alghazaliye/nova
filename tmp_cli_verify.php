<?php
/**
 * يستدعي verify-email-otp handler مباشرة من PHP CLI داخل container
 * بعد محاكاة $_SERVER و php://input — بدون Apache (لا timeout).
 * يُشغَّل عبر: sudo docker cp + docker exec php /tmp/cli_verify.php
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/verify-email-otp';
$_SERVER['HTTP_HOST'] = 'localhost:8080';

// capture output buffer
ob_start();
// fake input
$input = fopen('php://memory', 'r+');
fwrite($input, json_encode(['email' => 'docktest@example.com', 'otp' => '123456']));
rewind($input);

// include router bootstrap — router.php reads php://input
$orig = fopen('php://memory', 'r');
// PHP CLI: php://input is empty; router uses file_get_contents('php://input') once.
// We cannot mock php://input in CLI — so include the controller path directly:
ob_end_clean();

define('IN_TEST', true);

// Bootstrap minimal
require_once '/var/www/html/config/app.php';

// Manually drive the request
$body = ['email' => 'docktest@example.com', 'otp' => '123456'];

$svc = new EmailOtpService();
$svc->ensureTable();
$res = $svc->verifyCode('docktest@example.com', '123456');
echo "VERIFY_RESULT: " . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";

if ($res['verified']) {
    // create session manually
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT id, uuid, name, is_blocked FROM users WHERE email = ? LIMIT 1');
    $stmt->execute(['docktest@example.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "USER: " . json_encode($user) . "\n";

    $uc = new UserController();
    $ref = new ReflectionMethod($uc, 'getUserById');
    $ref->setAccessible(true);
    try {
        $ud = $ref->invoke($uc, (int)$user['id']);
        echo "getUserById OK: " . json_encode($ud, JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Throwable $e) {
        echo "getUserById ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}
