<?php
/**
 * NOVA Messenger - Application Configuration
 */

declare(strict_types=1);

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $value;
            putenv("{$key}={$value}");
        }
    }
}

// Error reporting based on environment
$appEnv = $_ENV['APP_ENV'] ?? 'production';
if ($appEnv === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Timezone
date_default_timezone_set('Asia/Riyadh');

// Response and CORS headers. Never reflect arbitrary origins in production.
header('Content-Type: application/json; charset=utf-8');
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '')));
if ($requestOrigin !== '' && (in_array('*', $allowedOrigins, true) || in_array($requestOrigin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . (in_array('*', $allowedOrigins, true) ? '*' : $requestOrigin));
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Read the Authorization header reliably across all SAPIs.
 * Some Apache/CGI setups strip HTTP_AUTHORIZATION, so fall back to
 * getallheaders()/apache_request_headers() when available.
 */
function nova_get_auth_header(): string
{
    // Collect candidate values from $_SERVER (Apache rewrites may prefix
    // variables with REDIRECT_, and header names are normalized to HTTP_*).
    $candidates = [];
    foreach ($_SERVER as $name => $val) {
        if (!is_string($val) || $val === '') {
            continue;
        }
        $lc = strtolower($name);
        if ($lc === 'http_authorization' || $lc === 'redirect_http_authorization') {
            $candidates[] = $val;
        } elseif ($lc === 'http_x_admin_auth' || $lc === 'redirect_http_x_admin_auth') {
            $candidates[] = 'Bearer ' . $val;
        }
    }
    $value = $candidates[0] ?? '';
    if ($value === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $val) {
            if (strtolower($name) === 'authorization' && $val) {
                $value = (string)$val;
                break;
            }
        }
    }
    return $value;
}

// Autoload helpers
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/JwtHelper.php';
require_once __DIR__ . '/../helpers/UuidHelper.php';
require_once __DIR__ . '/../helpers/FCMHelper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RateLimitMiddleware.php';
