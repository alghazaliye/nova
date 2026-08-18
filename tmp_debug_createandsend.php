<?php
/** Inside-container debug: run createAndSend and show exceptions. */
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once '/var/www/html/config/app.php';
require_once '/var/www/html/otp/EmailOtpService.php';

set_exception_handler(function ($e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n in " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "CAUSED BY: " . $e->getPrevious()->getMessage() . "\n in " . $e->getPrevious()->getFile() . ":" . $e->getPrevious()->getLine() . "\n";
    }
});

$email = 'docktest@example.com';
// clear old
$pdo = Database::getInstance();
$pdo->exec("DELETE FROM email_verification_codes WHERE email='$email'");

$svc = new EmailOtpService();
$res = $svc->createAndSend($email, 'اختبار3', 'registration', '127.0.0.1', 'debug');
echo "RESULT: " . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";

$stmt = $pdo->query("SELECT id, email, status, delivery_mode, code_hash IS NOT NULL has_hash, manual_code_hash IS NOT NULL has_manual FROM email_verification_codes WHERE email='$email' ORDER BY id DESC LIMIT 3");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ROW: " . json_encode($r) . "\n";
}
