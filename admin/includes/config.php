<?php

/**
 * NOVA Messenger - Admin Panel Configuration
 */

declare(strict_types=1);

// Load .env
$envFile = __DIR__ . '/../../backend/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

define('APP_NAME',    $_ENV['APP_NAME'] ?? 'NOVA Messenger');
define('APP_VERSION', '1.0.0');

// Database connection
function getAdminDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dbType = ($_ENV['DB_TYPE'] ?? 'sqlite');
        if ($dbType === 'turso') {
            $url   = $_ENV['TURSO_URL'] ?? '';
            $token = $_ENV['TURSO_AUTH_TOKEN'] ?? '';
            
            if (empty($url) || empty($token)) {
                die("Turso configuration missing. Set TURSO_URL and TURSO_AUTH_TOKEN in Render environment.");
            }
            
            $tursoPdoFile = realpath(__DIR__ . '/../../backend/config/TursoPdo.php');
            if ($tursoPdoFile) {
                require_once $tursoPdoFile;
                // TursoPdo behaves like PDO but is not a subclass. 
                // We cast or return it; admin panel uses $pdo->prepare/query which it supports.
                $pdo = new TursoPdo($url, $token);
            } else {
                die("TursoPdo adapter file not found.");
            }
        } elseif ($dbType === 'sqlite') {
            // Share the same SQLite database as the backend
            $dbPath = $_ENV['DB_PATH']
                ?? realpath(__DIR__ . '/../../backend/config/nova.sqlite');
            if ($dbPath === false) {
                $dbPath = __DIR__ . '/../../backend/config/nova.sqlite';
            }
            $dsn = 'sqlite:' . $dbPath;
            // Use MysqlCompatPdo so MySQL-specific SQL in admin panels
            // (INSERT IGNORE, ON DUPLICATE KEY UPDATE, NOW()) is adapted to SQLite.
            $compatFile = realpath(__DIR__ . '/../../backend/config/MysqlCompatPdo.php');
            if ($compatFile && !class_exists('MysqlCompatPdo', false)) {
                require_once $compatFile;
            }
            $pdoClass = class_exists('MysqlCompatPdo') ? 'MysqlCompatPdo' : 'PDO';
            $pdo = new $pdoClass($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_PORT'] ?? '3307',
                $_ENV['DB_NAME'] ?? 'nova'
            );
            $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
    }
    return $pdo;
}

// Session security
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isset($_SERVER['HTTPS']) ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
session_start();
