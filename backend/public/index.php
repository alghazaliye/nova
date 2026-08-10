<?php
/**
 * NOVA Messenger - API Entry Point
 * All requests are routed through this file.
 * Configure your web server to point to this directory.
 */

declare(strict_types=1);

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

// =====================================================
// Router
// =====================================================

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
if ($uri === '/auth/logout' && $method === 'POST') {
    (new AuthController())->logout();
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

// Call Routes
if ($uri === '/calls' && $method === 'POST') {
    (new CallController())->initiate();
}
if ($uri === '/calls' && $method === 'GET') {
    (new CallController())->index();
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

// Health check
if ($uri === '/health' && $method === 'GET') {
    Response::success(['status' => 'ok', 'version' => '1.0.0', 'timestamp' => date('c')]);
}

// 404 fallback
Response::notFound('المسار المطلوب غير موجود: ' . $uri);
