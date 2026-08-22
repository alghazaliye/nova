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
    private static PDO|TursoPdo|null $instance = null;

    public static function getInstance(): PDO|TursoPdo
    {
        if (self::$instance === null) {
            $dbType = strtolower((string)($_ENV['DB_TYPE'] ?? 'sqlite'));

            if ($dbType === 'turso') {
                $url   = $_ENV['TURSO_URL'] ?? '';
                $token = $_ENV['TURSO_AUTH_TOKEN'] ?? '';

                if (empty($url) || empty($token)) {
                    error_log('Turso configuration missing');
                    http_response_code(503);
                    echo json_encode(['success' => false, 'message' => 'إعدادات Turso غير مكتملة']);
                    exit;
                }

                require_once __DIR__ . '/TursoPdo.php';
                self::$instance = new TursoPdo($url, $token);
                
                // Initial migrations for Turso
                self::migrateMissingColumns();
                self::migrateMissingTables();
                self::migrateMissingIndexes();
                self::migrateLinkSessionsTable();
                self::applyConfiguredTimezone();
            } elseif ($dbType === 'mysql') {
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
                self::migrateMissingIndexes();
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
                'badge_color'               => "TEXT DEFAULT '#007AFF'",
            ],
	            'users' => [
	                'verified_until' => 'DATETIME DEFAULT NULL',
                    'badge_color'    => 'VARCHAR(50) DEFAULT NULL',
	            ],
	            'admins' => [
	                'username' => 'TEXT DEFAULT NULL',
	            ],
            'otp_rate_limits' => [
                'attempts_count' => 'INTEGER NOT NULL DEFAULT 1',
                'resend_count'   => 'INTEGER NOT NULL DEFAULT 0',
                'cooldown_until' => 'DATETIME DEFAULT NULL',
            ],
            'sessions' => [
                'device_name' => 'VARCHAR(255) DEFAULT NULL',
                'platform'    => 'VARCHAR(50) DEFAULT NULL',
            ],
            'user_devices' => [
                'platform' => 'VARCHAR(50) DEFAULT NULL',
            ],
            'stories' => [
                'deleted_by'  => 'INTEGER DEFAULT NULL',
                'views_count' => 'INTEGER NOT NULL DEFAULT 0',
            ],
	            'otp_verifications' => [
	                'seen_at'          => 'DATETIME DEFAULT NULL',
	                'manual_code_hash' => 'VARCHAR(255) DEFAULT NULL',
	                'manual_code'      => 'VARCHAR(50) DEFAULT NULL',
	            ],
            'email_verification_codes' => [
                'seen_at' => 'DATETIME DEFAULT NULL',
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
		                'created_at'             => 'DATETIME DEFAULT NULL',
		            ],
		            'user_appeals' => [
		                'attachment'                   => 'TEXT DEFAULT NULL',
		                'account_status_at_submission' => 'TEXT DEFAULT NULL',
		                'updated_at'                   => "DATETIME DEFAULT NULL",
		            ],
	            'user_subscriptions' => [
	                'payment_method' => 'TEXT DEFAULT NULL',
	                'payment_id'     => 'TEXT DEFAULT NULL',
	                'amount_paid'    => 'REAL DEFAULT NULL',
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
                    // Idempotent column addition: check if exists first to keep logs clean
                    $check = self::$instance->query("PRAGMA table_info({$table})")->fetchAll();
                    $exists = false;
                    foreach ($check as $col) {
                        if ($col['name'] === $column) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        self::$instance->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                    }
                } catch (PDOException $e) {
                    // Only log if it's NOT a "duplicate column" error
                    if (!str_contains($e->getMessage(), 'duplicate column name')) {
                        error_log('Migration error: ' . $e->getMessage());
                    }
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
    /**
     * Safe, idempotent index migrations: creates indexes that improve query performance.
     */
    private static function migrateMissingIndexes(): void
    {
        $indexes = [
            'idx_messages_conversation_id' => 'CREATE INDEX IF NOT EXISTS idx_messages_conversation_id ON messages(conversation_id)',
            'idx_messages_sender_id'       => 'CREATE INDEX IF NOT EXISTS idx_messages_sender_id ON messages(sender_id)',
            'idx_messages_created_at'      => 'CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at)',
            'idx_users_phone'              => 'CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone)',
            'idx_users_email'              => 'CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)',
            'idx_users_name'               => 'CREATE INDEX IF NOT EXISTS idx_users_name ON users(name)',
            'idx_conversations_last_msg'   => 'CREATE INDEX IF NOT EXISTS idx_conversations_last_msg ON conversations(last_message_id)',
            'idx_audit_logs_action'        => 'CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action)',
            'idx_audit_logs_created'       => 'CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at)',
            'idx_typing_status_expires'    => 'CREATE INDEX IF NOT EXISTS idx_typing_status_expires ON typing_status(expires_at)',
        ];
        foreach ($indexes as $ddl) {
            try {
                self::$instance->exec($ddl);
            } catch (PDOException $e) {
                error_log('Index migration skipped: ' . $e->getMessage());
            }
        }
    }

    private static function migrateMissingTables(): void
    {
        $tables = [
            'sessions' => "CREATE TABLE IF NOT EXISTS `sessions` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `user_id` integer NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `device_id` integer DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  `revoked_at` datetime DEFAULT NULL,
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
)",
	            'user_devices' => "CREATE TABLE IF NOT EXISTS `user_devices` (
	  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
	  `user_id` integer NOT NULL,
	  `device_uuid` varchar(190) NOT NULL,
	  `fcm_token` varchar(500) DEFAULT NULL,
	  `platform` varchar(50) DEFAULT NULL,
	  `last_active_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp,
  UNIQUE (`user_id`, `device_uuid`),
  CONSTRAINT `fk_user_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
)",
            'user_bans' => "CREATE TABLE IF NOT EXISTS `user_bans` (
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `user_id` integer NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_by` integer DEFAULT NULL,
  `banned_at` datetime NOT NULL DEFAULT current_timestamp,
  `suspend_until` datetime DEFAULT NULL,
  `unbanned_at` datetime DEFAULT NULL,
  `unbanned_by` integer DEFAULT NULL,
  CONSTRAINT `fk_user_bans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
)",
	            'user_appeals' => "CREATE TABLE IF NOT EXISTS `user_appeals` (
		  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
		  `user_id` integer NOT NULL,
		  `contact_value` text DEFAULT NULL,
		  `reason` text NOT NULL,
		  `attachment` text DEFAULT NULL,
		  `account_status_at_submission` text DEFAULT NULL,
		  `status` text NOT NULL DEFAULT 'pending',
		  `admin_note` text DEFAULT NULL,
		  `reviewed_by` integer DEFAULT NULL,
		  `reviewed_at` datetime DEFAULT NULL,
		  `created_at` datetime NOT NULL DEFAULT current_timestamp,
		  `updated_at` datetime NOT NULL DEFAULT current_timestamp,
		  CONSTRAINT `fk_user_appeals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
		)",
	            'report_attachments' => "CREATE TABLE IF NOT EXISTS `report_attachments` (
	  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
	  `report_id` integer NOT NULL,
	  `message_id` integer NOT NULL,
	  `conversation_id` integer NOT NULL,
	  `created_at` datetime NOT NULL DEFAULT current_timestamp,
	  UNIQUE (report_id, message_id),
	  CONSTRAINT `fk_report_attachments_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
	  CONSTRAINT `fk_report_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
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
	  `updated_at` datetime NOT NULL DEFAULT current_timestamp,
	  CONSTRAINT `fk_privacy_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
	)",
	            'typing_status' => "CREATE TABLE IF NOT EXISTS `typing_status` (
	  `conversation_id` integer NOT NULL,
	  `user_id` integer NOT NULL,
	  `expires_at` datetime NOT NULL,
	  `updated_at` datetime NOT NULL DEFAULT current_timestamp,
	  PRIMARY KEY (conversation_id, user_id),
	  CONSTRAINT `fk_typing_status_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
	)",
	            'otp_rate_limits' => "CREATE TABLE IF NOT EXISTS `otp_rate_limits` (
		  `phone` varchar(50) NOT NULL PRIMARY KEY,
		  `last_attempt_at` datetime NOT NULL,
		  `attempts_count` integer NOT NULL DEFAULT 1,
		  `resend_count` integer NOT NULL DEFAULT 0,
		  `cooldown_until` datetime DEFAULT NULL
		)",
            'webrtc_logs' => "CREATE TABLE IF NOT EXISTS `webrtc_logs` (
		  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
		  `call_id` integer NOT NULL,
		  `user_id` integer NOT NULL,
		  `event_type` varchar(50) NOT NULL,
		  `log_level` varchar(20) DEFAULT 'info',
		  `message` text,
		  `details` text,
		  `ip_address` varchar(45) DEFAULT NULL,
		  `user_agent` text DEFAULT NULL,
		  `created_at` datetime NOT NULL DEFAULT current_timestamp
		)",
            'otp_providers' => "CREATE TABLE IF NOT EXISTS `otp_providers` (
		  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
		  `name` varchar(120) NOT NULL,
		  `type` varchar(20) NOT NULL,
		  `status` varchar(20) NOT NULL DEFAULT 'disabled',
		  `priority` integer NOT NULL DEFAULT 0,
		  `is_default` integer NOT NULL DEFAULT 0,
		  `is_fallback` integer NOT NULL DEFAULT 0,
		  `api_base_url` varchar(500) DEFAULT NULL,
		  `api_key` varchar(500) DEFAULT NULL,
		  `api_secret` varchar(500) DEFAULT NULL,
		  `account_sid` varchar(300) DEFAULT NULL,
		  `message_template` text DEFAULT NULL,
		  `sender_id` varchar(100) DEFAULT NULL,
		  `extra_config` text DEFAULT NULL,
		  `success_count` integer NOT NULL DEFAULT 0,
		  `failure_count` integer NOT NULL DEFAULT 0,
		  `last_used_at` datetime DEFAULT NULL,
		  `created_at` datetime NOT NULL DEFAULT current_timestamp,
		  `updated_at` datetime NOT NULL DEFAULT current_timestamp
		)",
            'otp_verifications' => "CREATE TABLE IF NOT EXISTS `otp_verifications` (
		  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
		  `phone_number` varchar(30) NOT NULL,
		  `name` varchar(150) DEFAULT NULL,
		  `otp_hash` varchar(255) NOT NULL,
		  `manual_code_hash` varchar(255) DEFAULT NULL,
		  `manual_code` varchar(50) DEFAULT NULL,
		  `status` varchar(20) NOT NULL DEFAULT 'pending',
		  `attempts` integer NOT NULL DEFAULT 0,
		  `max_attempts` integer NOT NULL DEFAULT 5,
		  `resends` integer NOT NULL DEFAULT 0,
		  `delivery_mode` varchar(20) NOT NULL DEFAULT 'auto',
		  `delivery_status` varchar(20) DEFAULT NULL,
		  `provider_id` integer DEFAULT NULL,
		  `expires_at` datetime DEFAULT NULL,
		  `verified_at` datetime DEFAULT NULL,
		  `ip_address` varchar(45) DEFAULT NULL,
		  `user_agent` text DEFAULT NULL,
		  `created_at` datetime NOT NULL DEFAULT current_timestamp,
		  `updated_at` datetime NOT NULL DEFAULT current_timestamp
		)",
            'otp_delivery_logs' => "CREATE TABLE IF NOT EXISTS `otp_delivery_logs` (
		  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
		  `otp_id` integer NOT NULL,
		  `provider_id` integer NOT NULL,
		  `provider_type` varchar(20) NOT NULL,
		  `phone_number` varchar(30) NOT NULL,
		  `status` varchar(20) NOT NULL DEFAULT 'attempt',
		  `http_code` integer DEFAULT NULL,
		  `error_message` text DEFAULT NULL,
		  `response_summary` varchar(500) DEFAULT NULL,
		  `response_time_ms` integer DEFAULT NULL,
		  `created_at` datetime NOT NULL DEFAULT current_timestamp
		)",
        ];
        foreach ($tables as $table => $ddl) {
            try {
                self::$instance->exec($ddl);
            } catch (PDOException $e) {
                error_log('Table migration skipped: ' . $e->getMessage());
            }
        }

        // Seed test provider if missing
        try {
            $check = self::$instance->query("SELECT id FROM otp_providers WHERE type = 'test' LIMIT 1")->fetch();
            if (!$check) {
                self::$instance->exec("INSERT INTO otp_providers (name, type, status, priority, is_default, is_fallback) 
                                     VALUES ('مزود الاختبار', 'test', 'enabled', 1, 1, 0)");
            }
        } catch (\Throwable $e) {}
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
        if ($dbType === 'turso') return 'turso';
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
