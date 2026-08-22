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
                self::migrateMissingTables();
                self::migrateLinkSessionsTable();
                self::applyConfiguredTimezone();

                // Pragmas for SQLite performance and reliability
                try {
                    self::$instance->exec("PRAGMA journal_mode = WAL");
                    self::$instance->exec("PRAGMA synchronous = NORMAL");
                    self::$instance->exec("PRAGMA busy_timeout = 10000");
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
            'conversation_members' => [
                'disappear_after' => 'INTEGER DEFAULT NULL',
                'muted_until'     => 'DATETIME DEFAULT NULL'
            ],
            'messages'             => [
                'disappear_after'    => 'INTEGER DEFAULT NULL',
                'deleted_by'         => 'INTEGER DEFAULT NULL',
                'reply_to_status_id' => 'INTEGER DEFAULT NULL',
            ],
            'privacy_settings' => [
                'show_phone'         => 'INTEGER NOT NULL DEFAULT 2',
                'show_email'         => 'INTEGER NOT NULL DEFAULT 2',
                'show_avatar'        => 'INTEGER NOT NULL DEFAULT 1',
                'show_status_text'   => 'INTEGER NOT NULL DEFAULT 1',
                'messages_from'      => 'INTEGER NOT NULL DEFAULT 1',
                'calls_from'         => 'INTEGER NOT NULL DEFAULT 1',
                'groups_from'        => 'INTEGER NOT NULL DEFAULT 1',
                'find_by_phone'      => 'INTEGER NOT NULL DEFAULT 1',
                'find_by_email'      => 'INTEGER NOT NULL DEFAULT 1',
                'find_by_username'   => 'INTEGER NOT NULL DEFAULT 1',
                'display_identity'   => "TEXT NOT NULL DEFAULT 'name_username'",
                'story_privacy'      => 'INTEGER NOT NULL DEFAULT 1',
                'allow_by_phone'     => 'INTEGER NOT NULL DEFAULT 1',
            ],
            'reports' => [
                'reason_code' => "TEXT DEFAULT NULL",
                'priority'    => "TEXT NOT NULL DEFAULT 'medium'",
                'story_id'    => 'INTEGER DEFAULT NULL',
            ],
            'plans' => [
                'plan_type'                 => "TEXT NOT NULL DEFAULT 'free'",
                'enable_verification'       => 'INTEGER NOT NULL DEFAULT 0',
                'verification_duration_days' => 'INTEGER DEFAULT NULL',
            ],
            'users' => [
                'verified_until' => 'DATETIME DEFAULT NULL',
            ],
            'otp_rate_limits' => [
                'attempts_count' => 'INTEGER NOT NULL DEFAULT 1',
                'resend_count'   => 'INTEGER NOT NULL DEFAULT 0',
                'cooldown_until' => 'DATETIME DEFAULT NULL',
            ],
            'stories' => [
                'deleted_by'  => 'INTEGER DEFAULT NULL',
                'views_count' => 'INTEGER NOT NULL DEFAULT 0',
            ],
            'payment_requests' => [
                'id'                     => 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT',
                'user_id'                => 'INTEGER NOT NULL',
                'plan_id'                => 'INTEGER NOT NULL',
                'status'                 => "TEXT NOT NULL DEFAULT 'pending'",
                'receipt_path'           => 'TEXT DEFAULT NULL',
                'admin_note'             => 'TEXT DEFAULT NULL',
                'reviewed_by'            => 'INTEGER DEFAULT NULL',
                'reviewed_at'            => 'DATETIME DEFAULT NULL',
                'created_at'             => 'DATETIME NOT NULL DEFAULT current_timestamp',
            ],
        ];
        // payment_requests is a whole table, not columns — create it separately
        $tableMigrations = [
            'story_reactions' => "CREATE TABLE IF NOT EXISTS `story_reactions` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `story_id` integer NOT NULL,
  `user_id` integer NOT NULL,
  `reaction` varchar(10) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  UNIQUE (`story_id`, `user_id`),
  CONSTRAINT `fk_story_reactions_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_story_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
)",
            'story_replies' => "CREATE TABLE IF NOT EXISTS `story_replies` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `story_id` integer NOT NULL,
  `sender_id` integer NOT NULL,
  `message_id` integer NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  CONSTRAINT `fk_story_replies_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_story_replies_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_story_replies_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
)",
            'payment_requests' => "CREATE TABLE IF NOT EXISTS `payment_requests` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `user_id` integer NOT NULL,
  `plan_id` integer NOT NULL,
  `status` text NOT NULL DEFAULT 'pending',
  `receipt_path` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `reviewed_by` integer DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp
)",
        ];
        foreach ($migrations as $table => $columns) {
            if ($table === 'payment_requests') {
                // Whole table — handled via table migrations below
                continue;
            }
            foreach ($columns as $column => $definition) {
                try {
                    self::$instance->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                } catch (PDOException $e) {
                    // Column already exists or table missing — migration already applied or not needed
                    error_log('Migration skipped: ' . $e->getMessage());
                }
            }
        }
        foreach ($tableMigrations as $ddl) {
            try {
                self::$instance->exec($ddl);
            } catch (PDOException $e) {
                error_log('Table migration skipped: ' . $e->getMessage());
            }
        }
    }

    /**
     * Safe, idempotent migrations: creates tables that newer code expects
     * but may be missing in older database files (e.g. after deployment).
     */
    private static function migrateMissingTables(): void
    {
        $tables = [
            'user_bans' => "CREATE TABLE IF NOT EXISTS `user_bans` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `user_id` integer NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_by` integer DEFAULT NULL,
  `banned_at` datetime NOT NULL DEFAULT current_timestamp,
  `suspend_until` datetime DEFAULT NULL,
  `unbanned_at` datetime DEFAULT NULL,
  `unbanned_by` integer DEFAULT NULL
)",
            'user_appeals' => "CREATE TABLE IF NOT EXISTS `user_appeals` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `user_id` integer NOT NULL,
  `contact_value` text DEFAULT NULL,
  `reason` text NOT NULL,
  `status` text NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `reviewed_by` integer DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp
)",
            'report_attachments' => "CREATE TABLE IF NOT EXISTS `report_attachments` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `report_id` integer NOT NULL,
  `message_id` integer NOT NULL,
  `conversation_id` integer NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  UNIQUE (report_id, message_id)
)",
            'audit_logs' => "CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `admin_id` integer NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` integer DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp
)",
            'privacy_settings' => "CREATE TABLE IF NOT EXISTS `privacy_settings` (
  `user_id` integer NOT NULL PRIMARY KEY,
  `show_last_seen` integer DEFAULT 1,
  `show_online_status` integer DEFAULT 1,
  `show_read_receipts` integer DEFAULT 1,
  `show_phone` integer DEFAULT 2,
  `show_email` integer DEFAULT 2,
  `show_avatar` integer DEFAULT 1,
  `show_status_text` integer DEFAULT 1,
  `messages_from` integer DEFAULT 1,
  `calls_from` integer DEFAULT 1,
  `groups_from` integer DEFAULT 1,
  `find_by_phone` integer DEFAULT 1,
  `find_by_email` integer DEFAULT 1,
  `find_by_username` integer DEFAULT 1,
  `display_identity` varchar(50) DEFAULT 'name_username',
  `story_privacy` integer DEFAULT 1,
  `allow_by_phone` integer DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp
)",
            'typing_status' => "CREATE TABLE IF NOT EXISTS `typing_status` (
  `conversation_id` integer NOT NULL,
  `user_id` integer NOT NULL,
  `expires_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (conversation_id, user_id)
)",
            'otp_rate_limits' => "CREATE TABLE IF NOT EXISTS `otp_rate_limits` (
  `phone` varchar(50) NOT NULL PRIMARY KEY,
  `last_attempt_at` datetime NOT NULL,
  `attempts_count` integer NOT NULL DEFAULT 1,
  `resend_count` integer NOT NULL DEFAULT 0,
  `cooldown_until` datetime DEFAULT NULL
)",
        ];
        foreach ($tables as $table => $ddl) {
            try {
                self::$instance->exec($ddl);
            } catch (PDOException $e) {
                error_log('Table migration skipped: ' . $e->getMessage());
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

    private static function migrateLinkSessionsTable(): void
    {
        $ddl = <<<'SQL'
CREATE TABLE IF NOT EXISTS `link_sessions` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `session_uuid` varchar(190) NOT NULL,
  `user_id` integer DEFAULT NULL,
  `device_name` varchar(200) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending', -- pending, authorized, completed, expired
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  `expires_at` datetime NOT NULL,
  UNIQUE (`session_uuid`)
)
SQL;
        try {
            self::$instance->exec($ddl);
            // إضافة أعمدة مفقودة لـ device_registrations إذا لزم الأمر
            // نستخدم try-catch لكل ALTER TABLE لأن العمود قد يكون موجوداً بالفعل
            try {
                self::$instance->exec("ALTER TABLE device_registrations ADD COLUMN device_fingerprint varchar(200) DEFAULT NULL");
            } catch (\Exception $e) {}
            
            try {
                self::$instance->exec("ALTER TABLE device_registrations ADD COLUMN device_uuid varchar(200) DEFAULT NULL");
            } catch (\Exception $e) {}
        } catch (PDOException $e) {
            error_log('Link sessions migration skipped: ' . $e->getMessage());
        }
    }
}
