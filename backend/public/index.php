<?php
/**
 * NOVA Messenger - API Entry Point
 * All requests are routed through this file.
 * Configure your web server to point to this directory.
 */

declare(strict_types=1);

// Exception handler
set_exception_handler(function (\Throwable $e): void {
    error_log('[nova error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { 
        http_response_code(500); 
        header('Content-Type: application/json; charset=utf-8'); 
    }
    $env = $_ENV['APP_ENV'] ?? 'production';
    $msg = ($env === 'development') ? $e->getMessage() : 'خطأ داخلي في الخادم';
    $payload = ['success'=>false,'message'=>$msg,'error_code'=>'INTERNAL_ERROR'];
    if ($env === 'development') {
        $payload['trace'] = $e->getFile().':'.$e->getLine().' | '.$e->getTraceAsString();
    }
    echo json_encode($payload,JSON_UNESCAPED_UNICODE);
});

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
require_once __DIR__ . '/../helpers/SettingsHelper.php';
require_once __DIR__ . '/../controllers/ReportsController.php';
require_once __DIR__ . '/../controllers/AppealsController.php';
require_once __DIR__ . '/../controllers/PaymentRequestsController.php';
require_once __DIR__ . '/../controllers/SystemController.php';


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
    // Security: Enforce authentication for media access (except avatars which might be public)
    // We allow avatars to be public for now to avoid breaking UI previews, but attachments MUST be private.
    $isAvatar = strpos($rel, 'avatars/') === 0;
    $userId = null;
    if (!$isAvatar) {
        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int)$auth['user_id'];
        } catch (Exception $e) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول للملف', 'error_code' => 'UNAUTHORIZED']);
            exit;
        }
    }

    $storageBase = $_ENV['STORAGE_PATH'] ?? dirname(__DIR__) . '/storage';
    $file = rtrim($storageBase, '/') . '/' . $rel;
    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'الملف غير موجود', 'error_code' => 'NOT_FOUND']);
        exit;
    }

    // Security: Check ownership/permission if not public avatar
    if (!$isAvatar && $userId) {
        $userCtrl = new UserController();
        if (!$userCtrl->canAccessMedia($userId, $rel)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية للوصول لهذا الملف', 'error_code' => 'FORBIDDEN']);
            exit;
        }
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

// Auth Routes
if ($uri === "/heartbeat" && $method === "GET") { (new UserController())->heartbeat(); }
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
if ($uri === '/settings' && $method === 'GET') {
    (new UserController())->appSettings();
}
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
// Typing Indicator Routes
if (preg_match('#^/conversations/(\d+)/typing$#', $uri, $m) && $method === 'POST') {
    (new MessageController())->setTyping((int)$m[1]);
}
if (preg_match('#^/conversations/(\d+)/typing$#', $uri, $m) && $method === 'GET') {
    (new MessageController())->getTyping((int)$m[1]);
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
if ($uri === '/stories/upload' && $method === 'POST') {
    (new StoryController())->upload();
}
if (preg_match('#^/stories/(\d+)/views$#', $uri, $m) && $method === 'GET') {
    (new StoryController())->views((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/reactions$#', $uri, $m) && $method === 'GET') {
    (new StoryController())->reactions((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/replies$#', $uri, $m) && $method === 'GET') {
    (new StoryController())->replies((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/reaction$#', $uri, $m) && $method === 'POST') {
    (new StoryController())->react((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/reaction$#', $uri, $m) && $method === 'DELETE') {
    (new StoryController())->unreact((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/reply$#', $uri, $m) && $method === 'POST') {
    (new StoryController())->reply((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)$#', $uri, $m) && $method === 'PUT') {
    (new StoryController())->update((int)$m[1]);
}
if (preg_match('#^/stories/(\d+)/report$#', $uri, $m) && $method === 'POST') {
    (new StoryController())->report((int)$m[1]);
}
if (preg_match('#^/admin/stories/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    (new StoryController())->adminDelete((int)$m[1]);
}
if ($uri === '/admin/stories/stats' && $method === 'GET') {
    (new StoryController())->adminStats();
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
// Report Routes
if ($uri === '/reports' && $method === 'POST') {
    (new ReportsController())->create();
}
if ($uri === '/reports' && $method === 'GET') {
    (new ReportsController())->index();
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
if ($uri === '/calls/ice-servers' && $method === 'GET') {
    (new CallController())->iceServers();
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
if (preg_match('#^/admin/users/(\d+)/suspend$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->suspendUser((int)$m[1]);
}
if ($uri === '/admin/appeals' && $method === 'GET') {
    (new AdminController())->listAppeals();
}
if (preg_match('#^/admin/appeals/(\d+)/review$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->reviewAppeal((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/appeals$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->createAppeal((int)$m[1]);
}
// Appeals (user-facing)
if ($uri === '/appeals' && $method === 'POST') {
    (new AppealsController())->create();
}
if ($uri === '/appeals' && $method === 'GET') {
    (new AppealsController())->index();
}
if (preg_match('#^/admin/users/(\d+)/subscribe$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->subscribeUser((int)$m[1]);
}
if (preg_match('#^/admin/subscriptions/(\d+)/cancel$#', $uri, $m) && $method === 'POST') {
    (new AdminController())->cancelSubscription((int)$m[1]);
}
// Subscriptions (user-facing)
if ($uri === '/subscriptions/my' && $method === 'GET') {
    (new PaymentRequestsController())->mySubscriptions();
}
if ($uri === '/subscriptions/request' && $method === 'POST') {
    (new PaymentRequestsController())->createRequest();
}
if (preg_match('#^/subscriptions/request/(\d+)/upload$#', $uri, $m) && $method === 'POST') {
    (new PaymentRequestsController())->uploadReceipt((int)$m[1]);
}
// Payment requests (admin)
if ($uri === '/admin/payment-requests' && $method === 'GET') {
    (new PaymentRequestsController())->adminIndex();
}
if (preg_match('#^/admin/payment-requests/(\d+)/approve$#', $uri, $m) && $method === 'POST') {
    (new PaymentRequestsController())->approveRequest((int)$m[1]);
}
if (preg_match('#^/admin/payment-requests/(\d+)/reject$#', $uri, $m) && $method === 'POST') {
    (new PaymentRequestsController())->rejectRequest((int)$m[1]);
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
if (preg_match('#^/admin/users/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    (new AdminController())->userDelete((int)$m[1]);
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
if ($uri === '/heartbeat/offline' && $method === 'POST') {
    (new UserController())->setOffline();
}
if ($uri === '/settings' && $method === 'GET') {
    (new UserController())->appSettings();
}
    if ($uri === '/privacy' && $method === 'GET') {
        // We use the controller directly, but the controller calls authenticate() internally.
        (new UserController())->privacyGet();
    }
if ($uri === '/privacy' && $method === 'PUT') {
    (new UserController())->privacyUpdate();
}
	if (preg_match('#^/devices/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
	    (new DeviceController())->toggleDevice((int)$m[1]);
	}
	if ($uri === '/devices/link/init' && $method === 'POST') {
	    (new DeviceController())->createLinkSession();
	}
	if (preg_match('#^/devices/link/([^/]+)$#', $uri, $m) && $method === 'GET') {
	    (new DeviceController())->getLinkSessionStatus((string)$m[1]);
	}
	if ($uri === '/devices/link/authorize' && $method === 'POST') {
	    (new DeviceController())->authorizeLinkSession();
	}

// Public plans list (for app pricing screen)
if ($uri === '/plans' && $method === 'GET') {
    Response::success(Database::getInstance()
        ->query('SELECT id, name, description, price, currency, period, max_devices, features FROM plans WHERE is_active = 1 ORDER BY price ASC')
        ->fetchAll() ?: []);
}

// Health check
if ($uri === '/health' && $method === 'GET') {
    Response::success(['status' => 'ok', 'version' => '1.0.0', 'timestamp' => date('c'), 'timezone' => Database::getTimezone()]);
}
if ($uri === '/system/status' && $method === 'GET') {
    (new SystemController())->status();
}

// 404 fallback
Response::notFound('المسار المطلوب غير موجود: ' . $uri);
