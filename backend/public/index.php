<?php
/**
 * NOVA Messenger - API Entry Point
 * All requests are routed through this file.
 * Configure your web server to point to this directory.
 */

declare(strict_types=1);

// Production-safe global exception handler: convert uncaught exceptions to JSON 500
if (PHP_SAPI !== 'cli') {
    set_exception_handler(function (\Throwable $e): void {
        error_log('[nova error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
        $env = $_ENV['APP_ENV'] ?? 'production';
        $msg = ($env === 'development') ? $e->getMessage() : 'خطأ داخلي في الخادم';
        $payload = ['success'=>false,'message'=>$msg,'error_code'=>'INTERNAL_ERROR'];
        echo json_encode($payload,JSON_UNESCAPED_UNICODE);
    });
}

// Bootstrap
require_once __DIR__ . '/../config/app.php';

// Load controllers
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/ConversationController.php';
require_once __DIR__ . '/../controllers/MessageController.php';
require_once __DIR__ . '/../controllers/StoryController.php';
require_once __DIR__ . '/../controllers/CallController.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/AdminOtpController.php';
require_once __DIR__ . '/../controllers/AdminAuthController.php';
require_once __DIR__ . '/../controllers/EmailAuthController.php';
require_once __DIR__ . '/../controllers/DeviceController.php';
require_once __DIR__ . '/../controllers/GroupsController.php';


// ════════════════════════════════════════════════════════════════════
// Serve uploaded media files (video/audio/image) with CORS + Range support
// Matches: GET /media/{path...} and GET /nova/backend/storage/{path...}
// ════════════════════════════════════════════════════════════════════
// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Normalize both a virtual-host URL (/api/v1/...) and an XAMPP subfolder URL
// (/nova-messenger/backend/public/api/v1/...).
$basePath = '/api/v1';
$basePosition = strpos($uri, $basePath);
if ($basePosition !== false) {
    $uri = substr($uri, $basePosition + strlen($basePath));
}
$uri = '/' . ltrim($uri, '/');

$mediaMatch = null;
if (preg_match('#^/media/(.+)$#', $uri, $mediaMatch) || preg_match('#^/nova/backend/storage/(.+)$#', $uri, $mediaMatch) || preg_match('#^/storage/(.+)$#', $uri, $mediaMatch) || preg_match('#^/attachments/.*\.(mp4|mov|webm|mp3|wav|ogg|weba|m4a|aac|jpe?g|png|gif|webp)$#i', $uri, $mediaMatch) || preg_match('#^/voices/.*\.(mp3|wav|ogg|weba|m4a|aac|webm)$#i', $uri, $mediaMatch)) {
    $rel = trim($mediaMatch[1]);
    // Security: no directory traversal
    if (strpos($rel, '..') !== false || preg_match('#^\.\.$#', $rel)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'مسار غير صالح', 'error_code' => 'INVALID_PATH']);
        exit;
    }
    $allowedExts = ['mp4','mov','webm','mp3','wav','ogg','weba','m4a','aac','jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'نوع ملف غير مسموح', 'error_code' => 'INVALID_EXT']);
        exit;
    }
    $storageBase = $_ENV['STORAGE_PATH'] ?? dirname(__DIR__) . '/storage';
    $file = rtrim($storageBase, '/') . '/' . $rel;
    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'الملف غير موجود', 'error_code' => 'NOT_FOUND']);
        exit;
    }
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Range, Content-Type');
    header('Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges');
    $mimeTypes = [
        'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg',
        'weba' => 'audio/webm', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp',
    ];
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=31536000, immutable');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    $size = filesize($file);
    header('Content-Length: ' . $size);
    $range = $_SERVER['HTTP_RANGE'] ?? null;
    if ($range && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $rm)) {
        $start = $rm[1] !== '' ? (int)$rm[1] : 0;
        $end   = $rm[2] !== '' ? (int)$rm[2] : $size - 1;
        if ($start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */{$size}");
            exit;
        }
        if ($end >= $size) $end = $size - 1;
        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$size}");
        header('Content-Length: ' . ($end - $start + 1));
        $fh = fopen($file, 'rb');
        fseek($fh, $start);
        $remaining = $end - $start + 1;
        $chunk = 8192;
        while ($remaining > 0 && !feof($fh)) {
            $len = min($chunk, $remaining);
            $data = fread($fh, $len);
            echo $data;
            $remaining -= strlen($data);
        }
        fclose($fh);
        exit;
    }
    readfile($file);
    exit;
}



// =====================================================
// Router
// =====================================================

// TEMPORARY diagnostic endpoint — remove after debugging
require_once __DIR__ . '/../helpers/OtpEncryption.php';
require_once __DIR__ . '/../otp/EmailOtpService.php';

if ($uri === '/_diag' && $method === 'GET') {
    $diagKey = (string)($_ENV['NOVA_DIAG_KEY'] ?? getenv('NOVA_DIAG_KEY') ?? '');
    if (($_GET['key'] ?? '') !== $diagKey || $diagKey === '') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'forbidden', 'error_code' => 'FORBIDDEN']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $diagOut = [];
    $diagOut['env'] = [
        'DB_TYPE'             => (string)getenv('DB_TYPE'),
        'APP_ENV'             => (string)getenv('APP_ENV'),
        'GMAIL_SMTP_USERNAME' => (string)getenv('GMAIL_SMTP_USERNAME'),
        'GMAIL_SMTP_PASSWORD' => getenv('GMAIL_SMTP_PASSWORD') !== false ? (strlen((string)getenv('GMAIL_SMTP_PASSWORD')) ? 'SET(len=' . strlen((string)getenv('GMAIL_SMTP_PASSWORD')) . ')' : 'EMPTY') : 'NOT SET',
        'OTP_ENCRYPTION_KEY'  => getenv('OTP_ENCRYPTION_KEY') !== false ? (strlen((string)getenv('OTP_ENCRYPTION_KEY')) ? 'SET(len=' . strlen((string)getenv('OTP_ENCRYPTION_KEY')) . ')' : 'EMPTY') : 'NOT SET',
        'ENCRYPTION_KEY'      => getenv('ENCRYPTION_KEY') !== false ? (strlen((string)getenv('ENCRYPTION_KEY')) ? 'SET(len=' . strlen((string)getenv('ENCRYPTION_KEY')) . ')' : 'EMPTY') : 'NOT SET',
    ];
    $dbPath = getenv('DB_PATH') ?: __DIR__ . '/../config/nova.sqlite';
    $dPdo = new PDO('sqlite:' . $dbPath);
    $dPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $diagOut['providers'] = [];
    foreach ($dPdo->query('SELECT id, name, type, status, priority, host, port, encryption, username, from_email, length(password) AS pw_len FROM email_providers ORDER BY id') as $r) {
        $diagOut['providers'][] = ['id' => (int)$r['id'], 'name' => $r['name'], 'type' => $r['type'], 'status' => $r['status'], 'priority' => (int)$r['priority'], 'host' => $r['host'], 'port' => $r['port'] ? (int)$r['port'] : null, 'encryption' => $r['encryption'], 'username' => $r['username'], 'from_email' => $r['from_email'], 'pw_len' => $r['pw_len'] ? (int)$r['pw_len'] : null];
    }
    $st2 = $dPdo->prepare('SELECT password FROM email_providers WHERE id=1');
    $st2->execute();
    $encPwd = (string)$st2->fetchColumn();
    $decPwd = '';
    try { $decPwd = OtpEncryption::decrypt($encPwd); } catch (Throwable $e) { $decPwd = 'DECRYPT_ERROR: ' . substr($e->getMessage(), 0, 80); }
    $diagOut['gmail'] = [
        'encrypted_len'  => strlen($encPwd),
        'decrypted_len'  => strlen($decPwd),
        'decrypted_ok'   => ($decPwd !== '' && strpos($decPwd, 'DECRYPT_ERROR') !== 0),
        'decrypted_note' => ($decPwd === '' ? 'EMPTY (SMTP auth will fail)' : (strpos($decPwd, 'DECRYPT_ERROR') === 0 ? substr($decPwd, 0, 60) : 'len=' . strlen($decPwd))),
    ];
    try {
        $diagOut['live_send_test'] = (new EmailOtpService())->createAndSend('mahumad7733@gmail.com', 'diag', 'test', '127.0.0.1', 'diag');
    } catch (Throwable $e) {
        $diagOut['live_send_test'] = ['error' => substr($e->getMessage(), 0, 200)];
    }
    echo json_encode($diagOut, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Auth Routes
if ($uri === '/auth/register' && $method === 'POST') {
    (new AuthController())->register();
}
if ($uri === '/auth/login' && $method === 'POST') {
    (new AuthController())->login();
}
if ($uri === '/auth/verify-otp' && $method === 'POST') {
    (new AuthController())->verifyOtp();
}
if ($uri === '/auth/resend-otp' && $method === 'POST') {
    (new AuthController())->resendOtp();
}

// Auth config + Email/Username auth routes
if ($uri === '/auth/config' && $method === 'GET') {
    (new EmailAuthController())->config();
}
if ($uri === '/auth/register-email' && $method === 'POST') {
    (new EmailAuthController())->registerEmail();
}
if ($uri === '/auth/verify-email-otp' && $method === 'POST') {
    (new EmailAuthController())->verifyEmailOtp();
}
if ($uri === '/auth/resend-email-otp' && $method === 'POST') {
    (new EmailAuthController())->resendEmailOtp();
}
if ($uri === '/auth/login-email' && $method === 'POST') {
    (new EmailAuthController())->loginEmail();
}
if ($uri === '/auth/login-username' && $method === 'POST') {
    (new EmailAuthController())->loginUsername();
}
if ($uri === '/auth/set-password' && $method === 'POST') {
    (new EmailAuthController())->setPassword();
}
if ($uri === '/auth/logout' && $method === 'POST') {
    (new AuthController())->logout();
    file_put_contents('/tmp/nova_logout.log', date('H:i:s')." POST logout ".$_SERVER['REMOTE_ADDR']." token=".(substr(getallheaders()['Authorization'] ?? '',7,20))."
", FILE_APPEND);
}
if ($uri === '/auth/me' && $method === 'GET') {
    (new AuthController())->me();
}
if ($uri === '/auth/refresh' && $method === 'POST') {
    (new AuthController())->refresh();
}

// User Routes
if ($uri === '/users/me' && $method === 'GET') {
    (new UserController())->me();
}
if ($uri === '/users/me' && $method === 'PUT') {
    (new UserController())->updateMe();
}
if ($uri === '/users/avatar' && $method === 'POST') {
    (new UserController())->uploadAvatar();
}
if ($uri === '/users/search' && $method === 'GET') {
    (new UserController())->search();
}
if (preg_match('#^/users/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new UserController())->getUser((int)$m[1]);
}
if (preg_match('#^/users/(\d+)/block$#', $uri, $m) && $method === 'POST') {
    (new UserController())->blockUser((int)$m[1]);
}
if (preg_match('#^/users/(\d+)/block$#', $uri, $m) && $method === 'DELETE') {
    (new UserController())->unblockUser((int)$m[1]);
}

// Conversation Routes
if ($uri === '/conversations' && $method === 'GET') {
    (new ConversationController())->index();
}
if ($uri === '/conversations' && $method === 'POST') {
    (new ConversationController())->create();
}
if (preg_match('#^/conversations/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new ConversationController())->show((int)$m[1]);
}
if (preg_match('#^/conversations/(\d+)$#', $uri, $m) && $method === 'PUT') {
    (new ConversationController())->updateDisappearing((int)$m[1]);
}
if (preg_match('#^/conversations/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new ConversationController())->delete((int)$m[1]);
}
if (preg_match('#^/conversations/(\d+)/mute$#', $uri, $m) && $method === 'POST') {
    (new ConversationController())->mute((int)$m[1]);
}
if (preg_match('#^/conversations/(\d+)/pin$#', $uri, $m) && $method === 'POST') {
    (new ConversationController())->pin((int)$m[1]);
}

// Message Routes
if (preg_match('#^/conversations/(\d+)/messages$#', $uri, $m) && $method === 'GET') {
    (new MessageController())->index((int)$m[1]);
}
if (preg_match('#^/conversations/(\d+)/messages$#', $uri, $m) && $method === 'POST') {
    (new MessageController())->send((int)$m[1]);
}
if (preg_match('#^/messages/(\d+)$#', $uri, $m) && $method === 'PUT') {
    (new MessageController())->update((int)$m[1]);
}
if (preg_match('#^/messages/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new MessageController())->delete((int)$m[1]);
}
if (preg_match('#^/messages/(\d+)/read$#', $uri, $m) && $method === 'POST') {
    (new MessageController())->markRead((int)$m[1]);
}
if (preg_match('#^/messages/(\d+)/reaction$#', $uri, $m) && $method === 'POST') {
    (new MessageController())->react((int)$m[1]);
}
if (preg_match('#^/conversations/(\d+)/media$#', $uri, $m) && $method === 'POST') {
    (new MessageController())->uploadMedia((int)$m[1]);
}
if ($uri === '/messages/voice' && $method === 'POST') {
    (new MessageController())->uploadVoice();
}

// Story Routes
if ($uri === '/stories' && $method === 'GET') {
    (new StoryController())->index();
}
if ($uri === '/stories' && $method === 'POST') {
    (new StoryController())->create();
}
if (preg_match('#^/stories/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new StoryController())->show((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/view$#', $uri, $m) && $method === 'POST') {
    (new StoryController())->view((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new StoryController())->delete((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/upload$#', $uri, $m) && $method === 'POST') {
    (new StoryController())->upload((int)$m[1]);
}

// Group Routes
if ($uri === '/groups/mine' && $method === 'GET') {
    (new GroupsController())->mine();
}
if (preg_match('#^/groups/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new GroupsController())->show((int)$m[1]);
}
if (preg_match('#^/groups/(\d+)/members$#', $uri, $m) && $method === 'POST') {
    (new GroupsController())->addMembers((int)$m[1]);
}
if (preg_match('#^/groups/(\d+)/members/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new GroupsController())->removeMember((int)$m[1], (int)$m[2]);
}
if (preg_match('#^/groups/(\d+)/members/(\d+)/role$#', $uri, $m) && $method === 'PUT') {
    (new GroupsController())->setRole((int)$m[1], (int)$m[2]);
}
if (preg_match('#^/groups/(\d+)/settings$#', $uri, $m) && $method === 'PUT') {
    (new GroupsController())->updateSettings((int)$m[1]);
}
if (preg_match('#^/groups/(\d+)/title$#', $uri, $m) && $method === 'PUT') {
    (new GroupsController())->updateTitle((int)$m[1]);
}
if (preg_match('#^/groups/(\d+)/avatar$#', $uri, $m) && $method === 'POST') {
    (new GroupsController())->uploadAvatar((int)$m[1]);
}
if (preg_match('#^/groups/(\d+)/leave$#', $uri, $m) && $method === 'POST') {
    (new GroupsController())->leave((int)$m[1]);
}

// Contact Routes
if ($uri === '/contacts/new' && $method === 'GET') {
    (new UserController())->newContacts();
}
if ($uri === '/contacts' && $method === 'POST') {
    (new UserController())->addContact();
}
if (preg_match('#^/contacts/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new UserController())->removeContact((int)$m[1]);
}

// Call Routes
if ($uri === '/calls' && $method === 'POST') {
    (new CallController())->initiate();
}
if ($uri === '/calls' && $method === 'GET') {
    (new CallController())->index();
}
if ($uri === '/calls/incoming' && $method === 'GET') {
    (new CallController())->incoming();
}
if (preg_match('#^/calls/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new CallController())->show((int)$m[1]);
}
if (preg_match('#^/calls/(\d+)/answer$#', $uri, $m) && $method === 'POST') {
    (new CallController())->answer((int)$m[1]);
}
if (preg_match('#^/calls/(\d+)/reject$#', $uri, $m) && $method === 'POST') {
    (new CallController())->reject((int)$m[1]);
}
if (preg_match('#^/calls/(\d+)/end$#', $uri, $m) && $method === 'POST') {
    (new CallController())->end((int)$m[1]);
}
if (preg_match('#^/calls/(\d+)/signal$#', $uri, $m) && $method === 'POST') {
    (new CallController())->signal((int)$m[1]);
}
if (preg_match('#^/calls/(\d+)/signals$#', $uri, $m) && $method === 'GET') {
    (new CallController())->signals((int)$m[1]);
}

// Notification Routes
if ($uri === '/notifications' && $method === 'GET') {
    (new NotificationController())->index();
}
if (preg_match('#^/notifications/(\d+)/read$#', $uri, $m) && $method === 'POST') {
    (new NotificationController())->markRead((int)$m[1]);
}
if ($uri === '/notifications/read-all' && $method === 'POST') {
    (new NotificationController())->markAllRead();
}

// Admin Routes (admin-level access)
if ($uri === '/admin/plans' && $method === 'GET') {
    (new AdminController())->plansIndex();
}
if ($uri === '/admin/plans' && $method === 'POST') {
    (new AdminController())->plansCreate();
}
if (preg_match('#^/admin/plans/(\d+)$#', $uri, $m) && $method === 'PUT') {
    (new AdminController())->plansUpdate((int)$m[1]);
}
if (preg_match('#^/admin/plans/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new AdminController())->plansDelete((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/verify$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->verifyUser((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/ban$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->banUser((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/unban$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->unbanUser((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/subscribe$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->subscribeUser((int)$m[1]);
}
if (preg_match('#^/admin/subscriptions/(\d+)/cancel$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->cancelSubscription((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/admin$#', $uri, $m) && $method === 'GET') {
    (new AdminController())->userAdmin((int)$m[1]);
}
// OTP Management Routes (Admin — JWT + RBAC protected)
if ($uri === '/admin/otp/login' && $method === 'POST') {
    (new AdminOtpController())->adminApiLogin();
}
if ($uri === '/admin/otp/providers' && $method === 'GET') {
    (new AdminOtpController())->providersIndex();
}
if ($uri === '/admin/otp/providers' && $method === 'POST') {
    (new AdminOtpController())->providersCreate();
}
if (preg_match('#^/admin/otp/providers/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new AdminOtpController())->providersShow((int)$m[1]);
}
if (preg_match('#^/admin/otp/providers/(\d+)$#', $uri, $m) && $method === 'PUT') {
    (new AdminOtpController())->providersUpdate((int)$m[1]);
}
if (preg_match('#^/admin/otp/providers/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new AdminOtpController())->providersDelete((int)$m[1]);
}
if (preg_match('#^/admin/otp/providers/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
    (new AdminOtpController())->providersToggle((int)$m[1]);
}
if (preg_match('#^/admin/otp/providers/(\d+)/test$#', $uri, $m) && $method === 'POST') {
    (new AdminOtpController())->providersTest((int)$m[1]);
}
if ($uri === '/admin/otp/registrations' && $method === 'GET') {
    (new AdminOtpController())->registrationsIndex();
}
if (preg_match('#^/admin/otp/registrations/(\d+)/code$#', $uri, $m) && $method === 'GET') {
    (new AdminOtpController())->registrationsGetCode((int)$m[1]);
}
if (preg_match('#^/admin/otp/registrations/(\d+)/verify$#', $uri, $m) && $method === 'POST') {
    (new AdminOtpController())->registrationsVerify((int)$m[1]);
}
if (preg_match('#^/admin/otp/registrations/(\d+)/cancel$#', $uri, $m) && $method === 'POST') {
    (new AdminOtpController())->registrationsCancel((int)$m[1]);
}
if ($uri === '/admin/otp/stats' && $method === 'GET') {
    (new AdminOtpController())->stats();
}
if ($uri === '/admin/otp/settings' && $method === 'GET') {
    (new AdminOtpController())->settingsGet();
}
if ($uri === '/admin/otp/settings' && $method === 'POST') {
    (new AdminOtpController())->settingsUpdate();
}

// Admin Auth Settings Routes (JWT + RBAC protected)
if ($uri === '/admin/auth/settings' && $method === 'GET') {
    (new AdminAuthController())->settingsGet();
}
if ($uri === '/admin/auth/settings' && $method === 'POST') {
    (new AdminAuthController())->settingsUpdate();
}
if ($uri === '/admin/email-providers' && $method === 'GET') {
    (new AdminAuthController())->providersIndex();
}
if ($uri === '/admin/email-providers' && $method === 'POST') {
    (new AdminAuthController())->providersCreate();
}
if (preg_match('#^/admin/email-providers/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new AdminAuthController())->providersShow((int)$m[1]);
}
if (preg_match('#^/admin/email-providers/(\d+)$#', $uri, $m) && $method === 'PUT') {
    (new AdminAuthController())->providersUpdate((int)$m[1]);
}
if (preg_match('#^/admin/email-providers/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new AdminAuthController())->providersDelete((int)$m[1]);
}
if (preg_match('#^/admin/email-providers/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
    (new AdminAuthController())->providersToggle((int)$m[1]);
}
if (preg_match('#^/admin/email-providers/(\d+)/test$#', $uri, $m) && $method === 'POST') {
    (new AdminAuthController())->providersTest((int)$m[1]);
}
if ($uri === '/admin/email-registrations' && $method === 'GET') {
    (new AdminAuthController())->registrationsIndex();
}
if (preg_match('#^/admin/email-registrations/(\d+)/code$#', $uri, $m) && $method === 'GET') {
    (new AdminAuthController())->registrationsGetCode((int)$m[1]);
}
if (preg_match('#^/admin/email-registrations/(\d+)/verify$#', $uri, $m) && $method === 'POST') {
    (new AdminAuthController())->registrationsVerify((int)$m[1]);
}
if (preg_match('#^/admin/email-registrations/(\d+)/cancel$#', $uri, $m) && $method === 'POST') {
    (new AdminAuthController())->registrationsCancel((int)$m[1]);
}

if ($uri === '/admin/devices' && $method === 'GET') {
    (new AdminController())->devicesIndex();
}
if (preg_match('#^/admin/devices/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new AdminController())->deviceDelete((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/devices/(\d+)$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->deactivateDevice((int)$m[1], (int)$m[2]);
}

// Device Routes (authenticated users)
if ($uri === '/devices/register' && $method === 'POST') {
    (new DeviceController())->register();
}
if ($uri === '/devices' && $method === 'GET') {
    (new DeviceController())->index();
}
if ($uri === '/devices/fcm-token' && $method === 'POST') {
    (new DeviceController())->saveFcmToken();
}
if ($uri === '/heartbeat' && $method === 'POST') {
    (new UserController())->heartbeat();
}
if ($uri === '/settings' && $method === 'GET') {
    (new UserController())->appSettings();
}
if ($uri === '/privacy' && $method === 'GET') {
    (new UserController())->privacyGet();
}
if ($uri === '/privacy' && $method === 'PUT') {
    (new UserController())->privacyUpdate();
}
if (preg_match('#^/devices/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
    (new DeviceController())->toggleDevice((int)$m[1]);
}

// Public plans list (for app pricing screen)
if ($uri === '/plans' && $method === 'GET') {
    Response::success(Database::getInstance()
        ->query('SELECT id, name, description, price, currency, period, max_devices, features FROM plans WHERE is_active = 1 ORDER BY price ASC')
        ->fetchAll() ?: []);
}

// Health check
if ($uri === '/health' && $method === 'GET') {
    Response::success(['status' => 'ok', 'version' => '1.0.0', 'timestamp' => date('c')]);
}

// 404 fallback
Response::notFound('المسار المطلوب غير موجود: ' . $uri);
