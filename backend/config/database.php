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
                self::migrateMissingColumns();
                self::applyConfiguredTimezone();

                // Pragmas for SQLite performance and reliability
                try {
                    self::$instance->exec("PRAGMA journal_mode = WAL");
                    self::$instance->exec("PRAGMA busy_timeout = 5000");
                    // Read-uncommitted so that statements within the same request
                    // (e.g. INSERT then SELECT) always see their own writes under WAL.
                    self::$instance->exec("PRAGMA read_uncommitted = 1");
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

    /**
     * Safe, idempotent schema migrations: adds columns that newer code expects
     * but may be missing in older database files (e.g. after deployment).
     */
    private static function migrateMissingColumns(): void
    {
        $migrations = [
            'conversation_members' => ['disappear_after' => 'INTEGER DEFAULT NULL'],
            'messages'             => ['disappear_after' => 'INTEGER DEFAULT NULL'],
        ];
        foreach ($migrations as $table => $columns) {
            foreach ($columns as $column => $definition) {
                try {
                    self::$instance->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                } catch (PDOException $e) {
                    // Column already exists or table missing — migration already applied or not needed
                    error_log('Migration skipped: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Apply the timezone configured in app_settings (default_timezone) so that
     * all date/time formatting across the API and admin panel follows the
     * selected region, instead of a hardcoded timezone.
     */
    private static function applyConfiguredTimezone(): void
    {
        static $applied = false;
        if ($applied) {
            return;
        }
        $applied = true;
        try {
            $stmt = self::$instance->query("SELECT setting_value FROM app_settings WHERE setting_key = 'default_timezone' LIMIT 1");
            $row  = $stmt ? $stmt->fetch() : false;
            $tz   = is_array($row) && !empty($row['setting_value']) ? (string)$row['setting_value'] : '';
            if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
                date_default_timezone_set($tz);
            }
        } catch (\Throwable $e) {
            // Settings table may not exist yet (fresh install) — keep the default timezone
            error_log('Timezone setting not applied: ' . $e->getMessage());
        }
    }

    /** Return the configured timezone identifier (used for UI offset display). */
    public static function getTimezone(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $pdo  = self::getInstance();
            $stmt = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'default_timezone' LIMIT 1");
            $row  = $stmt ? $stmt->fetch() : false;
            $tz   = is_array($row) && !empty($row['setting_value']) ? (string)$row['setting_value'] : '';
            if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
                return $cached = $tz;
            }
        } catch (\Throwable $e) {
            error_log('Timezone read failed: ' . $e->getMessage());
        }
        return $cached = 'Asia/Riyadh';
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
