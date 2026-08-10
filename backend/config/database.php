<?php
/**
 * NOVA Messenger - Database Configuration
 * Uses PDO with Prepared Statements only.
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
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

        return self::$instance;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton.'); }
}
