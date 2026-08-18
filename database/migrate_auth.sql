-- =====================================================
-- NOVA Messenger — Auth & Email Providers Migration
-- Version: 1.1.0  (Auth Phone+Email unified system)
-- Idempotent: safe to run multiple times
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. users: unified single account (phone OR email OR both)
-- =====================================================

-- Column additions are conditional (idempotent).
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'email_verified');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'phone_verified');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `phone_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_verified`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Unique email index (NULLs can repeat — email-only users).
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'uq_users_email');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `uq_users_email` (`email`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. email_providers: SMTP / HTTP REST email senders
-- =====================================================

CREATE TABLE IF NOT EXISTS `email_providers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `type`          ENUM('smtp', 'http_rest') NOT NULL DEFAULT 'smtp',
  `status`        ENUM('enabled', 'disabled') NOT NULL DEFAULT 'disabled',
  `priority`      INT NOT NULL DEFAULT 0,
  `is_default`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_fallback`   TINYINT(1) NOT NULL DEFAULT 0,
  -- SMTP fields
  `host`          VARCHAR(200) NULL DEFAULT NULL,
  `port`          INT NULL DEFAULT NULL,
  `encryption`    ENUM('none', 'ssl', 'tls') NOT NULL DEFAULT 'tls',
  `username`      VARCHAR(200) NULL DEFAULT NULL,
  `password`      TEXT NULL DEFAULT NULL COMMENT 'AES-256-GCM encrypted',
  `from_email`    VARCHAR(200) NULL DEFAULT NULL,
  `from_name`     VARCHAR(150) NULL DEFAULT NULL,
  -- HTTP REST fields (api_key encrypted)
  `api_base_url`  VARCHAR(300) NULL DEFAULT NULL,
  `api_key`       TEXT NULL DEFAULT NULL COMMENT 'AES-256-GCM encrypted',
  `extra_config`  TEXT NULL DEFAULT NULL COMMENT 'JSON: method, auth_type, to_field, otp_field, template_mode, success_expr ...',
  `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at`  DATETIME NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email_providers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. email_delivery_logs
-- =====================================================

CREATE TABLE IF NOT EXISTS `email_delivery_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email_type`    ENUM('registration','login','phone_verification') NOT NULL DEFAULT 'registration',
  `to_email`      VARCHAR(190) NOT NULL,
  `provider_id`   INT UNSIGNED NULL DEFAULT NULL,
  `subject`       VARCHAR(255) NULL DEFAULT NULL,
  `status`        ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `http_code`     INT NULL DEFAULT NULL,
  `response_time_ms` INT NULL DEFAULT NULL,
  `response_summary` TEXT NULL DEFAULT NULL,
  `error_message` TEXT NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email_logs_to` (`to_email`),
  INDEX `idx_email_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. Auth & Email OTP settings (app_settings)
--    Default: phone registration+login ON, everything else OFF
-- =====================================================

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('auth_phone_registration', '1'),
('auth_email_registration', '0'),
('auth_phone_login', '1'),
('auth_email_login', '0'),
('auth_username_login', '0'),
('otp_phone_enabled', '1'),
('otp_email_enabled', '0'),
('otp_phone_expiry_minutes', '5'),
('otp_email_expiry_minutes', '5'),
('otp_phone_max_attempts', '5'),
('otp_email_max_attempts', '5'),
('otp_phone_resend_cooldown_seconds', '30'),
('otp_email_resend_cooldown_seconds', '30'),
('otp_phone_max_resends', '10'),
('otp_email_max_resends', '10'),
('otp_phone_delivery_mode', 'sms'),
('otp_email_delivery_mode', 'email'),
('otp_email_template', 'رمز التحقق الخاص بك هو: {OTP}. صالح لمدة {MINUTES} دقيقة. لا تشاركه مع أي شخص. — {APP_NAME}'),
('otp_email_from_provider_id', '0'),
('app_name', 'NOVA Messenger')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- =====================================================
-- 5. RBAC permissions for auth & email providers
-- =====================================================

INSERT INTO `permissions` (`name`, `description`) VALUES
('auth.settings.view', 'عرض إعدادات المصادقة والتسجيل'),
('auth.settings.update', 'تعديل إعدادات المصادقة والتسجيل'),
('email.providers.view', 'عرض مزودي البريد'),
('email.providers.create', 'إضافة مزود بريد'),
('email.providers.update', 'تعديل مزود بريد'),
('email.providers.delete', 'حذف مزود بريد'),
('email.providers.test', 'اختبار مزود بريد')
ON DUPLICATE KEY UPDATE `description` = `description`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'super_admin'
  AND p.name IN ('auth.settings.view', 'auth.settings.update', 'email.providers.view',
                 'email.providers.create', 'email.providers.update', 'email.providers.delete',
                 'email.providers.test')
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

-- =====================================================
-- 6. Default email providers (test SMTP + manual display)
-- =====================================================

INSERT INTO `email_providers` (`name`, `type`, `status`, `priority`, `is_default`, `is_fallback`,
  `host`, `port`, `encryption`, `from_email`, `from_name`)
SELECT 'اختبار (manual)', 'http_rest', 'enabled', 0, 1, 0, NULL, NULL, 'none', NULL, NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM email_providers WHERE `name` = 'اختبار (manual)');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 7. device_registrations (device management tables)
--    Required by EmailAuthController, DeviceController, UserController.
--    Base schema.sql does not define this table, so add it here
--    to keep Docker-initialized DBs consistent.
-- =====================================================

CREATE TABLE IF NOT EXISTS `device_registrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `device_uuid` VARCHAR(190) NOT NULL,
  `device_name` VARCHAR(200) NULL DEFAULT NULL,
  `device_model` VARCHAR(150) NULL DEFAULT NULL,
  `platform` VARCHAR(50) NULL DEFAULT NULL,
  `os` VARCHAR(80) NULL DEFAULT NULL,
  `os_version` VARCHAR(80) NULL DEFAULT NULL,
  `app_version` VARCHAR(40) NULL DEFAULT NULL,
  `device_fingerprint` VARCHAR(200) NULL DEFAULT NULL,
  `fcm_token` VARCHAR(500) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_seen` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_device_user` (`user_id`, `device_uuid`),
  KEY `idx_dr_user_active` (`user_id`, `is_active`),
  KEY `idx_dr_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. plans + user_subscriptions
--    Required by UserController::getUserById (plan badge).
--    Base schema.sql does not define these tables, so add
--    them here to keep Docker-initialized DBs consistent.
-- =====================================================

CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'SAR',
  `period` VARCHAR(20) NOT NULL DEFAULT 'monthly',
  `max_devices` INT NOT NULL DEFAULT 1,
  `badge_color` VARCHAR(20) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `description` VARCHAR(500) NULL DEFAULT NULL,
  `features` TEXT NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `starts_at` DATETIME NOT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plans` (`id`,`name`,`price`,`currency`,`period`,`max_devices`,`badge_color`,`description`,`features`,`is_active`) VALUES
(1, 'مجاني', 0.00, 'SAR', 'monthly', 1, NULL, 'الخطة المجانية الأساسية', 'حساب واحد، جهاز واحد', 1),
(2, 'بريميوم', 19.99, 'SAR', 'monthly', 3, '#3b82f6', 'خطة بريميوم للمستخدمين المتقدمين', '3 أجهزة، شارة مميزة، أولوية الدعم', 1),
(3, 'مؤسسي', 99.99, 'SAR', 'monthly', 10, '#8b5cf6', 'خطة للمؤسسات والفرق', '10 أجهزة، شارة مميزة، دعم أولوية قصوى', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;
