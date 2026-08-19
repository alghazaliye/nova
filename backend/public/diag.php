<?php
/**
 * Temporary diagnostic endpoint (NOVA render debug).
 * Usage: GET /_diag?key=<NOVA_DIAG_KEY>
 * Remove after debugging!
 */
if (($_GET['key'] ?? '') !== ($_ENV['NOVA_DIAG_KEY'] ?? getenv('NOVA_DIAG_KEY') ?? '')) {
    http_response_code(403);
    exit('forbidden');
}

// Load only the classes needed for this diagnostic (mirrors how controllers do it)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../otp/EmailOtpService.php';

header('Content-Type: application/json; charset=utf-8');
$out = [];

// 1) ENV visibility from PHP
$out['env'] = [
    'DB_TYPE'              => (string)getenv('DB_TYPE'),
    'APP_ENV'              => (string)getenv('APP_ENV'),
    'GMAIL_SMTP_USERNAME'  => (string)getenv('GMAIL_SMTP_USERNAME'),
    'GMAIL_SMTP_PASSWORD'  => getenv('GMAIL_SMTP_PASSWORD') !== false ? (strlen((string)getenv('GMAIL_SMTP_PASSWORD')) ? 'SET(len=' . strlen((string)getenv('GMAIL_SMTP_PASSWORD')) . ')' : 'EMPTY') : 'NOT SET',
    'OTP_ENCRYPTION_KEY'   => getenv('OTP_ENCRYPTION_KEY') !== false ? (strlen((string)getenv('OTP_ENCRYPTION_KEY')) ? 'SET(len=' . strlen((string)getenv('OTP_ENCRYPTION_KEY')) . ')' : 'EMPTY') : 'NOT SET',
    'ENCRYPTION_KEY'       => getenv('ENCRYPTION_KEY') !== false ? (strlen((string)getenv('ENCRYPTION_KEY')) ? 'SET(len=' . strlen((string)getenv('ENCRYPTION_KEY')) . ')' : 'EMPTY') : 'NOT SET',
];

// 2) DB state
$dbPath = getenv('DB_PATH') ?: __DIR__ . '/../config/nova.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$out['providers'] = [];
foreach ($pdo->query("SELECT id, name, type, status, priority, host, port, encryption, username, from_email, length(password) AS pw_len FROM email_providers ORDER BY id") as $r) {
    $out['providers'][] = $r;
}

// 3) enabled chain as EmailOtpService sees it
$out['enabled_chain'] = [];
foreach ($pdo->query("SELECT id, name, type, status, priority FROM email_providers WHERE status='enabled' ORDER BY priority ASC, id ASC") as $r) {
    $out['enabled_chain'][] = $r;
}

// 4) try decrypting Gmail provider password
$st = $pdo->prepare("SELECT password FROM email_providers WHERE id=2");
$st->execute();
$enc = $st->fetchColumn();
$dec = '';
try {
    $dec = OtpEncryption::decrypt((string)$enc);
} catch (Throwable $e) {
    $dec = 'DECRYPT_ERROR: ' . $e->getMessage();
}
$out['gmail'] = [
    'encrypted_len'  => strlen((string)$enc),
    'decrypted_len'  => strlen($dec),
    'decrypted_ok'   => ($dec !== '' && $dec !== (string)$enc),
    'decrypted_note' => ($dec === '' ? 'EMPTY (auth will fail)' : (strlen($dec) <= 8 ? substr($dec, 0, 4) . '...' : 'len=' . strlen($dec))),
];

// 5) quick live SMTP send test to mahumad7733@gmail.com
$svcClass = null;
$testSend = null;
if (class_exists('EmailOtpService')) {
    try {
        $svc = new EmailOtpService();
        $res = $svc->createAndSend('mahumad7733@gmail.com', 'diag', 'test', '127.0.0.1', 'diag');
        $testSend = $res;
    } catch (Throwable $e) {
        $testSend = ['error' => $e->getMessage()];
    }
}
$out['live_send_test'] = $testSend;

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
