<?php
/**
 * NOVA Messenger - Database Configuration
 * Uses PDO with Prepared Statements only.
 *
 * Adapters (selected via DB_TYPE in .env):
 *   sqlite (default) — local file database, ideal for single-server hosting.
 *   mysql            — MariaDB/MySQL server.
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbType = strtolower((string)($_ENV['DB_TYPE'] ?? 'sqlite'));

            if ($dbType === 'mysql') {
                $host     = $_ENV['DB_HOST']     ?? '127.0.0.1';
                $port     = $_ENV['DB_PORT']     ?? '3306';
                $dbName   = $_ENV['DB_NAME']     ?? 'nova_messenger';
                $user     = $_ENV['DB_USER']     ?? 'root';
                $password = $_ENV['DB_PASSWORD'] ?? '';

                $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ];

                self::connect($dsn, $user, $password, $options);
            } else {
                // SQLite: database file next to this config file (backend/config/nova.sqlite)
                $dbPath = rtrim((string)($_ENV['DB_PATH'] ?? (__DIR__ . '/nova.sqlite')), '/');
                if (!str_contains($dbPath, ':') && !str_starts_with($dbPath, '/')) {
                    $dbPath = __DIR__ . '/' . $dbPath;
                }

                $dsn = "sqlite:{$dbPath}";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                self::connectCompat($dsn, $options);

                // Pragmas for SQLite performance and reliability
                try {
                    self::$instance->exec("PRAGMA journal_mode = WAL");
                    self::$instance->exec("PRAGMA busy_timeout = 5000");
                    self::$instance->exec("PRAGMA foreign_keys = ON");
                    self::$instance->exec("PRAGMA encoding = 'UTF-8'");
                } catch (PDOException $e) {
                    error_log('SQLite pragma failed: ' . $e->getMessage());
                }
            }
        }

        return self::$instance;
    }

    /** SQLite branch: uses MysqlCompatPdo to adapt MySQL-specific SQL transparently. */
    private static function connectCompat(string $dsn, array $options): void
    {
        try {
            if (!class_exists('MysqlCompatPdo', false)) {
                require_once __DIR__ . '/MysqlCompatPdo.php';
            }
            self::$instance = new MysqlCompatPdo($dsn, null, null, $options);
        } catch (PDOException $e) {
            // Never expose DB credentials in error messages
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode([
                'success'    => false,
                'message'    => 'خدمة قاعدة البيانات غير متاحة حالياً',
                'error_code' => 'DB_CONNECTION_ERROR',
            ]);
            exit;
        }
    }

    private static function connect(string $dsn, ?string $user, ?string $password, array $options): void
    {
        try {
            self::$instance = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            // Never expose DB credentials in error messages
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode([
                'success'    => false,
                'message'    => 'خدمة قاعدة البيانات غير متاحة حالياً',
                'error_code' => 'DB_CONNECTION_ERROR',
            ]);
            exit;
        }
    }

    /** Return the active adapter type: sqlite | mysql */
    public static function getType(): string
    {
        $dbType = strtolower((string)($_ENV['DB_TYPE'] ?? 'sqlite'));
        return $dbType === 'mysql' ? 'mysql' : 'sqlite';
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton.'); }
}
